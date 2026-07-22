@extends('admin.layouts.app')
@section('title', 'Verification Reminders')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    @if(session('info'))
        <div class="px-3 py-2 bg-sky-500/10 border border-sky-400/30 text-sky-200 rounded-lg text-sm ak-blue">
            {{ session('info') }}
        </div>
    @endif

    @if(session('error'))
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm ak-red">
            {{ session('error') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-xl font-semibold text-white ak-strong">Email verification reminders</h1>
        <p class="text-sm text-white/60 mt-1 ak-muted">
            A scheduled job emails users who still haven't verified their email a gentle, rate-limited nudge. Tune how often it runs (or switch it off entirely) below. Reminders always stop the moment a user verifies, respect each user's email preference, and never apply when email verification isn't meaningful under your login policy.
        </p>
    </div>

    @unless($verificationMeaningful)
        <div class="flex items-start gap-2 p-3 rounded-xl border border-amber-400/30 bg-amber-500/10">
            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-300 ak-amber"></i>
            <div class="text-xs text-amber-200 ak-amber">
                Email verification isn't meaningful under your current login policy (no email login method is enabled), so these reminders won't send regardless of the settings below.
            </div>
        </div>
    @endunless

    <div class="glass rounded-2xl p-6 space-y-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-white ak-strong">Impact</h2>
                <p class="text-xs text-white/50 ak-muted">How the reminders are landing, so you can judge whether the cadence is too aggressive or too quiet.</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white ak-strong">{{ number_format($stats['unverifiedActive']) }}</div>
                <div class="text-xs text-white/60 mt-1 ak-muted">Active users still unverified</div>
                <div class="text-[11px] text-white/40 mt-0.5 ak-note">The pool reminders can target.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white ak-strong">{{ number_format($stats['lastRunCount']) }}</div>
                <div class="text-xs text-white/60 mt-1 ak-muted">Reminded on the last run</div>
                <div class="text-[11px] text-white/40 mt-0.5 ak-note">
                    @if($stats['lastRunAt'])
                        {{ $stats['lastRunAt']->diffForHumans() }}
                    @else
                        No reminders sent yet.
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white ak-strong">{{ number_format($stats['remindedLast30Days']) }}</div>
                <div class="text-xs text-white/60 mt-1 ak-muted">Users reminded in last 30 days</div>
                <div class="text-[11px] text-white/40 mt-0.5 ak-note">Distinct users, by their most recent reminder.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-white ak-strong">{{ number_format($stats['cappedUnverified']) }}</div>
                <div class="text-xs text-white/60 mt-1 ak-muted">Hit the reminder cap (still unverified)</div>
                <div class="text-[11px] text-white/40 mt-0.5 ak-note">Won't be reminded again unless the cap is raised.</div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-2xl font-bold text-emerald-300 ak-green">{{ number_format($stats['converted']) }}</div>
                <div class="text-xs text-white/60 mt-1 ak-muted">Verified after a reminder</div>
                <div class="text-[11px] text-white/40 mt-0.5 ak-note">Received at least one reminder, then verified.</div>
            </div>
        </div>

        @php
            $trendMax = collect($trend)->flatMap(fn ($w) => [$w['reminded'], $w['converted']])->max() ?: 0;
            $trendTotalReminded = collect($trend)->sum('reminded');
            $trendTotalConverted = collect($trend)->sum('converted');
        @endphp

        <div class="pt-5 mt-5 border-t border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-white ak-strong">Weekly trend</h3>
                    <p class="text-[11px] text-white/40 ak-note">
                        Last {{ count($trend) }} weeks, users reminded (by their most recent reminder) and verifications after a reminder.
                    </p>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-white/60 ak-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span> Reminded</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-400"></span> Converted</span>
                </div>
            </div>

            @if($trendMax === 0)
                <div class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-xs text-white/40 text-center ak-note">
                    No reminders or conversions recorded in this window yet.
                </div>
            @else
                <div class="mt-4 flex items-end justify-between gap-1.5 sm:gap-3 h-32">
                    @foreach($trend as $w)
                        <div class="flex-1 flex flex-col items-center justify-end h-full min-w-0">
                            <div class="flex-1 flex items-end justify-center gap-1 w-full" role="img"
                                 aria-label="{{ $w['label'] }}: {{ $w['reminded'] }} reminded, {{ $w['converted'] }} converted">
                                <div class="w-1/2 max-w-[14px] rounded-t bg-blue-500/80 hover:bg-blue-400 transition-colors"
                                     style="height: {{ $w['reminded'] > 0 ? max(4, round(($w['reminded'] / $trendMax) * 100)) : 0 }}%"
                                     title="{{ $w['reminded'] }} reminded"></div>
                                <div class="w-1/2 max-w-[14px] rounded-t bg-emerald-400/80 hover:bg-emerald-300 transition-colors"
                                     style="height: {{ $w['converted'] > 0 ? max(4, round(($w['converted'] / $trendMax) * 100)) : 0 }}%"
                                     title="{{ $w['converted'] }} converted"></div>
                            </div>
                            <div class="mt-1.5 text-[10px] text-white/40 truncate w-full text-center ak-note">{{ $w['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-[11px] text-white/40 ak-note">
                    {{ number_format($trendTotalReminded) }} reminded and {{ number_format($trendTotalConverted) }} verified over this window.
                </p>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm ak-red">
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
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white ak-strong">Send periodic verification reminders</span>
                        @if($enabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 ak-green">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5 ak-muted">
                        When off, the scheduled job becomes a no-op and no reminder emails are sent. The in-app banner shown to signed-in users is unaffected.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white ak-strong">Cadence</h2>
                <p class="text-xs text-white/50 ak-muted">Controls when and how often a user is reminded.</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="grace_days">Grace period (days)</label>
                    <input type="number" id="grace_days" name="grace_days"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_GRACE_DAYS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_GRACE_DAYS }}"
                           value="{{ old('grace_days', $graceDays) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                    <p class="text-xs text-white/40 mt-1 ak-note">Wait this long after sign-up before the first reminder.</p>
                    @error('grace_days')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="interval_days">Interval (days)</label>
                    <input type="number" id="interval_days" name="interval_days"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_INTERVAL_DAYS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_INTERVAL_DAYS }}"
                           value="{{ old('interval_days', $intervalDays) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                    <p class="text-xs text-white/40 mt-1 ak-note">Minimum gap between two reminders to the same user.</p>
                    @error('interval_days')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="max_reminders">Total cap</label>
                    <input type="number" id="max_reminders" name="max_reminders"
                           min="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MIN_MAX_REMINDERS }}"
                           max="{{ \App\Modules\Common\Support\EmailVerificationReminderSettings::MAX_MAX_REMINDERS }}"
                           value="{{ old('max_reminders', $maxReminders) }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                    <p class="text-xs text-white/40 mt-1 ak-note">Most reminders a single user will ever receive.</p>
                    @error('max_reminders')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition">
                <i class="fas fa-save mr-1.5"></i> Save settings
            </button>
        </div>
    </form>

    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-white ak-strong">Preview before going live</h2>
            <p class="text-xs text-white/50 mt-0.5 ak-muted">
                Send the exact reminder email (with sample verification and unsubscribe links) to your own address ({{ optional(auth('admin')->user())->email ?? auth()->user()?->email }}) to confirm it looks right and that your SMTP is delivering. It goes through the same mailer the real reminders use.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.email-verification-reminders.sample') }}">
            @csrf
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/15 text-white text-sm font-semibold transition ak-strong">
                <i class="fas fa-paper-plane mr-1.5"></i> Send sample to my email
            </button>
        </form>
    </div>
</div>
@endsection
