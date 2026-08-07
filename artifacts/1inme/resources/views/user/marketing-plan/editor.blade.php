@extends('user.layouts.app')
@section('title', ($plan?->name ?? 'New Marketing Plan') . ' — Marketing Plan Calculator')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8" x-data="mpcApp()" x-init="init()">
    <style>
        /* Theme-aware helpers — dark by default, paired light-mode rules. */
        .mpc-card  { border: 1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.03); }
        html.light-mode .mpc-card { border-color: rgba(15,23,42,0.12); background: #ffffff; }
        .mpc-title { color: #fff; } html.light-mode .mpc-title { color: #0f172a; }
        .mpc-text  { color: rgba(255,255,255,0.8); } html.light-mode .mpc-text { color: #1e293b; }
        .mpc-sub   { color: rgba(255,255,255,0.5); } html.light-mode .mpc-sub { color: #475569; }
        .mpc-faint { color: rgba(255,255,255,0.35); } html.light-mode .mpc-faint { color: #64748b; }
        .mpc-input {
            /* background-COLOR longhand on purpose: the app layout injects a
               chevron background-image into selects — a `background:` shorthand
               here would reset its no-repeat/position and tile the chevron
               into a criss-cross artifact across the select. */
            background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
            color: #fff; border-radius: 0.6rem; padding: 0.4rem 0.6rem; font-size: 0.82rem; width: 100%;
        }
        .mpc-input:focus { outline: none; border-color: #3b82f6; }
        html.light-mode .mpc-input { background-color: #fff; border-color: rgba(15,23,42,0.18); color: #0f172a; }
        .mpc-th { color: rgba(255,255,255,0.45); font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; text-align: left; padding: 0.5rem 0.6rem; white-space: nowrap; }
        html.light-mode .mpc-th { color: #64748b; }
        .mpc-td { padding: 0.4rem 0.6rem; font-size: 0.82rem; color: rgba(255,255,255,0.8); white-space: nowrap; }
        html.light-mode .mpc-td { color: #1e293b; }
        .mpc-row { border-top: 1px solid rgba(255,255,255,0.06); }
        html.light-mode .mpc-row { border-top-color: rgba(15,23,42,0.08); }
        /* ── Stepper tabs ── */
        .mpc-step {
            display: inline-flex; align-items: center; gap: 0.55rem;
            padding: 0.45rem 0.95rem 0.45rem 0.5rem; border-radius: 9999px;
            font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.55);
            border: 1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.03);
            transition: color .15s ease, border-color .15s ease, background .15s ease;
        }
        html.light-mode .mpc-step { color: #475569; border-color: rgba(15,23,42,0.12); background: #fff; }
        .mpc-step:hover { color: #93c5fd; border-color: rgba(59,130,246,0.45); }
        html.light-mode .mpc-step:hover { color: #2563eb; border-color: rgba(37,99,235,0.4); }
        .mpc-step:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; }
        .mpc-step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.5rem; height: 1.5rem; border-radius: 9999px; flex: none;
            font-size: 0.7rem; font-weight: 800;
            background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6);
            transition: background .15s ease, color .15s ease;
        }
        html.light-mode .mpc-step-num { background: rgba(15,23,42,0.06); color: #475569; }
        .mpc-step.active {
            background: rgba(37,99,235,0.16); border-color: rgba(59,130,246,0.55);
            color: #93c5fd; box-shadow: 0 0 0 1px rgba(59,130,246,0.15);
        }
        html.light-mode .mpc-step.active { background: rgba(37,99,235,0.08); border-color: rgba(37,99,235,0.45); color: #1d4ed8; }
        .mpc-step.active .mpc-step-num { background: #2563eb; color: #fff; }
        .mpc-step.done { color: rgba(255,255,255,0.75); }
        html.light-mode .mpc-step.done { color: #1e293b; }
        .mpc-step.done .mpc-step-num { background: rgba(16,185,129,0.18); color: #34d399; }
        html.light-mode .mpc-step.done .mpc-step-num { background: rgba(16,185,129,0.14); color: #059669; }
        .mpc-step-sep { width: 1.1rem; height: 1px; background: rgba(255,255,255,0.15); flex: none; align-self: center; }
        html.light-mode .mpc-step-sep { background: rgba(15,23,42,0.15); }
        /* Small screens: drop the connector dashes and tighten the pills so
           the four steps sit as a clean centered 2×2 wrap instead of a
           ragged dash-broken line. */
        @media (max-width: 640px) {
            .mpc-step-sep { display: none; }
            .mpc-step { font-size: 0.78rem; gap: 0.4rem; padding: 0.4rem 0.75rem 0.4rem 0.4rem; }
            .mpc-step-num { width: 1.3rem; height: 1.3rem; font-size: 0.65rem; }
        }
        .mpc-kpi { font-size: 1.35rem; font-weight: 800; color: #fff; }
        html.light-mode .mpc-kpi { color: #0f172a; }
        .mpc-export-btn { background: rgba(255,255,255,0.10); color: #fff; border: 1px solid rgba(255,255,255,0.10); }
        .mpc-export-btn:hover { background: rgba(255,255,255,0.15); }
        html.light-mode .mpc-export-btn { background: #fff; color: #0f172a; border-color: rgba(15,23,42,0.18); }
        html.light-mode .mpc-export-btn:hover { background: #f1f5f9; }
        .mpc-menu { background: #0f172a; border: 1px solid rgba(255,255,255,0.10); }
        html.light-mode .mpc-menu { background: #fff; border-color: rgba(15,23,42,0.12); }
        .mpc-menu-item { color: rgba(255,255,255,0.8); }
        .mpc-menu-item:hover { background: rgba(255,255,255,0.10); }
        html.light-mode .mpc-menu-item { color: #1e293b; }
        html.light-mode .mpc-menu-item:hover { background: #f1f5f9; }
    </style>

    {{-- ───── Header: name, currency toggle, save ───── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('user.marketing-plan.index') }}" class="text-[11px] font-bold uppercase tracking-[0.15em] text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-1"></i> Marketing Plan Calculator
            </a>
            {{-- Task #6767 — which industry preset this plan started from. --}}
            <span data-mpc-preset-badge
                  class="inline-block align-middle ml-2 px-2 py-0.5 rounded-md bg-blue-500/15 text-blue-400 text-[10px] font-bold uppercase tracking-wide"
                  title="Industry benchmark preset this plan started from"
                  x-text="presetLabel"></span>
            <input type="text" x-model="name" placeholder="Plan name (e.g. 2026 Growth Plan)"
                   class="mpc-input mt-1.5 !text-base !font-semibold" style="max-width: 26rem;">
        </div>
        <div class="flex items-center gap-2">
            <div class="flex rounded-xl overflow-hidden border border-white/10" role="group" aria-label="Display currency">
                <button type="button" @click="p.display_currency = 'INR'"
                        :class="p.display_currency === 'INR' ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/50'"
                        class="px-3 py-1.5 text-xs font-bold">₹ INR</button>
                <button type="button" @click="p.display_currency = 'USD'"
                        :class="p.display_currency === 'USD' ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/50'"
                        class="px-3 py-1.5 text-xs font-bold">$ USD</button>
            </div>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="mpc-export-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold">
                    <i class="fas fa-download"></i> Export <i class="fas fa-chevron-down text-[10px]"></i>
                </button>
                <div x-show="open" x-cloak
                     class="mpc-menu absolute right-0 mt-1.5 w-48 rounded-xl shadow-xl z-20 overflow-hidden">
                    <button type="button" @click="open = false; exportXlsx()"
                            class="mpc-menu-item w-full text-left px-4 py-2.5 text-sm">
                        <i class="fas fa-file-excel mr-2 text-emerald-400"></i> Excel (.xlsx)
                    </button>
                    <button type="button" @click="open = false; exportCsv()"
                            class="mpc-menu-item w-full text-left px-4 py-2.5 text-sm">
                        <i class="fas fa-file-csv mr-2 text-blue-400"></i> CSV (.csv)
                    </button>
                    <button type="button" @click="open = false; exportPdf()" :disabled="pdfBusy"
                            class="mpc-menu-item w-full text-left px-4 py-2.5 text-sm disabled:opacity-60">
                        <i class="fas mr-2 text-red-400" :class="pdfBusy ? 'fa-circle-notch fa-spin' : 'fa-file-pdf'"></i>
                        <span x-text="pdfBusy ? 'Preparing PDF…' : 'PDF one-pager (.pdf)'"></span>
                    </button>
                </div>
            </div>
            <button type="button" @click="save()" :disabled="saving"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-60">
                <i class="fas" :class="saving ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"></i>
                <span x-text="saving ? 'Saving…' : 'Save plan'"></span>
            </button>
        </div>
    </div>
    @if(!empty($aiSeed))
        {{-- Task #6739 — this new plan was pre-filled from an AI Marketing Strategist plan. --}}
        <div class="rounded-2xl border border-blue-500/30 bg-blue-500/10 p-4 mb-5">
            <p class="text-sm font-semibold text-blue-300">
                <i class="fas fa-wand-magic-sparkles mr-1.5"></i>
                Pre-filled from your AI strategy “{{ $aiSeed['strategy_title'] !== '' ? $aiSeed['strategy_title'] : 'Marketing Strategy' }}”
            </p>
            <p class="text-xs mpc-sub mt-1">
                @if(!empty($aiSeed['matched']))
                    Channel allocations were re-weighted toward the strategist's recommended channels
                    ({{ implode(', ', array_slice($aiSeed['matched'], 0, 6)) }}{{ count($aiSeed['matched']) > 6 ? '…' : '' }})
                    and the budget was taken from the strategy where possible.
                @else
                    The budget and company details were taken from the strategy where possible.
                @endif
                Everything below is a starting point — review and edit anything before saving.
            </p>
        </div>
    @endif

    <p class="text-xs text-emerald-400 mb-3" x-show="savedFlash" x-cloak><i class="fas fa-check mr-1"></i> Saved.</p>
    <p class="text-xs text-red-400 mb-3" x-show="saveError" x-cloak x-text="saveError"></p>

    {{-- ───── Stepper tabs ───── --}}
    <nav class="flex flex-wrap items-center justify-center gap-1.5 mb-5" aria-label="Calculator steps">
        <template x-for="(s, i) in steps" :key="s.key">
            <div class="flex items-center gap-1.5">
                <div class="mpc-step-sep" x-show="i > 0" aria-hidden="true"></div>
                <button type="button" class="mpc-step"
                        :class="tab === s.key ? 'active' : (stepIndex > i ? 'done' : '')"
                        :aria-label="(i + 1) + ' · ' + s.label"
                        :aria-current="tab === s.key ? 'step' : false"
                        @click="s.key === 'dashboard' ? openDashboard() : tab = s.key">
                    <span class="mpc-step-num">
                        <template x-if="stepIndex > i && tab !== s.key"><i class="fas fa-check text-[9px]"></i></template>
                        <template x-if="!(stepIndex > i && tab !== s.key)"><span x-text="i + 1"></span></template>
                    </span>
                    <span x-text="s.label"></span>
                </button>
            </div>
        </template>
    </nav>

    {{-- ───── Scenario switcher (Task #6769) ───── --}}
    <div class="rounded-2xl mpc-card p-3 mb-5" data-mpc-scenarios>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-bold uppercase tracking-[0.12em] mpc-faint">Scenario</span>
            <div class="flex rounded-xl overflow-hidden border border-white/10" role="group" aria-label="Scenario">
                <template x-for="k in scenKeys" :key="k">
                    <button type="button" @click="setScenario(k)"
                            :data-mpc-scenario="k"
                            :class="scenario === k ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/50'"
                            class="px-3 py-1.5 text-xs font-bold" x-text="scenLabels[k]"></button>
                </template>
            </div>
            <span class="text-[11px] mpc-faint" x-show="scenario === 'expected'">Your saved assumptions, unchanged.</span>
            <span class="text-[11px] mpc-faint" x-show="scenario !== 'expected'" x-cloak>All tables, charts and metrics reflect this scenario's multipliers.</span>
        </div>
        {{-- x-if (not x-show): p.scenarios has no 'expected' entry, so the
             x-model bindings below must not even evaluate while Expected is
             the active scenario. --}}
        <template x-if="scenario !== 'expected'">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
            <div>
                <label class="text-[10px] font-bold mpc-faint uppercase">Ad budget (% of base)</label>
                <input type="number" min="0" max="1000" step="1" x-model.number="p.scenarios[scenario].budget" class="mpc-input mt-0.5" data-mpc-scen-budget>
            </div>
            <div>
                <label class="text-[10px] font-bold mpc-faint uppercase">Cost / visitor (% of base)</label>
                <input type="number" min="1" max="1000" step="1" x-model.number="p.scenarios[scenario].cpv" class="mpc-input mt-0.5" data-mpc-scen-cpv>
            </div>
            <div>
                <label class="text-[10px] font-bold mpc-faint uppercase">Visitor → lead (% of base)</label>
                <input type="number" min="0" max="1000" step="1" x-model.number="p.scenarios[scenario].vl" class="mpc-input mt-0.5" data-mpc-scen-vl>
            </div>
            <div>
                <label class="text-[10px] font-bold mpc-faint uppercase">Lead → customer (% of base)</label>
                <input type="number" min="0" max="1000" step="1" x-model.number="p.scenarios[scenario].lc" class="mpc-input mt-0.5" data-mpc-scen-lc>
            </div>
        </div>
        </template>
        <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.scenarios" x-cloak x-text="clampHints.scenarios"></p>
    </div>

    {{-- ───── 1 · ASSUMPTIONS ───── --}}
    <div x-show="tab === 'assumptions'" class="space-y-5">
        <div class="grid md:grid-cols-3 gap-4">
            <div class="rounded-2xl mpc-card p-4">
                <label class="text-xs font-semibold mpc-sub">Company / Product name</label>
                <input type="text" x-model="p.company" class="mpc-input mt-1.5" placeholder="Acme Fitness App">
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <label class="text-xs font-semibold mpc-sub">Total annual ad-spend budget (₹, paid channels only)</label>
                <input type="number" min="0" x-model.number="p.annual_budget" class="mpc-input mt-1.5">
                <p class="text-[11px] mpc-faint mt-1">Excludes Sayzio's fixed subscription cost.</p>
                <p class="text-[11px] text-amber-400 mt-1" x-show="clampHints.annual_budget" x-cloak x-text="clampHints.annual_budget"></p>
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <label class="text-xs font-semibold mpc-sub">USD → INR display rate</label>
                <input type="number" min="1" step="0.01" x-model.number="p.usd_inr_rate" class="mpc-input mt-1.5">
                <p class="text-[11px] mpc-faint mt-1">Used only when the display toggle is set to $ USD.</p>
                <p class="text-[11px] text-amber-400 mt-1" x-show="clampHints.usd_inr_rate" x-cloak x-text="clampHints.usd_inr_rate"></p>
            </div>
        </div>

        <div class="rounded-2xl mpc-card p-4">
            <h3 class="text-sm font-bold mpc-title">Monthly seasonality weights <span class="mpc-faint font-normal">(1.0 = average month)</span></h3>
            <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-12 gap-2 mt-3">
                <template x-for="(m, i) in months" :key="i">
                    <div>
                        <label class="text-[10px] font-bold mpc-faint uppercase" x-text="m"></label>
                        <input type="number" min="0" step="0.1" x-model.number="p.weights[i]" class="mpc-input mt-0.5 !px-1.5 text-center">
                    </div>
                </template>
            </div>
            <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.weights" x-cloak x-text="clampHints.weights"></p>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title">Sayzio plan</h3>
                <select x-model="p.plan_slug" class="mpc-input mt-2">
                    <template x-for="opt in planOptions" :key="opt.slug">
                        <option :value="opt.slug" :selected="opt.slug === p.plan_slug"
                                x-text="opt.name + ' — ₹' + nf(opt.inr) + '/mo ($' + nf(opt.usd) + ')'"></option>
                    </template>
                </select>
                <p class="text-[11px] mpc-faint mt-2">Live pricing — defaults to your current plan. A fixed monthly subscription, not a % of your ad budget.</p>
                {{-- Task #6772 — prefill from real workspace usage. --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="applyActuals()" :disabled="actualsLoading"
                            class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold disabled:opacity-50"
                            data-mpc-use-actuals>
                        <span x-show="!actualsLoading">Use my Sayzio data</span>
                        <span x-show="actualsLoading" x-cloak>Loading your data…</span>
                    </button>
                    <span class="text-[11px] text-emerald-400 font-semibold" x-show="actualsApplied" x-cloak data-mpc-actuals-applied>✓ Prefilled from your real usage — everything stays editable.</span>
                </div>
                <p class="text-[11px] text-amber-400 mt-2" x-show="actualsError" x-cloak x-text="actualsError" data-mpc-actuals-error></p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">AI credits usage (₹/month, optional)</label>
                        <input type="number" min="0" x-model.number="p.ai_credits" class="mpc-input mt-1">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">Est. monthly organic visitors (bio page, QR, short links)</label>
                        <input type="number" min="0" x-model.number="p.organic_visitors" class="mpc-input mt-1">
                    </div>
                </div>
                <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.plan_inputs" x-cloak x-text="clampHints.plan_inputs"></p>
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title">Sayzio toolset effectiveness uplifts</h3>
                <label class="flex items-center gap-2 mt-2 text-sm mpc-text cursor-pointer">
                    <input type="checkbox" x-model="p.uplifts.apply" class="rounded">
                    Apply CRM / Dialer / Chat Widget uplifts to projections
                </label>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">Chat widget uplift — visitor → lead (%)</label>
                        <input type="number" min="0" step="0.5" x-model.number="p.uplifts.chat" class="mpc-input mt-1">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">CRM &amp; dialer uplift — lead → customer (%)</label>
                        <input type="number" min="0" step="0.5" x-model.number="p.uplifts.crm" class="mpc-input mt-1">
                    </div>
                </div>
                {{-- Task #6772 — "already in use" badges from real feature usage. --}}
                <div class="flex flex-wrap gap-1.5 mt-2" x-show="actuals && actuals.features" x-cloak data-mpc-feature-badges>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                          :class="actuals?.features?.chat ? 'bg-emerald-500/15 text-emerald-400' : 'bg-white/5 text-white/40'"
                          x-text="actuals?.features?.chat ? '✓ Chat widget in use' : 'Chat widget not used yet'"></span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                          :class="actuals?.features?.crm ? 'bg-emerald-500/15 text-emerald-400' : 'bg-white/5 text-white/40'"
                          x-text="actuals?.features?.crm ? '✓ CRM in use' : 'CRM not used yet'"></span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                          :class="actuals?.features?.dialer ? 'bg-emerald-500/15 text-emerald-400' : 'bg-white/5 text-white/40'"
                          x-text="actuals?.features?.dialer ? '✓ Dialer in use' : 'Dialer not used yet'"></span>
                </div>
                <p class="text-[11px] mpc-faint mt-2">Illustrative defaults — replace with your own before/after data. Turn off for baseline, apples-to-apples projections.</p>
                <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.uplifts" x-cloak x-text="clampHints.uplifts"></p>
            </div>
        </div>

        {{-- Task #6768 — finance assumptions behind CAC / ROAS / LTV metrics. --}}
        <div class="rounded-2xl mpc-card p-4">
            <h3 class="text-sm font-bold mpc-title">Finance assumptions <span class="mpc-faint font-normal">(for CAC, ROAS &amp; LTV:CAC metrics)</span></h3>
            <div class="grid sm:grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="text-[11px] font-semibold mpc-sub">Gross margin (% of revenue kept as gross profit)</label>
                    <input type="number" min="0" max="100" step="1" x-model.number="p.gross_margin" class="mpc-input mt-1">
                </div>
                <div>
                    <label class="text-[11px] font-semibold mpc-sub">Customer lifetime / repeat-purchase multiplier (×)</label>
                    <input type="number" min="0" step="0.1" x-model.number="p.ltv_multiplier" class="mpc-input mt-1">
                </div>
            </div>
            <p class="text-[11px] mpc-faint mt-2">LTV = average customer value × lifetime multiplier × gross margin. These feed the LTV:CAC, break-even and payback metrics on the Dashboard tab.</p>
            <p class="text-[11px] text-amber-400 mt-1" x-show="clampHints.finance" x-cloak x-text="clampHints.finance"></p>
        </div>

        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold mpc-title">Channel assumptions <span class="mpc-faint font-normal">(money in ₹ — base currency)</span></h3>
                {{-- Task #6767 — one-click industry benchmark presets. --}}
                <div class="flex items-center gap-2">
                    <label for="mpcPresetPick" class="text-[11px] font-semibold mpc-sub whitespace-nowrap">Industry preset</label>
                    <select id="mpcPresetPick" x-model="presetPick" @change="onPresetPick()"
                            class="mpc-input !w-auto" :title="presets[presetPick]?.description || ''">
                        <option value="custom" disabled hidden>Custom</option>
                        <template x-for="(pr, k) in presets" :key="k">
                            <option :value="k" x-text="pr.label"></option>
                        </template>
                    </select>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-lg"
                      :class="allocOk ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'"
                      x-text="(allocOk ? '✓ Allocation ' : '⚠ Allocation ') + nf(allocTotal, 1) + '% ' + (allocOk ? '' : '— must total 100%')"></span>
            </div>
            <table class="w-full mt-2 min-w-[900px]">
                <thead><tr>
                    <th class="mpc-th">Channel</th><th class="mpc-th">Alloc %</th><th class="mpc-th">Cost / visitor (₹)</th>
                    <th class="mpc-th">Visitor → lead %</th><th class="mpc-th">Lead → customer %</th>
                    <th class="mpc-th">Avg customer value (₹)</th><th class="mpc-th">Notes</th>
                </tr></thead>
                <tbody>
                    <template x-for="(c, i) in p.channels" :key="c.key">
                        <tr class="mpc-row">
                            <td class="mpc-td font-semibold" x-text="c.name"></td>
                            <td class="mpc-td" style="width:90px;">
                                <template x-if="c.fixed"><span class="mpc-faint text-xs">Fixed cost</span></template>
                                <template x-if="!c.fixed"><input type="number" min="0" step="0.5" x-model.number="c.alloc" class="mpc-input !w-20"></template>
                            </td>
                            <td class="mpc-td" style="width:110px;">
                                <template x-if="c.fixed"><span class="mpc-faint text-xs">N/A</span></template>
                                <template x-if="!c.fixed"><input type="number" min="1" x-model.number="c.cpv" class="mpc-input !w-24"></template>
                            </td>
                            <td class="mpc-td" style="width:90px;"><input type="number" min="0" step="0.5" x-model.number="c.vl" class="mpc-input !w-20"></td>
                            <td class="mpc-td" style="width:90px;"><input type="number" min="0" step="0.5" x-model.number="c.lc" class="mpc-input !w-20"></td>
                            <td class="mpc-td" style="width:130px;"><input type="number" min="0" x-model.number="c.acv" class="mpc-input !w-28"></td>
                            <td class="mpc-td !whitespace-normal text-xs mpc-sub" style="min-width:220px;" x-text="c.notes"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.channels" x-cloak x-text="clampHints.channels"></p>
            <p class="text-[11px] mpc-faint mt-2">Sayzio's row is a fixed monthly subscription + optional AI credits and is excluded from the 100% total — the other 15 paid channels' allocations sum to 100% of the annual ad budget on their own.</p>
        </div>
    </div>

    {{-- ───── 2 · MONTHLY PLAN ───── --}}
    <div x-show="tab === 'monthly'" x-cloak class="space-y-4">
        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h3 class="text-sm font-bold mpc-title">12-month plan by channel</h3>
                <select x-model="monthlyMetric" class="mpc-input !w-auto">
                    <option value="spend">Spend</option>
                    <option value="visitors">Visitors</option>
                    <option value="leads">Leads</option>
                    <option value="customers">Customers</option>
                    <option value="revenue">Revenue</option>
                </select>
            </div>
            <table class="w-full min-w-[1000px]">
                <thead><tr>
                    <th class="mpc-th">Channel</th>
                    <template x-for="m in months" :key="m"><th class="mpc-th text-right" x-text="m"></th></template>
                    <th class="mpc-th text-right">Total</th>
                </tr></thead>
                <tbody>
                    <template x-for="row in model.channels" :key="row.key">
                        <tr class="mpc-row">
                            <td class="mpc-td font-semibold" x-text="row.name"></td>
                            <template x-for="(v, mi) in row[monthlyMetric]" :key="mi">
                                <td class="mpc-td text-right" x-text="cellFmt(v)"></td>
                            </template>
                            <td class="mpc-td text-right font-bold" x-text="cellFmt(sum(row[monthlyMetric]))"></td>
                        </tr>
                    </template>
                    <tr class="mpc-row">
                        <td class="mpc-td font-bold mpc-title">All channels</td>
                        <template x-for="(v, mi) in model.monthTotals[monthlyMetric]" :key="mi">
                            <td class="mpc-td text-right font-bold" x-text="cellFmt(v)"></td>
                        </template>
                        <td class="mpc-td text-right font-bold mpc-title" x-text="cellFmt(sum(model.monthTotals[monthlyMetric]))"></td>
                    </tr>
                </tbody>
            </table>
            <p class="text-[11px] mpc-faint mt-2">Spend → visitors → leads → customers → revenue, respecting your seasonality weights. Sayzio's spend is flat each month; its traffic comes from your organic-visitor estimate.</p>
        </div>
    </div>

    {{-- ───── 3 · DASHBOARD ───── --}}
    <div x-show="tab === 'dashboard'" x-cloak class="space-y-4">
        {{-- On-tab export controls (Task #6765) — no need to scroll back to the header --}}
        <div class="flex flex-wrap items-center justify-end gap-2">
            <span class="text-[11px] font-bold uppercase tracking-[0.12em] mpc-faint mr-auto">Export this plan</span>
            <button type="button" @click="exportXlsx()" class="mpc-export-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold">
                <i class="fas fa-file-excel text-emerald-400"></i> Excel
            </button>
            <button type="button" @click="exportCsv()" class="mpc-export-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold">
                <i class="fas fa-file-csv text-blue-400"></i> CSV
            </button>
            <button type="button" @click="exportPdf()" :disabled="pdfBusy" class="mpc-export-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-60">
                <i class="fas text-red-400" :class="pdfBusy ? 'fa-circle-notch fa-spin' : 'fa-file-pdf'"></i>
                <span x-text="pdfBusy ? 'Preparing…' : 'PDF'"></span>
            </button>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total annual budget</p><p class="mpc-kpi mt-1" x-text="money(p.annual_budget)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total projected revenue (year)</p><p class="mpc-kpi mt-1" x-text="money(model.totals.revenue)"></p></div>
            <div class="rounded-2xl mpc-card p-4" title="Return on ad spend — projected revenue divided by total spend. Every ₹1 spent brings in this much revenue."><p class="text-xs mpc-sub">Blended ROAS <i class="fas fa-circle-info mpc-faint text-[10px]"></i></p><p class="mpc-kpi mt-1" x-text="nf(model.totals.roas, 2) + '×'"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total customers acquired (year)</p><p class="mpc-kpi mt-1" x-text="nf(model.totals.customers, 0)"></p></div>
            <div class="rounded-2xl mpc-card p-4" title="Customer acquisition cost — total spend divided by customers acquired. What you pay, on average, to win one customer."><p class="text-xs mpc-sub">Blended CAC <i class="fas fa-circle-info mpc-faint text-[10px]"></i></p><p class="mpc-kpi mt-1" x-text="money(model.totals.cac)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Blended ROI</p><p class="mpc-kpi mt-1" x-text="nf(model.totals.roi * 100, 0) + '%'"></p></div>
        </div>

        {{-- Task #6768 — finance metrics strip. --}}
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="rounded-2xl mpc-card p-4" title="Lifetime value vs acquisition cost — how much gross profit a customer generates over their lifetime for every ₹1 spent acquiring them. 3× or more is healthy; below 1× loses money.">
                <p class="text-xs mpc-sub">LTV : CAC <i class="fas fa-circle-info mpc-faint text-[10px]"></i></p>
                <p class="mpc-kpi mt-1" :class="ltvCacClass(model.totals.ltvCac)" x-text="ratio(model.totals.ltvCac)"></p>
            </div>
            <div class="rounded-2xl mpc-card p-4" title="The first month where cumulative gross profit (revenue × gross margin) covers cumulative spend.">
                <p class="text-xs mpc-sub">Break-even month <i class="fas fa-circle-info mpc-faint text-[10px]"></i></p>
                <p class="mpc-kpi mt-1" x-text="breakEvenLabel"></p>
            </div>
            <div class="rounded-2xl mpc-card p-4" title="How many months of average gross profit it takes to earn back the year's total spend.">
                <p class="text-xs mpc-sub">Payback period <i class="fas fa-circle-info mpc-faint text-[10px]"></i></p>
                <p class="mpc-kpi mt-1" x-text="paybackLabel"></p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="rounded-2xl mpc-card p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="text-sm font-bold mpc-title">Spend vs revenue by month</h3>
                    <button type="button" @click="downloadChart('month')" title="Download chart as image"
                            class="mpc-export-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold">
                        <i class="fas fa-image"></i> PNG
                    </button>
                </div>
                <canvas id="mpcMonthChart" height="220"></canvas>
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="text-sm font-bold mpc-title">Annual revenue by channel</h3>
                    <button type="button" @click="downloadChart('channel')" title="Download chart as image"
                            class="mpc-export-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold">
                        <i class="fas fa-image"></i> PNG
                    </button>
                </div>
                <canvas id="mpcChannelChart" height="220"></canvas>
            </div>
        </div>

        {{-- Task #6772 — Plan vs. Actual (real workspace usage). --}}
        <div class="rounded-2xl mpc-card p-4" data-mpc-plan-vs-actual>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h3 class="text-sm font-bold mpc-title">Plan vs. Actual <span class="mpc-faint font-normal">(your real Sayzio traffic &amp; leads, last 12 months)</span></h3>
                <button type="button" x-show="!actuals" @click="loadActuals()" :disabled="actualsLoading"
                        class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold disabled:opacity-50"
                        data-mpc-load-actuals>
                    <span x-show="!actualsLoading">Load my Sayzio data</span>
                    <span x-show="actualsLoading" x-cloak>Loading…</span>
                </button>
            </div>
            <p class="text-[11px] text-amber-400" x-show="actualsError && tab === 'dashboard'" x-cloak x-text="actualsError"></p>
            <template x-if="actuals && !actuals.has_data && !actuals.error">
                <p class="text-[12px] mpc-faint" data-mpc-actuals-empty>No usage data in this workspace yet — once your links, forms and store start collecting activity, your real numbers appear here next to the plan.</p>
            </template>
            <div x-show="actuals && actuals.has_data" x-cloak>
                <canvas id="mpcActualChart" height="220"></canvas>
                <p class="text-[11px] mpc-faint mt-2">Solid bars = your actual monthly visitors &amp; leads; lines = this plan's projection. Revenue actuals appear in the tooltip.</p>
            </div>
        </div>

        {{-- Task #6769 — side-by-side scenario comparison. --}}
        <div class="rounded-2xl mpc-card p-4 overflow-x-auto" data-mpc-comparison>
            <h3 class="text-sm font-bold mpc-title mb-2">Scenario comparison <span class="mpc-faint font-normal">(annual outcomes, all three at once)</span></h3>
            <table class="w-full min-w-[560px]">
                <thead><tr>
                    <th class="mpc-th">Metric</th>
                    <template x-for="s in comparison" :key="s.key">
                        <th class="mpc-th text-right" :class="scenario === s.key ? 'text-blue-400' : ''">
                            <span x-text="s.label"></span>
                            <span x-show="scenario === s.key" x-cloak class="ml-1 normal-case tracking-normal">· viewing</span>
                        </th>
                    </template>
                </tr></thead>
                <tbody>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">Annual spend</td>
                        <template x-for="s in comparison" :key="'sp' + s.key"><td class="mpc-td text-right" x-text="money(s.totals.spend)"></td></template></tr>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">Annual revenue</td>
                        <template x-for="s in comparison" :key="'rv' + s.key"><td class="mpc-td text-right" x-text="money(s.totals.revenue)"></td></template></tr>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">Customers acquired</td>
                        <template x-for="s in comparison" :key="'cu' + s.key"><td class="mpc-td text-right" x-text="nf(s.totals.customers, 0)"></td></template></tr>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">Blended ROAS</td>
                        <template x-for="s in comparison" :key="'ro' + s.key"><td class="mpc-td text-right" x-text="nf(s.totals.roas, 2) + '×'"></td></template></tr>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">Blended ROI</td>
                        <template x-for="s in comparison" :key="'ri' + s.key"><td class="mpc-td text-right" x-text="nf(s.totals.roi * 100, 0) + '%'"></td></template></tr>
                    <tr class="mpc-row"><td class="mpc-td font-semibold">LTV : CAC</td>
                        <template x-for="s in comparison" :key="'lc' + s.key"><td class="mpc-td text-right" :class="ltvCacClass(s.totals.ltvCac)" x-text="ratio(s.totals.ltvCac)"></td></template></tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <h3 class="text-sm font-bold mpc-title mb-2">Channel summary (annual)</h3>
            <table class="w-full min-w-[720px]">
                <thead><tr>
                    <th class="mpc-th">Channel</th><th class="mpc-th text-right">Annual spend</th><th class="mpc-th text-right">Annual revenue</th>
                    <th class="mpc-th text-right">Customers</th><th class="mpc-th text-right">CAC</th>
                    <th class="mpc-th text-right" title="Return on ad spend — revenue ÷ spend">ROAS</th>
                    <th class="mpc-th text-right" title="Lifetime gross profit per customer vs cost to acquire one — ≥3× healthy, <1× losing money">LTV:CAC</th>
                    <th class="mpc-th text-right">ROI %</th>
                </tr></thead>
                <tbody>
                    <template x-for="row in model.channels" :key="row.key">
                        <tr class="mpc-row">
                            <td class="mpc-td font-semibold" x-text="row.name"></td>
                            <td class="mpc-td text-right" x-text="money(sum(row.spend))"></td>
                            <td class="mpc-td text-right" x-text="money(sum(row.revenue))"></td>
                            <td class="mpc-td text-right" x-text="nf(sum(row.customers), 0)"></td>
                            <td class="mpc-td text-right" x-text="row.metrics.cac !== null ? money(row.metrics.cac) : '—'"></td>
                            <td class="mpc-td text-right" x-text="ratio(row.metrics.roas, 2)"></td>
                            <td class="mpc-td text-right font-semibold" :class="ltvCacClass(row.metrics.ltvCac)" x-text="ratio(row.metrics.ltvCac)"></td>
                            <td class="mpc-td text-right" x-text="sum(row.spend) > 0 ? nf((sum(row.revenue) - sum(row.spend)) / sum(row.spend) * 100, 0) + '%' : '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ───── 4 · SAYZIO ROI & VALUE ───── --}}
    <div x-show="tab === 'roi'" x-cloak class="space-y-4">
        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <h3 class="text-sm font-bold mpc-title">If you didn't use Sayzio — what you'd need instead</h3>
            @if(!empty($toolsLocked))
                <p class="text-[11px] mpc-sub mt-1" data-mpc-tools-locked><i class="fas fa-lock mr-1"></i>Monthly costs are set by Sayzio and can't be edited.</p>
            @endif
            <table class="w-full mt-2 min-w-[680px]">
                <thead><tr>
                    <th class="mpc-th">Sayzio feature</th><th class="mpc-th">Example standalone tool</th>
                    <th class="mpc-th">Est. monthly cost (₹)</th><th class="mpc-th">Notes</th>
                </tr></thead>
                <tbody>
                    <template x-for="(t, i) in p.tools" :key="i">
                        <tr class="mpc-row">
                            <td class="mpc-td font-semibold" x-text="t.feature"></td>
                            <td class="mpc-td mpc-sub" x-text="t.example"></td>
                            <td class="mpc-td" style="width:130px;">
                                @if(!empty($toolsLocked))
                                    <input type="number" class="mpc-input !w-28 opacity-60 cursor-not-allowed" :value="t.cost" readonly tabindex="-1" data-mpc-tool-cost-locked>
                                @else
                                    <input type="number" min="0" x-model.number="t.cost" class="mpc-input !w-28" data-mpc-tool-cost>
                                @endif
                            </td>
                            <td class="mpc-td !whitespace-normal text-xs mpc-sub" x-text="t.notes"></td>
                        </tr>
                    </template>
                    <tr class="mpc-row">
                        <td class="mpc-td font-bold mpc-title" colspan="2">TOTAL — estimated monthly cost without Sayzio</td>
                        <td class="mpc-td font-bold mpc-title" x-text="money(roi.toolsMonthly)"></td><td></td>
                    </tr>
                    <tr class="mpc-row">
                        <td class="mpc-td" colspan="2">Your current Sayzio monthly subscription cost (<span x-text="selectedPlan.name"></span>)</td>
                        <td class="mpc-td" x-text="money(roi.subMonthly)"></td><td></td>
                    </tr>
                    <tr class="mpc-row">
                        <td class="mpc-td" colspan="2">Extra monthly spend without Sayzio</td>
                        <td class="mpc-td font-semibold text-emerald-400" x-text="money(roi.extraMonthly)"></td><td></td>
                    </tr>
                    <tr class="mpc-row">
                        <td class="mpc-td" colspan="2">Extra annual spend without Sayzio</td>
                        <td class="mpc-td font-semibold text-emerald-400" x-text="money(roi.extraAnnual)"></td><td></td>
                    </tr>
                </tbody>
            </table>
            <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.tools" x-cloak x-text="clampHints.tools"></p>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title">Time you save by consolidating into Sayzio</h3>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">Est. hours saved per tool per month</label>
                        <input type="number" min="0" step="0.5" x-model.number="p.hours_per_tool" class="mpc-input mt-1">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold mpc-sub">Value of your time (₹/hour)</label>
                        <input type="number" min="0" x-model.number="p.time_value" class="mpc-input mt-1">
                    </div>
                </div>
                <p class="text-[11px] text-amber-400 mt-2" x-show="clampHints.time" x-cloak x-text="clampHints.time"></p>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="mpc-sub">Standalone tools replaced</dt><dd class="mpc-text font-semibold" x-text="p.tools.length"></dd></div>
                    <div class="flex justify-between"><dt class="mpc-sub">Total hours saved / month</dt><dd class="mpc-text font-semibold" x-text="nf(roi.hoursMonthly, 1) + ' h'"></dd></div>
                    <div class="flex justify-between"><dt class="mpc-sub">Monthly value of time saved</dt><dd class="mpc-text font-semibold" x-text="money(roi.timeMonthly)"></dd></div>
                    <div class="flex justify-between"><dt class="mpc-sub">Annual value of time saved</dt><dd class="text-emerald-400 font-semibold" x-text="money(roi.timeAnnual)"></dd></div>
                </dl>
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title">Sayzio effectiveness — revenue impact</h3>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="mpc-sub">Baseline annual revenue (no CRM/dialer/chat uplift)</dt><dd class="mpc-text font-semibold" x-text="money(roi.baselineRevenue)"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="mpc-sub">Annual revenue with current uplift setting</dt><dd class="mpc-text font-semibold" x-text="money(model.totals.revenue)"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="mpc-sub">Additional revenue from Sayzio effectiveness</dt><dd class="text-emerald-400 font-semibold" x-text="money(roi.upliftRevenue)"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="mpc-sub">Effectiveness uplift on revenue</dt><dd class="mpc-text font-semibold" x-text="nf(roi.upliftPct * 100, 1) + '%'"></dd></div>
                </dl>
                <p class="text-[11px] mpc-faint mt-3">Reflects the "Apply Sayzio toolset uplifts" setting on the Assumptions tab — turn it on to see the full effectiveness value.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-500/30 bg-blue-500/10 p-5">
            <h3 class="text-sm font-bold text-blue-300 uppercase tracking-wide">Total value of using Sayzio (year)</h3>
            <div class="grid sm:grid-cols-3 gap-4 mt-3">
                <div><p class="text-xs mpc-sub">Money saved on tools</p><p class="text-lg font-bold mpc-title" x-text="money(roi.extraAnnual)"></p></div>
                <div><p class="text-xs mpc-sub">Money saved via time</p><p class="text-lg font-bold mpc-title" x-text="money(roi.timeAnnual)"></p></div>
                <div><p class="text-xs mpc-sub">Effectiveness revenue</p><p class="text-lg font-bold mpc-title" x-text="money(roi.upliftRevenue)"></p></div>
            </div>
            <p class="mt-4 text-2xl font-extrabold text-blue-400" x-text="money(roi.totalValue)"></p>
            <p class="text-[11px] mpc-faint mt-1">Tangible savings (tools + time) + additional effectiveness revenue.</p>
        </div>
    </div>
</div>

<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/vendor/xlsx.mini.min.js') }}" defer></script>
<script src="{{ asset('js/vendor/html2canvas.min.js') }}" defer></script>
<script>
function mpcApp() {
    return {
        planId: @js($plan?->id),
        name: @js($plan?->name ?? ($seedName ?? 'My Marketing Plan')),
        p: @js($payload),
        planOptions: @js($planOptions),
        presets: @js($presets ?? []),
        presetPick: 'custom',
        months: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        steps: [
            { key: 'assumptions', label: 'Assumptions' },
            { key: 'monthly',     label: 'Monthly Plan' },
            { key: 'dashboard',   label: 'Dashboard' },
            { key: 'roi',         label: 'Sayzio ROI & Value' },
        ],
        tab: 'assumptions',
        monthlyMetric: 'spend',
        // Task #6772 — real Sayzio usage (fetched on demand, never persisted).
        actuals: null,
        actualsLoading: false,
        actualsError: '',
        actualsApplied: false,
        // Task #6769 — active scenario (view state, not persisted).
        scenario: 'expected',
        scenKeys: ['conservative', 'expected', 'aggressive'],
        scenLabels: { conservative: 'Conservative', expected: 'Expected', aggressive: 'Aggressive' },
        saving: false, savedFlash: false, saveError: '', pdfBusy: false,
        charts: {},
        dirty: false, _baseline: '',
        clampHints: {},
        _hintTimers: {},

        init() {
            // Ensure array shapes survive older/partial payloads.
            if (!Array.isArray(this.p.weights) || this.p.weights.length !== 12) this.p.weights = Array(12).fill(1);
            if (!this.p.uplifts) this.p.uplifts = { apply: true, chat: 8, crm: 15 };
            // The plan selector only lists public, active plans. If the stored
            // slug points at a plan that is no longer offered (internal /
            // archived / unpublished), fall back to the first public option so
            // the select never shows an empty/ghost value. Runs BEFORE the
            // dirty baseline so the silent self-heal isn't an "unsaved edit".
            if (this.planOptions.length && !this.planOptions.some(o => o.slug === this.p.plan_slug)) {
                this.p.plan_slug = this.planOptions[0].slug;
            }
            // Task #6768 — finance assumptions merged safely into old payloads.
            if (typeof this.p.gross_margin !== 'number' || !isFinite(this.p.gross_margin)) this.p.gross_margin = 60;
            if (typeof this.p.ltv_multiplier !== 'number' || !isFinite(this.p.ltv_multiplier)) this.p.ltv_multiplier = 1.5;
            // Task #6769 — scenario multipliers merged safely into old payloads
            // (plans saved before scenarios existed open with the defaults).
            const scenDef = @js(\App\Services\MarketingPlanDefaults::SCENARIO_DEFAULTS);
            if (!this.p.scenarios || typeof this.p.scenarios !== 'object' || Array.isArray(this.p.scenarios)) this.p.scenarios = {};
            for (const k of Object.keys(scenDef)) {
                const cur = (this.p.scenarios[k] && typeof this.p.scenarios[k] === 'object') ? this.p.scenarios[k] : {};
                const merged = { ...scenDef[k] };
                for (const f of Object.keys(scenDef[k])) {
                    if (typeof cur[f] === 'number' && isFinite(cur[f])) merged[f] = cur[f];
                }
                this.p.scenarios[k] = merged;
            }
            // Task #6742 — keep every numeric input in a sane range so the
            // engine can never produce Infinity-adjacent projections.
            // Run before the dirty baseline so a silent self-heal of an old
            // saved plan doesn't count as an unsaved edit.
            this.applyClamps(false);

            // Task #6767 — reflect the saved preset in the picker; unknown /
            // pre-preset payloads read as "Custom".
            this.presetPick = this.presets[this.p.industry_preset] ? this.p.industry_preset : 'custom';

            // ----- unsaved-changes guard -----
            this._baseline = this.snapshot();
            this.$watch('p', () => { this.applyClamps(true); this.recomputeDirty(); });
            this.$watch('name', () => this.recomputeDirty());
            window.addEventListener('beforeunload', (e) => {
                if (!this.dirty) return;
                e.preventDefault();
                e.returnValue = ''; // legacy browsers need a value to show the prompt
            });
            // In-app nav guard: confirm before following any link while dirty.
            document.addEventListener('click', (e) => {
                if (!this.dirty) return;
                const a = e.target.closest('a[href]');
                if (!a) return;
                const href = a.getAttribute('href') || '';
                if (href.startsWith('#') || a.target === '_blank' || a.hasAttribute('download')) return;
                if (!window.confirm('You have unsaved changes to this plan. Leave without saving?')) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);
        },

        // ---------- input clamping (Task #6742) ----------
        flagClamp(key, msg) {
            this.clampHints[key] = msg;
            clearTimeout(this._hintTimers[key]);
            this._hintTimers[key] = setTimeout(() => { this.clampHints[key] = ''; }, 5000);
        },
        clampNum(obj, prop, min, max) {
            const v = obj?.[prop];
            if (typeof v !== 'number' || !isFinite(v)) return false;   // blank/partial input — leave alone
            const c = Math.min(max, Math.max(min, v));
            if (c === v) return false;
            obj[prop] = c;
            return true;
        },
        applyClamps(hint = true) {
            const BIG = 1e12;
            const flag = (key, msg) => { if (hint) this.flagClamp(key, msg); };
            if (this.clampNum(this.p, 'usd_inr_rate', 1, 100000)) flag('usd_inr_rate', 'Adjusted — the USD → INR rate must be at least 1.');
            if (this.clampNum(this.p, 'annual_budget', 0, BIG)) flag('annual_budget', 'Adjusted — the budget cannot be negative.');
            if ([this.clampNum(this.p, 'ai_credits', 0, BIG), this.clampNum(this.p, 'organic_visitors', 0, BIG)].some(Boolean))
                flag('plan_inputs', 'Adjusted — costs and visitor counts cannot be negative.');
            let w = false;
            for (let i = 0; i < (this.p.weights || []).length; i++) w = this.clampNum(this.p.weights, i, 0, 100) || w;
            if (w) flag('weights', 'Adjusted — seasonality weights stay between 0 and 100.');
            if ([this.clampNum(this.p.uplifts, 'chat', 0, 100), this.clampNum(this.p.uplifts, 'crm', 0, 100)].some(Boolean))
                flag('uplifts', 'Adjusted — uplift percentages stay between 0 and 100.');
            if ([this.clampNum(this.p, 'gross_margin', 0, 100), this.clampNum(this.p, 'ltv_multiplier', 0, 1000)].some(Boolean))
                flag('finance', 'Adjusted — gross margin stays between 0 and 100%, the LTV multiplier cannot be negative.');
            // Task #6769 — scenario multipliers stay in sane percentage bounds
            // (CPV must stay above 0 — it divides spend).
            let sc = false;
            for (const k of Object.keys(this.p.scenarios || {})) {
                const s = this.p.scenarios[k];
                if (!s || typeof s !== 'object') continue;
                sc = this.clampNum(s, 'cpv', 1, 1000) || sc;
                sc = this.clampNum(s, 'vl', 0, 1000) || sc;
                sc = this.clampNum(s, 'lc', 0, 1000) || sc;
                sc = this.clampNum(s, 'budget', 0, 1000) || sc;
            }
            if (sc) flag('scenarios', 'Adjusted — scenario multipliers stay between 0 and 1000% (cost per visitor at least 1%).');
            let ch = false;
            for (const c of this.p.channels || []) {
                ch = this.clampNum(c, 'alloc', 0, 100) || ch;
                ch = this.clampNum(c, 'cpv', 0, BIG) || ch;
                ch = this.clampNum(c, 'vl', 0, 100) || ch;
                ch = this.clampNum(c, 'lc', 0, 100) || ch;
                ch = this.clampNum(c, 'acv', 0, BIG) || ch;
            }
            if (ch) flag('channels', 'Adjusted — allocations and conversion rates stay between 0 and 100%, costs cannot be negative.');
            let t = false;
            for (const tool of this.p.tools || []) t = this.clampNum(tool, 'cost', 0, BIG) || t;
            if (t) flag('tools', 'Adjusted — tool costs cannot be negative.');
            if ([this.clampNum(this.p, 'hours_per_tool', 0, BIG), this.clampNum(this.p, 'time_value', 0, BIG)].some(Boolean))
                flag('time', 'Adjusted — hours and time value cannot be negative.');
        },
        snapshot() { return JSON.stringify({ name: this.name, p: this.p }); },
        get stepIndex() { return this.steps.findIndex(s => s.key === this.tab); },
        recomputeDirty() { this.dirty = this.snapshot() !== this._baseline; },

        // ---------- industry presets (Task #6767) ----------
        get presetLabel() { return this.presets[this.p.industry_preset]?.label || 'Custom'; },
        onPresetPick() {
            const key = this.presetPick;
            if (key === 'custom' || !this.presets[key] || key === this.p.industry_preset) return;
            // Overwrites a plan someone has worked on → confirm first. A
            // fresh, untouched new plan applies silently.
            if ((this.planId || this.dirty)
                && !window.confirm('Apply the "' + this.presets[key].label + '" preset?\n\nThis overwrites the whole channel-assumptions table (allocations, cost per visitor, conversion rates, customer values and notes) with the preset\'s benchmarks. Your budget, seasonality and other inputs are kept. Everything stays editable afterwards.')) {
                this.presetPick = this.presets[this.p.industry_preset] ? this.p.industry_preset : 'custom';
                return;
            }
            this.applyPreset(key);
        },
        applyPreset(key) {
            this.p.channels = JSON.parse(JSON.stringify(this.presets[key].channels));
            this.p.industry_preset = key;
        },

        // ---------- helpers ----------
        n(v) { const x = parseFloat(v); return isFinite(x) ? x : 0; },
        sum(arr) { return (arr || []).reduce((a, b) => a + this.n(b), 0); },
        nf(v, d = 0) { return this.n(v).toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d }); },
        get curMult() { return this.p.display_currency === 'USD' ? 1 / Math.max(1e-9, this.n(this.p.usd_inr_rate)) : 1; },
        money(vInr) {
            const v = this.n(vInr) * this.curMult;
            return (this.p.display_currency === 'USD' ? '$' : '₹') + this.nf(v, v !== 0 && Math.abs(v) < 100 ? 2 : 0);
        },
        cellFmt(v) { return this.monthlyMetric === 'spend' || this.monthlyMetric === 'revenue' ? this.money(v) : this.nf(v, 0); },

        // ---------- finance-metric display helpers (Task #6768) ----------
        ratio(v, d = 1) { return v === null || !isFinite(v) ? '—' : this.nf(v, d) + '×'; },
        // Traffic-light class for LTV:CAC — ≥3 healthy, 1–3 borderline, <1 losing money.
        ltvCacClass(v) {
            if (v === null || !isFinite(v)) return 'mpc-faint';
            return v >= 3 ? 'text-emerald-400' : (v >= 1 ? 'text-amber-400' : 'text-red-400');
        },
        get breakEvenLabel() {
            const m = this.model.totals.breakEvenMonth;
            return m === null ? 'Beyond 12 mo' : this.months[m];
        },
        get paybackLabel() {
            const v = this.model.totals.paybackMonths;
            return v === null ? '—' : this.nf(v, 1) + ' mo';
        },

        get selectedPlan() {
            return this.planOptions.find(o => o.slug === this.p.plan_slug) || { name: '—', inr: 0, usd: 0 };
        },
        get allocTotal() {
            return this.sum(this.p.channels.filter(c => !c.fixed).map(c => c.alloc));
        },
        get allocOk() { return Math.abs(this.allocTotal - 100) < 0.05; },

        // ---------- scenarios (Task #6769) ----------
        /**
         * Multiplier factors (fractions of 1) for a scenario. "Expected" is
         * always the saved base — no multipliers. CPV is floored above 0
         * because it divides spend.
         */
        scenFactors(key) {
            if (key === 'expected') return { cpv: 1, vl: 1, lc: 1, budget: 1 };
            const s = (this.p.scenarios || {})[key] || {};
            return {
                cpv:    Math.max(0.01, this.n(s.cpv) > 0 ? this.n(s.cpv) / 100 : 1),
                vl:     Math.max(0, typeof s.vl === 'number' && isFinite(s.vl) ? s.vl / 100 : 1),
                lc:     Math.max(0, typeof s.lc === 'number' && isFinite(s.lc) ? s.lc / 100 : 1),
                budget: Math.max(0, typeof s.budget === 'number' && isFinite(s.budget) ? s.budget / 100 : 1),
            };
        },
        setScenario(key) {
            if (!this.scenKeys.includes(key)) return;
            this.scenario = key;
            if (this.tab === 'dashboard') this.$nextTick(() => this.renderCharts());
        },
        /** Annual totals for all three scenarios side-by-side. */
        get comparison() {
            return this.scenKeys.map(key => ({
                key,
                label: this.scenLabels[key],
                totals: this.buildModel(this.scenFactors(key)).totals,
            }));
        },

        // ---------- real Sayzio data (Task #6772) ----------
        async loadActuals() {
            if (this.actualsLoading) return this.actuals;
            this.actualsLoading = true; this.actualsError = '';
            try {
                const res = await fetch('{{ route('user.marketing-plan.actuals') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok || data.actuals?.error) throw new Error('Could not load your Sayzio data. Please try again.');
                this.actuals = data.actuals;
                if (this.tab === 'dashboard') this.$nextTick(() => this.renderCharts());
            } catch (e) {
                this.actualsError = e.message || 'Could not load your Sayzio data. Please try again.';
            } finally {
                this.actualsLoading = false;
            }
            return this.actuals;
        },
        /** Prefill the editable assumptions from the fetched actuals. */
        async applyActuals() {
            const a = this.actuals || await this.loadActuals();
            if (!a) return;
            if (!a.has_data) { this.actualsError = 'No usage data in this workspace yet — the defaults are your best starting point.'; return; }
            if (a.monthly_visitors > 0) this.p.organic_visitors = a.monthly_visitors;
            if (a.ai_coins_30d > 0) this.p.ai_credits = a.ai_coins_30d;
            if (a.plan_slug && this.planOptions.some(o => o.slug === a.plan_slug)) this.p.plan_slug = a.plan_slug;
            if (a.vl_rate !== null) {
                const row = this.p.channels.find(c => c.key === 'sayzio');
                if (row) row.vl = a.vl_rate;
            }
            this.actualsApplied = true;
            setTimeout(() => this.actualsApplied = false, 3500);
        },

        // ---------- the calculation engine ----------
        get model() { return this.buildModel(this.scenFactors(this.scenario)); },

        buildModel(sf) {
            const W = Math.max(1e-9, this.sum(this.p.weights));
            const vlMult = (this.p.uplifts.apply ? 1 + this.n(this.p.uplifts.chat) / 100 : 1) * sf.vl;
            const lcMult = (this.p.uplifts.apply ? 1 + this.n(this.p.uplifts.crm) / 100 : 1) * sf.lc;
            const subInr = this.n(this.selectedPlan.inr);
            const budget = this.n(this.p.annual_budget) * sf.budget;
            // Task #6768 — finance assumptions for CAC/ROAS/LTV metrics.
            const margin  = Math.min(100, Math.max(0, this.n(this.p.gross_margin))) / 100;
            const ltvMult = Math.max(0, this.n(this.p.ltv_multiplier));

            const channels = this.p.channels.map(c => {
                const spend = [], visitors = [], leads = [], customers = [], revenue = [];
                for (let m = 0; m < 12; m++) {
                    const w = this.n(this.p.weights[m]);
                    let sp, vis;
                    if (c.fixed) {
                        sp  = subInr + this.n(this.p.ai_credits);              // flat every month
                        vis = this.n(this.p.organic_visitors) * w / (W / 12);  // organic, seasonal
                    } else {
                        sp  = budget * this.n(c.alloc) / 100 * w / W;
                        vis = this.n(c.cpv) > 0 ? sp / (this.n(c.cpv) * sf.cpv) : 0;
                    }
                    // Effective conversion never exceeds 100% even under
                    // aggressive scenario multipliers + uplifts (Task #6769).
                    const ld = Math.min(vis, vis * this.n(c.vl) / 100 * vlMult);
                    const cu = Math.min(ld, ld * this.n(c.lc) / 100 * lcMult);
                    spend.push(sp); visitors.push(vis); leads.push(ld);
                    customers.push(cu); revenue.push(cu * this.n(c.acv));
                }
                // Task #6768 — per-channel annual finance metrics.
                const aSp = this.sum(spend), aRev = this.sum(revenue), aCu = this.sum(customers);
                const cac  = aCu > 0 ? aSp / aCu : null;
                const roas = aSp > 0 ? aRev / aSp : null;
                const ltv  = aCu > 0 ? (aRev / aCu) * ltvMult * margin : null;
                const metrics = {
                    cac, roas, ltv,
                    ltvCac: (cac !== null && cac > 0 && ltv !== null) ? ltv / cac : null,
                };
                return { key: c.key, name: c.name, spend, visitors, leads, customers, revenue, metrics };
            });

            const monthTotals = { spend: [], visitors: [], leads: [], customers: [], revenue: [] };
            for (let m = 0; m < 12; m++) {
                for (const k of Object.keys(monthTotals)) {
                    monthTotals[k].push(channels.reduce((a, row) => a + row[k][m], 0));
                }
            }

            const spend = this.sum(monthTotals.spend), revenue = this.sum(monthTotals.revenue),
                  customers = this.sum(monthTotals.customers);

            // Task #6768 — blended finance metrics from the monthly cashflow.
            // Cumulative cashflow = gross profit (revenue × margin) minus spend.
            const cac = customers > 0 ? spend / customers : 0;
            const ltv = customers > 0 ? (revenue / customers) * ltvMult * margin : 0;
            let cum = 0, breakEvenMonth = null;
            for (let m = 0; m < 12; m++) {
                cum += monthTotals.revenue[m] * margin - monthTotals.spend[m];
                if (breakEvenMonth === null && cum >= 0) breakEvenMonth = m; // 0-based month index
            }
            const grossProfit = revenue * margin;
            // Months of blended spend recovered by average monthly gross profit.
            const paybackMonths = (spend > 0 && grossProfit > 0) ? spend / (grossProfit / 12) : null;

            return {
                channels, monthTotals, vlMult, lcMult,
                totals: {
                    spend, revenue, customers,
                    roas: spend > 0 ? revenue / spend : 0,
                    cac,
                    roi:  spend > 0 ? (revenue - spend) / spend : 0,
                    ltv,
                    ltvCac: cac > 0 ? ltv / cac : null,
                    breakEvenMonth,
                    paybackMonths,
                },
            };
        },

        get roi() {
            const m = this.model;
            const toolsMonthly = this.sum(this.p.tools.map(t => t.cost));
            const subMonthly = this.n(this.selectedPlan.inr);
            const extraMonthly = toolsMonthly - subMonthly;
            const hoursMonthly = this.p.tools.length * this.n(this.p.hours_per_tool);
            const timeMonthly = hoursMonthly * this.n(this.p.time_value);
            const baselineRevenue = m.totals.revenue / (m.vlMult * m.lcMult);
            const upliftRevenue = m.totals.revenue - baselineRevenue;
            return {
                toolsMonthly, subMonthly, extraMonthly, extraAnnual: extraMonthly * 12,
                hoursMonthly, timeMonthly, timeAnnual: timeMonthly * 12,
                baselineRevenue, upliftRevenue,
                upliftPct: baselineRevenue > 0 ? upliftRevenue / baselineRevenue : 0,
                totalValue: extraMonthly * 12 + timeMonthly * 12 + upliftRevenue,
            };
        },

        // ---------- export ----------
        // Rounds a display-currency money value for the spreadsheet cells.
        xm(vInr) { const v = this.n(vInr) * this.curMult; return Math.round(v * 100) / 100; },
        xn(v, d = 2) { const f = Math.pow(10, d); return Math.round(this.n(v) * f) / f; },

        /**
         * Builds the export as named sections of AOA rows. All money is in
         * the currently selected display currency (INR/USD toggle).
         */
        exportSections() {
            const cur = this.p.display_currency, sym = cur === 'USD' ? '$' : '₹';
            const m = this.model, r = this.roi;

            const assumptions = [
                ['Marketing Plan — Assumptions'],
                ['Plan name', this.name || 'My Marketing Plan'],
                ['Company / Product', this.p.company || ''],
                ['Display currency', cur],
                ['USD → INR rate', this.xn(this.p.usd_inr_rate)],
                ['Total annual ad-spend budget (' + sym + ')', this.xm(this.p.annual_budget)],
                ['Sayzio plan', this.selectedPlan.name],
                ['Sayzio subscription (' + sym + '/month)', this.xm(this.selectedPlan.inr)],
                ['AI credits usage (' + sym + '/month)', this.xm(this.p.ai_credits)],
                ['Est. monthly organic visitors', this.xn(this.p.organic_visitors, 0)],
                ['Active scenario', this.scenLabels[this.scenario]],
                ['Apply Sayzio toolset uplifts', this.p.uplifts.apply ? 'Yes' : 'No'],
                ['Chat widget uplift — visitor → lead (%)', this.xn(this.p.uplifts.chat, 1)],
                ['CRM & dialer uplift — lead → customer (%)', this.xn(this.p.uplifts.crm, 1)],
                ['Gross margin (%)', this.xn(this.p.gross_margin, 1)],
                ['Customer lifetime / repeat-purchase multiplier (×)', this.xn(this.p.ltv_multiplier)],
                [],
                ['Monthly seasonality weights'],
                ['Month', ...this.months],
                ['Weight', ...this.p.weights.map(w => this.xn(w, 2))],
                [],
                ['Channel assumptions'],
                ['Channel', 'Alloc %', 'Cost / visitor (' + sym + ')', 'Visitor → lead %', 'Lead → customer %', 'Avg customer value (' + sym + ')', 'Notes'],
                ...this.p.channels.map(c => [
                    c.name,
                    c.fixed ? 'Fixed cost' : this.xn(c.alloc, 1),
                    c.fixed ? 'N/A' : this.xm(c.cpv),
                    this.xn(c.vl, 1), this.xn(c.lc, 1), this.xm(c.acv), c.notes || '',
                ]),
            ];

            const monthly = [['Monthly plan — all metrics in ' + cur + ' where money']];
            for (const [metric, label] of [['spend', 'Spend (' + sym + ')'], ['visitors', 'Visitors'], ['leads', 'Leads'], ['customers', 'Customers'], ['revenue', 'Revenue (' + sym + ')']]) {
                const isMoney = metric === 'spend' || metric === 'revenue';
                const fmt = v => isMoney ? this.xm(v) : this.xn(v, 1);
                monthly.push([]);
                monthly.push([label]);
                monthly.push(['Channel', ...this.months, 'Total']);
                for (const row of m.channels) {
                    monthly.push([row.name, ...row[metric].map(fmt), fmt(this.sum(row[metric]))]);
                }
                monthly.push(['All channels', ...m.monthTotals[metric].map(fmt), fmt(this.sum(m.monthTotals[metric]))]);
            }

            const dashboard = [
                ['Dashboard — annual totals (' + cur + ')'],
                ['Total annual budget (' + sym + ')', this.xm(this.p.annual_budget)],
                ['Total projected spend (' + sym + ')', this.xm(m.totals.spend)],
                ['Total projected revenue (' + sym + ')', this.xm(m.totals.revenue)],
                ['Blended ROAS (×)', this.xn(m.totals.roas)],
                ['Total customers acquired', this.xn(m.totals.customers, 0)],
                ['Blended CAC (' + sym + ')', this.xm(m.totals.cac)],
                ['Blended ROI (%)', this.xn(m.totals.roi * 100, 1)],
                ['Blended LTV (' + sym + ', gross profit per customer)', this.xm(m.totals.ltv)],
                ['LTV : CAC (×)', m.totals.ltvCac !== null ? this.xn(m.totals.ltvCac) : '—'],
                ['Break-even month (cumulative cashflow)', m.totals.breakEvenMonth !== null ? this.months[m.totals.breakEvenMonth] : 'Beyond 12 months'],
                ['Payback period (months)', m.totals.paybackMonths !== null ? this.xn(m.totals.paybackMonths, 1) : '—'],
                [],
                ['Channel summary (annual)'],
                ['Channel', 'Annual spend (' + sym + ')', 'Annual revenue (' + sym + ')', 'Customers', 'CAC (' + sym + ')', 'ROAS (×)', 'LTV:CAC (×)', 'ROI %'],
                ...m.channels.map(row => {
                    const sp = this.sum(row.spend), rev = this.sum(row.revenue), cu = this.sum(row.customers);
                    return [row.name, this.xm(sp), this.xm(rev), this.xn(cu, 0),
                            row.metrics.cac !== null ? this.xm(row.metrics.cac) : '—',
                            row.metrics.roas !== null ? this.xn(row.metrics.roas) : '—',
                            row.metrics.ltvCac !== null ? this.xn(row.metrics.ltvCac) : '—',
                            sp > 0 ? this.xn((rev - sp) / sp * 100, 1) : '—'];
                }),
            ];

            const roiRows = [
                ['Sayzio ROI & value (' + cur + ')'],
                [],
                ['If you didn\'t use Sayzio — what you\'d need instead'],
                ['Sayzio feature', 'Example standalone tool', 'Est. monthly cost (' + sym + ')', 'Notes'],
                ...this.p.tools.map(t => [t.feature, t.example, this.xm(t.cost), t.notes || '']),
                ['TOTAL — estimated monthly cost without Sayzio', '', this.xm(r.toolsMonthly), ''],
                ['Sayzio monthly subscription (' + this.selectedPlan.name + ')', '', this.xm(r.subMonthly), ''],
                ['Extra monthly spend without Sayzio', '', this.xm(r.extraMonthly), ''],
                ['Extra annual spend without Sayzio', '', this.xm(r.extraAnnual), ''],
                [],
                ['Time saved by consolidating into Sayzio'],
                ['Standalone tools replaced', this.p.tools.length],
                ['Est. hours saved per tool per month', this.xn(this.p.hours_per_tool, 1)],
                ['Value of your time (' + sym + '/hour)', this.xm(this.p.time_value)],
                ['Total hours saved / month', this.xn(r.hoursMonthly, 1)],
                ['Monthly value of time saved (' + sym + ')', this.xm(r.timeMonthly)],
                ['Annual value of time saved (' + sym + ')', this.xm(r.timeAnnual)],
                [],
                ['Sayzio effectiveness — revenue impact'],
                ['Baseline annual revenue, no uplift (' + sym + ')', this.xm(r.baselineRevenue)],
                ['Annual revenue with current uplift setting (' + sym + ')', this.xm(m.totals.revenue)],
                ['Additional revenue from Sayzio effectiveness (' + sym + ')', this.xm(r.upliftRevenue)],
                ['Effectiveness uplift on revenue (%)', this.xn(r.upliftPct * 100, 1)],
                [],
                ['Total value of using Sayzio (year)'],
                ['Money saved on tools (' + sym + ')', this.xm(r.extraAnnual)],
                ['Money saved via time (' + sym + ')', this.xm(r.timeAnnual)],
                ['Effectiveness revenue (' + sym + ')', this.xm(r.upliftRevenue)],
                ['TOTAL VALUE (' + sym + ')', this.xm(r.totalValue)],
            ];

            // Task #6769 — scenario multipliers + side-by-side annual outcomes.
            const comp = this.comparison;
            const compRow = (label, fn) => [label, ...comp.map(fn)];
            const scenMult = (field) => compRow(
                { budget: 'Ad budget (% of base)', cpv: 'Cost per visitor (% of base)', vl: 'Visitor → lead (% of base)', lc: 'Lead → customer (% of base)' }[field],
                s => s.key === 'expected' ? 100 : this.xn(this.p.scenarios?.[s.key]?.[field], 0),
            );
            const scenarios = [
                ['Scenario comparison — Conservative / Expected / Aggressive'],
                ['Expected is the saved plan; the other scenarios apply the multipliers below to it.'],
                [],
                ['Multipliers', ...comp.map(s => s.label)],
                scenMult('budget'), scenMult('cpv'), scenMult('vl'), scenMult('lc'),
                [],
                ['Annual outcome (' + cur + ')', ...comp.map(s => s.label)],
                compRow('Annual spend (' + sym + ')', s => this.xm(s.totals.spend)),
                compRow('Annual revenue (' + sym + ')', s => this.xm(s.totals.revenue)),
                compRow('Customers acquired', s => this.xn(s.totals.customers, 0)),
                compRow('Blended ROAS (×)', s => this.xn(s.totals.roas)),
                compRow('Blended ROI (%)', s => this.xn(s.totals.roi * 100, 1)),
                compRow('LTV : CAC (×)', s => s.totals.ltvCac !== null ? this.xn(s.totals.ltvCac) : '—'),
            ];

            return [
                ['Assumptions', assumptions],
                ['Monthly Plan', monthly],
                ['Dashboard', dashboard],
                ['Scenarios', scenarios],
                ['Sayzio ROI', roiRows],
            ];
        },

        exportFileBase() {
            return (this.name || 'marketing-plan').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'marketing-plan';
        },

        exportXlsx() {
            if (typeof XLSX === 'undefined') { this.saveError = 'Excel export is still loading — try again in a moment.'; return; }
            const wb = XLSX.utils.book_new();
            for (const [title, rows] of this.exportSections()) {
                XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rows), title);
            }
            XLSX.writeFile(wb, this.exportFileBase() + '.xlsx');
        },

        exportCsv() {
            const esc = v => {
                const s = String(v ?? '');
                return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            };
            const parts = [];
            for (const [title, rows] of this.exportSections()) {
                parts.push('=== ' + title + ' ===');
                for (const row of rows) parts.push(row.map(esc).join(','));
                parts.push('');
            }
            const blob = new Blob(['\ufeff' + parts.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = this.exportFileBase() + '.csv';
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
        },

        // ---------- PDF one-pager export ----------
        /**
         * Renders a branded, presentation-ready one-pager (KPIs, both charts,
         * channel summary, Sayzio total-value story) into an offscreen node,
         * rasterizes it with html2canvas and wraps the JPEG in a minimal
         * single-page PDF — fully client-side, respecting the INR/USD toggle.
         */
        async exportPdf() {
            if (this.pdfBusy) return;
            if (typeof html2canvas === 'undefined' || typeof Chart === 'undefined') {
                this.saveError = 'PDF export is still loading — try again in a moment.';
                return;
            }
            this.pdfBusy = true; this.saveError = '';
            const host = document.createElement('div');
            host.style.cssText = 'position:fixed;left:-12000px;top:0;width:1120px;z-index:-1;';
            const savedColor = Chart.defaults.color, savedBorder = Chart.defaults.borderColor;
            let tmpCharts = [];
            try {
                host.innerHTML = this.pdfPageHtml();
                document.body.appendChild(host);

                // Render both charts into the offscreen page with light styling
                // (the PDF page is always white regardless of app theme).
                Chart.defaults.color = '#475569';
                Chart.defaults.borderColor = 'rgba(15,23,42,0.10)';
                tmpCharts = this.renderPdfCharts(host);
                await new Promise(r => setTimeout(r, 60)); // let canvases paint

                const canvas = await html2canvas(host.firstElementChild, {
                    scale: 2, backgroundColor: '#ffffff', logging: false,
                    width: 1120, windowWidth: 1120,
                });
                const jpeg = canvas.toDataURL('image/jpeg', 0.92);
                const blob = this.jpegToPdfBlob(jpeg, canvas.width, canvas.height);
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = this.exportFileBase() + '.pdf';
                document.body.appendChild(a);
                a.click();
                setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
            } catch (e) {
                this.saveError = 'Could not generate the PDF: ' + (e?.message || 'unknown error');
            } finally {
                Chart.defaults.color = savedColor;
                Chart.defaults.borderColor = savedBorder;
                for (const c of tmpCharts) { try { c.destroy(); } catch (_) {} }
                host.remove();
                this.pdfBusy = false;
            }
        },

        pdfEsc(v) {
            return String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        },

        // Task #6763 — the one-pager must stay readable even if a plan carries
        // many extra channels. Cap the PDF summary table: keep the biggest
        // channels and aggregate the tail into a single "Other" row.
        pdfMaxChannelRows: 16,

        pdfSummaryRows() {
            const rows = this.model.channels.map(row => ({
                name: row.name,
                spend: this.sum(row.spend),
                revenue: this.sum(row.revenue),
                customers: this.sum(row.customers),
            }));
            const max = this.pdfMaxChannelRows;
            if (rows.length <= max) return rows;
            const sorted = rows.slice().sort((a, b) => (b.revenue - a.revenue) || (b.spend - a.spend));
            const keep = sorted.slice(0, max - 1);
            const rest = sorted.slice(max - 1);
            const other = { name: `Other (${rest.length} channels)`, spend: 0, revenue: 0, customers: 0 };
            for (const r of rest) { other.spend += r.spend; other.revenue += r.revenue; other.customers += r.customers; }
            return [...keep, other];
        },

        pdfPageHtml() {
            const esc = v => this.pdfEsc(v);
            const m = this.model, r = this.roi;
            const cur = this.p.display_currency;
            const today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            const kpi = (label, value) => `
                <div style="flex:1;min-width:150px;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;background:#f8fafc;">
                    <p style="margin:0;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">${label}</p>
                    <p style="margin:4px 0 0;font-size:22px;font-weight:800;color:#0f172a;">${value}</p>
                </div>`;
            // Task #6768 — LTV inputs for the per-row finance metrics.
            const pdfMargin  = Math.min(100, Math.max(0, this.n(this.p.gross_margin))) / 100;
            const pdfLtvMult = Math.max(0, this.n(this.p.ltv_multiplier));
            const chanRows = this.pdfSummaryRows().map(row => {
                const sp = row.spend, rev = row.revenue, cu = row.customers;
                const cac = cu > 0 ? sp / cu : null;
                const ltvCac = (cac !== null && cac > 0) ? ((rev / cu) * pdfLtvMult * pdfMargin) / cac : null;
                const ltvColor = ltvCac === null ? '#94a3b8' : (ltvCac >= 3 ? '#059669' : (ltvCac >= 1 ? '#d97706' : '#dc2626'));
                return `<tr>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;font-weight:600;color:#0f172a;">${esc(row.name)}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.money(sp)}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.money(rev)}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.nf(cu, 0)}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${cu > 0 ? this.money(sp / cu) : '—'}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${sp > 0 ? this.nf(rev / sp, 2) + '×' : '—'}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;font-weight:600;color:${ltvColor};">${this.ratio(ltvCac)}</td>
                    <td style="padding:5px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${sp > 0 ? this.nf((rev - sp) / sp * 100, 0) + '%' : '—'}</td>
                </tr>`;
            }).join('');
            const th = t => `<th style="padding:5px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;text-align:right;">${t}</th>`;
            return `
            <div style="width:1120px;background:#ffffff;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;padding:36px 40px;box-sizing:border-box;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #2563eb;padding-bottom:14px;">
                    <div>
                        <p style="margin:0;font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#2563eb;">Sayzio · Marketing Plan</p>
                        <h1 style="margin:4px 0 0;font-size:26px;font-weight:800;color:#0f172a;">${esc(this.name || 'My Marketing Plan')}</h1>
                        ${this.p.company ? `<p style="margin:2px 0 0;font-size:13px;color:#475569;">${esc(this.p.company)}</p>` : ''}
                    </div>
                    <div style="text-align:right;font-size:11px;color:#64748b;">
                        <p style="margin:0;">${today}</p>
                        <p style="margin:2px 0 0;">All amounts in <b style="color:#0f172a;">${cur}</b></p>
                    </div>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
                    ${kpi('Total annual budget', this.money(this.p.annual_budget))}
                    ${kpi('Projected revenue (year)', this.money(m.totals.revenue))}
                    ${kpi('Blended ROAS', this.nf(m.totals.roas, 2) + '×')}
                    ${kpi('Customers acquired', this.nf(m.totals.customers, 0))}
                    ${kpi('Blended CAC', this.money(m.totals.cac))}
                    ${kpi('Blended ROI', this.nf(m.totals.roi * 100, 0) + '%')}
                    ${kpi('LTV : CAC', this.ratio(m.totals.ltvCac))}
                    ${kpi('Break-even month', this.breakEvenLabel)}
                    ${kpi('Payback period', this.paybackLabel)}
                </div>

                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;margin-top:14px;">
                    <h3 style="margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Scenario comparison (annual)</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:11px;">
                        <thead><tr>
                            <th style="padding:3px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;text-align:left;">Scenario</th>
                            ${['Spend', 'Revenue', 'Customers', 'ROAS', 'ROI'].map(t => `<th style="padding:3px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;text-align:right;">${t}</th>`).join('')}
                        </tr></thead>
                        <tbody>${this.comparison.map(s => `<tr>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;font-weight:${s.key === this.scenario ? 800 : 600};color:${s.key === this.scenario ? '#2563eb' : '#0f172a'};">${esc(s.label)}${s.key === this.scenario ? ' ·' : ''}</td>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.money(s.totals.spend)}</td>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.money(s.totals.revenue)}</td>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.nf(s.totals.customers, 0)}</td>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.nf(s.totals.roas, 2)}×</td>
                            <td style="padding:4px 8px;border-top:1px solid #e2e8f0;text-align:right;color:#334155;">${this.nf(s.totals.roi * 100, 0)}%</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>

                <div style="display:flex;gap:16px;margin-top:14px;">
                    <div style="flex:1.2;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
                        <h3 style="margin:0 0 8px;font-size:12px;font-weight:800;color:#0f172a;">Spend vs revenue by month</h3>
                        <canvas data-pdf-chart="month" width="580" height="260"></canvas>
                    </div>
                    <div style="flex:1;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
                        <h3 style="margin:0 0 8px;font-size:12px;font-weight:800;color:#0f172a;">Annual revenue by channel</h3>
                        <canvas data-pdf-chart="channel" width="440" height="260"></canvas>
                    </div>
                </div>

                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-top:18px;">
                    <h3 style="margin:0 0 4px;font-size:12px;font-weight:800;color:#0f172a;">Channel summary (annual)</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:11px;">
                        <thead><tr>
                            <th style="padding:5px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;text-align:left;">Channel</th>
                            ${th('Annual spend')}${th('Annual revenue')}${th('Customers')}${th('CAC')}${th('ROAS')}${th('LTV:CAC')}${th('ROI %')}
                        </tr></thead>
                        <tbody>${chanRows}</tbody>
                    </table>
                </div>

                <div style="border:1px solid #bfdbfe;background:#eff6ff;border-radius:14px;padding:18px;margin-top:18px;">
                    <h3 style="margin:0;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#1d4ed8;">Total value of using Sayzio (year)</h3>
                    <div style="display:flex;gap:20px;margin-top:10px;">
                        <div style="flex:1;"><p style="margin:0;font-size:10px;color:#64748b;">Money saved on tools</p><p style="margin:2px 0 0;font-size:17px;font-weight:800;color:#0f172a;">${this.money(r.extraAnnual)}</p></div>
                        <div style="flex:1;"><p style="margin:0;font-size:10px;color:#64748b;">Money saved via time</p><p style="margin:2px 0 0;font-size:17px;font-weight:800;color:#0f172a;">${this.money(r.timeAnnual)}</p></div>
                        <div style="flex:1;"><p style="margin:0;font-size:10px;color:#64748b;">Effectiveness revenue</p><p style="margin:2px 0 0;font-size:17px;font-weight:800;color:#0f172a;">${this.money(r.upliftRevenue)}</p></div>
                    </div>
                    <p style="margin:12px 0 0;font-size:26px;font-weight:800;color:#2563eb;">${this.money(r.totalValue)}</p>
                    <p style="margin:3px 0 0;font-size:10px;color:#64748b;">Tangible savings (tools + time) + additional effectiveness revenue · Sayzio plan: ${esc(this.selectedPlan.name)}</p>
                </div>

                <p style="margin:16px 0 0;font-size:9px;color:#94a3b8;">Generated with the Sayzio Marketing Plan Calculator · Projections are illustrative estimates based on your assumptions.</p>
            </div>`;
        },

        renderPdfCharts(host) {
            const m = this.model, mult = this.curMult;
            const sym = this.p.display_currency === 'USD' ? '$' : '₹';
            const created = [];
            const monthEl = host.querySelector('canvas[data-pdf-chart="month"]');
            if (monthEl) {
                created.push(new Chart(monthEl, {
                    type: 'bar',
                    data: {
                        labels: this.months,
                        datasets: [
                            { label: 'Spend (' + sym + ')',   data: m.monthTotals.spend.map(v => v * mult),   backgroundColor: 'rgba(37,99,235,0.65)' },
                            { label: 'Revenue (' + sym + ')', data: m.monthTotals.revenue.map(v => v * mult), backgroundColor: 'rgba(16,185,129,0.65)' },
                        ],
                    },
                    options: { responsive: false, animation: false, devicePixelRatio: 2, plugins: { legend: { position: 'bottom' } } },
                }));
            }
            const chanEl = host.querySelector('canvas[data-pdf-chart="channel"]');
            if (chanEl) {
                let rows = m.channels
                    .map(r => ({ name: r.name, rev: this.sum(r.revenue) * mult }))
                    .filter(r => r.rev > 0)
                    .sort((a, b) => b.rev - a.rev);
                // Task #6763 — cap doughnut slices so an inflated channel list
                // can't blow up the legend and squash the chart on the one-pager.
                const maxSlices = 12;
                if (rows.length > maxSlices) {
                    const rest = rows.slice(maxSlices - 1);
                    rows = [
                        ...rows.slice(0, maxSlices - 1),
                        { name: `Other (${rest.length})`, rev: rest.reduce((s, r) => s + r.rev, 0) },
                    ];
                }
                const palette = ['#2563eb','#0ea5e9','#10b981','#f59e0b','#ef4444','#14b8a6','#3b82f6','#64748b','#22c55e','#eab308','#06b6d4','#f97316','#0284c7','#84cc16','#e11d48','#475569'];
                created.push(new Chart(chanEl, {
                    type: 'doughnut',
                    data: {
                        labels: rows.map(r => r.name),
                        datasets: [{ data: rows.map(r => r.rev), backgroundColor: rows.map((_, i) => palette[i % palette.length]), borderWidth: 0 }],
                    },
                    options: { responsive: false, animation: false, devicePixelRatio: 2, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } } } },
                }));
            }
            return created;
        },

        /**
         * Wraps a JPEG data-URL in a minimal one-page PDF (page sized to the
         * image at 96dpi → points). No library needed — the JPEG stream is
         * embedded verbatim via DCTDecode.
         */
        jpegToPdfBlob(dataUrl, pxW, pxH) {
            const b64 = dataUrl.split(',')[1];
            const bin = atob(b64);
            const img = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) img[i] = bin.charCodeAt(i);
            // Rendered at scale 2 → treat as 192dpi so the page prints at the intended size.
            const wPt = (pxW / 2) * 72 / 96, hPt = (pxH / 2) * 72 / 96;
            const enc = new TextEncoder();
            const content = `q ${wPt.toFixed(2)} 0 0 ${hPt.toFixed(2)} 0 0 cm /Im0 Do Q`;
            const objs = [
                '<< /Type /Catalog /Pages 2 0 R >>',
                '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${wPt.toFixed(2)} ${hPt.toFixed(2)}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>`,
                null, // image — handled specially below
                `<< /Length ${content.length} >>\nstream\n${content}\nendstream`,
            ];
            const parts = []; let offset = 0; const offsets = [];
            const push = (chunk) => { parts.push(chunk); offset += chunk.length; };
            push(enc.encode('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n'));
            for (let i = 0; i < objs.length; i++) {
                offsets.push(offset);
                if (i === 3) {
                    push(enc.encode(`4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${pxW} /Height ${pxH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${img.length} >>\nstream\n`));
                    push(img);
                    push(enc.encode('\nendstream\nendobj\n'));
                } else {
                    push(enc.encode(`${i + 1} 0 obj\n${objs[i]}\nendobj\n`));
                }
            }
            const xrefStart = offset;
            let xref = `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n`;
            for (const o of offsets) xref += String(o).padStart(10, '0') + ' 00000 n \n';
            xref += `trailer\n<< /Size ${objs.length + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`;
            push(enc.encode(xref));
            return new Blob(parts, { type: 'application/pdf' });
        },

        // ---------- charts ----------
        /**
         * Task #6765 — download a dashboard chart as a PNG. Chart.js canvases
         * are transparent, so composite onto the current theme's background
         * first (white in light mode, dashboard navy in dark) for a readable
         * standalone image.
         */
        downloadChart(key) {
            const src = document.getElementById(key === 'month' ? 'mpcMonthChart' : 'mpcChannelChart');
            if (!src || !src.width) return;
            const light = document.documentElement.classList.contains('light-mode');
            const out = document.createElement('canvas');
            out.width = src.width; out.height = src.height;
            const ctx = out.getContext('2d');
            ctx.fillStyle = light ? '#ffffff' : '#0f172a';
            ctx.fillRect(0, 0, out.width, out.height);
            ctx.drawImage(src, 0, 0);
            const a = document.createElement('a');
            a.href = out.toDataURL('image/png');
            a.download = this.exportFileBase() + (key === 'month' ? '-spend-vs-revenue.png' : '-revenue-by-channel.png');
            document.body.appendChild(a);
            a.click();
            setTimeout(() => a.remove(), 0);
        },

        openDashboard() {
            this.tab = 'dashboard';
            this.$nextTick(() => this.renderCharts());
        },
        renderCharts() {
            if (typeof Chart === 'undefined') return;
            const light = document.documentElement.classList.contains('light-mode');
            Chart.defaults.color = light ? '#475569' : 'rgba(255,255,255,0.65)';
            Chart.defaults.borderColor = light ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)';
            const m = this.model, mult = this.curMult;
            const sym = this.p.display_currency === 'USD' ? '$' : '₹';

            for (const k of Object.keys(this.charts)) { this.charts[k]?.destroy(); }
            this.charts = {};

            const monthEl = document.getElementById('mpcMonthChart');
            if (monthEl) {
                this.charts.month = new Chart(monthEl, {
                    type: 'bar',
                    data: {
                        labels: this.months,
                        datasets: [
                            { label: 'Spend (' + sym + ')',   data: m.monthTotals.spend.map(v => v * mult),   backgroundColor: 'rgba(37,99,235,0.55)' },
                            { label: 'Revenue (' + sym + ')', data: m.monthTotals.revenue.map(v => v * mult), backgroundColor: 'rgba(16,185,129,0.55)' },
                        ],
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
                });
            }

            // Task #6772 — Plan vs. Actual chart (only once actuals loaded).
            const actEl = document.getElementById('mpcActualChart');
            if (actEl && this.actuals && this.actuals.has_data) {
                const a = this.actuals.months || [];
                this.charts.actual = new Chart(actEl, {
                    data: {
                        labels: a.map(r => r.ym),
                        datasets: [
                            { type: 'bar',  label: 'Actual visitors', data: a.map(r => r.visitors), backgroundColor: 'rgba(37,99,235,0.55)' },
                            { type: 'bar',  label: 'Actual leads',    data: a.map(r => r.leads),    backgroundColor: 'rgba(16,185,129,0.55)' },
                            { type: 'line', label: 'Planned visitors', data: m.monthTotals.visitors, borderColor: '#60a5fa', backgroundColor: 'transparent', tension: 0.3 },
                            { type: 'line', label: 'Planned leads',    data: m.monthTotals.leads,    borderColor: '#34d399', backgroundColor: 'transparent', tension: 0.3 },
                        ],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { footer: (items) => {
                                const i = items[0]?.dataIndex;
                                const row = (i !== undefined) ? a[i] : null;
                                return row && row.revenue > 0 ? 'Actual revenue: ' + this.money(row.revenue) : '';
                            } } },
                        },
                    },
                });
            }

            const chanEl = document.getElementById('mpcChannelChart');
            if (chanEl) {
                const rows = m.channels
                    .map(r => ({ name: r.name, rev: this.sum(r.revenue) * mult }))
                    .filter(r => r.rev > 0)
                    .sort((a, b) => b.rev - a.rev);
                // Brand-blue-led palette (no purple).
                const palette = ['#2563eb','#0ea5e9','#10b981','#f59e0b','#ef4444','#14b8a6','#3b82f6','#64748b','#22c55e','#eab308','#06b6d4','#f97316','#0284c7','#84cc16','#e11d48','#475569'];
                this.charts.channel = new Chart(chanEl, {
                    type: 'doughnut',
                    data: {
                        labels: rows.map(r => r.name),
                        datasets: [{ data: rows.map(r => r.rev), backgroundColor: rows.map((_, i) => palette[i % palette.length]), borderWidth: 0 }],
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } },
                });
            }
        },

        // ---------- save / load ----------
        async save() {
            if (this.saving) return;
            this.saving = true; this.saveError = ''; this.savedFlash = false;
            try {
                const url = this.planId
                    ? '{{ route('user.marketing-plan.update', ['plan' => '__ID__']) }}'.replace('__ID__', this.planId)
                    : '{{ route('user.marketing-plan.store') }}';
                const res = await fetch(url, {
                    method: this.planId ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ name: this.name || 'My Marketing Plan', payload: this.p }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) throw new Error(data.message || 'Could not save the plan. Please try again.');
                this._baseline = this.snapshot();
                this.dirty = false;
                if (!this.planId && data.redirect) { window.location.href = data.redirect; return; }
                this.savedFlash = true;
                setTimeout(() => this.savedFlash = false, 2500);
            } catch (e) {
                this.saveError = e.message || 'Could not save the plan. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endsection
