    <div class="glass rounded-2xl border border-white/10  p-6 {{ $plan->is_archived ? 'opacity-60' : '' }}">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-white flex items-center gap-2">
                {{ $plan->name }}
                @if($plan->is_popular)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-500/15 text-blue-300" title="Shown as Most Popular on the homepage">
                        <i class="fas fa-star mr-1"></i>Most Popular
                    </span>
                @endif
                @if($plan->is_internal)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/15 text-amber-300" title="Admin/staff-only — hidden from public pricing, upgrade and the recommender">
                        <i class="fas fa-user-shield mr-1"></i>Internal
                    </span>
                @endif
                @php $introBadge = $plan->introDiscount(); @endphp
                @if($introBadge)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-300"
                          title="First-term intro discount active{{ !empty($introBadge['cycles']) ? ' — ' . implode(', ', $introBadge['cycles']) : '' }}">
                        <i class="fas fa-bolt mr-1"></i>{{ $introBadge['type'] === 'percent' ? $introBadge['percent'] . '% intro' : 'Intro offer' }}
                    </span>
                @endif
            </h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $plan->status === 'active' && !$plan->is_archived ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/10 text-white/60' }}">
                {{ $plan->is_archived ? 'Archived' : ucfirst($plan->status) }}
            </span>
        </div>
        <p class="text-sm text-white/40 mb-4">{{ $plan->description ?? 'No description' }}</p>

        @php
            $__prices = $plan->relationLoaded('prices') ? $plan->prices : collect();
            $__pk = $__prices->keyBy(fn($p) => $p->currency . '_' . $p->billing_cycle);

            // USD: authoritative from prices table; fall back to legacy decimal columns.
            $__usdMon = isset($__pk['USD_monthly'])
                ? \App\Services\PricingResolver::money((int) $__pk['USD_monthly']->amount_minor_units, 'USD')
                : '$' . number_format((float) $plan->monthly_price, 2);
            $__usdAnn = isset($__pk['USD_annual'])
                ? \App\Services\PricingResolver::money((int) $__pk['USD_annual']->amount_minor_units, 'USD')
                : '$' . number_format((float) $plan->annual_price, 2);

            // INR: authoritative from prices table; "—" when not set.
            $__inrMon = isset($__pk['INR_monthly'])
                ? \App\Services\PricingResolver::money((int) $__pk['INR_monthly']->amount_minor_units, 'INR')
                : null;
            $__inrAnn = isset($__pk['INR_annual'])
                ? \App\Services\PricingResolver::money((int) $__pk['INR_annual']->amount_minor_units, 'INR')
                : null;
        @endphp
        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Monthly</span>
                <span class="font-semibold text-white">
                    {{ $__usdMon }}
                    @if($__inrMon !== null)
                        <span class="text-white/50 font-normal"> / {{ $__inrMon }}</span>
                    @else
                        <span class="text-white/30 font-normal"> / —</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Annual</span>
                <span class="font-semibold text-white">
                    {{ $__usdAnn }}
                    @if($__inrAnn !== null)
                        <span class="text-white/50 font-normal"> / {{ $__inrAnn }}</span>
                    @else
                        <span class="text-white/30 font-normal"> / —</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Trial Days</span>
                <span class="text-white">{{ $plan->trial_days }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Users</span>
                <span class="text-white">{{ $plan->users_count }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-white/5">
            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-white/30 hover:text-blue-400" title="Edit"><i class="fas fa-edit"></i></a>
            <form action="{{ route('admin.plans.duplicate', $plan) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-blue-400" title="Duplicate (creates an internal, inactive copy)"><i class="fas fa-copy"></i></button>
            </form>
            <form action="{{ route('admin.plans.archive', $plan) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-amber-400" title="{{ $plan->is_archived ? 'Restore' : 'Archive (existing subscribers keep their plan)' }}">
                    <i class="fas {{ $plan->is_archived ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                </button>
            </form>
            @if($plan->users_count === 0)
            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this plan?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button type="submit" class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
            @endif
        </div>
    </div>
