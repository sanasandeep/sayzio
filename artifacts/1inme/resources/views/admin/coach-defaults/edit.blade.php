@extends('admin.layouts.app')
@section('title', 'Performance Coach Defaults')
@section('page-title', 'Performance Coach Defaults')

@php
    $builtinSlugs = array_keys($builtinPresets);
@endphp

@section('content')
<div class="max-w-4xl"
     x-data="{
        preset: @js($preset),
        presets: @js(collect($builtinPresets)->mapWithKeys(fn($v,$k)=>[$k=>$v['values']])->all()),
        labels:  @js(array_merge(collect($builtinPresets)->mapWithKeys(fn($v,$k)=>[$k=>$v['label']])->all(), collect($customPresets)->mapWithKeys(fn($p)=>[$p['key']=>$p['label']])->all(), ['custom' => 'Custom'])),
        values:  @js($effective),
        customs: @js(array_map(fn($p)=>['key'=>$p['key'],'label'=>$p['label'],'description'=>$p['description'],'values'=>$p['values']], $customPresets)),
        apply(p) {
            this.preset = p;
            if (p !== 'custom' && this.presets[p]) {
                this.values = { ...this.presets[p] };
            } else if (p !== 'custom') {
                const c = this.customs.find(x => x.key === p);
                if (c) this.values = { ...c.values };
            }
        },
        onEdit() { if (this.preset !== 'custom') this.preset = 'custom'; },
        addCustom() {
            this.customs.push({
                key: '', label: '', description: '',
                values: { ...(this.presets.creator || {}) },
            });
        },
        removeCustom(i) { this.customs.splice(i, 1); },
        blankValues() { return { ...(this.presets.creator || {}) }; }
     }">

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.coach-defaults.update') }}">
            @csrf

            <div class="mb-5">
                <h2 class="text-lg font-semibold text-white/90">Workspace defaults</h2>
                <p class="text-xs text-white/50 mt-1">
                    Brand-new links start from the preset you pick here. Creators can still override it per link.
                </p>
            </div>

            {{-- Preset picker --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($builtinPresets as $key => $meta)
                    <label class="pc-admin-card"
                           :class="preset === '{{ $key }}' ? 'is-active' : ''"
                           @click.prevent="apply('{{ $key }}')">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="preset" value="{{ $key }}" :checked="preset === '{{ $key }}'" class="accent-purple-400">
                            <span class="font-semibold text-white">{{ $meta['label'] }}</span>
                        </div>
                        <div class="text-xs text-white/50 mt-1">{{ $meta['description'] }}</div>
                    </label>
                @endforeach
                <template x-for="(c, i) in customs" :key="'pick-' + i">
                    <label class="pc-admin-card"
                           :class="preset === c.key ? 'is-active' : ''"
                           @click.prevent="c.key && apply(c.key)">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="preset" :value="c.key" :checked="preset === c.key" class="accent-purple-400" :disabled="!c.key">
                            <span class="font-semibold text-white" x-text="c.label || 'Untitled custom preset'"></span>
                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-200 border border-purple-400/30">Custom</span>
                        </div>
                        <div class="text-xs text-white/50 mt-1" x-text="c.description || 'Workspace preset'"></div>
                    </label>
                </template>
                <label class="pc-admin-card" :class="preset === 'custom' ? 'is-active' : ''"
                       @click.prevent="apply('custom')">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="preset" value="custom" :checked="preset === 'custom'" class="accent-purple-400">
                        <span class="font-semibold text-white">Custom</span>
                    </div>
                    <div class="text-xs text-white/50 mt-1">Hand-tune each threshold below.</div>
                </label>
            </div>

            {{-- Thresholds --}}
            <div class="mt-6 border-t border-white/10 pt-5">
                <h3 class="text-sm font-semibold text-white/80 mb-3">Thresholds</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="pc-admin-group">
                        <div class="pc-admin-group-title">Click-through rate (fraction)</div>
                        <div class="grid grid-cols-3 gap-2">
                            <label>Critical below<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_critical]" :value="(+values.ctr_critical).toFixed(2)" @input="values.ctr_critical=$event.target.value; onEdit()"></label>
                            <label>Warning below<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_warning]" :value="(+values.ctr_warning).toFixed(2)" @input="values.ctr_warning=$event.target.value; onEdit()"></label>
                            <label>Excellent at<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_excellent]" :value="(+values.ctr_excellent).toFixed(2)" @input="values.ctr_excellent=$event.target.value; onEdit()"></label>
                        </div>
                    </div>
                    <div class="pc-admin-group">
                        <div class="pc-admin-group-title">Bounce rate (%)</div>
                        <div class="grid grid-cols-3 gap-2">
                            <label>Critical above<input type="number" step="1" min="0" max="100" name="overrides[bounce_critical]" :value="Math.round(values.bounce_critical)" @input="values.bounce_critical=$event.target.value; onEdit()"></label>
                            <label>Warning above<input type="number" step="1" min="0" max="100" name="overrides[bounce_warning]" :value="Math.round(values.bounce_warning)" @input="values.bounce_warning=$event.target.value; onEdit()"></label>
                            <label>Excellent below<input type="number" step="1" min="0" max="100" name="overrides[bounce_excellent]" :value="Math.round(values.bounce_excellent)" @input="values.bounce_excellent=$event.target.value; onEdit()"></label>
                        </div>
                    </div>
                    <div class="pc-admin-group">
                        <div class="pc-admin-group-title">Avg. session length (seconds)</div>
                        <div class="grid grid-cols-2 gap-2">
                            <label>Low below<input type="number" step="1" min="1" max="600" name="overrides[engagement_low_seconds]" :value="Math.round(values.engagement_low_seconds)" @input="values.engagement_low_seconds=$event.target.value; onEdit()"></label>
                            <label>Excellent at<input type="number" step="1" min="1" max="600" name="overrides[engagement_excellent_seconds]" :value="Math.round(values.engagement_excellent_seconds)" @input="values.engagement_excellent_seconds=$event.target.value; onEdit()"></label>
                        </div>
                    </div>
                    <div class="pc-admin-group">
                        <div class="pc-admin-group-title">Momentum vs previous period</div>
                        <div class="grid grid-cols-3 gap-2">
                            <label>Critical drop<input type="number" step="0.05" min="-1" max="0" name="overrides[momentum_drop_critical]" :value="(+values.momentum_drop_critical).toFixed(2)" @input="values.momentum_drop_critical=$event.target.value; onEdit()"></label>
                            <label>Warning drop<input type="number" step="0.05" min="-1" max="0" name="overrides[momentum_drop_warning]" :value="(+values.momentum_drop_warning).toFixed(2)" @input="values.momentum_drop_warning=$event.target.value; onEdit()"></label>
                            <label>Win at<input type="number" step="0.05" min="0" max="5" name="overrides[momentum_win_threshold]" :value="(+values.momentum_win_threshold).toFixed(2)" @input="values.momentum_win_threshold=$event.target.value; onEdit()"></label>
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-white/40 mt-2">
                    These values are saved as the workspace "Custom" overrides and only apply when the Custom preset is selected above.
                </p>
            </div>

            {{-- Custom published presets --}}
            <div class="mt-6 border-t border-white/10 pt-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white/80">Custom presets</h3>
                        <p class="text-xs text-white/50 mt-1">
                            Published presets appear alongside the built-ins in every creator's per-link picker.
                        </p>
                    </div>
                    <button type="button" @click="addCustom()" class="px-3 py-1.5 text-xs bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-plus mr-1"></i> Add preset
                    </button>
                </div>

                <template x-for="(c, i) in customs" :key="'cp-' + i">
                    <div class="pc-admin-group mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <label class="flex-1">
                                <div class="pc-admin-sub-label">Key (lowercase, 2-32 chars, a-z 0-9 _ -)</div>
                                <input type="text" :name="'custom_presets[' + i + '][key]'" x-model="c.key" placeholder="org_landing"
                                       class="pc-admin-input" pattern="[a-z0-9][a-z0-9_-]{0,31}">
                            </label>
                            <label class="flex-1">
                                <div class="pc-admin-sub-label">Label</div>
                                <input type="text" :name="'custom_presets[' + i + '][label]'" x-model="c.label" placeholder="Our Landing"
                                       class="pc-admin-input" maxlength="64">
                            </label>
                            <button type="button" @click="removeCustom(i)"
                                    class="mt-5 px-2 py-1.5 text-xs bg-red-500/20 text-red-300 border border-red-500/30 rounded-lg hover:bg-red-500/30 transition"
                                    title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <label class="block mb-2">
                            <div class="pc-admin-sub-label">Description</div>
                            <input type="text" :name="'custom_presets[' + i + '][description]'" x-model="c.description"
                                   placeholder="When to use this preset" class="pc-admin-input" maxlength="160">
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div class="grid grid-cols-3 gap-2">
                                <label>CTR critical<input type="number" step="0.01" min="0" max="1" :name="'custom_presets[' + i + '][values][ctr_critical]'" x-model.number="c.values.ctr_critical"></label>
                                <label>CTR warning<input type="number" step="0.01" min="0" max="1" :name="'custom_presets[' + i + '][values][ctr_warning]'" x-model.number="c.values.ctr_warning"></label>
                                <label>CTR excellent<input type="number" step="0.01" min="0" max="1" :name="'custom_presets[' + i + '][values][ctr_excellent]'" x-model.number="c.values.ctr_excellent"></label>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <label>Bounce critical<input type="number" step="1" min="0" max="100" :name="'custom_presets[' + i + '][values][bounce_critical]'" x-model.number="c.values.bounce_critical"></label>
                                <label>Bounce warning<input type="number" step="1" min="0" max="100" :name="'custom_presets[' + i + '][values][bounce_warning]'" x-model.number="c.values.bounce_warning"></label>
                                <label>Bounce excellent<input type="number" step="1" min="0" max="100" :name="'custom_presets[' + i + '][values][bounce_excellent]'" x-model.number="c.values.bounce_excellent"></label>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <label>Session low<input type="number" step="1" min="1" max="600" :name="'custom_presets[' + i + '][values][engagement_low_seconds]'" x-model.number="c.values.engagement_low_seconds"></label>
                                <label>Session excellent<input type="number" step="1" min="1" max="600" :name="'custom_presets[' + i + '][values][engagement_excellent_seconds]'" x-model.number="c.values.engagement_excellent_seconds"></label>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <label>Momentum crit<input type="number" step="0.05" min="-1" max="0" :name="'custom_presets[' + i + '][values][momentum_drop_critical]'" x-model.number="c.values.momentum_drop_critical"></label>
                                <label>Momentum warn<input type="number" step="0.05" min="-1" max="0" :name="'custom_presets[' + i + '][values][momentum_drop_warning]'" x-model.number="c.values.momentum_drop_warning"></label>
                                <label>Momentum win<input type="number" step="0.05" min="0" max="5" :name="'custom_presets[' + i + '][values][momentum_win_threshold]'" x-model.number="c.values.momentum_win_threshold"></label>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="customs.length === 0" class="text-xs text-white/40 italic">
                    No custom presets published yet.
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6 mt-4 border-t border-white/10">
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-xl font-medium hover:bg-purple-700 transition">
                    <i class="fas fa-check mr-1"></i> Save defaults
                </button>
                <a href="{{ route('admin.dashboard') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
    .pc-admin-card {
        display: block; cursor: pointer;
        padding: 12px 14px; border-radius: 12px;
        border: 1px solid rgba(148,163,184,0.18);
        background: rgba(255,255,255,0.02);
        transition: border-color .15s ease, background .15s ease;
    }
    .pc-admin-card:hover { background: rgba(255,255,255,0.05); }
    .pc-admin-card.is-active { border-color: rgba(168,85,247,0.6); background: rgba(168,85,247,0.08); }
    .pc-admin-group {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(148,163,184,0.18);
        border-radius: 10px; padding: 12px;
    }
    .pc-admin-group-title {
        font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.55);
        margin-bottom: 8px; text-transform: uppercase; letter-spacing: .04em;
    }
    .pc-admin-group label {
        display: flex; flex-direction: column; gap: 3px;
        font-size: 10px; color: rgba(255,255,255,0.5);
        font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
    }
    .pc-admin-group input[type="number"],
    .pc-admin-group input[type="text"] {
        width: 100%;
        padding: 6px 8px; border-radius: 6px;
        border: 1px solid rgba(148,163,184,0.25);
        background: rgba(0,0,0,0.25); color: #fff;
        font-size: 13px; font-weight: 600; text-transform: none; letter-spacing: 0;
    }
    .pc-admin-group input:focus {
        outline: none; border-color: rgba(168,85,247,0.55);
        box-shadow: 0 0 0 2px rgba(168,85,247,0.15);
    }
    .pc-admin-sub-label {
        font-size: 10px; color: rgba(255,255,255,0.5);
        margin-bottom: 3px; text-transform: uppercase; letter-spacing: .04em; font-weight: 600;
    }
    .pc-admin-input {
        width: 100%; padding: 6px 10px; border-radius: 6px;
        border: 1px solid rgba(148,163,184,0.25);
        background: rgba(0,0,0,0.25); color: #fff; font-size: 13px;
    }
    .pc-admin-input:focus {
        outline: none; border-color: rgba(168,85,247,0.55);
        box-shadow: 0 0 0 2px rgba(168,85,247,0.15);
    }
</style>
@endsection
