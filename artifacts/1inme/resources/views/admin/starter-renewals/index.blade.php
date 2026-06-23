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

    {{-- Due / lapsed users with a per-user manual nudge --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-white">Users due for renewal</h2>
            <p class="text-xs text-white/50 mt-0.5">
                Free-Starter users whose yearly window is lapsing within 30 days or has already lapsed — most urgent first.
                Use <strong>Send reminder</strong> to nudge one now (email + in-app), the same message the daily job sends.
            </p>
        </div>

        @if($dueUsers->total() > 0)
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] uppercase tracking-wide text-white/40 bg-white/[0.03]">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">User</th>
                            <th class="px-4 py-2.5 font-semibold">Window ends</th>
                            <th class="px-4 py-2.5 font-semibold">Last reminded</th>
                            <th class="px-4 py-2.5 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.06]">
                        @foreach($dueUsers as $due)
                            @php
                                $endsAt = $due->starter_free_window_ends_at;
                                $isLapsed = $endsAt && $endsAt->isPast();
                            @endphp
                            <tr class="hover:bg-white/[0.02]">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-white truncate max-w-[220px]">{{ $due->name ?: 'Unnamed user' }}</div>
                                    <div class="text-xs text-white/50 truncate max-w-[220px]">{{ $due->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($endsAt)
                                        <div class="text-white/80">{{ $endsAt->format('M j, Y') }}</div>
                                        <div class="text-[11px] {{ $isLapsed ? 'text-red-300' : 'text-amber-300' }}">
                                            {{ $isLapsed ? 'Lapsed ' : 'Due ' }}{{ $endsAt->diffForHumans() }}
                                        </div>
                                    @else
                                        <span class="text-white/40">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($due->starter_renewal_reminder_sent_at)
                                        <div class="text-white/70">{{ $due->starter_renewal_reminder_sent_at->format('M j, Y') }}</div>
                                        <div class="text-[11px] text-white/40">{{ $due->starter_renewal_reminder_sent_at->diffForHumans() }}</div>
                                    @else
                                        <span class="text-white/40">Never</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.starter-renewals.users.send', $due->id) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-500/15 hover:bg-violet-500/25 border border-violet-400/30 text-violet-200 text-xs font-semibold transition">
                                            <i class="fas fa-paper-plane text-[10px]"></i> Send reminder
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $dueUsers->links() }}</div>
        @else
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-6 text-center text-sm text-white/50">
                No free-Starter users are due for renewal right now.
            </div>
        @endif
    </div>

    {{-- Preview a specific user --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-white">Preview a specific user</h2>
            <p class="text-xs text-white/50 mt-0.5">
                Enter a user ID or email to preview the exact reminder that user is due to receive — their real
                free-window end date and a working signed renew link. Leave blank to preview the generic sample.
            </p>
        </div>
        <form method="GET" action="{{ route('admin.starter-renewals.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="User ID or email"
                   class="flex-1 min-w-[220px] px-3 py-2 rounded-lg bg-white/[0.04] border border-white/15 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400/60">
            <button type="submit"
                    class="px-4 py-2 rounded-lg bg-violet-500/20 hover:bg-violet-500/30 border border-violet-400/30 text-violet-100 text-sm font-semibold transition">
                <i class="fas fa-magnifying-glass mr-1.5"></i> Preview this user
            </button>
            @if($search !== '')
                <a href="{{ route('admin.starter-renewals.index') }}"
                   class="px-4 py-2 rounded-lg bg-white/[0.04] hover:bg-white/10 border border-white/15 text-white/70 text-sm font-semibold transition">
                    Clear
                </a>
            @endif
        </form>

        @if($selectedUser)
            <div class="rounded-xl border border-emerald-400/30 bg-emerald-500/10 p-3 text-sm text-emerald-100">
                <i class="fas fa-circle-check mr-1.5"></i>
                Previewing the reminder for
                <strong>{{ $selectedUser->name ?: 'this user' }}</strong>
                (<span class="text-emerald-200/90">{{ $selectedUser->email }}</span>, ID {{ $selectedUser->id }}).
                @if($selectedEndsAt)
                    Free window ends <strong>{{ $selectedEndsAt->format('F j, Y') }}</strong>.
                @endif
            </div>
        @elseif($searchNotFound)
            <div class="rounded-xl border border-amber-400/30 bg-amber-500/10 p-3 text-sm text-amber-100">
                <i class="fas fa-triangle-exclamation mr-1.5"></i>
                No user found matching <strong>{{ $search }}</strong>. Showing the generic sample below instead.
            </div>
        @endif
    </div>

    {{-- Previews --}}
    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Email preview --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Email preview</h2>
                <p class="text-xs text-white/50 mt-0.5">
                    @if($selectedUser)
                        The exact reminder email {{ $selectedUser->name ?: $selectedUser->email }} will receive.
                    @else
                        The exact reminder email, rendered with sample data.
                    @endif
                </p>
            </div>
            <div class="rounded-xl overflow-hidden border border-white/10 bg-white">
                <iframe src="{{ route('admin.starter-renewals.preview-email', $selectedUser ? ['q' => $search] : []) }}"
                        title="Reminder email preview"
                        class="w-full"
                        style="height: 520px; border: 0;"></iframe>
            </div>
            <div>
                <a href="{{ route('admin.starter-renewals.preview-email', $selectedUser ? ['q' => $search] : []) }}" target="_blank" rel="noopener"
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
