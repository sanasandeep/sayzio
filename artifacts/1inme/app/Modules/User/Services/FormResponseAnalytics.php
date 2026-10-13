<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Form;
use App\Support\PlatformTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormResponseAnalytics
{
    public function build(Form $form, string $from, string $to, array $fieldIds = [], array $filters = []): array
    {
        $timezone = PlatformTimezone::forUser($form->user);
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();
        $q = $form->submissions()->completed()->where('is_spam', false)->whereBetween('created_at', [$start, $end]);
        $fields = collect($form->fields ?? [])->filter(fn ($f) => ! empty($f['id']) && ! in_array($f['type'] ?? 'text', ['heading', 'paragraph', 'divider', 'page_break', 'section', 'hidden']));
        // Only allow filters for this form's real fields; values remain parameter-bound JSON queries.
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '' && $fields->contains('id', $key)) {
                $q->where('data->'.$key, $value);
            }
        }
        $rows = $q->get();
        $total = $rows->count();
        $reports = [];
        foreach ($fields as $f) {
            if ($fieldIds && ! in_array($f['id'], $fieldIds, true)) {
                continue;
            }
            $values = collect();
            $filled = 0;
            foreach ($rows as $submission) {
                $answers = [];
                $parent = $f['parent'] ?? null;
                $group = $parent ? ($submission->data[$parent] ?? null) : null;
                if (is_array($group) && ! empty($group['_repeatable_group'])) {
                    foreach ($group['copies'] ?? [] as $copy) {
                        $answers[] = $copy[$f['id']] ?? null;
                    }
                } else {
                    $answers[] = $submission->data[$f['id']] ?? null;
                }
                $valid = array_values(array_filter($answers, fn ($v) => $this->hasAnswer($v) && (($f['type'] ?? '') !== 'consent' || in_array($v, [true, 1, '1'], true)) && ! (is_string($v) && (str_starts_with($v, '[upload failed:') || str_starts_with($v, '[signature not stored:')))));
                if ($valid) {
                    $filled++;
                }foreach ($valid as $value) {
                    $values->push($value);
                }
            }
            $r = ['id' => $f['id'], 'label' => $f['label'] ?? $f['id'], 'type' => $f['type'] ?? 'text', 'filled' => $filled, 'entries' => $values->count(), 'rate' => $total ? round($filled / $total * 100, 1) : 0, 'distribution' => [], 'numeric' => null, 'ranking' => []];
            if (in_array($r['type'], ['number', 'rating', 'scale', 'slider'])) {
                $nums = $values->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);
                $r['numeric'] = $nums->isNotEmpty() ? ['count' => $nums->count(), 'sum' => $nums->sum(), 'average' => round($nums->avg(), 2), 'min' => $nums->min(), 'max' => $nums->max()] : null;
            }
            if (in_array($r['type'], ['consent', 'select', 'radio', 'checkbox', 'yes_no', 'rating', 'scale', 'image_choice', 'ranking', 'date', 'time', 'country', 'currency'])) {
                foreach ($values as $v) {
                    foreach (is_array($v) ? array_unique(array_filter($v, 'is_scalar')) : [$v] as $option) {
                        if (! is_scalar($option)) {
                            continue;
                        } $label = mb_substr((string) $option, 0, 180);
                        $r['distribution'][$label] = ($r['distribution'][$label] ?? 0) + 1;
                    }
                }
                arsort($r['distribution']);
                $r['distribution'] = array_slice($r['distribution'], 0, 30, true);
            }
            if ($r['type'] === 'ranking') {
                $positions = [];
                foreach ($values as $ranking) {
                    if (is_array($ranking)) {
                        foreach (array_values($ranking) as $index => $option) {
                            if (is_scalar($option)) {
                                $positions[(string) $option][] = $index + 1;
                            }
                        }
                    }
                }
                foreach ($positions as $option => $votes) {
                    $r['ranking'][$option] = round(array_sum($votes) / count($votes), 2);
                }asort($r['ranking']);
            }
            $reports[] = $r;
        }
        $daily = [];
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $daily[$d->toDateString()] = 0;
        }
        foreach ($rows as $row) {
            $day = $row->created_at->copy()->timezone($timezone)->toDateString();
            $daily[$day] = ($daily[$day] ?? 0) + 1;
        }
        $devices = [];
        $countries = [];
        $sources = [];
        $revenue = [];
        foreach ($rows as $row) {
            $ua = strtolower((string) $row->user_agent);
            $device = $ua === '' ? 'Unknown' : (preg_match('/ipad|tablet/', $ua) ? 'Tablet' : (preg_match('/mobi|android|iphone/', $ua) ? 'Mobile' : 'Desktop'));
            $devices[$device] = ($devices[$device] ?? 0) + 1;
            $country = $row->country ?: 'Unknown';
            $countries[$country] = ($countries[$country] ?? 0) + 1;
            $source = parse_url($row->referrer ?? '', PHP_URL_HOST) ?: 'Direct';
            $sources[$source] = ($sources[$source] ?? 0) + 1;
            if ($row->payment_status === 'paid') {
                $currency = strtoupper($row->currency ?: 'USD');
                $revenue[$currency] = ($revenue[$currency] ?? 0) + (int) $row->amount_cents;
            }
        }
        $funnel = null;
        if (Schema::hasTable('form_visits')) {
            $v = DB::table('form_visits')->where('form_id', $form->id)->whereBetween('created_at', [$start, $end]);
            $views = (clone $v)->count();
            $starts = (clone $v)->whereNotNull('started_at')->count();
            $finished = (clone $v)->whereNotNull('completed_at')->count();
            $funnel = ['views' => $views, 'starts' => $starts, 'completed' => $finished, 'abandoned' => (clone $v)->whereNotNull('started_at')->whereNull('completed_at')->where('updated_at', '<', now()->subMinutes(30))->count(), 'average_seconds' => (clone $v)->whereNotNull('completed_at')->avg('duration_seconds'), 'conversion' => $views ? round($finished / $views * 100, 1) : null];
        }
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prior = $form->submissions()->completed()->where('is_spam', false)->whereBetween('created_at', [Carbon::parse($from, $timezone)->subDays($days)->startOfDay()->utc(), Carbon::parse($from, $timezone)->subDay()->endOfDay()->utc()]);
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '' && $fields->contains('id', $key)) {
                $prior->where('data->'.$key, $value);
            }
        }
        $previous = $prior->count();

        return compact('from', 'to', 'total', 'reports', 'daily', 'devices', 'countries', 'sources', 'revenue', 'funnel', 'previous', 'timezone');
    }

    private function hasAnswer(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $part) {
                if ($this->hasAnswer($part)) {
                    return true;
                }
            }

return false;
        }

        return $value !== null && (! is_string($value) || trim($value) !== '');
    }
}
