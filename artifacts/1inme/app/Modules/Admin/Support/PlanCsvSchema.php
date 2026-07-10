<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanFormCatalogue;

/**
 * Single source of truth for the admin "pricing plans" CSV round-trip.
 *
 * The {@see \App\Modules\Admin\Controllers\PlanController::export()} stream
 * and the CSV importer both build their column list from {@see columns()}
 * so the exported file and the importer stay in perfect lockstep: the same
 * human-readable headers, the same ordering, the same value formatting.
 *
 * Each column descriptor carries:
 *   - header:   the human-readable CSV column title (matched on import)
 *   - group:    'core' | 'price' | 'feature'
 *   - key:      plan attribute / feature key / price identifier
 *   - type:     how the cell is formatted (export) and parsed (import)
 *   - export:   Closure(Plan): string  — the exact exported cell value
 *   - match:    true only for the Slug column (used to find the plan; never
 *               written back)
 *   - required: true when a non-empty cell is mandatory (Name)
 *   - min/max:  numeric bounds for int / unlimited_int / float columns
 *   - currency/cycle: price columns only
 *   - options:  allowed values for 'select' columns
 */
class PlanCsvSchema
{
    /**
     * The deterministic, ordered column specification. Mirrors the layout
     * the human-readable export has always produced.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function columns(): array
    {
        $columns = [];

        // ---- Core attributes ----
        $columns[] = ['header' => 'Name',       'group' => 'core', 'key' => 'name',        'type' => 'string', 'required' => true,  'export' => fn (Plan $p) => (string) $p->name];
        $columns[] = ['header' => 'Slug',       'group' => 'core', 'key' => 'slug',        'type' => 'string', 'match' => true,     'export' => fn (Plan $p) => (string) $p->slug];
        $columns[] = ['header' => 'Status',     'group' => 'core', 'key' => 'status',      'type' => 'status', 'export' => fn (Plan $p) => (string) $p->status];
        $columns[] = ['header' => 'Default',    'group' => 'core', 'key' => 'is_default',  'type' => 'yesno',  'export' => fn (Plan $p) => $p->is_default  ? 'Yes' : 'No'];
        $columns[] = ['header' => 'Popular',    'group' => 'core', 'key' => 'is_popular',  'type' => 'yesno',  'export' => fn (Plan $p) => $p->is_popular  ? 'Yes' : 'No'];
        $columns[] = ['header' => 'Internal',   'group' => 'core', 'key' => 'is_internal', 'type' => 'yesno',  'export' => fn (Plan $p) => $p->is_internal ? 'Yes' : 'No'];
        $columns[] = ['header' => 'Archived',   'group' => 'core', 'key' => 'is_archived', 'type' => 'yesno',  'export' => fn (Plan $p) => $p->is_archived ? 'Yes' : 'No'];
        $columns[] = ['header' => 'Sort order', 'group' => 'core', 'key' => 'sort_order',  'type' => 'int', 'min' => 0, 'export' => fn (Plan $p) => (string) $p->sort_order];

        // ---- Pricing — USD + INR × monthly + annual (minor → major) ----
        foreach (['USD', 'INR'] as $currency) {
            foreach (['monthly', 'annual'] as $cycle) {
                $columns[] = [
                    'header'   => "Price {$currency}/{$cycle}",
                    'group'    => 'price',
                    'key'      => "price_{$currency}_{$cycle}",
                    'type'     => 'price',
                    'currency' => $currency,
                    'cycle'    => $cycle,
                    'min'      => 0,
                    'export'   => function (Plan $p) use ($currency, $cycle) {
                        $price = $p->prices->first(
                            fn ($pr) => $pr->currency === $currency && $pr->billing_cycle === $cycle
                        );
                        if (!$price) { return ''; }
                        return number_format($price->amount_minor_units / 100, 2, '.', '');
                    },
                ];
            }
        }

        // ---- Modules ----
        foreach (PlanFormCatalogue::modules() as $key => $meta) {
            $columns[] = [
                'header' => 'Module: ' . $meta['label'],
                'group'  => 'feature',
                'key'    => $key,
                'type'   => 'yesno',
                'export' => fn (Plan $p) => !empty(($p->features ?? [])[$key]) ? 'Yes' : 'No',
            ];
        }

        // ---- Quantity limits (-1 → "Unlimited") ----
        foreach (PlanFormCatalogue::quantityLimits() as $q) {
            $key = $q['key'];
            $columns[] = [
                'header' => $q['label'],
                'group'  => 'feature',
                'key'    => $key,
                'type'   => 'unlimited_int',
                'min'    => -1,
                'max'    => $q['max'] ?? null,
                'export' => function (Plan $p) use ($key) {
                    $features = $p->features ?? [];
                    if (!array_key_exists($key, $features)) { return ''; }
                    $val = $features[$key];
                    if (is_array($val)) {
                        if (!array_key_exists('default', $val)) { return ''; }
                        $val = $val['default'];
                    }
                    return (int) $val === -1 ? 'Unlimited' : (string) $val;
                },
            ];
        }

        // ---- Feature flags (bool → Yes/No, select → value) ----
        foreach (PlanFormCatalogue::featureFlags() as $flag) {
            $key  = $flag['key'];
            $type = $flag['type'];
            $col  = [
                'header' => PlanFormCatalogue::labelFor($key),
                'group'  => 'feature',
                'key'    => $key,
                'type'   => $type === 'bool' ? 'yesno' : 'select',
                'export' => function (Plan $p) use ($key, $type) {
                    $features = $p->features ?? [];
                    $val = $features[$key] ?? null;
                    if ($type === 'bool') {
                        return !empty($val) ? 'Yes' : 'No';
                    }
                    return (string) ($val ?? '');
                },
            ];
            if ($type === 'select') {
                $col['options'] = array_keys($flag['options'] ?? []);
            }
            $columns[] = $col;
        }

        // ---- AI suite booleans ----
        foreach (PlanFormCatalogue::aiSuite() as $row) {
            $key = $row['key'];
            $columns[] = [
                'header' => 'AI: ' . PlanFormCatalogue::labelFor($key),
                'group'  => 'feature',
                'key'    => $key,
                'type'   => 'yesno',
                'export' => fn (Plan $p) => !empty(($p->features ?? [])[$key]) ? 'Yes' : 'No',
            ];
        }

        // ---- AI coin multipliers (float, 1 = no change) ----
        foreach (PlanFormCatalogue::aiCoinMultipliers() as $row) {
            $key = $row['key'];
            $columns[] = [
                'header' => $row['label'],
                'group'  => 'feature',
                'key'    => $key,
                'type'   => 'float',
                'min'    => 0,
                'export' => function (Plan $p) use ($key) {
                    $val = ($p->features ?? [])[$key] ?? null;
                    return $val !== null ? (string) $val : '1';
                },
            ];
        }

        // ---- Referral program integer fields ----
        foreach (PlanFormCatalogue::referralFields() as $row) {
            $key = $row['key'];
            $columns[] = [
                'header' => $row['label'],
                'group'  => 'feature',
                'key'    => $key,
                'type'   => 'int',
                'min'    => 0,
                'export' => function (Plan $p) use ($key) {
                    $val = ($p->features ?? [])[$key] ?? null;
                    return $val !== null ? (string) (int) $val : '0';
                },
            ];
        }

        return $columns;
    }

    /**
     * Ordered list of the header strings, for the CSV header row.
     *
     * @return array<int,string>
     */
    public static function headers(): array
    {
        return array_column(self::columns(), 'header');
    }

    /**
     * The header used to match a CSV row to an existing plan.
     */
    public static function matchHeader(): string
    {
        foreach (self::columns() as $col) {
            if (!empty($col['match'])) {
                return $col['header'];
            }
        }
        return 'Slug';
    }

    /**
     * Parse and validate a single raw CSV cell against a column descriptor.
     *
     * @return array{skip:bool,value:mixed,canonical:string,error:?string}
     *   - skip:      the cell is blank / means "leave unchanged" (never an
     *                error unless the column is required)
     *   - value:     the typed value ready to persist (when !skip && !error)
     *   - canonical: the value re-formatted exactly as export would render
     *                it, so callers can diff old vs new as plain strings
     *   - error:     a human-readable validation message, or null
     */
    public static function parseCell(array $col, ?string $raw): array
    {
        $skip = ['skip' => true, 'value' => null, 'canonical' => '', 'error' => null];
        $err  = fn (string $m) => ['skip' => false, 'value' => null, 'canonical' => '', 'error' => $m];
        $ok   = fn (mixed $v, string $c) => ['skip' => false, 'value' => $v, 'canonical' => $c, 'error' => null];

        // Undo the export's spreadsheet formula-injection guard (a leading
        // apostrophe prefixed onto values starting with = + - @).
        $s = (string) ($raw ?? '');
        if ($s !== '' && $s[0] === "'") {
            $s = substr($s, 1);
        }
        $s = trim($s);

        $required = !empty($col['required']);
        if ($s === '') {
            return $required ? $err('is required') : $skip;
        }

        switch ($col['type']) {
            case 'string':
                return $ok($s, $s);

            case 'status':
                $v = strtolower($s);
                if (!in_array($v, ['active', 'inactive'], true)) {
                    return $err('must be "active" or "inactive"');
                }
                return $ok($v, $v);

            case 'select':
                $v = strtolower($s);
                $options = $col['options'] ?? [];
                if ($options && !in_array($v, $options, true)) {
                    return $err('must be one of: ' . implode(', ', $options));
                }
                return $ok($v, $v);

            case 'yesno':
                $v = strtolower($s);
                if (in_array($v, ['yes', 'y', 'true', '1'], true)) {
                    return $ok(true, 'Yes');
                }
                if (in_array($v, ['no', 'n', 'false', '0'], true)) {
                    return $ok(false, 'No');
                }
                return $err('must be "Yes" or "No"');

            case 'int':
                if (!preg_match('/^-?\d+$/', $s)) {
                    return $err('must be a whole number');
                }
                $n = (int) $s;
                if (isset($col['min']) && $n < $col['min']) {
                    return $err('must be at least ' . $col['min']);
                }
                if (isset($col['max']) && $col['max'] !== null && $n > $col['max']) {
                    return $err('must be at most ' . $col['max']);
                }
                return $ok($n, (string) $n);

            case 'unlimited_int':
                $v = strtolower($s);
                if ($v === 'unlimited' || $v === '-1') {
                    return $ok(-1, 'Unlimited');
                }
                if (!preg_match('/^-?\d+$/', $s)) {
                    return $err('must be a whole number or "Unlimited"');
                }
                $n = (int) $s;
                if ($n < -1) {
                    return $err('must be -1 (unlimited) or greater');
                }
                if (isset($col['max']) && $col['max'] !== null && $n > $col['max']) {
                    return $err('must be at most ' . $col['max']);
                }
                return $ok($n, $n === -1 ? 'Unlimited' : (string) $n);

            case 'float':
                if (!is_numeric($s)) {
                    return $err('must be a number');
                }
                $f = (float) $s;
                if (isset($col['min']) && $f < $col['min']) {
                    return $err('must be at least ' . $col['min']);
                }
                return $ok($f, (string) $f);

            case 'price':
                if (!is_numeric($s)) {
                    return $err('must be a number');
                }
                $f = (float) $s;
                if ($f < 0) {
                    return $err('must be 0 or greater');
                }
                $minor = (int) round($f * 100);
                return $ok($minor, number_format($minor / 100, 2, '.', ''));

            default:
                return $ok($s, $s);
        }
    }
}
