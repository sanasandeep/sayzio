<?php
namespace App\Services\Billing;

/** Additive components share one taxable base and one inclusive/exclusive mode. */
class TaxComponents
{
    public static function allocate(int $taxMinor, array $components): array
    {
        $totalRate = array_sum(array_column($components, 'rate_bps'));
        if ($totalRate <= 0) return [];
        $result = []; $remainders = []; $remaining = $taxMinor;
        foreach (array_values($components) as $i => $component) {
            $exact = $taxMinor * $component['rate_bps'] / $totalRate;
            $amount = (int) floor($exact);
            $remaining -= $amount;
            $remainders[$i] = $exact - $amount;
            $result[] = ['name' => $component['name'], 'rate_bps' => (int) $component['rate_bps'], 'amount_minor' => $amount];
        }
        arsort($remainders);
        foreach (array_keys($remainders) as $index) {
            if ($remaining <= 0) break;
            if ($result[$index]['rate_bps'] > 0) { $result[$index]['amount_minor']++; $remaining--; }
        }
        return $result;
    }

    public static function normalize(array $data): array
    {
        if (isset($data['rate_percent'])) $data['rate_bps'] = (int) round($data['rate_percent'] * 100);
        unset($data['rate_percent']);
        if (!array_key_exists('components', $data)) return $data;
        $components = array_values(array_filter($data['components'] ?? [], fn ($c) => trim($c['name'] ?? '') !== ''));
        foreach ($components as &$component) {
            $component = ['name' => trim($component['name']), 'rate_bps' => isset($component['rate_percent']) ? (int) round($component['rate_percent'] * 100) : (int) ($component['rate_bps'] ?? 0)];
        }
        unset($component);
        if ($components) {
            if (!empty($data['is_compound'])) throw \Illuminate\Validation\ValidationException::withMessages(['components' => 'Component profiles use additive rates. Disable compound tax.']);
            $data['rate_bps'] = array_sum(array_column($components, 'rate_bps'));
            if ($data['rate_bps'] > 10000) throw \Illuminate\Validation\ValidationException::withMessages(['components' => 'Combined rate must be at most 100%.']);
        }
        $data['components'] = $components ?: null;
        return $data;
    }
}
