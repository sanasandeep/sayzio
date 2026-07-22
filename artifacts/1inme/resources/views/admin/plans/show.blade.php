@extends('admin.layouts.app')
@section('title', $plan->name)

@php
    use App\Modules\Common\Support\PlanFormCatalogue;
    use App\Modules\User\Models\BiolinkBlock;
    use App\Modules\User\Support\IntegrationConfigRegistry;

    $features    = $plan->features ?? [];
    $modules     = PlanFormCatalogue::modules();
    $quantities  = PlanFormCatalogue::quantityLimits();
    $featureFlags= PlanFormCatalogue::featureFlags();
    $aiSuite     = PlanFormCatalogue::aiSuite();
    $integrationKinds = IntegrationConfigRegistry::kinds();

    $fmt = function ($v) {
        if ($v === -1) return 'Unlimited';
        if ($v === null || $v === '') return '-';
        return (string) $v;
    };
    $bool = fn($v) => !empty($v)
        ? '<span class="text-emerald-400 ak-green">Yes</span>'
        : '<span class="text-red-400 ak-red">No</span>';

    $blockAllowed = $features['block_types_allowed'] ?? '*';
    $intCaps      = (array) ($features['integration_accounts_max'] ?? []);
    $intAllowed   = (array) ($features['integration_providers_allowed'] ?? []);
    $storageMb    = (int) ($features['storage_limit_mb'] ?? 0);
@endphp

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.plans.index') }}" class="text-white/30 hover:text-white/50 ak-note"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-white ak-strong">{{ $plan->name }}</h1>
    <a href="{{ route('admin.plans.edit', $plan) }}" class="ml-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Edit Plan</a>
</div>

<div class="space-y-6 max-w-5xl">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glass rounded-2xl border border-white/10 p-6">
            <h2 class="text-base font-semibold text-white mb-4 ak-strong">Plan Details</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Status</dt><dd><span class="px-2 py-0.5 rounded text-xs {{ $plan->status === 'active' ? 'bg-emerald-500/15 text-emerald-300 ak-green' : 'bg-red-500/15 text-red-300 ak-red' }}">{{ ucfirst($plan->status) }}</span></dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Monthly (USD)</dt><dd class="font-medium text-white ak-strong">${{ number_format($plan->monthly_price, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Annual (USD)</dt><dd class="font-medium text-white ak-strong">${{ number_format($plan->annual_price, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Monthly (INR)</dt><dd class="font-medium text-white ak-strong">₹{{ number_format($plan->monthly_price_secondary ?? 0, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Annual (INR)</dt><dd class="font-medium text-white ak-strong">₹{{ number_format($plan->annual_price_secondary ?? 0, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Trial Days</dt><dd class="text-white ak-strong">{{ $plan->trial_days }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Grace Days</dt><dd class="text-white ak-strong">{{ $plan->grace_days ?? 7 }}</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Refund Window</dt><dd class="text-white ak-strong">{{ $plan->refund_window_days ?? 7 }} days</dd></div>
                <div class="flex justify-between"><dt class="text-white/40 ak-note">Active Users</dt><dd class="text-white ak-strong">{{ $plan->users_count }}</dd></div>
            </dl>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6">
            <h2 class="text-base font-semibold text-white mb-4 ak-strong">Modules</h2>
            <dl class="space-y-1.5 text-sm">
                @foreach($modules as $mk => $m)
                    @php $on = !array_key_exists($mk, $features) ? true : (bool) $features[$mk]; @endphp
                    <div class="flex justify-between">
                        <dt class="text-white/60 ak-muted">{{ $m['label'] }}</dt>
                        <dd>{!! $bool($on) !!}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-base font-semibold text-white mb-4 ak-strong">Quantity limits</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            @foreach($quantities as $q)
                @php $qv = $features[$q['key']] ?? $q['default']; @endphp
                <div class="flex justify-between border border-white/5 rounded-lg px-3 py-2 bg-white/[0.02]">
                    <span class="text-white/60 ak-muted">{{ $q['label'] }}</span>
                    @if(is_array($qv))
                        {{-- max_aliases_per_link can be a per-type map: show the
                             default plus any overrides as a compact summary. --}}
                        <span class="text-white font-medium text-right ak-strong">
                            {{ $fmt($qv['default'] ?? 0) }}
                            <span class="text-white/40 text-[10px] block ak-note">
                                @foreach(array_diff_key($qv, ['default' => true]) as $t => $tv){{ $t }}: {{ $fmt($tv) }}@if(!$loop->last), @endif @endforeach
                            </span>
                        </span>
                    @else
                        <span class="text-white font-medium ak-strong">{{ $fmt($qv) }}</span>
                    @endif
                </div>
            @endforeach
            <div class="flex justify-between border border-white/5 rounded-lg px-3 py-2 bg-white/[0.02]">
                <span class="text-white/60 ak-muted">Max workspaces</span>
                <span class="text-white font-medium ak-strong">{{ $fmt($features['max_workspaces'] ?? 1) }}</span>
            </div>
            <div class="flex justify-between border border-white/5 rounded-lg px-3 py-2 bg-white/[0.02]">
                <span class="text-white/60 ak-muted">Max seats / workspace</span>
                <span class="text-white font-medium ak-strong">{{ $fmt($features['max_seats_per_workspace'] ?? 1) }}</span>
            </div>
            <div class="flex justify-between border border-white/5 rounded-lg px-3 py-2 bg-white/[0.02]">
                <span class="text-white/60 ak-muted">Storage</span>
                <span class="text-white font-medium ak-strong">
                    @if($storageMb === -1) Unlimited
                    @elseif($storageMb > 0) {{ $storageMb }} MB <span class="text-white/40 ak-note">≈ {{ number_format($storageMb / 1024, 2) }} GB</span>
                    @else 0
                    @endif
                </span>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-base font-semibold text-white mb-4 ak-strong">Link in Bio block allowlist</h2>
        @if($blockAllowed === '*' || $blockAllowed === null)
            <p class="text-sm text-emerald-300 ak-green">All blocks allowed (<code>*</code>)</p>
        @elseif(is_array($blockAllowed))
            <p class="text-sm text-white/60 mb-3 ak-muted">{{ count($blockAllowed) }} block type(s) allowed:</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($blockAllowed as $slug)
                    @php $meta = BiolinkBlock::TYPES[$slug] ?? null; @endphp
                    <span class="text-[11px] px-2 py-1 rounded-md bg-white/5 border border-white/10 text-white/70 ak-strong">
                        @if($meta)<i class="fas {{ $meta['icon'] }} mr-1 text-white/40 ak-note"></i>{{ $meta['label'] }}@else{{ $slug }}@endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-base font-semibold text-white mb-4 ak-strong">Features &amp; analytics</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-sm">
            @foreach($featureFlags as $flag)
                <div class="flex justify-between px-3 py-1.5">
                    <dt class="text-white/60 ak-muted">{{ PlanFormCatalogue::labelFor($flag['key']) }}</dt>
                    <dd>
                        @if($flag['type'] === 'bool')
                            {!! $bool($features[$flag['key']] ?? false) !!}
                        @else
                            <span class="text-white ak-strong">{{ $features[$flag['key']] ?? '—' }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-base font-semibold text-white mb-4 ak-strong">AI suite</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-sm">
            @foreach($aiSuite as $row)
                <div class="flex justify-between px-3 py-1.5">
                    <dt class="text-white/60 ak-muted">{{ PlanFormCatalogue::labelFor($row['key']) }}</dt>
                    <dd>{!! $bool($features[$row['key']] ?? false) !!}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-base font-semibold text-white mb-4 ak-strong">Integration accounts</h2>
        <div class="space-y-3">
            @foreach($integrationKinds as $kind => $info)
                @php
                    $cap = $intCaps[$kind] ?? null;
                    $allow = $intAllowed[$kind] ?? '*';
                @endphp
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-white ak-strong">{{ $info['label'] }}</h3>
                        <span class="text-xs text-white/60 ak-muted">Cap: <span class="text-white font-medium ak-strong">{{ $fmt($cap) }}</span></span>
                    </div>
                    @if($allow === '*')
                        <p class="text-xs text-emerald-300 ak-green">All providers allowed (<code>*</code>)</p>
                    @elseif(is_array($allow))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($allow as $p)
                                <span class="text-[11px] px-2 py-1 rounded bg-white/5 border border-white/10 text-white/70 ak-strong">{{ $p }}</span>
                            @endforeach
                            @if(empty($allow))
                                <span class="text-[11px] text-red-300 ak-red">No providers allowed</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
