    <div class="glass rounded-2xl border border-white/10 p-6 {{ $plan->is_archived ? 'opacity-60' : '' }} relative">
        {{-- Compare & Edit checkbox (visible when selection mode is implied by planIndex scope) --}}
        <div class="absolute top-4 right-4 z-10" title="Select to compare">
            <input type="checkbox"
                   class="plan-card-checkbox"
                   :checked="selected['{{ $plan->id }}'] ?? false"
                   @change="toggle('{{ $plan->id }}')"
                   aria-label="Select {{ addslashes($plan->name) }} for comparison">
        </div>

        <div class="flex items-center justify-between mb-2 pr-7">
            <h3 class="font-semibold text-white flex items-center gap-2 flex-wrap ak-strong">
                {{ $plan->name }}
                @if($plan->is_popular)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-500/15 text-blue-300 ak-blue" title="Shown as Most Popular on the homepage">
                        <i class="fas fa-star mr-1"></i>Most Popular
                    </span>
                @endif
                @if($plan->is_internal)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/15 text-amber-300 ak-amber" title="Admin/staff-only, hidden from public pricing, upgrade and the recommender">
                        <i class="fas fa-user-shield mr-1"></i>Internal
                    </span>
                @endif
                @php $introBadge = $plan->introDiscount(); @endphp
                @if($introBadge)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-300 ak-green"
                          title="First-term intro discount active{{ !empty($introBadge['cycles']) ? ', ' . implode(', ', $introBadge['cycles']) : '' }}">
                        <i class="fas fa-bolt mr-1"></i>{{ $introBadge['type'] === 'percent' ? $introBadge['percent'] . '% intro' : 'Intro offer' }}
                    </span>
                @endif
            </h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $plan->status === 'active' && !$plan->is_archived ? 'bg-emerald-500/10 text-emerald-400 ak-green' : 'bg-white/10 text-white/60 ak-muted' }}">
                {{ $plan->is_archived ? 'Archived' : ucfirst($plan->status) }}
            </span>
        </div>
        <p class="text-sm text-white/40 mb-4 ak-note">{{ $plan->description ?? 'No description' }}</p>

        @php
            $__mon = \App\Services\PricingResolver::adminDisplayPair($plan, 'monthly');
            $__ann = \App\Services\PricingResolver::adminDisplayPair($plan, 'annual');
        @endphp
        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-white/40 ak-note">Monthly</span>
                <span class="font-semibold text-white ak-strong">
                    {{ $__mon['usd'] }}
                    @if($__mon['inr'] !== null)
                        <span class="text-white/50 font-normal ak-muted"> / {{ $__mon['inr'] }}</span>
                    @else
                        <span class="text-white/30 font-normal ak-note"> / - </span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40 ak-note">Annual</span>
                <span class="font-semibold text-white ak-strong">
                    {{ $__ann['usd'] }}
                    @if($__ann['inr'] !== null)
                        <span class="text-white/50 font-normal ak-muted"> / {{ $__ann['inr'] }}</span>
                    @else
                        <span class="text-white/30 font-normal ak-note"> / - </span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40 ak-note">Trial Days</span>
                <span class="text-white ak-strong">{{ $plan->trial_days }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40 ak-note">Users</span>
                <span class="text-white ak-strong">{{ $plan->users_count }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-white/5">
            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-white/30 hover:text-blue-400 ak-note" title="Edit"><i class="fas fa-edit"></i></a>
            <form action="{{ route('admin.plans.duplicate', $plan) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-blue-400 ak-note" title="Duplicate (creates an internal, inactive copy)"><i class="fas fa-copy"></i></button>
            </form>
            <form action="{{ route('admin.plans.archive', $plan) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-amber-400 ak-note" title="{{ $plan->is_archived ? 'Restore' : 'Archive (existing subscribers keep their plan)' }}">
                    <i class="fas {{ $plan->is_archived ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                </button>
            </form>
            @if($plan->users_count === 0)
            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this plan?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button type="submit" class="text-white/30 hover:text-red-400 ak-note" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
            @endif
        </div>
    </div>
