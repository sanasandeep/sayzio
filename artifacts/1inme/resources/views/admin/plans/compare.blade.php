@extends('admin.layouts.app')
@section('title', 'Compare Plans')
@section('page-title', 'Compare & Edit Plans')

@php
use App\Modules\Common\Support\PlanFormCatalogue;
use App\Modules\Admin\Models\Addon;

// ── All catalogue data ────────────────────────────────────────────────────
$allQtyRows      = PlanFormCatalogue::quantityLimits();
$allFlagRows     = PlanFormCatalogue::featureFlags();
$allFlagsByKey   = collect($allFlagRows)->keyBy('key');
$modules         = PlanFormCatalogue::modules();
$aiSuiteItems    = PlanFormCatalogue::aiSuite();
$aiMultipliers   = PlanFormCatalogue::aiCoinMultipliers();
$referralFields  = PlanFormCatalogue::referralFields();
$coinGrants      = PlanFormCatalogue::includedCoinGrants();
$blocksByCat     = PlanFormCatalogue::blockTypesByCategory();
$integrations    = PlanFormCatalogue::integrationMatrix();
$aliasTypes      = PlanFormCatalogue::aliasLinkTypes();
$addons          = Addon::ordered()->get();

// Flatten all block slugs → label for JS
$allBlockSlugs = [];
foreach ($blocksByCat as $cat) {
    foreach ($cat['types'] as $slug => $meta) {
        $allBlockSlugs[$slug] = $meta['label'];
    }
}

// Core sections for the first three rows-groups
$coreSections = [
    'Basics' => [
        ['key' => 'name',        'label' => 'Plan Name',    'type' => 'text'],
        ['key' => 'description', 'label' => 'Description',  'type' => 'text',
         'hint' => 'Shown on the public pricing page under the plan name.'],
        ['key' => 'status',      'label' => 'Status',       'type' => 'select',
         'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ['key' => 'sort_order',  'label' => 'Sort Order',   'type' => 'number', 'min' => 0],
        ['key' => 'is_popular',  'label' => 'Most Popular', 'type' => 'bool',
         'hint' => 'Only one plan can be marked popular at a time.'],
        ['key' => 'is_internal', 'label' => 'Internal',     'type' => 'bool',
         'hint' => 'Hidden from public pricing & the recommender.'],
    ],
    'Pricing (minor units)' => [
        ['key' => 'monthly_price',           'label' => 'Monthly USD',  'type' => 'number', 'min' => 0, 'hint' => 'Cents, e.g. 999 = $9.99'],
        ['key' => 'annual_price',            'label' => 'Annual USD',   'type' => 'number', 'min' => 0, 'hint' => 'Cents'],
        ['key' => 'monthly_price_secondary', 'label' => 'Monthly INR',  'type' => 'number', 'min' => 0, 'hint' => 'Paise, e.g. 79900 = ₹799'],
        ['key' => 'annual_price_secondary',  'label' => 'Annual INR',   'type' => 'number', 'min' => 0, 'hint' => 'Paise'],
    ],
    'Days & Policy' => [
        ['key' => 'trial_days',         'label' => 'Trial Days',         'type' => 'number', 'min' => 0],
        ['key' => 'grace_days',         'label' => 'Grace Days',         'type' => 'number', 'min' => 0],
        ['key' => 'refund_window_days', 'label' => 'Refund Window (days)', 'type' => 'number', 'min' => 0],
    ],
];

// Build intro discount sub-rows (shown inline when intro enabled)
$introRows = [
    ['key' => 'intro_enabled',         'label' => 'Enable Intro Discount',  'type' => 'bool', 'hint' => 'First billing term only; never stacks with promo codes.'],
    ['key' => 'intro_type',            'label' => 'Discount Type',          'type' => 'select', 'options' => ['percent' => 'Percentage off', 'fixed' => 'Fixed amount off'],
     'show_when' => 'intro_enabled'],
    ['key' => 'intro_percent',         'label' => 'Percent Off (1–100)',    'type' => 'number', 'min' => 0, 'max' => 100,
     'show_when' => 'intro_type_percent'],
    ['key' => 'intro_fixed_usd',       'label' => 'Fixed USD Off (cents)',  'type' => 'number', 'min' => 0,
     'show_when' => 'intro_type_fixed'],
    ['key' => 'intro_fixed_inr',       'label' => 'Fixed INR Off (paise)', 'type' => 'number', 'min' => 0,
     'show_when' => 'intro_type_fixed'],
    ['key' => 'intro_cycles_monthly',  'label' => 'Applies to Monthly',    'type' => 'bool',
     'show_when' => 'intro_enabled'],
    ['key' => 'intro_cycles_annual',   'label' => 'Applies to Annual',     'type' => 'bool',
     'show_when' => 'intro_enabled'],
    ['key' => 'intro_label',           'label' => 'Badge Label',            'type' => 'text',
     'hint' => 'Optional short text shown on the pricing card.', 'show_when' => 'intro_enabled'],
];

// ── Build Alpine planData ─────────────────────────────────────────────────
$planData = [];
foreach ($plans as $plan) {
    $f = $plan->features ?? [];

    $introCfg = $plan->introDiscount() ?? [];
    $introEnabled   = (bool) ($introCfg['enabled'] ?? false);
    $introType      = ($introCfg['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $introPercent   = (int) ($introCfg['percent'] ?? 0);
    $introFixedUsd  = (int) ($introCfg['fixed']['USD'] ?? 0);
    $introFixedInr  = (int) ($introCfg['fixed']['INR'] ?? 0);
    $introCycles    = (array) ($introCfg['cycles'] ?? ['monthly', 'annual']);
    $introLabel     = (string) ($introCfg['label'] ?? '');

    // Block allowlist
    $blockAllowedRaw = $f['block_types_allowed'] ?? '*';
    $blockMode  = ($blockAllowedRaw === '*' || $blockAllowedRaw === null) ? 'all' : 'pick';
    $blockTypes = is_array($blockAllowedRaw) ? $blockAllowedRaw : [];

    // Integration settings
    $intAllowed = is_array($f['integration_providers_allowed'] ?? null) ? $f['integration_providers_allowed'] : [];
    $intCaps    = is_array($f['integration_accounts_max'] ?? null) ? $f['integration_accounts_max'] : [];
    $intMode    = [];
    $intProv    = [];
    foreach ($integrations as $kind => $_) {
        $stored = $intAllowed[$kind] ?? '*';
        if (is_array($stored)) {
            $intMode[$kind] = 'pick';
            $intProv[$kind] = $stored;
        } else {
            $intMode[$kind] = 'all';
            $intProv[$kind] = [];
        }
        $intCaps[$kind] = (int) ($intCaps[$kind] ?? 1);
    }

    $entry = [
        'name'                    => $plan->name,
        'description'             => (string) ($plan->description ?? ''),
        'status'                  => $plan->status,
        'sort_order'              => (int) $plan->sort_order,
        'is_popular'              => (bool) $plan->is_popular,
        'is_internal'             => (bool) $plan->is_internal,
        'monthly_price'           => (int) round(((float) $plan->monthly_price) * 100),
        'annual_price'            => (int) round(((float) $plan->annual_price) * 100),
        'monthly_price_secondary' => (int) round(((float) $plan->monthly_price_secondary) * 100),
        'annual_price_secondary'  => (int) round(((float) $plan->annual_price_secondary) * 100),
        'trial_days'              => (int) $plan->trial_days,
        'grace_days'              => (int) ($plan->grace_days ?? 7),
        'refund_window_days'      => (int) ($plan->refund_window_days ?? 7),
        // Intro discount (flat for clean dirty-detection)
        'intro_enabled'           => $introEnabled,
        'intro_type'              => $introType,
        'intro_percent'           => $introPercent,
        'intro_fixed_usd'         => $introFixedUsd,
        'intro_fixed_inr'         => $introFixedInr,
        'intro_cycles_monthly'    => in_array('monthly', $introCycles, true),
        'intro_cycles_annual'     => in_array('annual', $introCycles, true),
        'intro_label'             => $introLabel,
        // Block allowlist
        'block_mode'  => $blockMode,
        'block_types' => $blockTypes,
        // Integration accounts
        'integration_caps'      => $intCaps,
        'integration_mode'      => $intMode,
        'integration_providers' => $intProv,
        // Addons
        'addon_ids'   => $plan->addons()->pluck('addons.id')->map(fn($v) => (int) $v)->values()->all(),
        // Metadata
        '_loaded_at'  => $plan->updated_at?->toISOString(),
        'features'    => [],
    ];

    // ALL quantity limits
    foreach ($allQtyRows as $q) {
        $v = $f[$q['key']] ?? null;
        if (is_array($v)) { $v = $v['default'] ?? 0; }
        $entry['features'][$q['key']] = (int) ($v ?? 0);
    }

    // Per-link-type alias overrides
    $aliasRaw   = $f['max_aliases_per_link'] ?? 0;
    $aliasByType = is_array($aliasRaw) ? $aliasRaw : [];
    $aliasOverrides = [];
    foreach (array_keys($aliasTypes) as $slug) {
        $tv = $aliasByType[$slug] ?? null;
        $aliasOverrides[$slug] = is_numeric($tv) ? (int) $tv : null;
    }
    $entry['features']['max_aliases_per_link_by_type'] = $aliasOverrides;

    // Modules (default ON)
    foreach (array_keys($modules) as $mk) {
        $entry['features'][$mk] = array_key_exists($mk, $f) ? (bool) $f[$mk] : true;
    }

    // ALL feature flags
    foreach ($allFlagRows as $flag) {
        if (($flag['type'] ?? 'bool') === 'bool') {
            $entry['features'][$flag['key']] = (bool) ($f[$flag['key']] ?? false);
        } else {
            $entry['features'][$flag['key']] = $f[$flag['key']] ?? ($flag['default'] ?? '');
        }
    }

    // AI suite items
    foreach ($aiSuiteItems as $ai) {
        $entry['features'][$ai['key']] = (bool) ($f[$ai['key']] ?? false);
    }

    // AI coin multipliers
    foreach ($aiMultipliers as $m) {
        $v = $f[$m['key']] ?? null;
        $entry['features'][$m['key']] = ($v !== null && $v !== '') ? (float) $v : null;
    }

    // Referral program
    foreach ($referralFields as $r) {
        $entry['features'][$r['key']] = (int) ($f[$r['key']] ?? 0);
    }

    // Included coin grants
    foreach ($coinGrants as $cg) {
        $entry['features'][$cg['key']] = (int) ($f[$cg['key']] ?? 0);
    }

    $planData[(string) $plan->id] = $entry;
}
@endphp

@push('styles')
<style>
/* ── Compare grid ────────────────────────────────────────────────────────── */
.compare-table { border-collapse: separate; border-spacing: 0; }
.compare-table th, .compare-table td { padding: 0; }

/* Sticky label column */
.compare-label-col {
    position: sticky; left: 0; z-index: 2;
    background: var(--bg-sidebar, #18181b);
    min-width: 190px; max-width: 230px;
    border-right: 1px solid rgba(255,255,255,0.07);
}
html.light-mode .compare-label-col { background: #f1f5f9; border-right-color: #e2e8f0; }

/* Sticky plan-header */
.compare-table thead th { position: sticky; top: 0; z-index: 3; }
.compare-table thead .compare-label-col { z-index: 4; }

/* Section header rows */
.compare-section-row td {
    background: rgba(255,255,255,0.04);
    padding: 6px 14px;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    border-top: 1px solid rgba(255,255,255,0.07);
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
html.light-mode .compare-section-row td {
    background: rgba(15,23,42,0.04);
    color: rgba(15,23,42,0.45);
    border-top-color: #e2e8f0; border-bottom-color: #e2e8f0;
}
.compare-section-row .compare-label-col { background: rgba(255,255,255,0.055); }
html.light-mode .compare-section-row .compare-label-col { background: rgba(15,23,42,0.06); }

/* Collapsible section toggle row */
.compare-section-toggle td {
    background: rgba(255,255,255,0.025);
    padding: 5px 14px;
    border-top: 1px solid rgba(255,255,255,0.05);
    cursor: pointer;
}
html.light-mode .compare-section-toggle td {
    background: rgba(15,23,42,0.025);
    border-top-color: #e8edf5;
}
.compare-section-toggle:hover td { background: rgba(255,255,255,0.04); }
html.light-mode .compare-section-toggle:hover td { background: rgba(15,23,42,0.04); }

/* Data rows */
.compare-data-row:hover td { background: rgba(255,255,255,0.015); }
html.light-mode .compare-data-row:hover td { background: rgba(15,23,42,0.02); }

/* Diff highlight */
.compare-data-row.row-differs td:not(.compare-label-col) { background: rgba(245,158,11,0.05); }
html.light-mode .compare-data-row.row-differs td:not(.compare-label-col) { background: rgba(245,158,11,0.06); }

/* Dirty cell */
.compare-cell-dirty { position: relative; }
.compare-cell-dirty::after {
    content: ''; position: absolute; inset: 2px;
    border: 2px solid rgba(245,158,11,0.6); border-radius: 8px;
    pointer-events: none;
}
html.light-mode .compare-cell-dirty::after { border-color: rgba(202,138,4,0.65); }

/* Error cell */
.compare-cell-error .cmp-input { border-color: rgba(239,68,68,0.7) !important; box-shadow: 0 0 0 2px rgba(239,68,68,0.15) !important; }
html.light-mode .compare-cell-error .cmp-input { border-color: rgba(220,38,38,0.7) !important; }

/* Shared inputs */
.cmp-input {
    width: 100%;
    background-color: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff; font-size: 13px;
    padding: 5px 10px; outline: none; min-width: 0;
    transition: border-color .15s, box-shadow .15s;
}
.cmp-input:focus { border-color: rgba(61,107,255,0.6); box-shadow: 0 0 0 2px rgba(61,107,255,0.15); }
html.light-mode .cmp-input { background-color: #fff; border-color: #cbd5e1; color: #0f172a; }
html.light-mode .cmp-input:focus { border-color: #3d6bff; box-shadow: 0 0 0 2px rgba(61,107,255,0.12); }
select.cmp-input, html.light-mode select.cmp-input {
    background-repeat: no-repeat; background-position: right 0.75rem center;
    background-size: 1rem 1rem; padding-right: 2.25rem;
}

/* Checkbox toggle */
.cmp-toggle { width: 20px; height: 20px; accent-color: #3d6bff; cursor: pointer; }

/* Plan header card */
.compare-plan-header {
    background: var(--bg-card, #1f1f23);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 12px 16px; min-width: 200px;
}
html.light-mode .compare-plan-header { background: #f8fafc; border-bottom-color: #e2e8f0; }

/* Block/provider badge */
.cmp-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 6px;
    background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.65);
    border: 1px solid rgba(255,255,255,0.1);
}
html.light-mode .cmp-badge { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.cmp-badge.all { background: rgba(16,185,129,0.12); color: #34d399; border-color: rgba(16,185,129,0.2); }
html.light-mode .cmp-badge.all { background: rgba(16,185,129,0.1); color: #059669; border-color: rgba(16,185,129,0.2); }
.cmp-btn-edit {
    font-size: 11px; padding: 2px 8px; border-radius: 6px;
    background: rgba(61,107,255,0.12); color: #7ca2ff;
    border: 1px solid rgba(61,107,255,0.2); cursor: pointer; transition: all .15s;
}
.cmp-btn-edit:hover { background: rgba(61,107,255,0.22); color: #a3bdff; }
html.light-mode .cmp-btn-edit { background: rgba(61,107,255,0.08); color: #3d6bff; border-color: rgba(61,107,255,0.2); }
html.light-mode .cmp-btn-edit:hover { background: rgba(61,107,255,0.15); }

/* Floating save bar */
.compare-save-bar {
    position: sticky; bottom: 0; z-index: 20;
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 12px 20px; display: flex; align-items: center; gap: 12px;
    background: rgba(18,18,22,0.95); backdrop-filter: blur(16px);
}
html.light-mode .compare-save-bar { background: rgba(248,250,252,0.97); border-top-color: #e2e8f0; }

/* Full-screen modal overlay */
.cmp-modal-overlay {
    position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; padding: 20px;
}
html.light-mode .cmp-modal-overlay { background: rgba(15,23,42,0.6); }
.cmp-modal-box {
    background: #1a1a1f; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; max-width: 700px; width: 100%;
    max-height: 80vh; display: flex; flex-direction: column; overflow: hidden;
}
html.light-mode .cmp-modal-box { background: #fff; border-color: #e2e8f0; }
.cmp-modal-header {
    padding: 18px 22px 14px; border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
html.light-mode .cmp-modal-header { border-bottom-color: #e2e8f0; }
.cmp-modal-body { overflow-y: auto; padding: 18px 22px; flex: 1; }
.cmp-modal-footer {
    padding: 14px 22px; border-top: 1px solid rgba(255,255,255,0.08);
    display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0;
}
html.light-mode .cmp-modal-footer { border-top-color: #e2e8f0; }
</style>
@endpush

@section('content')
<div x-data="planCompare(@js($planData), @js($plans->pluck('name', 'id')->mapWithKeys(fn($v, $k) => [(string)$k => $v])), @js($addons->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()))"
     x-init="init()"
     data-save-url="{{ route('admin.plans.compare.save') }}"
     class="flex flex-col"
     style="min-height: calc(100vh - 140px);">

{{-- ── Page header ──────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.plans.index') }}"
           @click.prevent="if (anyDirty()) { if (!confirm('You have unsaved changes. Leave anyway?')) return; } window.location.href = $event.currentTarget.href"
           class="p-2 rounded-xl border border-white/10 text-white/50 hover:text-white hover:border-white/30 transition text-sm">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-base font-semibold text-white/90">Comparing {{ $plans->count() }} plans</h1>
            <p class="text-xs text-white/40 mt-0.5">Edit any cell and click <strong>Save changes</strong>. All plan settings are editable here. Use <span class="text-amber-300/70">∞</span> to set unlimited (-1).</p>
        </div>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <span x-show="saveOk" x-cloak x-transition class="text-sm text-emerald-400 flex items-center gap-1.5">
            <i class="fas fa-check-circle"></i> Saved!
        </span>
        <span x-show="saving" x-cloak class="text-sm text-white/50 flex items-center gap-1.5">
            <i class="fas fa-spinner fa-spin"></i> Saving…
        </span>
        <button type="button" @click="save()"
                :disabled="!anyDirty() || saving"
                class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fas fa-save mr-2"></i>Save changes
        </button>
        <span class="text-[11px] text-white/30"><span x-text="dirtyCount()"></span> plan(s) changed</span>
    </div>
</div>

{{-- ── Error summary ──────────────────────────────────────────────────────── --}}
<template x-if="Object.keys(errors).length > 0">
    <div class="mb-4 glass rounded-xl border border-rose-500/30 bg-rose-500/5 p-4 text-sm text-rose-200">
        <p class="font-semibold mb-2"><i class="fas fa-exclamation-circle mr-1.5"></i>Some plans could not be saved:</p>
        <ul class="space-y-1 text-xs">
            <template x-for="[planId, planErrors] in Object.entries(errors)" :key="planId">
                <li><strong x-text="planNameById(planId)"></strong>: <span x-text="Object.values(planErrors).flat().join(' · ')"></span></li>
            </template>
        </ul>
    </div>
</template>

{{-- ── Legend ──────────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-5 mb-4 text-xs text-white/40">
    <span class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded" style="background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.5);"></span>
        Values differ across plans
    </span>
    <span class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded" style="border:2px solid rgba(245,158,11,0.6);"></span>
        Cell edited (unsaved)
    </span>
    <span class="text-white/25">-1 = unlimited</span>
</div>

{{-- ── Compare table ──────────────────────────────────────────────────────── --}}
<div class="flex-1 overflow-x-auto rounded-2xl border border-white/10 glass">
    <table class="compare-table w-full">
        {{-- Sticky plan-name header --}}
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

        {{-- ══ Core sections (Basics, Pricing, Days) ══════════════════════════ --}}
        @foreach($coreSections as $sectionTitle => $coreRows)
        <tr class="compare-section-row">
            <td class="compare-label-col">{{ $sectionTitle }}</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($coreRows as $row)
        @php $rk = $row['key']; @endphp
        <tr class="compare-data-row" :class="coreRowDiffers('{{ $rk }}') ? 'row-differs' : ''">
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
                        <input type="checkbox" class="cmp-toggle" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        <span class="text-xs" :class="plans['{{ $pid }}']['{{ $rk }}'] ? 'text-blue-300' : 'text-white/40'"
                              x-text="plans['{{ $pid }}']['{{ $rk }}'] ? 'Yes' : 'No'"></span>
                    </label>
                @elseif($row['type'] === 'select')
                    <select class="cmp-input" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        @foreach($row['options'] as $optVal => $optLabel)
                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @elseif($row['type'] === 'number')
                    <input type="number" class="cmp-input"
                           @isset($row['min']) min="{{ $row['min'] }}" @endisset
                           @isset($row['max']) max="{{ $row['max'] }}" @endisset
                           x-model.number="plans['{{ $pid }}']['{{ $rk }}']">
                @else
                    <input type="text" class="cmp-input" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                @endif
                <p x-show="coreErr('{{ $pid }}', '{{ $rk }}')" x-text="coreErr('{{ $pid }}', '{{ $rk }}')"
                   class="text-[10px] text-rose-400 mt-1 px-1" x-cloak></p>
            </td>
            @endforeach
        </tr>
        @endforeach
        @endforeach

        {{-- ══ Intro Discount ══════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Intro Discount</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($introRows as $row)
        @php
            $rk = $row['key'];
            $showWhen = $row['show_when'] ?? null;
        @endphp
        <tr class="compare-data-row" :class="coreRowDiffers('{{ $rk }}') ? 'row-differs' : ''"
            @if($showWhen === 'intro_enabled')
                x-show="Object.values(plans).some(p => p.intro_enabled)"
                style="display:none"
            @elseif($showWhen === 'intro_type_percent')
                x-show="Object.values(plans).some(p => p.intro_enabled && p.intro_type === 'percent')"
                style="display:none"
            @elseif($showWhen === 'intro_type_fixed')
                x-show="Object.values(plans).some(p => p.intro_enabled && p.intro_type === 'fixed')"
                style="display:none"
            @endif
            >
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $row['label'] }}</div>
                @if(!empty($row['hint']))
                <div class="text-[10px] text-white/35 mt-0.5">{{ $row['hint'] }}</div>
                @endif
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2"
                :class="{ 'compare-cell-dirty': isCoreDirty('{{ $pid }}', '{{ $rk }}') }"
                @if($showWhen === 'intro_type_percent')
                    x-show="plans['{{ $pid }}'].intro_enabled && plans['{{ $pid }}'].intro_type === 'percent'"
                @elseif($showWhen === 'intro_type_fixed')
                    x-show="plans['{{ $pid }}'].intro_enabled && plans['{{ $pid }}'].intro_type === 'fixed'"
                @elseif($showWhen === 'intro_enabled')
                    x-show="plans['{{ $pid }}'].intro_enabled"
                @endif
                >
                @if($row['type'] === 'bool')
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" class="cmp-toggle" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        <span class="text-xs" :class="plans['{{ $pid }}']['{{ $rk }}'] ? 'text-blue-300' : 'text-white/40'"
                              x-text="plans['{{ $pid }}']['{{ $rk }}'] ? 'Yes' : 'No'"></span>
                    </label>
                @elseif($row['type'] === 'select')
                    <select class="cmp-input" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                        @foreach($row['options'] as $optVal => $optLabel)
                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @elseif($row['type'] === 'number')
                    <input type="number" class="cmp-input"
                           @isset($row['min']) min="{{ $row['min'] }}" @endisset
                           @isset($row['max']) max="{{ $row['max'] }}" @endisset
                           x-model.number="plans['{{ $pid }}']['{{ $rk }}']">
                @else
                    <input type="text" class="cmp-input" x-model="plans['{{ $pid }}']['{{ $rk }}']">
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Quantity Limits ═════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Quantity Limits</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($allQtyRows as $q)
        @php $qk = $q['key']; @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $qk }}') ? 'row-differs' : ''">
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
                    <input type="number" class="cmp-input" min="-1"
                           @isset($q['max']) max="{{ $q['max'] }}" @endisset
                           x-model.number="plans['{{ $pid }}'].features['{{ $qk }}']">
                    <button type="button" @click="plans['{{ $pid }}'].features['{{ $qk }}'] = -1"
                            title="Set unlimited (-1)"
                            class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-bold text-white/50 hover:text-white bg-white/5 hover:bg-white/10 transition">∞</button>
                </div>
                <p x-show="featErr('{{ $pid }}', '{{ $qk }}')" x-text="featErr('{{ $pid }}', '{{ $qk }}')"
                   class="text-[10px] text-rose-400 mt-1 px-1" x-cloak></p>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ── Per-link-type alias overrides (collapsible) ─────────────────── --}}
        <tr class="compare-section-toggle" @click="aliasOverridesOpen = !aliasOverridesOpen">
            <td class="compare-label-col px-4 py-2">
                <span class="flex items-center gap-2 text-[11px] font-semibold text-white/50">
                    <i class="fas" :class="aliasOverridesOpen ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    Per-type alias overrides
                </span>
            </td>
            <td colspan="{{ $plans->count() }}" class="px-4 py-2 text-[10px] text-white/30">
                Blank = inherit global "Extra aliases per link" value above. Click to expand.
            </td>
        </tr>
        @foreach($aliasTypes as $slug => $label)
        <tr class="compare-data-row"
            x-show="aliasOverridesOpen"
            style="display:none">
            <td class="compare-label-col px-4 py-2">
                <div class="text-[11px] font-medium text-white/70">{{ $label }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-1.5"
                :class="{ 'compare-cell-dirty': isAliasOverrideDirty('{{ $pid }}', '{{ $slug }}') }">
                <div class="flex items-center gap-1">
                    <input type="number" class="cmp-input" min="-1" placeholder="inherit"
                           :value="plans['{{ $pid }}'].features.max_aliases_per_link_by_type?.['{{ $slug }}'] ?? ''"
                           @input="setAliasOverride('{{ $pid }}', '{{ $slug }}', $event.target.value)">
                    <button type="button" @click="setAliasOverride('{{ $pid }}', '{{ $slug }}', -1)"
                            title="Unlimited" class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-bold text-white/50 hover:text-white bg-white/5 hover:bg-white/10 transition">∞</button>
                </div>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Modules ══════════════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Modules</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($modules as $mk => $moduleMeta)
        <tr class="compare-data-row" :class="featRowDiffers('{{ $mk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $moduleMeta['label'] }}</div>
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $moduleMeta['desc'] }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $mk }}') }">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" class="cmp-toggle" x-model="plans['{{ $pid }}'].features['{{ $mk }}']">
                    <span class="text-xs" :class="plans['{{ $pid }}'].features['{{ $mk }}'] ? 'text-blue-300' : 'text-white/40'"
                          x-text="plans['{{ $pid }}'].features['{{ $mk }}'] ? 'On' : 'Off'"></span>
                </label>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Feature Flags ════════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Feature Flags</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($allFlagRows as $flag)
        @php
            $fk = $flag['key'];
            $isSelect = ($flag['type'] ?? 'bool') === 'select';
            $copy = PlanFormCatalogue::copyFor($fk);
            $flagLabel = $copy['name'] ?? ucwords(str_replace('_', ' ', $fk));
            $flagDesc  = $copy['description'] ?? '';
        @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $fk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $flagLabel }}</div>
                @if($flagDesc)
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $flagDesc }}</div>
                @endif
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $fk }}') }">
                @if($isSelect)
                    <select class="cmp-input" x-model="plans['{{ $pid }}'].features['{{ $fk }}']">
                        @foreach($flag['options'] ?? [] as $optVal => $optLabel)
                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" class="cmp-toggle" x-model="plans['{{ $pid }}'].features['{{ $fk }}']">
                        <span class="text-xs" :class="plans['{{ $pid }}'].features['{{ $fk }}'] ? 'text-blue-300' : 'text-white/40'"
                              x-text="plans['{{ $pid }}'].features['{{ $fk }}'] ? 'Yes' : 'No'"></span>
                    </label>
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ AI Suite ════════════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">AI Suite</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($aiSuiteItems as $ai)
        @php
            $ak = $ai['key'];
            $aiCopy = PlanFormCatalogue::copyFor($ak);
            $aiLabel = $aiCopy['name'] ?? ucwords(str_replace('_', ' ', $ak));
        @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $ak }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $aiLabel }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $ak }}') }">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" class="cmp-toggle" x-model="plans['{{ $pid }}'].features['{{ $ak }}']">
                    <span class="text-xs" :class="plans['{{ $pid }}'].features['{{ $ak }}'] ? 'text-blue-300' : 'text-white/40'"
                          x-text="plans['{{ $pid }}'].features['{{ $ak }}'] ? 'Yes' : 'No'"></span>
                </label>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ── AI Coin Multipliers ─────────────────────────────────────────── --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">AI Coin Multipliers</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($aiMultipliers as $m)
        @php $mk2 = $m['key']; @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $mk2 }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $m['label'] }}</div>
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $m['hint'] }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $mk2 }}') }">
                <input type="number" class="cmp-input" step="0.01" min="0" placeholder="1"
                       :value="plans['{{ $pid }}'].features['{{ $mk2 }}'] ?? ''"
                       @input="setMultiplier('{{ $pid }}', '{{ $mk2 }}', $event.target.value)">
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Referral Program ════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Referral Program</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($referralFields as $r)
        @php $rk2 = $r['key']; @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $rk2 }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $r['label'] }}</div>
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $r['hint'] }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $rk2 }}') }">
                <input type="number" class="cmp-input" min="0"
                       x-model.number="plans['{{ $pid }}'].features['{{ $rk2 }}']">
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Included Coin Grants ════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Included Coins</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @foreach($coinGrants as $cg)
        @php $cgk = $cg['key']; @endphp
        <tr class="compare-data-row" :class="featRowDiffers('{{ $cgk }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $cg['label'] }}</div>
                <div class="text-[10px] text-white/35 mt-0.5 leading-tight">{{ $cg['hint'] }}</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2" :class="{ 'compare-cell-dirty': isFeatDirty('{{ $pid }}', '{{ $cgk }}') }">
                <input type="number" class="cmp-input" min="0" step="1"
                       x-model.number="plans['{{ $pid }}'].features['{{ $cgk }}']">
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Block Allowlist ═════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Block Allowlist</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        <tr class="compare-data-row" :class="blockModeRowDiffers() ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">Block access mode</div>
                <div class="text-[10px] text-white/35 mt-0.5">Which Link in Bio block types users on this plan can use.</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isPlanBlockDirty('{{ $pid }}') }">
                <div class="flex flex-col gap-2">
                    <div class="flex gap-3">
                        <label class="flex items-center gap-1.5 text-xs text-white/80 cursor-pointer">
                            <input type="radio" class="accent-blue-500" x-model="plans['{{ $pid }}'].block_mode" value="all">
                            All blocks
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-white/80 cursor-pointer">
                            <input type="radio" class="accent-blue-500" x-model="plans['{{ $pid }}'].block_mode" value="pick">
                            Pick…
                        </label>
                    </div>
                    <div x-show="plans['{{ $pid }}'].block_mode === 'pick'">
                        <button type="button" class="cmp-btn-edit" @click="openBlockModal('{{ $pid }}')">
                            <i class="fas fa-cube mr-1"></i>
                            <span x-text="plans['{{ $pid }}'].block_types.length + ' types selected'"></span>
                        </button>
                    </div>
                    <div x-show="plans['{{ $pid }}'].block_mode === 'all'" class="cmp-badge all text-[10px]">
                        <i class="fas fa-check"></i> All blocks
                    </div>
                </div>
            </td>
            @endforeach
        </tr>

        {{-- ══ Integration Accounts ════════════════════════════════════════════ --}}
        @foreach($integrations as $kind => $info)
        <tr class="compare-section-row">
            <td class="compare-label-col">{{ $info['label'] }}</td>
            <td colspan="{{ $plans->count() }}" class="text-[10px] text-white/30 px-4">{{ $info['subtitle'] }}</td>
        </tr>
        <tr class="compare-data-row" :class="integrationCapRowDiffers('{{ $kind }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">Max accounts</div>
                <div class="text-[10px] text-white/35 mt-0.5">-1 = unlimited</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2" :class="{ 'compare-cell-dirty': isIntCapDirty('{{ $pid }}', '{{ $kind }}') }">
                <div class="flex items-center gap-1">
                    <input type="number" class="cmp-input" min="-1"
                           x-model.number="plans['{{ $pid }}'].integration_caps['{{ $kind }}']">
                    <button type="button" @click="plans['{{ $pid }}'].integration_caps['{{ $kind }}'] = -1"
                            title="Unlimited" class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-bold text-white/50 hover:text-white bg-white/5 hover:bg-white/10 transition">∞</button>
                </div>
            </td>
            @endforeach
        </tr>
        <tr class="compare-data-row" :class="integrationModeRowDiffers('{{ $kind }}') ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">Allowed providers</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isIntModeDirty('{{ $pid }}', '{{ $kind }}') }">
                <div class="flex flex-col gap-2">
                    <div class="flex gap-3">
                        <label class="flex items-center gap-1.5 text-xs text-white/80 cursor-pointer">
                            <input type="radio" class="accent-blue-500" x-model="plans['{{ $pid }}'].integration_mode['{{ $kind }}']" value="all">
                            All
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-white/80 cursor-pointer">
                            <input type="radio" class="accent-blue-500" x-model="plans['{{ $pid }}'].integration_mode['{{ $kind }}']" value="pick">
                            Pick…
                        </label>
                    </div>
                    <div x-show="plans['{{ $pid }}'].integration_mode['{{ $kind }}'] === 'pick'">
                        <button type="button" class="cmp-btn-edit" @click="openIntModal('{{ $pid }}', '{{ $kind }}')">
                            <i class="fas fa-plug mr-1"></i>
                            <span x-text="(plans['{{ $pid }}'].integration_providers['{{ $kind }}'] || []).length + ' providers'"></span>
                        </button>
                    </div>
                    <div x-show="plans['{{ $pid }}'].integration_mode['{{ $kind }}'] === 'all'" class="cmp-badge all text-[10px]">
                        <i class="fas fa-check"></i> All providers
                    </div>
                </div>
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- ══ Addons ══════════════════════════════════════════════════════════ --}}
        <tr class="compare-section-row">
            <td class="compare-label-col">Eligible Addons</td>
            <td colspan="{{ $plans->count() }}"></td>
        </tr>
        @if($addons->isEmpty())
        <tr class="compare-data-row">
            <td class="compare-label-col px-4 py-3 text-xs text-white/40" colspan="{{ $plans->count() + 1 }}">No addons in the catalog.</td>
        </tr>
        @else
        @foreach($addons as $addon)
        @php $addonId = (int) $addon->id; @endphp
        <tr class="compare-data-row" :class="addonRowDiffers({{ $addonId }}) ? 'row-differs' : ''">
            <td class="compare-label-col px-4 py-2.5">
                <div class="text-xs font-medium text-white/80">{{ $addon->name }}</div>
                <div class="text-[10px] text-white/35 mt-0.5">${{ number_format($addon->monthly_price, 2) }}/mo · {{ str_replace('_',' ',$addon->type) }}@if($addon->is_archived) · <span class="text-amber-400/70">archived</span>@endif</div>
            </td>
            @foreach($plans as $plan)
            @php $pid = (string) $plan->id; @endphp
            <td class="px-3 py-2.5" :class="{ 'compare-cell-dirty': isAddonDirty('{{ $pid }}', {{ $addonId }}) }">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" class="cmp-toggle"
                           :checked="plans['{{ $pid }}'].addon_ids.includes({{ $addonId }})"
                           @change="toggleAddon('{{ $pid }}', {{ $addonId }}, $event.target.checked)">
                    <span class="text-xs" :class="plans['{{ $pid }}'].addon_ids.includes({{ $addonId }}) ? 'text-blue-300' : 'text-white/40'"
                          x-text="plans['{{ $pid }}'].addon_ids.includes({{ $addonId }}) ? 'Eligible' : 'Not eligible'"></span>
                </label>
            </td>
            @endforeach
        </tr>
        @endforeach
        @endif

        </tbody>
    </table>
</div>

{{-- ── Sticky save bar ────────────────────────────────────────────────────── --}}
<div class="compare-save-bar mt-0">
    <span class="text-xs text-white/40 flex-1">
        <span x-show="anyDirty()" x-cloak><span x-text="dirtyCount()"></span> plan(s) have unsaved edits.</span>
        <span x-show="!anyDirty()">No unsaved changes.</span>
    </span>
    <span x-show="saveOk" x-cloak x-transition class="text-sm text-emerald-400 flex items-center gap-1.5"><i class="fas fa-check-circle"></i> Saved!</span>
    <span x-show="saving" x-cloak class="text-sm text-white/50 flex items-center gap-1.5"><i class="fas fa-spinner fa-spin"></i> Saving…</span>
    <button type="button" @click="save()" :disabled="!anyDirty() || saving"
            class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
        <i class="fas fa-save mr-2"></i>Save changes
    </button>
    <a href="{{ route('admin.plans.index') }}"
       @click.prevent="if (anyDirty()) { if (!confirm('You have unsaved changes. Leave anyway?')) return; } window.location.href = $event.currentTarget.href"
       class="px-4 py-2 rounded-xl text-sm font-medium text-white/60 hover:text-white border border-white/10 hover:border-white/25 transition">
        <i class="fas fa-arrow-left mr-1.5"></i>Back
    </a>
</div>

{{-- ══ Block Picker Modal ════════════════════════════════════════════════════ --}}
<template x-if="blockModalPlanId !== null">
    <div class="cmp-modal-overlay" @click.self="closeBlockModal()">
        <div class="cmp-modal-box">
            <div class="cmp-modal-header">
                <div>
                    <h3 class="text-sm font-semibold text-white" x-text="'Block types, ' + planNameById(blockModalPlanId)"></h3>
                    <p class="text-[11px] text-white/40 mt-0.5">
                        <span x-text="plans[blockModalPlanId]?.block_types.length"></span> selected of {{ count($allBlockSlugs) }} total
                    </p>
                </div>
                <button type="button" @click="closeBlockModal()" class="text-white/40 hover:text-white text-lg leading-none transition"><i class="fas fa-times"></i></button>
            </div>
            <div class="cmp-modal-body space-y-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <button type="button" @click="selectAllBlocks()" class="text-xs px-3 py-1.5 rounded-lg bg-blue-600/20 text-blue-300 hover:bg-blue-600/30 border border-blue-500/20 transition">Select all</button>
                    <button type="button" @click="deselectAllBlocks()" class="text-xs px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10 border border-white/10 transition">Deselect all</button>
                </div>
                @foreach($blocksByCat as $catKey => $cat)
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-semibold text-white/70">{{ $cat['label'] }} <span class="text-white/30 font-normal">({{ count($cat['types']) }})</span></h4>
                        <button type="button" @click="selectBlockCat(@js(array_keys($cat['types'])))"
                                class="text-[10px] px-2 py-1 rounded bg-white/5 text-white/50 hover:bg-white/10 transition">All</button>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($cat['types'] as $slug => $meta)
                        <label class="flex items-center gap-2 text-xs text-white/70 px-2.5 py-1.5 rounded-lg hover:bg-white/5 cursor-pointer border border-transparent hover:border-white/5"
                               :class="plans[blockModalPlanId]?.block_types.includes('{{ $slug }}') ? 'bg-blue-600/10 border-blue-500/20 text-blue-200' : ''">
                            <input type="checkbox" class="accent-blue-500 shrink-0"
                                   :checked="plans[blockModalPlanId]?.block_types.includes('{{ $slug }}')"
                                   @change="toggleBlockType('{{ $slug }}', $event.target.checked)">
                            <i class="fas {{ $meta['icon'] ?? 'fa-cube' }} text-[10px] text-white/40 shrink-0"></i>
                            {{ $meta['label'] }}
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="cmp-modal-footer">
                <button type="button" @click="closeBlockModal()"
                        class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                    Done (<span x-text="plans[blockModalPlanId]?.block_types.length"></span> selected)
                </button>
            </div>
        </div>
    </div>
</template>

{{-- ══ Integration Provider Modal ════════════════════════════════════════════ --}}
<template x-if="intModalState !== null">
    <div class="cmp-modal-overlay" @click.self="closeIntModal()">
        <div class="cmp-modal-box">
            <div class="cmp-modal-header">
                <div>
                    <h3 class="text-sm font-semibold text-white" x-text="'Provider allowlist, ' + planNameById(intModalState.planId)"></h3>
                    <p class="text-[11px] text-white/40 mt-0.5" x-text="'Integration kind: ' + intModalState.kind"></p>
                </div>
                <button type="button" @click="closeIntModal()" class="text-white/40 hover:text-white text-lg leading-none transition"><i class="fas fa-times"></i></button>
            </div>
            <div class="cmp-modal-body">
                @foreach($integrations as $kind => $info)
                <template x-if="intModalState.kind === '{{ $kind }}'">
                    <div>
                        <p class="text-xs text-white/50 mb-3">{{ $info['subtitle'] }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($info['providers'] as $p)
                            <label class="flex items-center gap-2 text-xs text-white/70 px-2.5 py-2 rounded-lg hover:bg-white/5 cursor-pointer border border-transparent hover:border-white/5"
                                   :class="(plans[intModalState.planId]?.integration_providers['{{ $kind }}'] || []).includes('{{ $p['slug'] }}') ? 'bg-blue-600/10 border-blue-500/20 text-blue-200' : ''">
                                <input type="checkbox" class="accent-blue-500"
                                       :checked="(plans[intModalState.planId]?.integration_providers['{{ $kind }}'] || []).includes('{{ $p['slug'] }}')"
                                       @change="toggleIntProvider('{{ $kind }}', '{{ $p['slug'] }}', $event.target.checked)">
                                {{ $p['label'] }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </template>
                @endforeach
            </div>
            <div class="cmp-modal-footer">
                <button type="button" @click="closeIntModal()"
                        class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">Done</button>
            </div>
        </div>
    </div>
</template>

</div>{{-- /x-data --}}
@endsection

@push('scripts')
<script>
function planCompare(initial, nameMap, allAddons) {
    return {
        plans:    JSON.parse(JSON.stringify(initial)),
        original: JSON.parse(JSON.stringify(initial)),
        errors:   {},
        saving:   false,
        saveOk:   false,

        // Collapsible
        aliasOverridesOpen: false,

        // Modal state
        blockModalPlanId: null,
        intModalState:    null, // {planId, kind}

        // ── Helpers ─────────────────────────────────────────────────────
        planNameById(id) { return nameMap[String(id)] || ('Plan #' + id); },

        // ── Dirty detection ──────────────────────────────────────────────
        isCoreDirty(id, field) {
            return JSON.stringify(this.plans[id]?.[field]) !==
                   JSON.stringify(this.original[id]?.[field]);
        },
        isFeatDirty(id, key) {
            return JSON.stringify(this.plans[id]?.features?.[key]) !==
                   JSON.stringify(this.original[id]?.features?.[key]);
        },
        isPlanBlockDirty(id) {
            return JSON.stringify(this.plans[id]?.block_mode) !== JSON.stringify(this.original[id]?.block_mode) ||
                   JSON.stringify([...(this.plans[id]?.block_types||[])].sort()) !== JSON.stringify([...(this.original[id]?.block_types||[])].sort());
        },
        isIntCapDirty(id, kind) {
            return JSON.stringify(this.plans[id]?.integration_caps?.[kind]) !==
                   JSON.stringify(this.original[id]?.integration_caps?.[kind]);
        },
        isIntModeDirty(id, kind) {
            return JSON.stringify(this.plans[id]?.integration_mode?.[kind]) !==
                   JSON.stringify(this.original[id]?.integration_mode?.[kind]) ||
                   JSON.stringify([...(this.plans[id]?.integration_providers?.[kind]||[])].sort()) !==
                   JSON.stringify([...(this.original[id]?.integration_providers?.[kind]||[])].sort());
        },
        isAddonDirty(id, addonId) {
            const cur = [...(this.plans[id]?.addon_ids||[])].sort();
            const orig = [...(this.original[id]?.addon_ids||[])].sort();
            return JSON.stringify(cur) !== JSON.stringify(orig);
        },
        isAliasOverrideDirty(id, slug) {
            const cur  = this.plans[id]?.features?.max_aliases_per_link_by_type?.[slug] ?? null;
            const orig = this.original[id]?.features?.max_aliases_per_link_by_type?.[slug] ?? null;
            return cur !== orig;
        },
        coreRowDiffers(field) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = JSON.stringify(this.plans[ids[0]]?.[field]);
            return ids.some(id => JSON.stringify(this.plans[id]?.[field]) !== ref);
        },
        featRowDiffers(key) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = JSON.stringify(this.plans[ids[0]]?.features?.[key]);
            return ids.some(id => JSON.stringify(this.plans[id]?.features?.[key]) !== ref);
        },
        blockModeRowDiffers() {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const refMode = this.plans[ids[0]]?.block_mode;
            const refTypes = JSON.stringify([...(this.plans[ids[0]]?.block_types||[])].sort());
            return ids.some(id =>
                this.plans[id]?.block_mode !== refMode ||
                JSON.stringify([...(this.plans[id]?.block_types||[])].sort()) !== refTypes
            );
        },
        integrationCapRowDiffers(kind) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = JSON.stringify(this.plans[ids[0]]?.integration_caps?.[kind]);
            return ids.some(id => JSON.stringify(this.plans[id]?.integration_caps?.[kind]) !== ref);
        },
        integrationModeRowDiffers(kind) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const refMode = this.plans[ids[0]]?.integration_mode?.[kind];
            const refProv = JSON.stringify([...(this.plans[ids[0]]?.integration_providers?.[kind]||[])].sort());
            return ids.some(id =>
                this.plans[id]?.integration_mode?.[kind] !== refMode ||
                JSON.stringify([...(this.plans[id]?.integration_providers?.[kind]||[])].sort()) !== refProv
            );
        },
        addonRowDiffers(addonId) {
            const ids = Object.keys(this.plans);
            if (ids.length < 2) return false;
            const ref = (this.plans[ids[0]]?.addon_ids||[]).includes(addonId);
            return ids.some(id => (this.plans[id]?.addon_ids||[]).includes(addonId) !== ref);
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

        // ── Error helpers ────────────────────────────────────────────────
        coreErr(id, field) {
            const errs = this.errors[id]; if (!errs) return '';
            const msgs = errs[field];
            return Array.isArray(msgs) ? (msgs[0] || '') : (msgs || '');
        },
        featErr(id, key) {
            const errs = this.errors[id]; if (!errs) return '';
            const msgs = errs['features.' + key] || errs['features_' + key];
            return Array.isArray(msgs) ? (msgs[0] || '') : (msgs || '');
        },

        // ── Field setters (for null-able fields) ─────────────────────────
        setAliasOverride(planId, slug, val) {
            const n = val === '' || val === null ? null : parseInt(val, 10);
            if (!this.plans[planId].features.max_aliases_per_link_by_type) {
                this.plans[planId].features.max_aliases_per_link_by_type = {};
            }
            this.plans[planId].features.max_aliases_per_link_by_type[slug] = isNaN(n) ? null : n;
        },
        setMultiplier(planId, key, val) {
            const n = val === '' || val === null ? null : parseFloat(val);
            this.plans[planId].features[key] = (val === '' || isNaN(n)) ? null : n;
        },

        // ── Addon helpers ────────────────────────────────────────────────
        toggleAddon(planId, addonId, checked) {
            const arr = this.plans[planId].addon_ids;
            if (checked && !arr.includes(addonId)) arr.push(addonId);
            else if (!checked) {
                const idx = arr.indexOf(addonId);
                if (idx !== -1) arr.splice(idx, 1);
            }
        },

        // ── Block modal ──────────────────────────────────────────────────
        openBlockModal(planId) { this.blockModalPlanId = planId; },
        closeBlockModal()      { this.blockModalPlanId = null; },
        toggleBlockType(slug, checked) {
            if (!this.blockModalPlanId) return;
            const arr = this.plans[this.blockModalPlanId].block_types;
            if (checked && !arr.includes(slug)) arr.push(slug);
            else if (!checked) { const idx = arr.indexOf(slug); if (idx !== -1) arr.splice(idx, 1); }
        },
        selectAllBlocks() {
            if (!this.blockModalPlanId) return;
            this.plans[this.blockModalPlanId].block_types = @js(array_keys($allBlockSlugs));
        },
        deselectAllBlocks() {
            if (!this.blockModalPlanId) return;
            this.plans[this.blockModalPlanId].block_types = [];
        },
        selectBlockCat(slugs) {
            if (!this.blockModalPlanId) return;
            const arr = this.plans[this.blockModalPlanId].block_types;
            slugs.forEach(s => { if (!arr.includes(s)) arr.push(s); });
        },

        // ── Integration modal ────────────────────────────────────────────
        openIntModal(planId, kind) { this.intModalState = { planId, kind }; },
        closeIntModal()            { this.intModalState = null; },
        toggleIntProvider(kind, slug, checked) {
            if (!this.intModalState) return;
            const pid = this.intModalState.planId;
            if (!this.plans[pid].integration_providers[kind]) this.plans[pid].integration_providers[kind] = [];
            const arr = this.plans[pid].integration_providers[kind];
            if (checked && !arr.includes(slug)) arr.push(slug);
            else if (!checked) { const idx = arr.indexOf(slug); if (idx !== -1) arr.splice(idx, 1); }
        },

        // ── Save ─────────────────────────────────────────────────────────
        async save() {
            if (!this.anyDirty() || this.saving) return;
            this.saving = true; this.errors = {}; this.saveOk = false;

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
                    for (const id of Object.keys(payload)) {
                        if (!this.errors[id]) {
                            if (data.saved_at && data.saved_at[id]) {
                                this.plans[id]._loaded_at = data.saved_at[id];
                            }
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

        // ── Lifecycle ─────────────────────────────────────────────────────
        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.anyDirty()) { e.preventDefault(); e.returnValue = ''; }
            });
        },
    };
}
</script>
@endpush
