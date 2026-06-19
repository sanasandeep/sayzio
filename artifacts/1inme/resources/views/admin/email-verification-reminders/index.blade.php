@extends('admin.layouts.app')
@section('title', 'Verification Reminders')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-xl font-semibold text-white">Email verification reminders</h1>
        <p class="text-sm text-white/60 mt-1">
            A scheduled job emails users who still haven't verified their email a gentle, rate-limited nudge. Tune how often it runs — or switch it off entirely — below. Reminders always stop the moment a user verifies, respect each user's email preference, and never apply when email verification isn't meaningful under your login policy.
        </p>
    </div>

    @unless($verificationMeaningful)
        <div class="flex items-start gap-2 p-3 rounded-xl border border-amber-400/30 bg-amber-500/10">
            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-300"></i>
            <div class="text-xs text-amber-200">
                Email verification isn't meaningful under your current login policy (no email login method is enabled), so these reminders won't send regardless of the settings below.
            </div>
        </div>
    @endunless

    <div class="glass rounded-2xl p-6 space-y-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-white">Impact</h2>
                <p class="text-xs text-white/50">How the reminders are landing, so you can judge whether the cadence is too aggressive or too quiet.</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['unverifiedActive']) }}</div>
                <div class="text-xs text-white/60 mt-1">Active users still unverified</div>
                <div class="text-[11px] text-white/40 mt-0.5">The pool reminders can target.</div>
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

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['remindedLast30Days']) }}</div>
                <div class="text-xs text-white/60 mt-1">Users reminded in last 30 days</div>
                <div class="text-[11px] text-white/40 mt-0.5">Distinct users, by their most recent reminder.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white">{{ number_format($stats['cappedUnverified']) }}</div>
                <div class="text-xs text-white/60 mt-1">Hit the reminder cap (still unverified)</div>
                <div class="text-[11px] text-white/40 mt-0.5">Won't be reminded again unless the cap is raised.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-emerald-300">{{ number_format($stats['converted']) }}</div>
                <div class="text-xs text-white/60 mt-1">Verified after a reminder</div>
                <div class="text-[11px] text-white/40 mt-0.5">Received at least one reminder, then verified.</div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.email-verification-reminders.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="glass rounded-2xl p-6 space-y-4">
            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox"
                       name="enabled"
                       value="1"
                       @checked(old('enabled', $enabled))
                       class="mt-1 w-5 h-5 accent-violet-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Send periodic verification reminders</span>
                        @if($enabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        When off, the scheduled job becomes a no-op and no reminder emails are sent. The in-app banner shown to signed-in users is unaffected.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Cadence</h2>
                <p class="text-xs text-white/50">Controls when and how often a user is reminded.</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1" for="grace_days">Grace period (days)</label>
                    <input type="number" id="grace_days" name="grace_days"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_GRACE_DAYS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_GRACE_DAYS }}"
                           value="{{ old('grace_days', $graceDays) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <p class="text-xs text-white/40 mt-1">Wait this long after sign-up before the first reminder.</p>
                    @error('grace_days')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1" for="interval_days">Interval (days)</label>
                    <input type="number" id="interval_days" name="interval_days"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_INTERVAL_DAYS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_INTERVAL_DAYS }}"
                           value="{{ old('interval_days', $intervalDays) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <p class="text-xs text-white/40 mt-1">Minimum gap between two reminders to the same user.</p>
                    @error('interval_days')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1" for="max_reminders">Total cap</label>
                    <input type="number" id="max_reminders" name="max_reminders"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_MAX_REMINDERS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_MAX_REMINDERS }}"
                           value="{{ old('max_reminders', $maxReminders) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <p class="text-xs text-white/40 mt-1">Most reminders a single user will ever receive.</p>
                    @error('max_reminders')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold transition">
                <i class="fas fa-save mr-1.5"></i> Save settings
            </button>
        </div>
    </form>
</div>
@endsection
