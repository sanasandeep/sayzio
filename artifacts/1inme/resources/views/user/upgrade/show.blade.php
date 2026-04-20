@extends('user.layouts.app')
@section('title', 'Upgrade your plan')
@section('page-title', 'Upgrade')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <div class="text-center space-y-2">
        <h1 class="text-3xl font-semibold text-white">Pick the plan that fits your work</h1>
        <p class="text-white/60">All prices in <span class="font-medium text-white">{{ $currency }}</span>@if($user && $user->country) — based on your billing country (<span class="uppercase">{{ $user->country }}</span>). <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">Change country</a>@else. <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">Set your country</a> for accurate pricing@endif.</p>

        <div class="inline-flex rounded-full border border-white/10 bg-white/[0.02] p-1 mt-3">
            <a href="{{ route('user.upgrade', ['cycle' => 'monthly']) }}"
               class="px-4 py-1.5 text-sm rounded-full transition {{ $cycle === 'monthly' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white' }}">Monthly</a>
            <a href="{{ route('user.upgrade', ['cycle' => 'annual']) }}"
               class="px-4 py-1.5 text-sm rounded-full transition {{ $cycle === 'annual' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white' }}">Annual <span class="text-[10px] opacity-70">save 2 months</span></a>
        </div>

        @if(!$user || !$user->country)
        <form method="POST" action="{{ route('user.upgrade.switch-currency') }}" class="inline-flex items-center gap-2 mt-3 ml-3">
            @csrf
            <label class="text-xs text-white/40">Preview in:</label>
            <select name="currency" onchange="this.form.submit()" class="px-3 py-1 text-xs bg-white/5 border border-white/10 rounded-full text-white/80">
                <option value="USD" {{ $currency === 'USD' ? 'selected' : '' }} class="bg-[#0d0818]">USD ($)</option>
                <option value="INR" {{ $currency === 'INR' ? 'selected' : '' }} class="bg-[#0d0818]">INR (₹)</option>
            </select>
        </form>
        @endif
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
                    <p class="text-sm text-white/50 mt-2 min-h-[2.5rem]">{{ $plan->description }}</p>
                </div>

                @php $features = $plan->features ?? []; @endphp
                @if(!empty($features))
                <ul class="mt-4 space-y-1.5 text-sm text-white/70 flex-grow">
                    @foreach(['max_links' => 'links', 'max_biolinks' => 'bio pages', 'max_projects' => 'projects', 'storage_limit_mb' => 'MB storage', 'contacts_max' => 'contacts'] as $key => $label)
                        @if(isset($features[$key]))
                            <li class="flex items-start gap-2"><span class="text-violet-400">•</span><span>{{ $features[$key] == -1 ? 'Unlimited' : number_format((int)$features[$key]) }} {{ $label }}</span></li>
                        @endif
                    @endforeach
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
                    <div class="text-[10px] uppercase tracking-wider text-white/30 mt-2">{{ str_replace('_',' ',$a->type) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
