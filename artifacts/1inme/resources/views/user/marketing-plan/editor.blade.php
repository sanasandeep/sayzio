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
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
            color: #fff; border-radius: 0.6rem; padding: 0.4rem 0.6rem; font-size: 0.82rem; width: 100%;
        }
        .mpc-input:focus { outline: none; border-color: #3b82f6; }
        html.light-mode .mpc-input { background: #fff; border-color: rgba(15,23,42,0.18); color: #0f172a; }
        .mpc-th { color: rgba(255,255,255,0.45); font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; text-align: left; padding: 0.5rem 0.6rem; white-space: nowrap; }
        html.light-mode .mpc-th { color: #64748b; }
        .mpc-td { padding: 0.4rem 0.6rem; font-size: 0.82rem; color: rgba(255,255,255,0.8); white-space: nowrap; }
        html.light-mode .mpc-td { color: #1e293b; }
        .mpc-row { border-top: 1px solid rgba(255,255,255,0.06); }
        html.light-mode .mpc-row { border-top-color: rgba(15,23,42,0.08); }
        .mpc-tab { padding: 0.5rem 0.9rem; border-radius: 0.75rem; font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.55); }
        html.light-mode .mpc-tab { color: #475569; }
        .mpc-tab:hover { color: #60a5fa; }
        .mpc-tab.active { background: rgba(37,99,235,0.15); color: #60a5fa; }
        html.light-mode .mpc-tab.active { background: rgba(37,99,235,0.10); color: #2563eb; }
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

    {{-- ===== Header: name, currency toggle, save ===== --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('user.marketing-plan.index') }}" class="text-[11px] font-bold uppercase tracking-[0.15em] text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-1"></i> Marketing Plan Calculator
            </a>
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
                </div>
            </div>
            <button type="button" @click="save()" :disabled="saving"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-60">
                <i class="fas" :class="saving ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"></i>
                <span x-text="saving ? 'Saving…' : 'Save plan'"></span>
            </button>
        </div>
    </div>
    <p class="text-xs text-emerald-400 mb-3" x-show="savedFlash" x-cloak><i class="fas fa-check mr-1"></i> Saved.</p>
    <p class="text-xs text-red-400 mb-3" x-show="saveError" x-cloak x-text="saveError"></p>

    {{-- ===== Tabs ===== --}}
    <div class="flex flex-wrap gap-1.5 mb-5">
        <button type="button" class="mpc-tab" :class="tab === 'assumptions' && 'active'" @click="tab = 'assumptions'">1 · Assumptions</button>
        <button type="button" class="mpc-tab" :class="tab === 'monthly' && 'active'" @click="tab = 'monthly'">2 · Monthly Plan</button>
        <button type="button" class="mpc-tab" :class="tab === 'dashboard' && 'active'" @click="openDashboard()">3 · Dashboard</button>
        <button type="button" class="mpc-tab" :class="tab === 'roi' && 'active'" @click="tab = 'roi'">4 · Sayzio ROI &amp; Value</button>
    </div>

    {{-- ========================= 1 · ASSUMPTIONS ========================= --}}
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
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <label class="text-xs font-semibold mpc-sub">USD → INR display rate</label>
                <input type="number" min="1" step="0.01" x-model.number="p.usd_inr_rate" class="mpc-input mt-1.5">
                <p class="text-[11px] mpc-faint mt-1">Used only when the display toggle is set to $ USD.</p>
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
                <p class="text-[11px] mpc-faint mt-2">Illustrative defaults — replace with your own before/after data. Turn off for baseline, apples-to-apples projections.</p>
            </div>
        </div>

        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold mpc-title">Channel assumptions <span class="mpc-faint font-normal">(money in ₹ — base currency)</span></h3>
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
            <p class="text-[11px] mpc-faint mt-2">Sayzio's row is a fixed monthly subscription + optional AI credits and is excluded from the 100% total — the other 15 paid channels' allocations sum to 100% of the annual ad budget on their own.</p>
        </div>
    </div>

    {{-- ========================= 2 · MONTHLY PLAN ========================= --}}
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

    {{-- ========================= 3 · DASHBOARD ========================= --}}
    <div x-show="tab === 'dashboard'" x-cloak class="space-y-4">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total annual budget</p><p class="mpc-kpi mt-1" x-text="money(p.annual_budget)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total projected revenue (year)</p><p class="mpc-kpi mt-1" x-text="money(model.totals.revenue)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Blended ROAS</p><p class="mpc-kpi mt-1" x-text="nf(model.totals.roas, 2) + '×'"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Total customers acquired (year)</p><p class="mpc-kpi mt-1" x-text="nf(model.totals.customers, 0)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Blended CAC</p><p class="mpc-kpi mt-1" x-text="money(model.totals.cac)"></p></div>
            <div class="rounded-2xl mpc-card p-4"><p class="text-xs mpc-sub">Blended ROI</p><p class="mpc-kpi mt-1" x-text="nf(model.totals.roi * 100, 0) + '%'"></p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title mb-2">Spend vs revenue by month</h3>
                <canvas id="mpcMonthChart" height="220"></canvas>
            </div>
            <div class="rounded-2xl mpc-card p-4">
                <h3 class="text-sm font-bold mpc-title mb-2">Annual revenue by channel</h3>
                <canvas id="mpcChannelChart" height="220"></canvas>
            </div>
        </div>

        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <h3 class="text-sm font-bold mpc-title mb-2">Channel summary (annual)</h3>
            <table class="w-full min-w-[720px]">
                <thead><tr>
                    <th class="mpc-th">Channel</th><th class="mpc-th text-right">Annual spend</th><th class="mpc-th text-right">Annual revenue</th>
                    <th class="mpc-th text-right">Customers</th><th class="mpc-th text-right">CAC</th><th class="mpc-th text-right">ROI %</th>
                </tr></thead>
                <tbody>
                    <template x-for="row in model.channels" :key="row.key">
                        <tr class="mpc-row">
                            <td class="mpc-td font-semibold" x-text="row.name"></td>
                            <td class="mpc-td text-right" x-text="money(sum(row.spend))"></td>
                            <td class="mpc-td text-right" x-text="money(sum(row.revenue))"></td>
                            <td class="mpc-td text-right" x-text="nf(sum(row.customers), 0)"></td>
                            <td class="mpc-td text-right" x-text="sum(row.customers) > 0 ? money(sum(row.spend) / sum(row.customers)) : '—'"></td>
                            <td class="mpc-td text-right" x-text="sum(row.spend) > 0 ? nf((sum(row.revenue) - sum(row.spend)) / sum(row.spend) * 100, 0) + '%' : '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ========================= 4 · SAYZIO ROI & VALUE ========================= --}}
    <div x-show="tab === 'roi'" x-cloak class="space-y-4">
        <div class="rounded-2xl mpc-card p-4 overflow-x-auto">
            <h3 class="text-sm font-bold mpc-title">If you didn't use Sayzio — what you'd need instead</h3>
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
                            <td class="mpc-td" style="width:130px;"><input type="number" min="0" x-model.number="t.cost" class="mpc-input !w-28"></td>
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
<script>
function mpcApp() {
    return {
        planId: @js($plan?->id),
        name: @js($plan?->name ?? 'My Marketing Plan'),
        p: @js($payload),
        planOptions: @js($planOptions),
        months: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        tab: 'assumptions',
        monthlyMetric: 'spend',
        saving: false, savedFlash: false, saveError: '',
        charts: {},
        dirty: false, _baseline: '',

        init() {
            // Ensure array shapes survive older/partial payloads.
            if (!Array.isArray(this.p.weights) || this.p.weights.length !== 12) this.p.weights = Array(12).fill(1);
            if (!this.p.uplifts) this.p.uplifts = { apply: true, chat: 8, crm: 15 };

            // ----- unsaved-changes guard -----
            this._baseline = this.snapshot();
            this.$watch('p', () => this.recomputeDirty());
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
        snapshot() { return JSON.stringify({ name: this.name, p: this.p }); },
        recomputeDirty() { this.dirty = this.snapshot() !== this._baseline; },

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

        get selectedPlan() {
            return this.planOptions.find(o => o.slug === this.p.plan_slug) || { name: '—', inr: 0, usd: 0 };
        },
        get allocTotal() {
            return this.sum(this.p.channels.filter(c => !c.fixed).map(c => c.alloc));
        },
        get allocOk() { return Math.abs(this.allocTotal - 100) < 0.05; },

        // ---------- the calculation engine ----------
        get model() {
            const W = Math.max(1e-9, this.sum(this.p.weights));
            const vlMult = this.p.uplifts.apply ? 1 + this.n(this.p.uplifts.chat) / 100 : 1;
            const lcMult = this.p.uplifts.apply ? 1 + this.n(this.p.uplifts.crm) / 100 : 1;
            const subInr = this.n(this.selectedPlan.inr);
            const budget = this.n(this.p.annual_budget);

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
                        vis = this.n(c.cpv) > 0 ? sp / this.n(c.cpv) : 0;
                    }
                    const ld = vis * this.n(c.vl) / 100 * vlMult;
                    const cu = ld * this.n(c.lc) / 100 * lcMult;
                    spend.push(sp); visitors.push(vis); leads.push(ld);
                    customers.push(cu); revenue.push(cu * this.n(c.acv));
                }
                return { key: c.key, name: c.name, spend, visitors, leads, customers, revenue };
            });

            const monthTotals = { spend: [], visitors: [], leads: [], customers: [], revenue: [] };
            for (let m = 0; m < 12; m++) {
                for (const k of Object.keys(monthTotals)) {
                    monthTotals[k].push(channels.reduce((a, row) => a + row[k][m], 0));
                }
            }

            const spend = this.sum(monthTotals.spend), revenue = this.sum(monthTotals.revenue),
                  customers = this.sum(monthTotals.customers);
            return {
                channels, monthTotals, vlMult, lcMult,
                totals: {
                    spend, revenue, customers,
                    roas: spend > 0 ? revenue / spend : 0,
                    cac:  customers > 0 ? spend / customers : 0,
                    roi:  spend > 0 ? (revenue - spend) / spend : 0,
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
                ['Apply Sayzio toolset uplifts', this.p.uplifts.apply ? 'Yes' : 'No'],
                ['Chat widget uplift — visitor → lead (%)', this.xn(this.p.uplifts.chat, 1)],
                ['CRM & dialer uplift — lead → customer (%)', this.xn(this.p.uplifts.crm, 1)],
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
                [],
                ['Channel summary (annual)'],
                ['Channel', 'Annual spend (' + sym + ')', 'Annual revenue (' + sym + ')', 'Customers', 'CAC (' + sym + ')', 'ROI %'],
                ...m.channels.map(row => {
                    const sp = this.sum(row.spend), rev = this.sum(row.revenue), cu = this.sum(row.customers);
                    return [row.name, this.xm(sp), this.xm(rev), this.xn(cu, 0),
                            cu > 0 ? this.xm(sp / cu) : '—',
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

            return [
                ['Assumptions', assumptions],
                ['Monthly Plan', monthly],
                ['Dashboard', dashboard],
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

        // ---------- charts ----------
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
