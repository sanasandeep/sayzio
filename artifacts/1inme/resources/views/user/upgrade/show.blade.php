@extends('user.layouts.app')
@section('title', 'Upgrade your plan')
@section('page-title', 'Upgrade')

@section('content')
<style>[x-cloak]{display:none!important}</style>
{{-- Currency flips USD/INR instantly client-side (both currencies are
     embedded per card/addon below); the choice is persisted in the
     background. Billing cycle still navigates server-side via links. --}}
@php
    // Compact catalog for the client-side add-on cart: per-addon unit price
    // (minor units, current cycle) in each currency plus its eligible plan
    // ids, so the running total and per-plan checkout links resolve without
    // another round-trip.
    $addonCatalog = [];
    foreach ($addons as $row) {
        $am = $row['model'];
        $unit = [];
        foreach (['USD', 'INR'] as $cur) {
            $unit[$cur] = (int) ($row['prices'][$cur][$cycle]['amount_minor'] ?? 0);
        }
        $addonCatalog[(int) $am->id] = [
            'planIds' => $row['planIds'],
            'unit'    => $unit,
        ];
    }
@endphp
<div class="max-w-6xl mx-auto space-y-8"
     x-data="{
        currency: '{{ $currency }}',
        sel: {},
        catalog: @json($addonCatalog),
        maxQty: 99,
        switchCurrency(c){
            if (this.currency === c) return;
            this.currency = c;
            const url = '{{ route('user.upgrade.switch-currency') }}';
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            const data = new FormData();
            data.append('currency', c);
            data.append('_token', token);
            try {
                fetch(url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (e) { /* swallow — UX must not depend on persistence */ }
        },
        isSelected(id){ return (this.sel[id] ?? 0) > 0; },
        qtyOf(id){ return this.sel[id] ?? 1; },
        toggle(id){ if (this.isSelected(id)) { delete this.sel[id]; } else { this.sel[id] = 1; } },
        inc(id){ this.sel[id] = Math.min(this.maxQty, (this.sel[id] ?? 1) + 1); },
        dec(id){ const q = (this.sel[id] ?? 1) - 1; if (q < 1) { delete this.sel[id]; } else { this.sel[id] = q; } },
        addonCount(){ return Object.keys(this.sel).length; },
        fmtMoney(minor, cur){
            const sym = cur === 'INR' ? '₹' : (cur === 'USD' ? '$' : cur + ' ');
            return sym + (minor/100).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        },
        addonTotalMinor(){
            let t = 0;
            for (const id in this.sel) {
                const c = this.catalog[id];
                if (c) { t += (c.unit[this.currency] ?? 0) * this.sel[id]; }
            }
            return t;
        },
        addonTotalFormatted(){ return this.fmtMoney(this.addonTotalMinor(), this.currency); },
        checkoutUrl(planId){
            let url = '{{ route('user.checkout.show') }}?plan=' + planId + '&cycle={{ $cycle }}';
            for (const id in this.sel) {
                const c = this.catalog[id];
                if (this.sel[id] > 0 && c && c.planIds.includes(planId)) {
                    url += '&addons[' + id + ']=' + this.sel[id];
                }
            }
            return url;
        }
     }">
    <div class="text-center space-y-2">
        <h1 class="text-3xl font-semibold text-white">Pick the plan that fits your work</h1>
        @if(!$user || !$user->country)
            <p class="text-white/60">All prices below.
                <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">Set your country</a> for accurate pricing.
            </p>
        @endif

        <div class="inline-flex rounded-full border border-white/10 bg-white/[0.02] p-1 mt-3">
            <a href="{{ route('user.upgrade', ['cycle' => 'monthly']) }}"
               class="px-4 py-1.5 text-sm rounded-full transition {{ $cycle === 'monthly' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white' }}">Monthly</a>
            <a href="{{ route('user.upgrade', ['cycle' => 'annual']) }}"
               class="px-4 py-1.5 text-sm rounded-full transition {{ $cycle === 'annual' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white' }}">Annual <span class="text-[10px] opacity-70">save 2 months</span></a>
        </div>

        <div class="pt-1">
            @include('public.pricing._currency_badge', [
                'currency'       => $currency,
                'currencySource' => $currencySource,
                'user'           => $user,
                'switchRoute'    => 'user.upgrade.switch-currency',
            ])
        </div>
    </div>

    {{-- ───── Smart "Recommended for you" callout ───── --}}
    @php $rec = $recommendation ?? null; @endphp
    @if($rec && $rec['recommendedPlan'])
        @php $recPlan = $rec['recommendedPlan']; @endphp
        <div class="rounded-2xl border border-pink-400/40 p-5 sm:p-6 bg-gradient-to-br from-violet-600/15 via-pink-500/10 to-amber-500/5 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-pink-500/15 blur-3xl pointer-events-none"></div>
            <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-5 items-center">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] text-pink-300 mb-1">
                        <i class="fas fa-wand-magic-sparkles"></i> Recommended for you
                    </div>
                    <div class="text-white text-xl font-semibold">
                        Step up to <span class="text-white">{{ $recPlan->name }}</span>
                    </div>
                    <p class="text-sm text-white/70 mt-1">{{ $rec['reason'] }}</p>
                </div>
                @if(!empty($rec['usage']))
                    <div class="space-y-2">
                        @foreach(array_slice($rec['usage'], 0, 3) as $u)
                            <div>
                                <div class="flex items-baseline justify-between text-xs">
                                    <span class="text-white/70 capitalize">{{ $u['label'] }}</span>
                                    <span class="text-white/55">
                                        @if($u['unlimited'])
                                            <span class="text-emerald-300 font-semibold">{{ number_format($u['used']) }}</span> · unlimited
                                        @else
                                            <span class="text-white font-semibold">{{ number_format($u['used']) }}</span> / {{ number_format($u['cap']) }} <span class="text-white/40">({{ $u['pct'] }}%)</span>
                                        @endif
                                    </span>
                                </div>
                                @unless($u['unlimited'])
                                    <div class="h-1.5 rounded-full bg-white/[.06] mt-1 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ max(2, $u['pct']) }}%; background: linear-gradient(90deg,{{ $u['pct'] >= 70 ? '#f59e0b,#ef4444' : '#7c3aed,#ec4899,#f59e0b' }});"></div>
                                    </div>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @php
        // Same JIT-class fix as the public pricing page: dynamic
        // `md:grid-cols-{N}` interpolation isn't picked up by Tailwind so
        // we choose from a pre-known set instead.
        $upgPlanCount = count($plans);
        $upgGrid = match (true) {
            $upgPlanCount >= 4 => 'md:grid-cols-2 lg:grid-cols-4',
            $upgPlanCount === 3 => 'md:grid-cols-3',
            $upgPlanCount === 2 => 'md:grid-cols-2',
            default => 'md:grid-cols-1',
        };
        $recPlanIdInline = $rec['recommendedPlan']->id ?? null;
    @endphp
    <div class="grid grid-cols-1 {{ $upgGrid }} gap-5">
        @foreach($plans as $row)
            @php
                $plan = $row['model'];
                $isCurrent = $user && $user->plan_id === $plan->id;
                $isRec = !$isCurrent && $recPlanIdInline === $plan->id;
            @endphp
            <div x-data='{ prices: @json($row['prices']), taxByCur: @json($row['taxByCur']) }'
                 class="relative rounded-2xl border {{ $isCurrent ? 'border-emerald-500/60 ring-1 ring-emerald-500/40' : ($isRec ? 'border-pink-400/60 ring-1 ring-pink-400/30' : 'border-white/10') }} bg-white/[0.02] p-6 flex flex-col">
                @if($isRec)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-violet-500 to-pink-500 text-white text-[10px] font-bold rounded-full uppercase tracking-wider shadow-lg shadow-pink-500/20">
                        <i class="fas fa-wand-magic-sparkles mr-1"></i> Recommended
                    </div>
                @endif
                <div class="space-y-1">
                    <div class="text-xs uppercase tracking-wider text-white/40">{{ $plan->name }}</div>
                    @php $introCell = "prices[currency] && prices[currency].{$cycle} && prices[currency].{$cycle}.intro ? prices[currency].{$cycle}.intro : null"; @endphp
                    {{-- First-term intro badge --}}
                    <template x-if="{{ $introCell }}">
                        <div class="mb-1 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-400/15 border border-emerald-300/30 text-emerald-300 text-[10px] font-bold uppercase tracking-wider">
                            <i class="fas fa-bolt"></i>
                            <span x-text="(({{ $introCell }}).label) || ('Save ' + ({{ $introCell }}).percent_off + '% first {{ $cycle === 'annual' ? 'year' : 'month' }}')"></span>
                        </div>
                    </template>
                    <div class="flex items-baseline gap-1 flex-wrap">
                        {{-- Struck-through normal price when intro active --}}
                        <template x-if="{{ $introCell }}">
                            <span class="text-lg font-medium text-white/40 line-through decoration-2" x-text="({{ $introCell }}).normal_formatted"></span>
                        </template>
                        <span class="text-3xl font-semibold text-white"
                              x-text="({{ $introCell }}) ? ({{ $introCell }}).first_formatted : ((prices[currency] && prices[currency].{{ $cycle }} && prices[currency].{{ $cycle }}.formatted) || '{{ $row['shown']['formatted'] }}')">{{ $row['shown']['formatted'] }}</span>
                        <span class="text-sm text-white/40">/ {{ $cycle === 'annual' ? 'yr' : 'mo' }}</span>
                    </div>
                    {{-- Intro fineprint: revert-to-normal on renewal --}}
                    <template x-if="{{ $introCell }}">
                        <div class="text-[11px] text-emerald-300/90">
                            First {{ $cycle === 'annual' ? 'year' : 'month' }} only — renews at <span x-text="({{ $introCell }}).normal_formatted"></span>/{{ $cycle === 'annual' ? 'yr' : 'mo' }}
                        </div>
                    </template>
                    @if($cycle === 'annual')
                        <div class="text-[11px] text-white/40"
                             x-show="prices[currency] && prices[currency].monthly && prices[currency].monthly.amount_minor > 0" x-cloak>
                            vs <span x-text="(prices[currency] && prices[currency].monthly && prices[currency].monthly.formatted) || '{{ $row['monthly']['formatted'] }}'">{{ $row['monthly']['formatted'] }}</span>/mo billed monthly
                        </div>
                    @endif
                    {{-- Tax / fineprint per currency, toggled by Alpine. Both currencies
                         are pre-rendered so the instant switch stays accurate for
                         buyers with a billing address. --}}
                    @foreach(['USD','INR'] as $cur)
                        @php
                            $cPrice = $row['prices'][$cur][$cycle] ?? null;
                            $cTax   = $row['taxByCur'][$cur][$cycle] ?? null;
                        @endphp
                        @if(($cPrice['amount_minor'] ?? 0) > 0)
                            <div x-show="currency==='{{ $cur }}'" x-cloak>
                                @if($cTax && !empty($cTax['tax_breakdown']))
                                    <div class="mt-2 text-[11px] text-white/55 space-y-0.5 border-t border-white/5 pt-2">
                                        @foreach($cTax['tax_breakdown'] as $line)
                                            <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $cur) }}</span></div>
                                        @endforeach
                                        <div class="flex justify-between font-medium text-white/85 pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $cTax['grand_total_minor'], $cur) }}</span></div>
                                    </div>
                                    @if(!empty($cTax['reverse_charge_note']))
                                        <div class="mt-1 text-[10px] uppercase tracking-wider text-amber-300/80">{{ $cTax['reverse_charge_note'] }}</div>
                                    @endif
                                @elseif($cTax)
                                    <div class="mt-2 text-[11px] text-emerald-300/80">No tax applies for {{ $cTax['place_of_supply'] ?? 'your region' }}.</div>
                                @else
                                    <div class="mt-2 text-[11px] text-white/40">+ taxes as applicable —
                                        <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">add billing address</a>
                                        to see exact tax.
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                    <p class="text-sm text-white/50 mt-2 min-h-[2.5rem]">{{ $plan->description }}</p>
                </div>

                @php $features = $plan->features ?? []; @endphp
                @if(!empty($features))
                <ul class="mt-4 space-y-1.5 text-sm text-white/70 flex-grow">
                    @foreach([
                        'max_links' => 'links',
                        'max_biolinks' => 'Link in Bio pages',
                        'max_projects' => 'projects',
                        'storage_limit_mb' => 'MB storage',
                        'contacts_max' => 'contacts',
                        'max_forms' => 'forms',
                        'max_buzz_items' => 'buzz popups',
                        'max_buzz_impressions' => 'Buzz views / mo',
                        'max_splash_pages' => 'splash pages',
                        'max_files' => 'files',
                        'max_vault_items' => 'vault items',
                        'max_task_boards' => 'task boards',
                        'max_leads' => 'leads',
                        'max_events' => 'events',
                        'max_workspaces' => 'team workspaces',
                        'max_seats_per_workspace' => 'seats per workspace',
                        'max_minds' => 'AI Minds',
                        'max_personas' => 'AI Personas',
                        'max_companions' => 'AI Companions',
                    ] as $key => $label)
                        @if(isset($features[$key]) && (int) $features[$key] !== 0)
                            <li class="flex items-start gap-2"><span class="text-violet-400">•</span><span>{{ $features[$key] == -1 ? 'Unlimited' : number_format((int)$features[$key]) }} {{ $label }}</span></li>
                        @endif
                    @endforeach
                    @php
                        $boolFeatures = [
                            'calendar_sync'           => 'Calendar sync',
                            'verification_eligible'   => 'Verified badge eligibility',
                            'creator_profile_public'  => 'Public creator profile',
                            'link_password'           => 'Password-protected links',
                            'link_expiry'             => 'Link expiry & active windows',
                            'link_geo_targeting'      => 'Geo targeting per link',
                            'link_device_targeting'   => 'Device targeting per link',
                            'link_deep_link'          => 'Deep-link / open-in-app',
                            'link_smart_rules'        => 'Smart redirect rules',
                            'ai_widget'               => 'Site Assistant widget',
                            'ai_voice_assistant'      => 'Voice Assistant',
                            'ask_coach'               => 'Ask Coach',
                            'card_scan'               => 'Card & Brochure Scanner',
                            'ai_resume_tools'         => 'AI Resume Tools',
                        ];
                    @endphp
                    @foreach($boolFeatures as $key => $label)
                        @if(!empty($features[$key]))
                            <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span>{{ $label }}</span></li>
                        @endif
                    @endforeach
                    @php $blockVal = $features['block_types_allowed'] ?? null; @endphp
                    @if(\App\Modules\Common\Support\PlanBlockLabels::isAll($blockVal))
                        <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span>All Link in Bio block types</span></li>
                    @else
                        @php $blockNames = \App\Modules\Common\Support\PlanBlockLabels::labelsFor($blockVal); @endphp
                        @if($blockNames)
                            @php $blockPreview = array_slice($blockNames, 0, 6); $blockExtra = count($blockNames) - count($blockPreview); @endphp
                            <li class="flex items-start gap-2">
                                <span class="text-violet-400">•</span>
                                <span class="min-w-0">
                                    <span class="block">{{ count($blockNames) }} Link in Bio block types</span>
                                    <span x-data="{ open: false }" class="block mt-1">
                                        <span class="block text-xs text-white/45 leading-snug" x-show="!open">{{ implode(', ', $blockPreview) }}@if($blockExtra > 0)<span class="text-white/60"> &amp; {{ $blockExtra }} more</span>@endif</span>
                                        <span class="block text-xs text-white/45 leading-snug" x-show="open" x-cloak>{{ implode(', ', $blockNames) }}</span>
                                        @if($blockExtra > 0)
                                            <button type="button" @click="open = !open" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-violet-300 hover:text-violet-200 transition">
                                                <span x-show="!open">Show all {{ count($blockNames) }} blocks</span>
                                                <span x-show="open" x-cloak>Show fewer</span>
                                                <i class="fas fa-chevron-down text-[8px]" :class="open ? 'rotate-180' : ''"></i>
                                            </button>
                                        @endif
                                    </span>
                                </span>
                            </li>
                        @endif
                    @endif
                    @if(!empty($features['api_access']))
                        @php
                            $apiCalls = (int) ($features['api_calls_monthly'] ?? 0);
                            $apiRate  = (int) ($features['api_rate_per_min'] ?? 0);
                        @endphp
                        <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span>Developer API access</span></li>
                        @if($apiCalls !== 0)
                            <li class="flex items-start gap-2"><span class="text-violet-400">•</span><span>{{ $apiCalls === -1 ? 'Unlimited' : number_format($apiCalls) }} API calls / month</span></li>
                        @endif
                        @if($apiRate !== 0)
                            <li class="flex items-start gap-2"><span class="text-violet-400">•</span><span>{{ $apiRate === -1 ? 'Unlimited' : number_format($apiRate) }} API rate (calls / min)</span></li>
                        @endif
                    @endif
                </ul>
                @endif

                <div class="mt-5">
                    @if($isCurrent)
                        <button disabled class="w-full px-4 py-2.5 bg-white/10 text-white/60 rounded-xl font-medium cursor-not-allowed">Current plan</button>
                    @else
                        <a :href="checkoutUrl({{ $plan->id }})"
                           class="block text-center w-full px-4 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">
                            Choose {{ $plan->name }}<span x-show="addonCount() > 0" x-cloak> + add-ons</span>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($addons->isNotEmpty())
    <div class="space-y-3 pt-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">Add-ons</h2>
                <p class="text-sm text-white/50">Pick any number of add-ons, set a quantity for each, then choose a plan to check out together.</p>
            </div>
            <div x-show="addonCount() > 0" x-cloak class="text-right">
                <div class="text-xs text-white/50"><span x-text="addonCount()"></span> add-on<span x-show="addonCount() !== 1">s</span> selected</div>
                <div class="text-lg font-semibold text-white" x-text="addonTotalFormatted()"></div>
                <div class="text-[11px] text-white/40">added to your plan at checkout</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($addons as $row)
                @php $a = $row['model']; $eligible = !empty($row['planIds']); @endphp
                <div x-data='{ prices: @json($row['prices']), taxByCur: @json($row['taxByCur']) }'
                     class="rounded-xl border bg-white/[0.02] p-4 transition"
                     :class="isSelected({{ $a->id }}) ? 'border-violet-500/60 ring-1 ring-violet-500/30' : 'border-white/10'">
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="font-medium text-white">{{ $a->name }}</div>
                        <div class="text-sm text-white/80 whitespace-nowrap"><span x-text="(prices[currency] && prices[currency].{{ $cycle }} && prices[currency].{{ $cycle }}.formatted) || '{{ $row['shown']['formatted'] }}'">{{ $row['shown']['formatted'] }}</span><span class="text-xs text-white/40"> / {{ $cycle === 'annual' ? 'yr' : 'mo' }}</span></div>
                    </div>
                    @if($a->description)<p class="text-xs text-white/50 mt-1">{{ $a->description }}</p>@endif
                    {{-- Per-currency tax fineprint, toggled by Alpine. --}}
                    @foreach(['USD','INR'] as $cur)
                        @php
                            $cPrice = $row['prices'][$cur][$cycle] ?? null;
                            $cTax   = $row['taxByCur'][$cur][$cycle] ?? null;
                        @endphp
                        @if(($cPrice['amount_minor'] ?? 0) > 0)
                            <div x-show="currency==='{{ $cur }}'" x-cloak>
                                @if($cTax && !empty($cTax['tax_breakdown']))
                                    <div class="mt-2 text-[11px] text-white/55 space-y-0.5 border-t border-white/5 pt-2">
                                        @foreach($cTax['tax_breakdown'] as $line)
                                            <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $cur) }}</span></div>
                                        @endforeach
                                        <div class="flex justify-between font-medium text-white/85 pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $cTax['grand_total_minor'], $cur) }}</span></div>
                                    </div>
                                @else
                                    <div class="mt-2 text-[10px] text-white/40">+ taxes as applicable</div>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    @if($eligible)
                        <div class="mt-3 flex items-center justify-between gap-2 border-t border-white/5 pt-3">
                            <label class="flex items-center gap-2 text-sm text-white/75 cursor-pointer select-none">
                                <input type="checkbox" :checked="isSelected({{ $a->id }})" @change="toggle({{ $a->id }})"
                                       class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/40">
                                <span x-text="isSelected({{ $a->id }}) ? 'Added' : 'Add to plan'"></span>
                            </label>
                            <div x-show="isSelected({{ $a->id }})" x-cloak class="flex items-center gap-1.5">
                                <button type="button" @click="dec({{ $a->id }})" aria-label="Decrease quantity"
                                        class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-white/80 leading-none">−</button>
                                <span class="w-6 text-center text-sm text-white tabular-nums" x-text="qtyOf({{ $a->id }})">1</span>
                                <button type="button" @click="inc({{ $a->id }})" aria-label="Increase quantity"
                                        class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-white/80 leading-none">+</button>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1">
                            <span class="text-[10px] uppercase tracking-wider text-white/30">Works with</span>
                            @foreach($a->plans as $p)
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-violet-500/10 text-violet-300/80 border border-violet-500/15">{{ $p->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-3 text-[11px] text-white/40 border-t border-white/5 pt-3">Not available for any plan yet.</div>
                    @endif
                    <div class="text-[10px] uppercase tracking-wider text-white/30 mt-2">{{ str_replace('_',' ',$a->type) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ───── Coins → AI credits ───── --}}
    @if($wallet_enabled)
    <div class="space-y-3 pt-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">
                    <i class="fas fa-coins text-amber-300"></i> Coins &amp; AI credits
                </h2>
                <p class="text-sm text-white/50">
                    Top up coins to cover API overage, activate paid add-ons, and fund AI credits for the features below.
                </p>
            </div>
            <a href="{{ route('user.wallet.buy') }}"
               class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300 transition shadow-lg shadow-amber-500/20 whitespace-nowrap">
                Buy coins
            </a>
        </div>
        @include('public.pricing._ai_coin_uses')
    </div>
    @endif
</div>
@endsection
