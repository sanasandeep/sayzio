@extends('admin.layouts.app')
@section('title', 'Referrals')
@section('page-title', 'Referrals')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Global toggle --}}
    <div class="glass rounded-2xl border border-white/10 p-5 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-semibold text-white">Referral program</h2>
            <p class="text-xs text-white/50 mt-1">Toggle the program globally. When off, the dashboard, /r/&lcub;code&rcub; route, and reward engine all stop.</p>
        </div>
        <form method="POST" action="{{ route('admin.referrals.toggle') }}">
            @csrf
            <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium {{ $enabled ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }} text-white">
                {{ $enabled ? 'Disable' : 'Enable' }}
            </button>
        </form>
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['Clicks', $totals['clicks'], 'fa-mouse-pointer'],
            ['Signups', $totals['signups'], 'fa-user-plus'],
            ['Conversions', $totals['conversions'], 'fa-check-circle'],
            ['Days granted', $totals['days_granted'], 'fa-gift'],
        ] as [$label, $val, $icon])
            <div class="glass rounded-xl p-4 border border-white/10">
                <div class="text-[11px] uppercase tracking-wider text-white/40 flex items-center gap-2"><i class="fas {{ $icon }}"></i> {{ $label }}</div>
                <div class="text-2xl font-bold text-white mt-1">{{ number_format($val) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Top referrers --}}
    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10 text-sm font-semibold text-white">Top referrers</div>
        @if($topReferrers->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-white/40">No referrers yet.</div>
        @else
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02]">
                <tr><th class="px-5 py-2 text-left">User</th><th class="px-5 py-2 text-left">Code</th><th class="px-5 py-2 text-right">Signups</th><th class="px-5 py-2 text-right">Conversions</th><th class="px-5 py-2 text-right">Days earned</th></tr>
            </thead>
            <tbody>
            @foreach($topReferrers as $u)
                <tr class="border-t border-white/5">
                    <td class="px-5 py-3 text-white/80">{{ $u->name }} <span class="text-white/30">{{ $u->email }}</span></td>
                    <td class="px-5 py-3 font-mono text-white/60">{{ $u->referral_code }}</td>
                    <td class="px-5 py-3 text-right text-white/80">{{ $u->signups }}</td>
                    <td class="px-5 py-3 text-right text-white/80">{{ $u->conversions }}</td>
                    <td class="px-5 py-3 text-right text-white/80">{{ $u->days_earned }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Recent conversions --}}
    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10 text-sm font-semibold text-white">Recent conversions</div>
        @if($recentConversions->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-white/40">No conversions yet.</div>
        @else
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02]">
                <tr><th class="px-5 py-2 text-left">Referrer</th><th class="px-5 py-2 text-left">Referred</th><th class="px-5 py-2 text-left">When</th></tr>
            </thead>
            <tbody>
            @foreach($recentConversions as $r)
                <tr class="border-t border-white/5">
                    <td class="px-5 py-3 text-white/80">{{ $r->referrer?->name }} <span class="text-white/30">{{ $r->referrer?->email }}</span></td>
                    <td class="px-5 py-3 text-white/80">{{ $r->referredUser?->name }} <span class="text-white/30">{{ $r->referredUser?->email }}</span></td>
                    <td class="px-5 py-3 text-white/60">{{ $r->converted_at?->diffForHumans() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
