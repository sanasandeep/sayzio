@extends('user.layouts.app')
@section('title', 'Plans Management')

@section('content')
<div class="p-4 lg:p-8 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Subscription Plans</h1>
            <p class="text-sm mt-1" style="color: var(--text-dimmed);">Manage subscription plans and pricing</p>
        </div>
        <a href="{{ route('user.plans.create') }}" class="btn-primary inline-flex items-center gap-2 text-sm px-5 py-2.5">
            <i class="fas fa-plus text-xs"></i>
            <span>Add Plan</span>
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium bg-red-500/10 text-red-400 border border-red-500/20">
        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($plans as $plan)
        <div class="glass rounded-2xl p-6 relative overflow-hidden group hover:shadow-lg transition-all duration-300" style="border: 1px solid var(--border-glass);">
            @if($plan->is_default)
            <div class="absolute top-3 right-3">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-500/20 text-blue-400">Default</span>
            </div>
            @endif

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br
                    {{ $plan->monthly_price == 0 ? 'from-gray-500 to-gray-600' : ($plan->monthly_price < 20 ? 'from-blue-500 to-cyan-500' : ($plan->monthly_price < 50 ? 'from-blue-500 to-blue-500' : 'from-amber-500 to-orange-500')) }}">
                    <i class="fas {{ $plan->monthly_price == 0 ? 'fa-layer-group' : ($plan->monthly_price < 20 ? 'fa-rocket' : ($plan->monthly_price < 50 ? 'fa-crown' : 'fa-gem')) }} text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base" style="color: var(--text-primary);">{{ $plan->name }}</h3>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold
                        {{ $plan->status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-white/10 text-gray-400' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $plan->status === 'active' ? 'bg-emerald-400' : 'bg-gray-400' }}"></span>
                        {{ ucfirst($plan->status) }}
                    </span>
                </div>
            </div>

            <p class="text-xs mb-4 line-clamp-2" style="color: var(--text-dimmed);">{{ $plan->description ?? 'No description' }}</p>

            <div class="space-y-2.5 mb-5">
                <div class="flex justify-between items-baseline">
                    <span class="text-xs" style="color: var(--text-dimmed);">Monthly</span>
                    <span class="text-lg font-bold" style="color: var(--text-primary);">
                        ${{ number_format($plan->monthly_price, 2) }}
                    </span>
                </div>
                <div class="flex justify-between items-baseline">
                    <span class="text-xs" style="color: var(--text-dimmed);">Annual</span>
                    <span class="text-sm font-semibold" style="color: var(--text-muted);">
                        ${{ number_format($plan->annual_price, 2) }}<span class="text-[10px] font-normal">/yr</span>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs" style="color: var(--text-dimmed);">Trial</span>
                    <span class="text-sm font-medium" style="color: var(--text-muted);">{{ $plan->trial_days }} days</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs" style="color: var(--text-dimmed);">Users</span>
                    <span class="text-sm font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-users text-[10px] mr-1 opacity-50"></i>{{ $plan->users_count }}
                    </span>
                </div>
            </div>

            @php $features = $plan->features ?? []; @endphp
            @if(!empty($features))
            <div class="mb-4 pt-3" style="border-top: 1px solid var(--border-subtle);">
                <p class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Limits</p>
                <div class="flex flex-wrap gap-1.5">
                    @if(isset($features['max_links']))
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium" style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                        {{ $features['max_links'] }} links
                    </span>
                    @endif
                    @if(isset($features['max_biolinks']))
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium" style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                        {{ $features['max_biolinks'] }} Link in Bio pages
                    </span>
                    @endif
                    @if(isset($features['max_projects']))
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium" style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                        {{ $features['max_projects'] }} projects
                    </span>
                    @endif
                    @foreach(['custom_domains','qr_customization','pixels','teams','ecommerce','custom_branding'] as $fKey)
                        @if(!empty($features[$fKey]))
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-500/10 text-blue-400">
                            {{ str_replace('_', ' ', ucfirst($fKey)) }}
                        </span>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            <div class="flex items-center gap-2 pt-3" style="border-top: 1px solid var(--border-subtle);">
                <a href="{{ route('user.plans.edit', $plan) }}" class="flex-1 text-center text-xs font-semibold py-2 rounded-lg transition-all" style="background: var(--bg-glass-hover); color: var(--text-muted); border: 1px solid var(--border-subtle);"
                   onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                    <i class="fas fa-edit mr-1.5"></i>Edit
                </a>
                @if($plan->users_count === 0)
                <form action="{{ route('user.plans.destroy', $plan) }}" method="POST" class="flex-1" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this plan?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full text-center text-xs font-semibold py-2 rounded-lg transition-all bg-red-500/10 text-red-400 hover:bg-red-500/20" style="border: 1px solid rgba(239,68,68,0.15);">
                        <i class="fas fa-trash mr-1.5"></i>Delete
                    </button>
                </form>
                @else
                <div class="flex-1 text-center text-xs py-2 rounded-lg opacity-40 cursor-not-allowed" style="color: var(--text-faint);" title="Cannot delete, plan has users">
                    <i class="fas fa-lock mr-1"></i>In Use
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @if($plans->isEmpty())
    <div class="glass rounded-2xl p-12 text-center" style="border: 1px solid var(--border-glass);">
        <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-layer-group text-2xl text-blue-400"></i>
        </div>
        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No Plans Yet</h3>
        <p class="text-sm mb-4" style="color: var(--text-dimmed);">Create your first subscription plan to get started.</p>
        <a href="{{ route('user.plans.create') }}" class="btn-primary inline-flex items-center gap-2 text-sm px-5 py-2.5">
            <i class="fas fa-plus text-xs"></i> Create Plan
        </a>
    </div>
    @endif
</div>
@endsection
