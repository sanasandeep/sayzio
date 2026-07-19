@extends('user.layouts.app')
@section('title', $proof->name)

@push('styles')
<style>
/* --- Buzz editor: theme-aware base (dark mode defaults) --- */
.bz-tab.active{background:rgba(61,107,255,.18);color:#fff;border-color:rgba(61,107,255,.4)}
.bz-input{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);border-radius:10px;padding:8px 10px;color:#fff;font-size:13px}
.bz-input:focus{outline:none;border-color:#3d6bff}
.bz-label{display:block;font-size:11.5px;color:rgba(255,255,255,.55);margin-bottom:4px;font-weight:500}
.bz-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px}
.bz-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:500;background:rgba(61,107,255,.15);color:#bccfff;border:1px solid rgba(61,107,255,.25)}
.bz-divider{height:1px;background:rgba(255,255,255,.08);margin:14px 0}
.bz-btn-icon{width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);cursor:pointer;font-size:12px}
.bz-btn-icon:hover{background:rgba(255,255,255,.08);color:#fff}
.bz-trig-card{background:rgba(255,255,255,.04);border:1px dashed rgba(255,255,255,.12);border-radius:10px;padding:10px}
.preview-frame{height:520px;background:repeating-linear-gradient(45deg,rgba(255,255,255,.02) 0 10px,transparent 10px 20px);border-radius:14px;border:1px solid rgba(255,255,255,.08);position:relative;overflow:hidden}
[x-cloak]{display:none!important}

/* --- Buzz editor: LIGHT MODE overrides (scoped to #bz-editor only) --- */
html.light-mode #bz-editor .bz-tab{color:#475569;border-color:rgba(15,23,42,.10);background:#fff}
html.light-mode #bz-editor .bz-tab:hover{background:#f8fafc}
html.light-mode #bz-editor .bz-tab.active{background:rgba(61,107,255,.10);color:#2342c7;border-color:rgba(61,107,255,.35)}
html.light-mode #bz-editor .bz-input{background:#fff;border-color:#e2e8f0;color:#0f172a}
html.light-mode #bz-editor .bz-input::placeholder{color:#94a3b8}
html.light-mode #bz-editor .bz-input:focus{border-color:#3d6bff;box-shadow:0 0 0 3px rgba(61,107,255,.10)}
html.light-mode #bz-editor .bz-label{color:#475569;font-weight:600}
html.light-mode #bz-editor .bz-card{background:#fff;border-color:#e2e8f0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
html.light-mode #bz-editor .bz-divider{background:#e2e8f0}
html.light-mode #bz-editor .bz-pill{background:rgba(61,107,255,.10);color:#2342c7;border-color:rgba(61,107,255,.25)}
html.light-mode #bz-editor .bz-btn-icon{background:#f1f5f9;border-color:#e2e8f0;color:#475569}
html.light-mode #bz-editor .bz-btn-icon:hover{background:#e2e8f0;color:#0f172a}
html.light-mode #bz-editor .bz-trig-card{background:#f8fafc;border-color:#cbd5e1;border-style:dashed}
html.light-mode #bz-editor .preview-frame{background:repeating-linear-gradient(45deg,#f1f5f9 0 10px,transparent 10px 20px);border-color:#e2e8f0}

/* Tailwind utility overrides (color-on-color invisibility) — scoped */
html.light-mode .bz-scope .text-white,
html.light-mode .bz-scope h1.text-white,
html.light-mode .bz-scope h2.text-white,
html.light-mode .bz-scope h3.text-white,
html.light-mode .bz-scope h4.text-white{color:#0f172a !important}
html.light-mode .bz-scope [class*="text-white/"]{color:#475569 !important}
html.light-mode .bz-scope [class*="text-white/40"],
html.light-mode .bz-scope [class*="text-white/50"]{color:#64748b !important}
html.light-mode .bz-scope [class*="text-white/60"],
html.light-mode .bz-scope [class*="text-white/70"]{color:#475569 !important}
html.light-mode .bz-scope [class*="bg-white/"]{background:#f8fafc !important}
html.light-mode .bz-scope [class*="border-white/"]{border-color:#e2e8f0 !important}
html.light-mode .bz-scope [class*="bg-black/"]{background:#0f172a !important;color:#f8fafc !important}
html.light-mode .bz-scope [class*="bg-black/"] *{color:inherit !important}

/* --- Inline template picker cards --- */
.bz-tpl{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);cursor:pointer;display:flex;flex-direction:column}
.bz-tpl:hover{border-color:rgba(61,107,255,.50);background:rgba(61,107,255,.08);transform:translateY(-1px)}
.bz-tpl-preview{height:110px;background:repeating-linear-gradient(45deg,rgba(255,255,255,.02) 0 8px,transparent 8px 16px);border-bottom:1px solid rgba(255,255,255,.08);position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:10px}
.bz-tpl-label{color:#fff}
.bz-tpl-desc{color:rgba(255,255,255,.45);line-height:1.35}

/* Mini-preview building blocks (used inside .bz-tpl-preview by templatePreview()) */
.tpl-card{background:#fff;color:#0f172a;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px 10px;font-size:10px;line-height:1.25;display:flex;gap:8px;align-items:center;max-width:170px}
.tpl-card.dark{background:#18181b;color:#fff}
.tpl-card .tpl-avatar{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#90acff,#3d6bff);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:600}
.tpl-card .tpl-body{display:flex;flex-direction:column;min-width:0}
.tpl-card .tpl-t1{font-weight:600}
.tpl-card .tpl-t2{font-size:9px;color:#64748b;margin-top:1px}
.tpl-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:600;background:#3d6bff;color:#fff}
.tpl-bar{position:absolute;left:0;right:0;height:22px;display:flex;align-items:center;justify-content:center;font-size:9.5px;font-weight:600;color:#fff;letter-spacing:.3px}
.tpl-bar.top{top:6px}
.tpl-bar.bottom{bottom:6px}
.tpl-bubble{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;box-shadow:0 6px 18px rgba(0,0,0,.22)}
.tpl-stars{color:#f59e0b;letter-spacing:1px;font-size:11px}
.tpl-num{font-size:18px;font-weight:700;color:#fff;font-variant-numeric:tabular-nums}
.tpl-strike{text-decoration:line-through;color:#94a3b8;font-size:10px}
.tpl-new{color:#dc2626;font-weight:700;font-size:13px}

html.light-mode .bz-tpl{background:#f8fafc;border-color:#e2e8f0}
html.light-mode .bz-tpl:hover{background:rgba(61,107,255,.06);border-color:rgba(61,107,255,.40)}
html.light-mode .bz-tpl-preview{background:repeating-linear-gradient(45deg,#eef2f7 0 8px,#f8fafc 8px 16px);border-bottom-color:#e2e8f0}
html.light-mode .bz-tpl-label{color:#0f172a}
html.light-mode .bz-tpl-desc{color:#64748b}
</style>
@endpush

@section('content')
@php
    $design    = array_merge(\App\Modules\User\Models\SocialProof::defaultDesign(),    (array)($proof->design ?? []));
    $targeting = array_merge(\App\Modules\User\Models\SocialProof::defaultTargeting(), (array)($proof->targeting ?? []));
    $notifications = is_array($proof->notifications) ? $proof->notifications : [];
@endphp

<div id="bz-header" class="bz-scope mb-5 flex items-center justify-between">
    <div>
        <a href="{{ route('user.social-proofs.index') }}" class="text-white/50 hover:text-white text-xs"><i class="fas fa-arrow-left mr-1"></i> Back to Buzz</a>
        <h1 class="text-2xl font-bold text-white mt-1">{{ $proof->name }}</h1>
        <p class="text-white/40 text-xs mt-0.5">{{ $proof->notificationCount() }} notification(s), embed this widget on your biolinks or any external site.</p>
    </div>
    <div class="flex items-center gap-3 text-xs text-white/60">
        <div><span class="text-white text-base font-semibold">{{ number_format($stats['impressions_30d']) }}</span> imp / 30d</div>
        <div><span class="text-white text-base font-semibold">{{ number_format($stats['clicks_30d']) }}</span> clicks</div>
        <div><span class="text-white text-base font-semibold">{{ $stats['ctr'] }}%</span> CTR</div>
        @isset($buzzUsage)
        <div class="pl-3 border-l border-white/10" title="Monthly Buzz views across all your campaigns, counted against your plan allowance.">
            <span class="text-white text-base font-semibold {{ $buzzUsage['paused'] ? 'text-rose-300' : '' }}">{{ number_format($buzzUsage['used']) }}</span>/{{ $buzzUsage['unlimited'] ? '∞' : number_format($buzzUsage['allowance']) }} views/mo
        </div>
        @endisset
    </div>
</div>
@isset($buzzUsage)
    @if($buzzUsage['paused'])
    <div class="bz-scope mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-center justify-between flex-wrap gap-2">
        <span><i class="fas fa-pause mr-1.5"></i> You've reached your monthly Buzz views allowance ({{ number_format($buzzUsage['allowance']) }}). Your widgets are paused and will resume next month.</span>
        <a href="{{ route('user.upgrade') }}" class="underline hover:text-rose-200 whitespace-nowrap">Upgrade for more</a>
    </div>
    @endif
@endisset

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<div id="bz-editor" class="bz-scope" x-data="buzzEditor()" x-init="init()" x-cloak>

<form method="POST" action="{{ route('user.social-proofs.update', $proof) }}" @submit="syncBeforeSubmit">
    @csrf @method('PUT')
    <input type="hidden" name="notifications_json" :value="JSON.stringify(notifications)">
    <input type="hidden" name="directory_badge_notification_id" :value="directoryBadgeId || ''">

    {{-- Tab bar --}}
    <div class="flex gap-2 mb-5 border-b border-white/5 pb-3 overflow-x-auto">
        <template x-for="t in tabs" :key="t.id">
            <button type="button" @click="activeTab = t.id"
                    class="bz-tab px-4 py-2 rounded-xl text-sm border border-white/10 text-white/70 hover:bg-white/5 whitespace-nowrap"
                    :class="{'active': activeTab === t.id}">
                <i class="fas mr-1.5" :class="t.icon"></i> <span x-text="t.label"></span>
            </button>
        </template>
        <div class="ml-auto flex items-center gap-2">
            <label class="inline-flex items-center gap-2 text-xs text-white/60 px-3 py-2 rounded-xl bg-white/5 border border-white/10">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ $proof->is_active ? 'checked' : '' }}> Active
            </label>
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Save changes</button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    {{-- LEFT: Tab content --}}
    <div class="xl:col-span-2 space-y-4">

    {{-- ============== GENERAL ============== --}}
    <div x-show="activeTab === 'general'" class="space-y-4">
        <div class="bz-card">
            <label class="bz-label">Campaign name</label>
            <input type="text" name="name" required value="{{ old('name', $proof->name) }}" class="bz-input" maxlength="120">
        </div>

        <div class="bz-card">
            <h3 class="text-white font-semibold text-sm mb-1">Embed snippet</h3>
            <p class="text-white/40 text-xs mb-3">Paste this into any HTML page (or attach to a biolink in the Bio editor).</p>
            <div class="bg-black/40 rounded-xl p-3 font-mono text-xs text-emerald-300 break-all">
                &lt;script src="{{ url('/sp/' . $proof->uuid . '.js') }}" async&gt;&lt;/script&gt;
            </div>
            <button type="button" class="mt-2 text-xs text-blue-300 hover:text-blue-200"
                    @click="navigator.clipboard.writeText('<script src=\'{{ url('/sp/' . $proof->uuid . '.js') }}\' async></script>'); $event.target.textContent='Copied ✓'">
                <i class="fas fa-copy mr-1"></i> Copy snippet
            </button>
        </div>
    </div>

    {{-- ============== NOTIFICATIONS ============== --}}
    <div x-show="activeTab === 'notifications'" class="space-y-4">

        <div class="bz-card flex items-center justify-between">
            <div>
                <h3 class="text-white font-semibold text-sm">Notifications in this campaign</h3>
                <p class="text-white/40 text-xs mt-0.5">Add as many notifications as you want. They'll all run on the embed and rotate based on their triggers.</p>
            </div>
            <button type="button" @click="openTypePicker = !openTypePicker" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
                <i class="fas mr-1" :class="openTypePicker ? 'fa-times' : 'fa-plus'"></i>
                <span x-text="openTypePicker ? 'Cancel' : 'Add notification'"></span>
            </button>
        </div>

        {{-- Inline type picker (shows when "Add notification" toggled) --}}
        <div x-show="openTypePicker" x-collapse>
            <div class="bz-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h4 class="text-white font-semibold text-sm">Choose a template</h4>
                        <p class="text-white/40 text-xs mt-0.5">Pick the kind of notification you want to add. Each one shows a quick preview of how it looks.</p>
                    </div>
                    <input type="text" x-model="typeFilter" placeholder="Search…" class="bz-input" style="max-width:220px">
                </div>

                @foreach(\App\Modules\User\Models\SocialProof::TYPE_GROUPS as $group => $keys)
                <div class="mb-4" x-show="groupHasMatch(@js($keys))">
                    <h5 class="bz-label uppercase tracking-wider mb-2">{{ $group }}</h5>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($keys as $k)
                        <button type="button"
                                x-show="matchesFilter('{{ $k }}', @js(\App\Modules\User\Models\SocialProof::TYPES[$k]))"
                                @click="addNotification('{{ $k }}'); openTypePicker = false"
                                class="bz-tpl text-left rounded-xl overflow-hidden transition group">
                            <div class="bz-tpl-preview" x-html="templatePreview('{{ $k }}')"></div>
                            <div class="bz-tpl-meta px-3 py-2.5">
                                <div class="bz-tpl-label text-sm font-semibold">{{ \App\Modules\User\Models\SocialProof::TYPES[$k] }}</div>
                                <div class="bz-tpl-desc text-[11px] mt-0.5">{{ \App\Modules\User\Models\SocialProof::TYPE_DESCRIPTIONS[$k] ?? '' }}</div>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Notifications list --}}
        <template x-for="(n, idx) in notifications" :key="n.id">
            <div class="bz-card">
                <div class="flex items-center gap-2 mb-3">
                    <button type="button" @click="n._open = !n._open" class="text-white/50 hover:text-white text-xs">
                        <i class="fas" :class="n._open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    </button>
                    <span class="bz-pill" x-text="typeLabel(n.type)"></span>
                    <input type="text" x-model="n.name" @input="livePreview()" class="bz-input flex-1" placeholder="Notification name">
                    <label class="inline-flex items-center gap-1.5 text-xs text-white/60 cursor-pointer">
                        <input type="checkbox" x-model="n.is_active" @change="livePreview()"> Active
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer"
                           :class="isBadgeEligible(n.type) ? 'text-amber-300' : 'text-white/30 cursor-not-allowed'"
                           :title="isBadgeEligible(n.type) ? 'Use this notification as your Creators directory badge' : 'This notification type can\'t be shown on the directory card'">
                        <input type="checkbox"
                               :disabled="!isBadgeEligible(n.type)"
                               :checked="directoryBadgeId === n.id"
                               @change="toggleDirectoryBadge(n)">
                        <i class="fas fa-id-badge"></i> Directory badge
                    </label>
                    <button type="button" @click="moveUp(idx)" class="bz-btn-icon" title="Move up"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" @click="moveDown(idx)" class="bz-btn-icon" title="Move down"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" @click="duplicate(idx)" class="bz-btn-icon" title="Duplicate"><i class="fas fa-copy"></i></button>
                    <button type="button" @click="remove(idx)" class="bz-btn-icon hover:!text-rose-400" title="Delete"><i class="fas fa-trash"></i></button>
                </div>

                <div x-show="n._open" x-collapse>
                    {{-- Per-type settings --}}
                    <div class="mt-2">
                        <h4 class="text-white/70 text-xs font-semibold uppercase tracking-wider mb-2">Content</h4>
                        <div :id="'fields-' + n.id"></div>
                        <div x-html="renderFields(n)"></div>
                    </div>

                    <div class="bz-divider"></div>

                    {{-- Triggers --}}
                    <h4 class="text-white/70 text-xs font-semibold uppercase tracking-wider mb-2">Triggers</h4>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs text-white/50">Show when:</span>
                        <select x-model="n.triggers_logic" @change="livePreview()" class="bz-input" style="width:auto">
                            <option value="or">ANY trigger fires</option>
                            <option value="and">ALL triggers fire</option>
                        </select>
                        <button type="button" @click="addTrigger(n)" class="ml-auto bz-btn-icon !w-auto !px-2.5"><i class="fas fa-plus mr-1"></i> Add trigger</button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(tr, tidx) in n.triggers" :key="tidx">
                            <div class="bz-trig-card flex items-start gap-2">
                                <select x-model="tr.kind" @change="initTriggerParams(tr); livePreview()" class="bz-input" style="width:auto;min-width:170px">
                                    <option value="on_load">On page load</option>
                                    <option value="after_delay">After delay</option>
                                    <option value="on_scroll">On scroll %</option>
                                    <option value="on_exit_intent">On exit intent</option>
                                    <option value="on_click">On click selector</option>
                                    <option value="after_idle">After user idle</option>
                                    <option value="url_contains">URL contains</option>
                                </select>
                                <div class="flex-1">
                                    <template x-if="tr.kind === 'after_delay' || tr.kind === 'after_idle'">
                                        <div class="flex items-center gap-2">
                                            <input type="number" min="0" x-model.number="tr.params.seconds" @input="livePreview()" class="bz-input" placeholder="seconds" style="width:120px">
                                            <span class="text-white/50 text-xs">seconds</span>
                                        </div>
                                    </template>
                                    <template x-if="tr.kind === 'on_scroll'">
                                        <div class="flex items-center gap-2">
                                            <input type="number" min="1" max="100" x-model.number="tr.params.percent" @input="livePreview()" class="bz-input" placeholder="50" style="width:120px">
                                            <span class="text-white/50 text-xs">% of page</span>
                                        </div>
                                    </template>
                                    <template x-if="tr.kind === 'on_click'">
                                        <input type="text" x-model="tr.params.selector" @input="livePreview()" class="bz-input" placeholder="CSS selector e.g. #cta-btn">
                                    </template>
                                    <template x-if="tr.kind === 'url_contains'">
                                        <input type="text" x-model="tr.params.text" @input="livePreview()" class="bz-input" placeholder="substring of the URL e.g. /pricing">
                                    </template>
                                    <template x-if="tr.kind === 'on_load' || tr.kind === 'on_exit_intent'">
                                        <span class="text-white/40 text-xs">No parameters</span>
                                    </template>
                                </div>
                                <button type="button" @click="n.triggers.splice(tidx,1); livePreview()" class="bz-btn-icon hover:!text-rose-400"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                    </div>

                    <div class="bz-divider"></div>

                    {{-- Design override --}}
                    <h4 class="text-white/70 text-xs font-semibold uppercase tracking-wider mb-2">Design override</h4>
                    <p class="text-white/40 text-xs mb-2">Leave blank to inherit campaign design from the Design tab.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="bz-label">Position</label>
                            <select x-model="n.design_override.position" @change="livePreview()" class="bz-input">
                                <option value="">Inherit</option>
                                <option value="bottom-left">Bottom left</option>
                                <option value="bottom-right">Bottom right</option>
                                <option value="top-left">Top left</option>
                                <option value="top-right">Top right</option>
                            </select>
                        </div>
                        <div>
                            <label class="bz-label">Theme</label>
                            <select x-model="n.design_override.theme" @change="livePreview()" class="bz-input">
                                <option value="">Inherit</option>
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="notifications.length === 0" class="bz-card text-center py-8">
            <p class="text-white/50 text-sm mb-3">No notifications yet, add your first one!</p>
            <button type="button" @click="openTypePicker = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm">Add notification</button>
        </div>
    </div>

    {{-- ============== DESIGN ============== --}}
    <div x-show="activeTab === 'design'" class="space-y-4">
        <div class="bz-card grid grid-cols-2 gap-3">
            <div>
                <label class="bz-label">Position</label>
                <select name="design[position]" x-model="design.position" @change="livePreview()" class="bz-input">
                    <option value="bottom-left">Bottom left</option>
                    <option value="bottom-right">Bottom right</option>
                    <option value="top-left">Top left</option>
                    <option value="top-right">Top right</option>
                </select>
            </div>
            <div>
                <label class="bz-label">Theme</label>
                <select name="design[theme]" x-model="design.theme" @change="livePreview()" class="bz-input">
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>
            <div>
                <label class="bz-label">Accent color</label>
                <input type="color" name="design[accent]" x-model="design.accent" @input="livePreview()" class="bz-input h-10 p-1">
            </div>
            <div>
                <label class="bz-label">Border radius</label>
                <select name="design[rounded]" x-model="design.rounded" @change="livePreview()" class="bz-input">
                    <option value="sm">Small</option>
                    <option value="md">Medium</option>
                    <option value="lg">Large</option>
                    <option value="xl">XL</option>
                    <option value="full">Pill</option>
                </select>
            </div>
            <div>
                <label class="bz-label">Animation</label>
                <select name="design[animation]" x-model="design.animation" @change="livePreview()" class="bz-input">
                    <option value="slide-up">Slide up</option>
                    <option value="fade">Fade</option>
                    <option value="zoom">Zoom</option>
                </select>
            </div>
            <div class="flex flex-col justify-end gap-2 pb-1">
                <label class="inline-flex items-center gap-2 text-xs text-white/70">
                    <input type="hidden" name="design[shadow]" value="0">
                    <input type="checkbox" name="design[shadow]" value="1" x-model="design.shadow" @change="livePreview()"> Drop shadow
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-white/70">
                    <input type="hidden" name="design[show_close]" value="0">
                    <input type="checkbox" name="design[show_close]" value="1" x-model="design.show_close" @change="livePreview()"> Show close button
                </label>
            </div>
        </div>
    </div>

    {{-- ============== TARGETING ============== --}}
    <div x-show="activeTab === 'targeting'" class="space-y-4">
        <div class="bz-card grid grid-cols-2 gap-3">
            <div>
                <label class="bz-label">Initial delay (sec)</label>
                <input type="number" min="0" name="targeting[delay]" x-model.number="targeting.delay" @input="livePreview()" class="bz-input">
            </div>
            <div>
                <label class="bz-label">Interval between rotations (sec)</label>
                <input type="number" min="0" name="targeting[interval]" x-model.number="targeting.interval" @input="livePreview()" class="bz-input">
            </div>
            <div>
                <label class="bz-label">Visible duration (sec), 0 = persistent</label>
                <input type="number" min="0" name="targeting[duration]" x-model.number="targeting.duration" @input="livePreview()" class="bz-input">
            </div>
            <div>
                <label class="bz-label">Max shows per session (0 = unlimited)</label>
                <input type="number" min="0" name="targeting[max_per_session]" x-model.number="targeting.max_per_session" @input="livePreview()" class="bz-input">
            </div>
            <div class="col-span-2">
                <label class="bz-label">Devices</label>
                <div class="flex gap-3">
                    <input type="hidden" name="targeting[devices][]" value="">
                    @foreach(['desktop','tablet','mobile'] as $dv)
                    <label class="inline-flex items-center gap-1.5 text-xs text-white/70">
                        <input type="checkbox" name="targeting[devices][]" value="{{ $dv }}" {{ in_array($dv, $targeting['devices'] ?? []) ? 'checked' : '' }}> {{ ucfirst($dv) }}
                    </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="bz-label">Show only on these paths (one per line, * wildcard)</label>
                <textarea name="targeting[pages_include]" rows="3" class="bz-input" placeholder="/pricing&#10;/blog/*">{{ implode("\n", (array)($targeting['pages_include'] ?? [])) }}</textarea>
            </div>
            <div>
                <label class="bz-label">Hide on these paths</label>
                <textarea name="targeting[pages_exclude]" rows="3" class="bz-input" placeholder="/admin/*&#10;/checkout">{{ implode("\n", (array)($targeting['pages_exclude'] ?? [])) }}</textarea>
            </div>
        </div>
    </div>

    </div>{{-- /left col --}}

    {{-- RIGHT: Live preview --}}
    <div class="space-y-3">
        <div class="bz-card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-semibold text-sm"><i class="fas fa-eye mr-1.5 text-blue-300"></i> Live preview</h3>
                <button type="button" @click="livePreview()" class="text-xs text-blue-300 hover:text-blue-200"><i class="fas fa-sync-alt mr-1"></i> Refresh</button>
            </div>
            <div id="bz-preview" class="preview-frame">
                <div class="absolute inset-0 flex items-center justify-center text-white/30 text-xs pointer-events-none" id="bz-preview-hint">Notifications will appear here</div>
            </div>
            <p class="text-white/40 text-[11px] mt-2">Triggers and timings are evaluated against this preview pane (no tracking is recorded).</p>
        </div>
    </div>

    </div>{{-- /grid --}}

</form>
</div>

<script>
window.__BUZZ = {
    notifications: @json($notifications),
    design: @json($design),
    targeting: @json($targeting),
    uuid: @json($proof->uuid),
    types: @json(\App\Modules\User\Models\SocialProof::TYPES),
    directoryBadgeTypes: @json(\App\Modules\User\Models\SocialProof::DIRECTORY_BADGE_TYPES),
    directoryBadgeId: @json($proof->directory_badge_notification_id),
    defaultsForUrl: null,
    defaults: @json(collect(array_keys(\App\Modules\User\Models\SocialProof::TYPES))->mapWithKeys(fn($t) => [$t => \App\Modules\User\Models\SocialProof::defaultSettingsFor($t)])),
};

function buzzEditor() {
    return {
        tabs: [
            {id:'general',       label:'General',       icon:'fa-cog'},
            {id:'notifications', label:'Notifications', icon:'fa-bell'},
            {id:'design',        label:'Design',        icon:'fa-palette'},
            {id:'targeting',     label:'Targeting',     icon:'fa-bullseye'},
        ],
        activeTab: 'notifications',
        openTypePicker: false,
        typeFilter: '',
        types: window.__BUZZ.types,
        notifications: window.__BUZZ.notifications.map(n => ({...n, _open: false})),
        design: {...window.__BUZZ.design},
        targeting: {...window.__BUZZ.targeting},
        directoryBadgeId: window.__BUZZ.directoryBadgeId || null,
        directoryBadgeTypes: window.__BUZZ.directoryBadgeTypes || [],

        init() {
            // Open the first notification by default
            if (this.notifications.length) this.notifications[0]._open = true;
            // Make sure design_override is always an object (not [] coming from PHP empty arrays)
            this.notifications.forEach(n => {
                if (Array.isArray(n.design_override)) n.design_override = {};
                if (!n.design_override) n.design_override = {};
                if (!n.triggers || !n.triggers.length) n.triggers = [{kind:'on_load',params:{}}];
                n.triggers.forEach(t => { if (!t.params) t.params = {}; });
            });
            // Ensure widget runtime is loaded for live preview
            this.loadRuntime().then(() => this.livePreview());
            // Re-render preview when typing (debounced)
            this.$watch('notifications', () => this.livePreview(), { deep: true });
        },

        loadRuntime() {
            return new Promise(resolve => {
                if (window.__1inmeSP && window.__1inmeSP.renderDraft) return resolve();
                var s = document.createElement('script');
                s.src = '{{ url('/js/social-proof-widget.js') }}?v=' + Date.now();
                s.onload = resolve;
                document.head.appendChild(s);
            });
        },

        typeLabel(t) { return this.types[t] || t; },

        isBadgeEligible(t) { return this.directoryBadgeTypes.indexOf(t) !== -1; },

        toggleDirectoryBadge(n) {
            if (!this.isBadgeEligible(n.type)) return;
            this.directoryBadgeId = (this.directoryBadgeId === n.id) ? null : n.id;
        },

        matchesFilter(key, label) {
            var q = (this.typeFilter || '').trim().toLowerCase();
            if (!q) return true;
            return key.toLowerCase().indexOf(q) !== -1 || (label || '').toLowerCase().indexOf(q) !== -1;
        },
        groupHasMatch(keys) {
            return keys.some(k => this.matchesFilter(k, this.types[k] || ''));
        },

        /**
         * Visual mini-preview rendered inside each template card. Uses
         * lightweight HTML chunks (no widget runtime) so the picker stays fast.
         */
        templatePreview(type) {
            const tpl = {
                recent_activity:
                    '<div class="tpl-card"><div class="tpl-avatar">SR</div>'+
                    '<div class="tpl-body"><div class="tpl-t1">Sarah from NYC</div><div class="tpl-t2">just signed up · 2m ago</div></div></div>',
                visitor_count:
                    '<div class="tpl-card"><span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 2s infinite"></span>'+
                    '<div class="tpl-body"><div class="tpl-t1">23 people viewing</div><div class="tpl-t2">right now</div></div></div>',
                conversion_count:
                    '<div class="tpl-card"><div class="tpl-avatar" style="background:linear-gradient(135deg,#34d399,#059669)">✓</div>'+
                    '<div class="tpl-body"><div class="tpl-t1">142 purchases</div><div class="tpl-t2">in the last 24 hours</div></div></div>',
                social_followers:
                    '<div class="tpl-card"><div class="tpl-avatar" style="background:#1da1f2">𝕏</div>'+
                    '<div class="tpl-body"><div class="tpl-t1">12.4k followers</div><div class="tpl-t2">join us on social</div></div></div>',
                trust_badge:
                    '<div class="tpl-card"><div class="tpl-body"><div class="tpl-stars">★★★★★</div><div class="tpl-t2" style="margin-top:2px">4.9 · 2,341 reviews</div></div></div>',
                review:
                    '<div class="tpl-card"><div class="tpl-avatar">M</div>'+
                    '<div class="tpl-body"><div class="tpl-stars">★★★★★</div><div class="tpl-t2">"Best purchase ever!"</div></div></div>',
                testimonial_quote:
                    '<div class="tpl-card" style="max-width:180px"><div class="tpl-body">'+
                    '<div style="font-size:14px;color:#3d6bff;line-height:1">"</div>'+
                    '<div class="tpl-t2" style="font-style:italic;color:#0f172a">A total game-changer for our team.</div>'+
                    '<div class="tpl-t2" style="margin-top:3px"> - Alex K.</div></div></div>',
                email_signup:
                    '<div class="tpl-card" style="max-width:180px;flex-direction:column;align-items:stretch;gap:5px"><div class="tpl-t1">Get 10% off</div>'+
                    '<div style="display:flex;gap:4px"><div style="flex:1;height:18px;background:#f1f5f9;border-radius:4px"></div><div style="height:18px;padding:0 8px;background:#3d6bff;color:#fff;border-radius:4px;font-size:9px;display:flex;align-items:center">Join</div></div></div>',
                exit_offer:
                    '<div class="tpl-card" style="max-width:180px;flex-direction:column;align-items:stretch;gap:5px;border:2px solid #3d6bff">'+
                    '<div class="tpl-t1" style="color:#3d6bff">Wait! Don\'t leave</div>'+
                    '<div class="tpl-t2">Take 15% off your order</div></div>',
                feedback_thumbs:
                    '<div class="tpl-card" style="gap:6px"><div class="tpl-body" style="flex-direction:row;align-items:center;gap:5px">'+
                    '<div class="tpl-t1">Helpful?</div><span style="font-size:14px">👍</span><span style="font-size:14px">👎</span></div></div>',
                countdown:
                    '<div class="tpl-card" style="flex-direction:column;align-items:center;gap:3px;background:#0f172a;color:#fff">'+
                    '<div class="tpl-t2" style="color:#cbd5e1">Sale ends in</div>'+
                    '<div style="font-family:monospace;font-size:13px;font-weight:700;letter-spacing:1px">02:14:36</div></div>',
                flash_sale:
                    '<div style="background:linear-gradient(90deg,#ef4444,#dc2626);color:#fff;padding:10px 14px;border-radius:8px;font-size:11px;font-weight:600;text-align:center;width:170px">'+
                    '⚡ FLASH SALE · 40% OFF</div>',
                low_stock:
                    '<div class="tpl-card" style="border-left:3px solid #f59e0b">'+
                    '<div class="tpl-body"><div class="tpl-t1" style="color:#b45309">⚠ Only 3 left</div>'+
                    '<div class="tpl-t2">in stock</div></div></div>',
                price_drop:
                    '<div class="tpl-card"><div class="tpl-body"><div class="tpl-t2">Was <span class="tpl-strike">$99</span></div>'+
                    '<div class="tpl-new">Now $59</div></div></div>',
                announcement_bar:
                    '<div class="tpl-bar top" style="background:#3d6bff">🎉 Free shipping over $50</div>'+
                    '<div style="margin-top:14px;color:rgba(255,255,255,.4);font-size:9px">Top bar style</div>',
                sticky_cta:
                    '<div class="tpl-bar bottom" style="background:#0f172a">'+
                    '<span>Get started today</span>'+
                    '<span style="margin-left:8px;background:#3d6bff;padding:2px 8px;border-radius:4px">Sign up →</span></div>',
                cookie_consent:
                    '<div class="tpl-bar bottom" style="background:#1f2937;height:30px;justify-content:space-between;padding:0 8px;font-size:8.5px">'+
                    '<span>🍪 We use cookies</span>'+
                    '<span style="background:#3d6bff;padding:2px 6px;border-radius:3px">Accept</span></div>',
                whatsapp_chat:
                    '<div class="tpl-bubble" style="background:#25d366">💬</div>',
                click_to_call:
                    '<div class="tpl-bubble" style="background:#0f172a">📞</div>',
                video_popup:
                    '<div style="width:120px;height:74px;background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:8px;display:flex;align-items:center;justify-content:center;position:relative">'+
                    '<div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;color:#3d6bff;font-size:14px">▶</div></div>',
                share_buttons:
                    '<div style="display:flex;gap:6px">'+
                    ['#1877f2','#1da1f2','#0a66c2','#bd081c','#25d366'].map(c =>
                        '<div style="width:26px;height:26px;border-radius:50%;background:'+c+';display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px">●</div>'
                    ).join('') + '</div>',
                custom_html:
                    '<div class="tpl-card" style="font-family:monospace;background:#0f172a;color:#10b981;font-size:9.5px"><span>&lt;/&gt;</span>'+
                    '<div class="tpl-body" style="color:#cbd5e1"><div>Your HTML here</div><div class="tpl-t2" style="color:#64748b">// fully custom</div></div></div>',
            };
            return tpl[type] || '<div class="tpl-card"><div class="tpl-body"><div class="tpl-t1">Notification</div></div></div>';
        },

        addNotification(type) {
            var n = {
                id: crypto.randomUUID ? crypto.randomUUID() : ('n_' + Math.random().toString(36).slice(2)),
                type: type,
                name: this.types[type] || 'Notification',
                settings: JSON.parse(JSON.stringify(window.__BUZZ.defaults[type] || {})),
                design_override: {},
                triggers: [{kind:'on_load',params:{}}],
                triggers_logic: 'or',
                is_active: true,
                sort_order: this.notifications.length,
                _open: true,
            };
            this.notifications.push(n);
            this.$nextTick(() => this.livePreview());
        },

        remove(idx) {
            var self = this;
            window.themedConfirm({
                title: 'Remove this notification?',
                message: 'It will be deleted from this proof set when you save.',
                confirmText: 'Remove',
                confirmIcon: 'fa-trash',
                iconClass: 'fa-trash',
                onConfirm: function () {
                    var removed = self.notifications.splice(idx, 1)[0];
                    if (removed && self.directoryBadgeId === removed.id) self.directoryBadgeId = null;
                    self.livePreview();
                },
            });
        },

        duplicate(idx) {
            var copy = JSON.parse(JSON.stringify(this.notifications[idx]));
            copy.id = crypto.randomUUID ? crypto.randomUUID() : ('n_' + Math.random().toString(36).slice(2));
            copy.name = copy.name + ' (copy)';
            copy._open = true;
            this.notifications.splice(idx + 1, 0, copy);
            this.livePreview();
        },

        moveUp(idx)   { if (idx > 0)                          { var x = this.notifications.splice(idx,1)[0]; this.notifications.splice(idx-1, 0, x); this.livePreview(); } },
        moveDown(idx) { if (idx < this.notifications.length-1) { var x = this.notifications.splice(idx,1)[0]; this.notifications.splice(idx+1, 0, x); this.livePreview(); } },

        addTrigger(n) {
            n.triggers.push({kind:'on_load', params:{}});
            this.livePreview();
        },

        initTriggerParams(tr) {
            tr.params = {};
            if (tr.kind === 'after_delay') tr.params.seconds = 3;
            if (tr.kind === 'after_idle')  tr.params.seconds = 5;
            if (tr.kind === 'on_scroll')   tr.params.percent = 50;
            if (tr.kind === 'on_click')    tr.params.selector = '';
            if (tr.kind === 'url_contains')tr.params.text = '';
        },

        livePreview() {
            if (this._previewT) clearTimeout(this._previewT);
            this._previewT = setTimeout(() => {
                if (!window.__1inmeSP || !window.__1inmeSP.renderDraft) return;
                var hint = document.getElementById('bz-preview-hint');
                if (hint) hint.style.display = this.notifications.some(n => n.is_active) ? 'none' : '';
                window.__1inmeSP.renderDraft(window.__BUZZ.uuid, {
                    uuid: window.__BUZZ.uuid,
                    design: {...this.design},
                    targeting: {...this.targeting},
                    notifications: JSON.parse(JSON.stringify(this.notifications.filter(n => n.is_active))),
                    live_visitors: 23,
                }, {
                    uuid: window.__BUZZ.uuid,
                    trackUrl: '/sp/' + window.__BUZZ.uuid + '/track',
                    preview: true,
                    mountTo: '#bz-preview',
                });
            }, 250);
        },

        syncBeforeSubmit() {
            // Strip UI-only fields before submission
            this.notifications = this.notifications.map(({_open, ...rest}) => rest);
        },

        // Per-type form generator. Returns HTML using x-model bindings —
        // Alpine wires them up automatically because this is rendered inside
        // the same x-data scope.
        renderFields(n) {
            var s = n.settings;
            switch (n.type) {
                case 'recent_activity':
                    return this.fieldsRecent(n);
                case 'visitor_count':
                    return this.tpl([
                        this.text(n,'settings.text','Text','{count} people are viewing this page'),
                        this.row([this.num(n,'settings.min','Min count'), this.num(n,'settings.max','Max count')]),
                    ]);
                case 'conversion_count':
                    return this.tpl([
                        this.text(n,'settings.text','Text','{count} purchased recently'),
                        this.num(n,'settings.count','Count'),
                    ]);
                case 'social_followers':
                    return this.tpl([
                        this.row([
                            this.select(n,'settings.network','Network',{instagram:'Instagram',twitter:'Twitter/X',facebook:'Facebook',linkedin:'LinkedIn',tiktok:'TikTok',youtube:'YouTube'}),
                            this.text(n,'settings.handle','Handle','@brand'),
                        ]),
                        this.row([this.num(n,'settings.count','Followers'), this.text(n,'settings.url','Profile URL','https://...')]),
                    ]);
                case 'trust_badge':
                    return this.tpl([
                        this.row([this.num(n,'settings.rating','Rating (0-5)',0,5,0.1), this.num(n,'settings.reviews','Reviews count')]),
                        this.text(n,'settings.label','Label','on Trustpilot'),
                    ]);
                case 'review':
                    return this.fieldsReviews(n);
                case 'testimonial_quote':
                    return this.tpl([
                        this.textarea(n,'settings.quote','Quote'),
                        this.row([this.text(n,'settings.author','Author','Jane Doe'), this.text(n,'settings.role','Role','CEO at Acme')]),
                    ]);
                case 'email_signup':
                    return this.tpl([
                        this.text(n,'settings.title','Title','Join our newsletter'),
                        this.textarea(n,'settings.body','Body'),
                        this.text(n,'settings.cta','Button label','Subscribe'),
                    ]);
                case 'exit_offer':
                    return this.tpl([
                        this.text(n,'settings.title','Title'),
                        this.textarea(n,'settings.body','Body'),
                        this.row([this.text(n,'settings.cta','Button label'), this.text(n,'settings.cta_url','Button URL','https://...')]),
                    ]);
                case 'feedback_thumbs':
                    return this.tpl([this.text(n,'settings.question','Question','Was this helpful?')]);
                case 'countdown':
                    return this.tpl([
                        this.text(n,'settings.title','Title','Limited offer ends in'),
                        this.text(n,'settings.ends_at','Ends at (ISO 8601 e.g. 2026-12-31T23:59:00Z)'),
                        this.text(n,'settings.expired_text','Expired text','Offer expired'),
                    ]);
                case 'flash_sale':
                    return this.tpl([
                        this.row([this.text(n,'settings.title','Title','Flash sale!'), this.text(n,'settings.discount','Discount','20% OFF')]),
                        this.text(n,'settings.ends_at','Ends at (ISO 8601)'),
                        this.row([this.text(n,'settings.cta','Button label','Shop now'), this.text(n,'settings.cta_url','Button URL')]),
                    ]);
                case 'low_stock':
                    return this.tpl([this.text(n,'settings.text','Text','Only {count} left in stock!'), this.num(n,'settings.count','Stock count')]);
                case 'price_drop':
                    return this.tpl([
                        this.text(n,'settings.text','Text','Price dropped from {old} to {new}!'),
                        this.row([this.text(n,'settings.old_price','Old price','$99'), this.text(n,'settings.new_price','New price','$49')]),
                    ]);
                case 'announcement_bar':
                    return this.tpl([
                        this.text(n,'settings.text','Bar text'),
                        this.row([this.text(n,'settings.cta_label','Button label'), this.text(n,'settings.cta_url','Button URL')]),
                        this.select(n,'settings.placement','Placement',{top:'Top',bottom:'Bottom'}),
                    ]);
                case 'sticky_cta':
                    return this.tpl([
                        this.text(n,'settings.text','Text'),
                        this.row([this.text(n,'settings.cta_label','Button label'), this.text(n,'settings.cta_url','Button URL')]),
                    ]);
                case 'cookie_consent':
                    return this.tpl([
                        this.text(n,'settings.title','Title'),
                        this.textarea(n,'settings.body','Body'),
                        this.row([this.text(n,'settings.accept_label','Accept label'), this.text(n,'settings.reject_label','Reject label')]),
                        this.text(n,'settings.policy_url','Privacy policy URL'),
                    ]);
                case 'whatsapp_chat':
                    return this.tpl([
                        this.text(n,'settings.phone','Phone (with country code)','+1234567890'),
                        this.text(n,'settings.label','Bubble label','Chat with us'),
                        this.textarea(n,'settings.message','Pre-filled message'),
                    ]);
                case 'click_to_call':
                    return this.tpl([
                        this.text(n,'settings.phone','Phone','+1234567890'),
                        this.text(n,'settings.label','Bubble label','Call us'),
                    ]);
                case 'video_popup':
                    return this.tpl([
                        this.text(n,'settings.video_url','Embed URL (YouTube/Vimeo embed link)'),
                        this.text(n,'settings.thumbnail_text','Thumbnail caption','Watch our 60s demo'),
                    ]);
                case 'share_buttons':
                    return this.tpl([
                        this.text(n,'settings.text','Heading','Share'),
                        this.text(n,'settings.url','URL to share (blank = current page)'),
                        '<div><label class="bz-label">Networks</label><div class="flex gap-3 flex-wrap text-xs text-white/70">' +
                          ['twitter','facebook','linkedin','whatsapp','telegram','email'].map(net =>
                            '<label class="inline-flex items-center gap-1"><input type="checkbox" value="'+net+'" '
                              + (((n.settings.networks||[]).indexOf(net) >= 0) ? 'checked' : '')
                              + ' @change="toggleNet($event,\''+n.id+'\',\''+net+'\')"> '+net+'</label>'
                          ).join('') + '</div></div>',
                    ]);
                case 'custom_html':
                    return this.tpl([
                        '<div><label class="bz-label">HTML (sanitized, script/iframe/event handlers stripped)</label>'
                        + '<textarea x-model="getNotif(\''+n.id+'\').settings.html" @input="livePreview()" rows="6" class="bz-input font-mono text-xs"></textarea></div>'
                    ]);
            }
            return '';
        },

        getNotif(id) { return this.notifications.find(n => n.id === id); },

        toggleNet(e, id, net) {
            var n = this.getNotif(id);
            if (!n) return;
            n.settings.networks = n.settings.networks || [];
            var i = n.settings.networks.indexOf(net);
            if (e.target.checked && i < 0) n.settings.networks.push(net);
            if (!e.target.checked && i >= 0) n.settings.networks.splice(i, 1);
            this.livePreview();
        },

        // ----- field builders (return HTML strings using x-model on getNotif(id)...) -----
        tpl(parts) { return '<div class="space-y-3">' + parts.join('') + '</div>'; },
        row(parts) { return '<div class="grid grid-cols-2 gap-2">' + parts.join('') + '</div>'; },
        text(n, path, label, ph) {
            return '<div><label class="bz-label">' + this.esc(label) + '</label>'
                + '<input type="text" x-model="getNotif(\''+n.id+'\').' + path + '" @input="livePreview()" class="bz-input" placeholder="' + this.esc(ph||'') + '"></div>';
        },
        textarea(n, path, label) {
            return '<div><label class="bz-label">' + this.esc(label) + '</label>'
                + '<textarea x-model="getNotif(\''+n.id+'\').' + path + '" @input="livePreview()" rows="2" class="bz-input"></textarea></div>';
        },
        num(n, path, label, min, max, step) {
            var attrs = '';
            if (min != null) attrs += ' min="'+min+'"';
            if (max != null) attrs += ' max="'+max+'"';
            if (step != null) attrs += ' step="'+step+'"';
            return '<div><label class="bz-label">' + this.esc(label) + '</label>'
                + '<input type="number"' + attrs + ' x-model.number="getNotif(\''+n.id+'\').' + path + '" @input="livePreview()" class="bz-input"></div>';
        },
        select(n, path, label, opts) {
            var options = Object.keys(opts).map(k => '<option value="'+this.esc(k)+'">'+this.esc(opts[k])+'</option>').join('');
            return '<div><label class="bz-label">' + this.esc(label) + '</label>'
                + '<select x-model="getNotif(\''+n.id+'\').' + path + '" @change="livePreview()" class="bz-input">' + options + '</select></div>';
        },
        esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});},

        fieldsRecent(n) {
            var pool = n.settings.pool || [];
            var rows = pool.map(function (it, i) {
                return '<div class="flex gap-2 items-center bg-white/5 rounded-lg p-2 mb-1">'
                  + '<input type="text" placeholder="Name"     value="'+(it.name||'').replace(/"/g,'&quot;')+'"     @input="getNotif(\''+n.id+'\').settings.pool['+i+'].name=$event.target.value;livePreview()" class="bz-input">'
                  + '<input type="text" placeholder="Location" value="'+(it.location||'').replace(/"/g,'&quot;') +'"  @input="getNotif(\''+n.id+'\').settings.pool['+i+'].location=$event.target.value;livePreview()" class="bz-input">'
                  + '<input type="text" placeholder="Action"   value="'+(it.action||'').replace(/"/g,'&quot;')   +'"  @input="getNotif(\''+n.id+'\').settings.pool['+i+'].action=$event.target.value;livePreview()" class="bz-input">'
                  + '<button type="button" class="bz-btn-icon hover:!text-rose-400" @click="getNotif(\''+n.id+'\').settings.pool.splice('+i+',1);livePreview()"><i class="fas fa-times"></i></button>'
                  + '</div>';
            }).join('');
            return this.tpl([
                this.row([
                    this.text(n,'settings.title_template','Title template','{name} from {location}'),
                    this.text(n,'settings.body_template','Body template','{action}'),
                ]),
                '<div><label class="bz-label">Activity pool ({name}, {location}, {action} placeholders)</label>'
                + '<div>' + rows + '</div>'
                + '<button type="button" class="text-xs text-blue-300 hover:text-blue-200 mt-1" @click="getNotif(\''+n.id+'\').settings.pool = getNotif(\''+n.id+'\').settings.pool || []; getNotif(\''+n.id+'\').settings.pool.push({name:\'\',location:\'\',action:\'just signed up\'}); livePreview()"><i class="fas fa-plus mr-1"></i>Add row</button></div>',
            ]);
        },

        fieldsReviews(n) {
            var items = n.settings.items || [];
            var rows = items.map(function (it, i) {
                return '<div class="bg-white/5 rounded-lg p-2 mb-1 space-y-1">'
                  + '<div class="flex gap-2"><input type="text" placeholder="Author" value="'+(it.author||'').replace(/"/g,'&quot;')+'" @input="getNotif(\''+n.id+'\').settings.items['+i+'].author=$event.target.value;livePreview()" class="bz-input">'
                  + '<input type="number" min="1" max="5" placeholder="Rating" value="'+(it.rating||5)+'" @input="getNotif(\''+n.id+'\').settings.items['+i+'].rating=parseInt($event.target.value)||5;livePreview()" class="bz-input" style="width:90px">'
                  + '<button type="button" class="bz-btn-icon hover:!text-rose-400" @click="getNotif(\''+n.id+'\').settings.items.splice('+i+',1);livePreview()"><i class="fas fa-times"></i></button></div>'
                  + '<textarea placeholder="Review text" @input="getNotif(\''+n.id+'\').settings.items['+i+'].text=$event.target.value;livePreview()" class="bz-input" rows="2">'+this.esc(it.text||'')+'</textarea>'
                  + '</div>';
            }.bind(this)).join('');
            return this.tpl([
                '<label class="inline-flex items-center gap-2 text-xs text-white/70"><input type="checkbox" x-model="getNotif(\''+n.id+'\').settings.rotate" @change="livePreview()"> Rotate through reviews</label>',
                '<div><label class="bz-label">Reviews</label><div>' + rows + '</div>'
                + '<button type="button" class="text-xs text-blue-300 hover:text-blue-200 mt-1" @click="getNotif(\''+n.id+'\').settings.items = getNotif(\''+n.id+'\').settings.items || []; getNotif(\''+n.id+'\').settings.items.push({author:\'\',text:\'\',rating:5}); livePreview()"><i class="fas fa-plus mr-1"></i>Add review</button></div>',
            ]);
        },
    };
}
</script>
@endsection
