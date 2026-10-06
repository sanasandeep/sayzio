<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\MenuOrderCoupon;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Support\MenuHandoverTiming;
use App\Modules\User\Support\MenuFulfilment;
use App\Modules\User\Support\MenuOrderToken;
use Illuminate\Support\Facades\DB;

class MenuCouponPrebooking
{
    public function reserve($link, $menu, string $kind, array $data)
    {
        $restaurant = $kind === 'restaurant';
        $model = $restaurant ? RestaurantOrder::class : StoreOrder::class;
        $mode = $data['fulfilment'];
        if (!in_array($mode, MenuFulfilment::modesFor((array) $menu->settings, $restaurant), true)) {
            throw new \InvalidArgumentException('That handover option is unavailable.');
        }
        if ($mode === 'delivery' && trim((string) ($data['customer_address'] ?? '')) === '') {
            throw new \InvalidArgumentException('Delivery requires an address.');
        }
        $timezone = $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault();
        $wanted = MenuHandoverTiming::accept((array) $menu->settings, $mode, $timezone, $data['wanted_at']);
        if (!$wanted || $wanted->lte(now())) throw new \InvalidArgumentException('Choose an available future time.');
        return DB::transaction(function () use ($link, $menu, $kind, $data, $model, $mode, $wanted, $timezone) {
            $coupon = MenuOrderCoupon::where('menu_id', $menu->id)->where('menu_type', $kind)
                ->where('order_type', $kind)->where('code', MenuOrderCoupon::normalize($data['coupon_code']))->lockForUpdate()->first();
            if (!$coupon || !$coupon->isRedeemable()) throw new \InvalidArgumentException('Coupon unavailable. Check your code or ask staff.');
            $purchase = $model::find($coupon->order_id);
            if (!$purchase || $purchase->status === 'cancelled') throw new \InvalidArgumentException('Coupon unavailable. Check your code or ask staff.');
            $reservation = DB::table('menu_coupon_reservations')->where('coupon_id', $coupon->id)->first();
            if ($reservation) {
                $existing = $model::find($reservation->order_id);
                if ($existing && $existing->status !== 'cancelled') {
                    throw new \InvalidArgumentException('This coupon already has a booking. Ask staff to change it.');
                }
            }
            [$number, $period] = MenuOrderToken::reserve($kind, $menu->id, (array) $menu->settings, $timezone);
            $order = $model::create([
                'menu_id' => $menu->id, 'link_id' => $link->id, 'status' => 'new',
                'fulfilment' => $mode, 'wanted_at' => $wanted, 'token_number' => $number, 'token_period' => $period,
                'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'subtotal' => 0, 'total' => 0, 'currency' => $menu->currency,
                'meta' => ['coupon_reservation' => true, 'coupon_id' => $coupon->id, 'prepaid_servings' => 1],
            ]);
            $order->items()->create(['name' => $coupon->item_name, 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0]);
            DB::table('menu_coupon_reservations')->updateOrInsert(['coupon_id' => $coupon->id], [
                'order_type' => $kind, 'order_id' => $order->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $order;
        });
    }
}
