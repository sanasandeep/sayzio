@extends('admin.layouts.app')
@section('title', 'Free Renewal Reminders')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('info'))
        <div class="px-3 py-2 bg-sky-500/10 border border-sky-400/30 text-sky-200 rounded-lg text-sm">
            {{ session('info') }}
        </div>
    @endif

    @if(session('error'))
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-xl font-semibold text-white">Free plan renewal reminders</h1>
        <p class="text-sm text-white/60 mt-1">
            Once a year, free Starter users get an email and an in-app nudge to re-confirm their plan, with a one-click
            <strong>“renew free for another year”</strong> action. It's a reminder only — lapsing never locks an account,
            downgrades anything, or removes data. Preview exactly what users see and send yourself a test below before any
            copy or branding change goes out.
        </p>
    </div>

    {{-- Impact / who's due --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-white">Who's due</h2>
            <p class="text-xs text-white/50">
                Free-Starter users with an email and a tracked yearly window@if($stats['defaultPlanName']) (plan: <span class="text-white/70">{{ $stats['defaultPlanName'] }}</span>)@endif.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-amber-300">{{ number_format($stats['dueNext30Days']) }}</div>
                <div class="text-xs text-white/60 mt-1">Due for renewal in next 30 days</div>
                <div class="text-[11px] text-white/40 mt-0.5">Free windows lapsing soon.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['onStarter']) }}</div>
                <div class="text-xs text-white/60 mt-1">Free-Starter users tracked</div>
                <div class="text-[11px] text-white/40 mt-0.5">The pool reminders can target.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['lapsed']) }}</div>
                <div class="text-xs text-white/60 mt-1">Windows already lapsed</div>
                <div class="text-[11px] text-white/40 mt-0.5">Still active — just awaiting re-confirmation.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['remindedLast30Days']) }}</div>
                <div class="text-xs text-white/60 mt-1">Reminded in last 30 days</div>
                <div class="text-[11px] text-white/40 mt-0.5">Distinct users, by their window stamp.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['lastRunCount']) }}</div>
                <div class="text-xs text-white/60 mt-1">Reminded on the last run</div>
                <div class="text-[11px] text-white/40 mt-0.5">
                    @if($stats['lastRunAt'])
                        {{ $stats['lastRunAt']->diffForHumans() }}
                    @else
                        No reminders sent yet.
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Previews --}}
    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Email preview --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Email preview</h2>
                <p class="text-xs text-white/50 mt-0.5">The exact reminder email, rendered with sample data.</p>
            </div>
            <div class="rounded-xl overflow-hidden border border-white/10 bg-white">
                <iframe src="{{ route('admin.starter-renewals.preview-email') }}"
                        title="Reminder email preview"
                        class="w-full"
                        style="height: 520px; border: 0;"></iframe>
            </div>
            <div>
                <a href="{{ route('admin.starter-renewals.preview-email') }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-300 hover:text-violet-200">
                    <i class="fas fa-up-right-from-square text-[10px]"></i> Open email preview in a new tab
                </a>
            </div>
        </div>

        {{-- In-app notification preview --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">In-app notification preview</h2>
                <p class="text-xs text-white/50 mt-0.5">How the nudge appears in a user's notifications feed.</p>
            </div>

            <div class="rounded-2xl border divide-y bg-white" style="border-color: #e2e8f0;">
                <div class="relative p-4 flex items-start gap-3" style="background: rgba(124,58,237,0.04);">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                         style="background: rgba(124,58,237,0.12); color:#7c3aed;">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold" style="color:#1e293b;">{{ $inApp['title'] }}</p>
                        <p class="text-sm" style="color:#334155;">{{ $inApp['message'] }}</p>
                        <p class="text-xs mt-1" style="color:#94a3b8;">just now</p>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-white/40">
                The feed row links to the user's dashboard, where the matching “Renew free” banner lives.
            </p>
        </div>
    </div>

    {{-- Test send --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-white">Send yourself a test</h2>
            <p class="text-xs text-white/50 mt-0.5">
                Sends the real reminder email — and, if your admin login has a matching user account, an in-app
                notification — to you{{ $adminEmail ? ' (' . $adminEmail . ')' : '' }} through the same mailer the live
                job uses. Nothing is sent to real users.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.starter-renewals.sample') }}">
            @csrf
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/15 text-white text-sm font-semibold transition">
                <i class="fas fa-paper-plane mr-1.5"></i> Send test reminder to me
            </button>
        </form>
    </div>
</div>
@endsection
