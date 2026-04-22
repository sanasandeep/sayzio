@extends('user.layouts.app')
@section('title', 'Upgrade your plan')
@section('page-title', 'Upgrade')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
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

    <div class="grid grid-cols-1 md:grid-cols-{{ min(count($plans), 4) }} gap-5">
        @foreach($plans as $row)
            @php $plan = $row['model']; $isCurrent = $user && $user->plan_id === $plan->id; @endphp
            <div class="rounded-2xl border {{ $isCurrent ? 'border-violet-500/60 ring-1 ring-violet-500/40' : 'border-white/10' }} bg-white/[0.02] p-6 flex flex-col">
                <div class="space-y-1">
                    <div class="text-xs uppercase tracking-wider text-white/40">{{ $plan->name }}</div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl font-semibold text-white">{{ $row['shown']['formatted'] }}</span>
                        <span class="text-sm text-white/40">/ {{ $cycle === 'annual' ? 'yr' : 'mo' }}</span>
                    </div>
                    @if($cycle === 'annual' && $row['monthly']['amount_minor'] > 0)
                        <div class="text-[11px] text-white/40">
                            vs {{ $row['monthly']['formatted'] }}/mo billed monthly
                        </div>
                    @endif
                    @php $tax = $row['tax'] ?? null; @endphp
                    @if($row['shown']['amount_minor'] > 0)
                        @if($tax && !empty($tax['tax_breakdown']))
                            <div class="mt-2 text-[11px] text-white/55 space-y-0.5 border-t border-white/5 pt-2">
                                @foreach($tax['tax_breakdown'] as $line)
                                    <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $currency) }}</span></div>
                                @endforeach
                                <div class="flex justify-between font-medium text-white/85 pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $tax['grand_total_minor'], $currency) }}</span></div>
                            </div>
                            @if(!empty($tax['reverse_charge_note']))
                                <div class="mt-1 text-[10px] uppercase tracking-wider text-amber-300/80">{{ $tax['reverse_charge_note'] }}</div>
                            @endif
                        @elseif($tax)
                            <div class="mt-2 text-[11px] text-emerald-300/80">No tax applies for {{ $tax['place_of_supply'] ?? 'your region' }}.</div>
                        @else
                            <div class="mt-2 text-[11px] text-white/40">+ taxes as applicable —
                                <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">add billing address</a>
                                to see exact tax.
                            </div>
                        @endif
                    @endif
                    <p class="text-sm text-white/50 mt-2 min-h-[2.5rem]">{{ $plan->description }}</p>
                </div>

                @php $features = $plan->features ?? []; @endphp
                @if(!empty($features))
                <ul class="mt-4 space-y-1.5 text-sm text-white/70 flex-grow">
                    @foreach([
                        'max_links' => 'links',
                        'max_biolinks' => 'bio pages',
                        'max_projects' => 'projects',
                        'storage_limit_mb' => 'MB storage',
                        'contacts_max' => 'contacts',
                        'max_forms' => 'forms',
                        'max_buzz_items' => 'buzz popups',
                        'max_splash_pages' => 'splash pages',
                        'max_files' => 'files',
                        'max_vault_items' => 'vault items',
                        'max_task_boards' => 'task boards',
                        'max_leads' => 'leads',
                        'max_events' => 'events',
                        'max_workspaces' => 'team workspaces',
                        'max_seats_per_workspace' => 'seats per workspace',
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
                        ];
                    @endphp
                    @foreach($boolFeatures as $key => $label)
                        @if(!empty($features[$key]))
                            <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span>{{ $label }}</span></li>
                        @endif
                    @endforeach
                    @if(($features['block_types_allowed'] ?? null) === '*')
                        <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span>All biolink block types</span></li>
                    @elseif(is_array($features['block_types_allowed'] ?? null))
                        <li class="flex items-start gap-2"><span class="text-violet-400">•</span><span>{{ count($features['block_types_allowed']) }} biolink block types</span></li>
                    @endif
                </ul>
                @endif

                <div class="mt-5">
                    @if($isCurrent)
                        <button disabled class="w-full px-4 py-2.5 bg-white/10 text-white/60 rounded-xl font-medium cursor-not-allowed">Current plan</button>
                    @else
                        <button class="w-full px-4 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition" disabled title="Checkout coming soon">Choose {{ $plan->name }}</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($addons->isNotEmpty())
    <div class="space-y-3 pt-4">
        <h2 class="text-xl font-semibold text-white">Add-ons</h2>
        <p class="text-sm text-white/50">Extend any paid plan with extra capabilities.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($addons as $row)
                @php $a = $row['model']; @endphp
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="font-medium text-white">{{ $a->name }}</div>
                        <div class="text-sm text-white/80 whitespace-nowrap">{{ $row['shown']['formatted'] }}<span class="text-xs text-white/40"> / {{ $cycle === 'annual' ? 'yr' : 'mo' }}</span></div>
                    </div>
                    @if($a->description)<p class="text-xs text-white/50 mt-1">{{ $a->description }}</p>@endif
                    @php $atax = $row['tax'] ?? null; @endphp
                    @if($row['shown']['amount_minor'] > 0 && $atax && !empty($atax['tax_breakdown']))
                        <div class="mt-2 text-[11px] text-white/55 space-y-0.5 border-t border-white/5 pt-2">
                            @foreach($atax['tax_breakdown'] as $line)
                                <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $currency) }}</span></div>
                            @endforeach
                            <div class="flex justify-between font-medium text-white/85 pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $atax['grand_total_minor'], $currency) }}</span></div>
                        </div>
                    @elseif($row['shown']['amount_minor'] > 0 && !$atax)
                        <div class="mt-2 text-[10px] text-white/40">+ taxes as applicable</div>
                    @endif
                    <div class="text-[10px] uppercase tracking-wider text-white/30 mt-2">{{ str_replace('_',' ',$a->type) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
