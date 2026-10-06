<?php

namespace App\Modules\User\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MenuPerformance
{
    public static function of($query, string $model, int $menuId, array $range, int $target, string $timezone = 'UTC'): array
    {
        $out = ['target' => $target, 'prep' => [], 'collection' => [], 'late' => 0, 'customers' => [],
            'reasons' => [], 'hours' => [], 'weekdays' => [], 'bulk_orders' => 0, 'bulk_value' => 0,
            'extras_units' => 0, 'extras_value' => 0, 'collected' => 0, 'payment_records' => 0,
            'unpaid_value' => 0, 'billable' => 0, 'value' => 0];
        $kind = str_contains($model, 'Restaurant') ? 'restaurant' : 'store';
        $table = (new $model)->getTable();
        $rows = (clone $query)->reorder()->with('items')->addSelect($table.'.*')->selectSub(
            DB::table('menu_order_coupons')->selectRaw('COUNT(*)')->whereColumn('order_id', $table.'.id')->where('order_type', $kind), 'coupon_count'
        );
        $rows->chunkById(500, function ($orders) use (&$out, $target, $timezone) {
            foreach ($orders as $order) {
                $meta = (array) $order->meta;
                if ($order->status === 'cancelled') {
                    $reason = trim((string) ($meta['cancellation_reason'] ?? '')) ?: 'Not recorded';
                    $out['reasons'][$reason] = ($out['reasons'][$reason] ?? 0) + 1;
                    continue;
                }
                $out['billable']++;
                $out['value'] += (float) $order->total;
                $local = $order->created_at->copy()->timezone($timezone);
                $hour = $local->format('H'); $day = $local->format('l');
                $out['hours'][$hour] = ($out['hours'][$hour] ?? 0) + 1;
                $out['weekdays'][$day] = ($out['weekdays'][$day] ?? 0) + 1;
                // Phone/contact only: names are not reliable customer identities.
                $who = preg_replace('/[^0-9+]/', '', (string) $order->customer_phone);
                if (!$who && $order instanceof \App\Modules\User\Models\StoreOrder) {
                    $who = strtolower(trim((string) $order->customer_contact));
                }
                if ($who) $out['customers'][$who] = ($out['customers'][$who] ?? 0) + 1;
                $times = $meta['status_times'] ?? [];
                $ready = isset($times['ready']) ? Carbon::parse($times['ready']) : null;
                if ($ready && $ready->gte($order->created_at)) {
                    $out['prep'][] = $order->created_at->diffInSeconds($ready) / 60;
                    if (isset($times['completed'])) {
                        $completed = Carbon::parse($times['completed']);
                        if ($completed->gte($ready)) $out['collection'][] = $ready->diffInSeconds($completed) / 60;
                    }
                }
                if (in_array($order->status, ['new', 'accepted', 'preparing', 'packing'], true)) {
                    $dueStart = $order->wanted_at && $order->wanted_at->gt($order->created_at) ? $order->wanted_at : $order->created_at;
                    if ($dueStart->copy()->addMinutes($target)->lt(now())) $out['late']++;
                }
                if ((int) $order->coupon_count > 0) { $out['bulk_orders']++; $out['bulk_value'] += (float) $order->total; }
                foreach ($order->items as $item) {
                    if ((float) $item->options_total > 0) {
                        $out['extras_units'] += $item->quantity;
                        $out['extras_value'] += (float) $item->options_total;
                    }
                }
                if (array_key_exists('collected_amount', $meta)) {
                    $out['payment_records']++;
                    $out['collected'] += (float) $meta['collected_amount'];
                    $out['unpaid_value'] += max(0, (float) $order->total - (float) $meta['collected_amount']);
                }
            }
        });
        foreach (['hours', 'weekdays', 'reasons'] as $key) arsort($out[$key]);
        $out['repeat_customers'] = count(array_filter($out['customers'], fn ($n) => $n >= 2));
        $out['identified_customers'] = count($out['customers']);
        unset($out['customers']);
        $out['previous'] = null;
        // Compare equal elapsed windows, including partial today, rather than a partial day against a full day.
        if ($range['from']) {
            $end = $range['to'] ?? now();
            $seconds = max(1, $range['from']->diffInSeconds($end) + 1);
            $previousEnd = $range['from']->copy()->subSecond();
            $previousStart = $previousEnd->copy()->subSeconds($seconds - 1);
            $previous = $model::where('menu_id', $menuId)->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$previousStart, $previousEnd]);
            $out['previous'] = ['orders' => (clone $previous)->count(), 'value' => (float) $previous->sum('total')];
        }
        return $out;
    }
}
