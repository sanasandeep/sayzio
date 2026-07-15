@extends('admin.layouts.app')
@section('title', 'Compare Plans')
@section('page-title', 'Compare &amp; Edit Plans')

@php
use App\Modules\Common\Support\PlanFormCatalogue;

// ── Editable sections for the compare grid ───────────────────────────────
// Each "row" spec: key, label, src (core|feature), type, and type-specific options.

$coreSections = [
    'Basics' => [
        ['key' => 'name',        'label' => 'Plan Name',   'type' => 'text'],
        ['key' => 'status',      'label' => 'Status',      'type' => 'select',
         'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ['key' => 'sort_order',  'label' => 'Sort Order',  'type' => 'number', 'min' => 0],
        ['key' => 'is_popular',  'label' => 'Most Popular','type' => 'bool',
         'hint' => 'Only one plan can be popular at a time'],
        ['key' => 'is_internal', 'label' => 'Internal',    'type' => 'bool',
         'hint' => 'Hidden from public pricing & recommender'],
    ],
    'Pricing (minor units)' => [
        ['key' => 'monthly_price',           'label' => 'Monthly USD',  'type' => 'number', 'min' => 0,
         'hint' => 'Cents — e.g. 999 = $9.99'],
        ['key' => 'annual_price',            'label' => 'Annual USD',   'type' => 'number', 'min' => 0,
         'hint' => 'Cents'],
        ['key' => 'monthly_price_secondary', 'label' => 'Monthly INR',  'type' => 'number', 'min' => 0,
         'hint' => 'Paise — e.g. 79900 = ₹799'],
        ['key' => 'annual_price_secondary',  'label' => 'Annual INR',   'type' => 'number', 'min' => 0,
         'hint' => 'Paise'],
    ],
    'Days & Policy' => [
        ['key' => 'trial_days',         'label' => 'Trial Days',         'type' => 'number', 'min' => 0],
        ['key' => 'grace_days',         'label' => 'Grace Days',         'type' => 'number', 'min' => 0],
        ['key' => 'refund_window_days', 'label' => 'Refund Window Days', 'type' => 'number', 'min' => 0],
    ],
];

// Quantity limits shown in compare (most frequently compared subset)
$compareQtyKeys = [
    'max_links', 'max_biolinks', 'storage_limit_mb', 'max_file_size_mb',
    'contacts_max', 'max_custom_domains', 'stats_retention_days',
    'api_calls_monthly', 'api_rate_per_min', 'max_forms',
    'min_alias_length', 'max_alias_length', 'max_aliases_per_link',
    'max_workspaces', 'max_seats_per_workspace',
];
$allQty = collect(PlanFormCatalogue::quantityLimits())->keyBy('key');
$compareQtyRows = array_values(array_filter(
    PlanFormCatalogue::quantityLimits(),
    fn($q) => in_array($q['key'], $compareQtyKeys)
));

// Key feature flags to show (curated for compare purposes)
$compareFlagKeys = [
    'analytics', 'analytics_export',
    'custom_branding', 'remove_branding', 'custom_favicon', 'custom_code',
    'api_access', 'priority_support',
    'pixels', 'utm_params', 'qr_customization', 'seo_settings',
    'link_password', 'link_expiry', 'link_geo_targeting',
    'link_device_targeting', 'link_smart_rules', 'link_active_window',
    'scheduled_posts', 'verification_eligible', 'teams', 'ecommerce',
    'files', 'vaults', 'tasks', 'leads',
];
$allFlags = collect(PlanFormCatalogue::featureFlags())->keyBy('key');
$compareFlagRows = [];
$localFlagLabels = ['teams' => 'Teams Enabled', 'ecommerce' => 'E-Commerce'];
foreach ($compareFlagKeys as $fk) {
    if ($allFlags->has($fk)) {
        $compareFlagRows[] = $allFlags->get($fk);
    } elseif (isset($localFlagLabels[$fk])) {
        $compareFlagRows[] = ['key' => $fk, 'type' => 'bool', 'label' => $localFlagLabels[$fk]];
    }
}

// All modules
$modules = PlanFormCatalogue::modules();

// ── Build initial Alpine data for each plan ──────────────────────────────
$planData = [];
foreach ($plans as $plan) {
    $f = $plan->features ?? [];

    $entry = [
        'name'                    => $plan->name,
        'status'                  => $plan->status,
        'sort_order'              => (int) $plan->sort_order,
        'is_popular'              => (bool) $plan->is_popular,
        'is_internal'             => (bool) $plan->is_internal,
        'monthly_price'           => (int) round(((float) $plan->monthly_price) * 100),
        'annual_price'            => (int) round(((float) $plan->annual_price) * 100),
        'monthly_price_secondary' => (int) round(((float) $plan->monthly_price_secondary) * 100),
        'annual_price_secondary'  => (int) round(((float) $plan->annual_price_secondary) * 100),
        'trial_days'              => (int) $plan->trial_days,
        'grace_days'              => (int) $plan->grace_days,
        'refund_window_days'      => (int) $plan->refund_window_days,
        'features'                => [],
    ];

    // Quantities
    foreach ($compareQtyRows as $q) {
        $v = $f[$q['key']] ?? null;
        if (is_array($v)) { $v = $v['default'] ?? 0; }
        $entry['features'][$q['key']] = (int) ($v ?? 0);
    }

    // Modules: default TRUE if the key doesn't exist (mirrors _form.blade.php)
    foreach (array_keys($modules) as $mk) {
        $entry['features'][$mk] = array_key_exists($mk, $f) ? (bool) $f[$mk] : true;
    }

    // Feature flags
    foreach ($compareFlagRows as $flag) {
        if (($flag['type'] ?? 'bool') === 'bool') {
            $entry['features'][$flag['key']] = (bool) ($f[$flag['key']] ?? false);
        } else {
            $entry['features'][$flag['key']] = $f[$flag['key']] ?? ($flag['default'] ?? '');
        }
    }

    $planData[(string) $plan->id] = $entry;
}
@endphp

@push('styles')
<style>
/* ── Compare grid table ─────────────────────────────────────────────── */
.compare-table { border-collapse: separate; border-spacing: 0; }
.compare-table th,
.compare-table td { padding: 0; }

/* Sticky label column */
.compare-label-col {
    position: sticky; left: 0; z-index: 2;
    background: var(--bg-sidebar, #18181b);
    min-width: 180px; max-width: 220px;
    border-right: 1px solid rgba(255,255,255,0.07);
}
html.light-mode .compare-label-col { background: #f1f5f9; border-right-color: #e2e8f0; }

/* Sticky plan-header row */
.compare-table thead th { position: sticky; top: 0; z-index: 3; }
.compare-table thead .compare-label-col { z-index: 4; }

/* Section header rows */
.compare-section-row td {
    background: rgba(255,255,255,0.035);
    padding: 6px 14px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    border-top: 1px solid rgba(255,255,255,0.07);
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
html.light-mode .compare-section-row td {
    background: rgba(15,23,42,0.04);
    color: rgba(15,23,42,0.45);
    border-top-color: #e2e8f0;
    border-bottom-color: #e2e8f0;
}
.compare-section-row .compare-label-col {
    background: rgba(255,255,255,0.05);
}
html.light-mode .compare-section-row .compare-label-col {
    background: rgba(15,23,42,0.05);
}

/* Data rows */
.compare-data-row:hover td { background: rgba(255,255,255,0.02); }
html.light-mode .compare-data-row:hover td { background: rgba(15,23,42,0.02); }

/* Diff: at least two plans have different values */
.compare-data-row.row-differs td:not(.compare-label-col) {
    background: rgba(245,158,11,0.05);
}
html.light-mode .compare-data-row.row-differs td:not(.compare-label-col) {
    background: rgba(245,158,11,0.06);
}

/* Dirty cell (value edited away from original) */
.compare-cell-dirty { position: relative; }
.compare-cell-dirty::after {
    content: '';
    position: absolute; inset: 2px;
    border: 2px solid rgba(245,158,11,0.6);
    border-radius: 8px;
    pointer-events: none;
}
html.light-mode .compare-cell-dirty::after {
    border-color: rgba(202,138,4,0.65);
}

/* Error cell */
.compare-cell-error .cmp-input {
    border-color: rgba(239,68,68,0.7) !important;
    box-shadow: 0 0 0 2px rgba(239,68,68,0.15) !important;
}
html.light-mode .compare-cell-error .cmp-input {
    border-color: rgba(220,38,38,0.7) !important;
}

/* Shared input style */
.cmp-input {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: #fff;
    font-size: 13px;
    padding: 5px 10px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    min-width: 0;
}
.cmp-input:focus { border-color: rgba(61,107,255,0.6); box-shadow: 0 0 0 2px rgba(61,107,255,0.15); }
html.light-mode .cmp-input {
    background: #fff;
    border-color: #cbd5e1;
    color: #0f172a;
}
html.light-mode .cmp-input:focus { border-color: #3d6bff; box-shadow: 0 0 0 2px rgba(61,107,255,0.12); }

/* Checkbox toggle in cells */
.cmp-toggle {
    width: 20px; height: 20px;
    accent-color: #3d6bff;
    cursor: pointer;
}

/* Plan header card */
.compare-plan-header {
    background: var(--bg-card, #1f1f23);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 12px 16px;
    min-width: 200px;
}
html.light-mode .compare-plan-header { background: #f8fafc; border-bottom-color: #e2e8f0; }

/* Floating save bar */
.compare-save-bar {
    position: sticky; bottom: 0; z-index: 20;
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 12px 20px;
    display: flex; align-items: center; gap: 12px;
    background: rgba(18,18,22,0.95);
    backdrop-filter: blur(16px);
}
html.light-mode .compare-save-bar {
    background: rgba(248,250,252,0.97);
    border-top-color: #e2e8f0;
}
</style>
@endpush

@section('content')
<div x-data="planCompare(@js($planData))"
     x-init="init()"
     data-save-url="{{ route('admin.plans.compare.save') }}"
     class="flex flex-col"
     style="min-height: calc(100vh - 140px);">

{{-- ── Page header ─────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.plans.index') }}"
           @click.prevent="if (anyDirty()) { if (!confirm('You have unsaved changes. Leave anyway?')) return; } window.location.href = $event.currentTarget.href"
           class="p-2 rounded-xl border border-white/10 text-white/50 hover:text-white hover:border-white/30 transition text-sm">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-base font-semibold text-white/90">Comparing {{ $plans->count() }} plans</h1>
            <p class="text-xs text-white/40 mt-0.5">
                Edit any cell and click <strong>Save changes</strong>.
                Block allowlists and integration settings are preserved automatically.
                For advanced fields, use the individual plan editor.
            </p>
        </div>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <span x-show="saveOk" x-cloak x-transition
              class="text-sm text-emerald-400 flex items-center gap-1.5">
            <i class="fas fa-check-circle"></i> Saved!
        </span>
        <span x-show="saving" x-cloak class="text-sm text-white/50 flex items-center gap-1.5">
            <i class="fas fa-spinner fa-spin"></i> Saving…
        </span>
        <button type="button"
                @click="save()"
                :disabled="!anyDirty() || saving"
                class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fas fa-save mr-2"></i>Save changes
        </button>
        <span class="text-[11px] text-white/30">
            <span x-text="dirtyCount()"></span> plan(s) changed
        </span>
    </div>
</div>

{{-- ── Error summary ─────────────────────────────────────────────────── --}}
<template x-if="Object.keys(errors).length > 0">
    <div class="mb-4 glass rounded-xl border border-rose-500/30 bg-rose-500/5 p-4 text-sm text-rose-200">
        <p class="font-semibold mb-2"><i class="fas fa-exclamation-circle mr-1.5"></i>Some plans could not be saved:</p>
        <ul class="space-y-1 text-xs">
            <template x-for="[planId, planErrors] in Object.entries(errors)" :key="planId">
                <li>
                    <strong x-text="planNameById(planId)"></strong>:
                    <span x-text="Object.values(planErrors).flat().join(' · ')"></span>
                </li>
            </template>
        </ul>
    </div>
</template>

{{-- ── Legend ─────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-5 mb-4 text-xs text-white/40">
    <span class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded" style="background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.5);"></span>
        Values differ across plans
    </span>
    <span class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded" style="border:2px solid rgba(245,158,11,0.6);"></span>
        Cell edited (unsaved)
    </span>
    <span class="text-white/25">-1 = unlimited for quantity fields</span>
</div>

{{-- ── Compare table ─────────────────────────────────────────────────── --}}
<div class="flex-1 overflow-x-auto rounded-2xl border border-white/10 glass">
    <table class="compare-table w-full">
        {{-- ── Sticky plan-name header ──────────────────────────────── --}}
        <thead>
            <tr>
                <th class="compare-label-col compare-plan-header">
                    <span class="text-[10px] uppercase tracking-wider font-bold text-white/30">Field</span>
                </th>
                @foreach($plans as $plan)
                <th class="compare-plan-header text-left">
                    <div class="font-semibold text-white text-sm truncate">{{ $plan->name }}</div>
                    <div class="text-[10px] text-white/40 mt-0.5">
                        #{{ $plan->id }} · {{ ucfirst($plan->status) }}
                        @if($plan->is_internal) · <span class="text-amber-300/70">Internal</span>@endif
                    </div>
                    <a href="{{ route('admin.plans.edit', $plan) }}"
                       class="inline-flex items-center gap-1 text-[10px] text-blue-400/70 hover:text-blue-400 mt-1 transition">
                        <i class="fas fa-external-link-alt"></i>Full editor
                    </a>
                </th>
                @endforeach
            </tr>
        </thead>

        <tbody>

        {{-- ══ Core sections (Basics, Pricing, Days) ══════════════════════ --}}
        @foreach($coreSections as $sectionTitle => $coreRows)

        {{-- Section header --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">{{ $sectionTitle }}</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>

        @foreach($coreRows as $row)
        @php $rk = $row['key']; @endphp
        <tr class="compare-data-row"
            :class="coreRowDiffers('{{ $rk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $row['label'] }}</div>
                @if(!empty($row['hint']))
                <div class="text-[10px] text-white/35 mt-0.5">{{ $row['hint'] }}</div>
                @endif
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2"
                :class="{ 'compare-cell-dirty': isCoreDirty('{{ $pid }}', '{{ $rk }}'), 'compare-cell-error': !!coreErr('{{ $pid }}', '{{ $rk }}') }">
                @if($row['type'] === 'bool')
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               class="cmp-toggle"
                               x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        <span class="text-xs" :class="plans['{{ $pid }}']['{{ $rk }}'] ? 'text-blue-300' : 'text-white/40'"
                              x-text="plans['{{ $pid }}']['{{ $rk }}'] ? 'Yes' : 'No'"></span>
                    </label>
                @elseif($row['type'] === 'select')
                    <select class="cmp-input"
                            x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        @foreach($row['options'] as $optVal => $optLabel)
                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @elseif($row['type'] === 'number')
                    <input type="number"
                           class="cmp-input"
                           @isset($row['min']) min="{{ $row['min'] }}" @endisset
                           @isset($row['max']) max="{{ $row['max'] }}" @endisset
                           x-model.number="plans['{{ $pid }}']['{{ $rk }}']">
                @else
                    <input type="text"
                           class="cmp-input"
                           x-model="plans['{{ $pid }}']['{{ $rk }}']">
                @endif
                <p x-show="coreErr('{{ $pid }}', '{{ $rk }}')"
                   x-text="coreErr('{{ $pid }}', '{{ $rk }}')"
                   class="text-[10px] text-rose-400 mt-1 px-1" x-cloak></p>
            </td>
            @endforeach
        </tr>
        @endforeach

        @endforeach

        {{-- ══ Quantity limits ═════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Quantity Limits</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>

        @foreach($compareQtyRows as $q)
        @php $qk = $q['key']; @endphp
        <tr class="compare-data-row"
            :class="featRowDiffers('{{ $qk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $q['label'] }}</div>
                @if(!empty($q['hint']))
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $q['hint'] }}</div>
                @endif
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2"
                :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $qk }}'), 'compare-cell-error': !!featErr('{{ $pid }}', '{{ $qk }}') }">
                <div class="flex items-center gap-1">
                    <input type="number"
                           class="cmp-input"
                           min="-1"
                           x-model.number="plans['{{ $pid }}'].features['{{ $qk }}']">
                    <button type="button"
                            @click="plans['{{ $pid }}'].features['{{ $qk }}'] = -1"
                            title="Set unlimited (-1)"
                            class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-bold text-white/50 hover:text-white bg-white/5 hover:bg-white/10 transition">∞</button>
                </div>
                <p x-show="featErr('{{ $pid }}', '{{ $qk }}')"
                   x-text="featErr('{{ $pid }}', '{{ $qk }}')"
                   class="text-[10px] text-rose-400 mt-1 px-1" x-cloak></p>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Modules ══════════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Modules</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>

        @foreach($modules as $mk => $moduleMeta)
        <tr class="compare-data-row"
            :class="featRowDiffers('{{ $mk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $moduleMeta['label'] }}</div>
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight truncate">{{ $moduleMeta['desc'] }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5"
                :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $mk }}'), 'compare-cell-error': !!featErr('{{ $pid }}', '{{ $mk }}') }">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox"
                           class="cmp-toggle"
                           x-model="plans['{{ $pid }}'].features['{{ $mk }}']">
                    <span class="text-xs"
                          :class="plans['{{ $pid }}'].features['{{ $mk }}'] ? 'text-blue-300' : 'text-white/40'"
                          x-text="plans['{{ $pid }}'].features['{{ $mk }}'] ? 'On' : 'Off'"></span>
                </label>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Feature flags ══════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Feature Flags</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>

        @foreach($compareFlagRows as $flag)
        @php $fk = $flag['key']; $isSelect = ($flag['type'] ?? 'bool') === 'select'; @endphp
        <tr class="compare-data-row"
            :class="featRowDiffers('{{ $fk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">
                    {{ PlanFormCatalogue::labelFor($fk, $fk) }}
                </div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5"
                :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $fk }}'), 'compare-cell-error': !!featErr('{{ $pid }}', '{{ $fk }}') }">
                @if($isSelect)
                    <select class="cmp-input"
                            x-model="plans['{{ $pid }}'].features['{{ $fk }}']">
                        @foreach($flag['options'] ?? [] as $optVal => $optLabel)
                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               class="cmp-toggle"
                               x-model="plans['{{ $pid }}'].features['{{ $fk }}']">
                        <span class="text-xs"
                              :class="plans['{{ $pid }}'].features['{{ $fk }}'] ? 'text-blue-300' : 'text-white/40'"
                              x-text="plans['{{ $pid }}'].features['{{ $fk }}'] ? 'Yes' : 'No'"></span>
                    </label>
                @endif
                <p x-show="featErr('{{ $pid }}', '{{ $fk }}')"
                   x-text="featErr('{{ $pid }}', '{{ $fk }}')"
                   class="text-[10px] text-rose-400 mt-1 px-1" x-cloak></p>
            </td>
            @endforeach
        </tr>
        @endforeach

        </tbody>
    </table>
</div>

{{-- ── Sticky save bar (repeated at bottom for long grids) ──────────── --}}
<div class="compare-save-bar mt-0">
    <span class="text-xs text-white/40 flex-1">
        <span x-show="anyDirty()" x-cloak>
            <span x-text="dirtyCount()"></span> plan(s) have unsaved edits.
        </span>
        <span x-show="!anyDirty()">No unsaved changes.</span>
    </span>
    <span x-show="saveOk" x-cloak x-transition class="text-sm text-emerald-400 flex items-center gap-1.5">
        <i class="fas fa-check-circle"></i> Saved!
    </span>
    <span x-show="saving" x-cloak class="text-sm text-white/50 flex items-center gap-1.5">
        <i class="fas fa-spinner fa-spin"></i> Saving…
    </span>
    <button type="button"
            @click="save()"
            :disabled="!anyDirty() || saving"
            class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
        <i class="fas fa-save mr-2"></i>Save changes
    </button>
    <a href="{{ route('admin.plans.index') }}"
       @click.prevent="if (anyDirty()) { if (!confirm('You have unsaved changes. Leave anyway?')) return; } window.location.href = $event.currentTarget.href"
       class="px-4 py-2 rounded-xl text-sm font-medium text-white/60 hover:text-white border border-white/10 hover:border-white/25 transition">
        <i class="fas fa-arrow-left mr-1.5"></i>Back
    </a>
</div>

</div>{{-- /x-data --}}
@endsection

@push('scripts')
<script>
// Plan name map for error summary display
const __planNames = @js($plans->pluck('name', 'id')->mapWithKeys(fn($v, $k) => [(string) $k => $v]));

function planCompare(initial) {
    return {
        plans:    JSON.parse(JSON.stringify(initial)),
        original: JSON.parse(JSON.stringify(initial)),
        errors:   {},
        saving:   false,
        saveOk:   false,

        // ── Lookup helpers ──────────────────────────────────────────
        planNameById(id) {
            return __planNames[String(id)] || ('Plan #' + id);
        },

        // ── Dirty detection ─────────────────────────────────────────
        isCoreDirty(id, field) {
            return JSON.stringify(this.plans[id]?.[field]) !==
                   JSON.stringify(this.original[id]?.[field]);
        },
        isFeatDirty(id, key) {
            return JSON.stringify(this.plans[id]?.features?.[key]) !==
                   JSON.stringify(this.original[id]?.features?.[key]);
        },

        // Does any plan differ from the others on a core field?
        coreRowDiffers(field) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = JSON.stringify(this.plans[ids[0]]?.[field]);
            return ids.some(id => JSON.stringify(this.plans[id]?.[field]) !== ref);
        },
        // Does any plan differ from the others on a feature key?
        featRowDiffers(key) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = JSON.stringify(this.plans[ids[0]]?.features?.[key]);
            return ids.some(id => JSON.stringify(this.plans[id]?.features?.[key]) !== ref);
        },

        anyDirty() {
            return Object.keys(this.plans).some(id =>
                JSON.stringify(this.plans[id]) !== JSON.stringify(this.original[id])
            );
        },

        dirtyCount() {
            return Object.keys(this.plans).filter(id =>
                JSON.stringify(this.plans[id]) !== JSON.stringify(this.original[id])
            ).length;
        },

        // ── Error helpers ───────────────────────────────────────────
        coreErr(id, field) {
            const errs = this.errors[id];
            if (!errs) return '';
            const msgs = errs[field];
            return Array.isArray(msgs) ? (msgs[0] || '') : (msgs || '');
        },
        featErr(id, key) {
            const errs = this.errors[id];
            if (!errs) return '';
            // Laravel validation uses dot notation for nested paths
            const msgs = errs['features.' + key] || errs['features_' + key];
            return Array.isArray(msgs) ? (msgs[0] || '') : (msgs || '');
        },

        // ── Save ────────────────────────────────────────────────────
        async save() {
            if (!this.anyDirty() || this.saving) return;
            this.saving = true;
            this.errors = {};
            this.saveOk = false;

            // Collect only plans that actually changed
            const payload = {};
            for (const id of Object.keys(this.plans)) {
                if (JSON.stringify(this.plans[id]) !== JSON.stringify(this.original[id])) {
                    payload[id] = this.plans[id];
                }
            }

            try {
                const resp = await fetch(this.$el.dataset.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ plans: payload }),
                });
                const data = await resp.json();

                if (data.errors && Object.keys(data.errors).length > 0) {
                    this.errors = data.errors;
                }

                if (data.ok) {
                    // Advance the baseline for plans that saved successfully
                    for (const id of Object.keys(payload)) {
                        if (!this.errors[id]) {
                            this.original[id] = JSON.parse(JSON.stringify(this.plans[id]));
                        }
                    }
                    this.saveOk = true;
                    setTimeout(() => { this.saveOk = false; }, 3500);
                }
            } catch (err) {
                console.error('[planCompare] save failed', err);
            }

            this.saving = false;
        },

        // ── Lifecycle ───────────────────────────────────────────────
        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.anyDirty()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },
    };
}
</script>
@endpush
