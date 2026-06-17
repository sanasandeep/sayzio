@extends('user.layouts.app', ['pageTitle' => 'Adult content (18+)'])

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">

    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-2">
            <i class="fas fa-check-circle mt-0.5"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-2">
            <i class="fas fa-circle-exclamation mt-0.5"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Adult content (18+)</h1>
    <p class="text-sm mt-1" style="color: var(--text-muted);">
        Toggle 18+ content on your profile. Visitors will see an age-verification screen before
        viewing your profile, and the directory will only surface your profile to viewers who have
        opted in to adult content. <strong>1INME takes 0%</strong> &mdash; you'll receive 100% of
        what your fans pay (minus your processor's fee).
    </p>

    @if($isSuspended)
        <div class="mt-6 p-5 rounded-2xl border border-rose-200 bg-rose-50">
            <div class="flex items-start gap-3">
                <i class="fas fa-shield-halved text-rose-600 mt-1"></i>
                <div>
                    <h3 class="font-bold text-rose-900">Adult flag suspended by moderation</h3>
                    <p class="text-sm text-rose-800 mt-1">
                        @if($user->adult_flag_suspended_reason)
                            Reason: <em>{{ $user->adult_flag_suspended_reason }}</em>
                        @else
                            A moderator has suspended this profile's 18+ tag.
                        @endif
                        Contact support if you believe this was in error.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div x-data="{ open: {{ $isEnabled ? 'true' : 'false' }}, age: false, legal: false, processor: false }"
         class="mt-6 p-6 rounded-2xl border shadow-sm" style="background: var(--bg-card); border-color: var(--border-glass);">

        <div class="flex items-center justify-between gap-4 mb-2">
            <div>
                <div class="text-base font-bold" style="color: var(--text-primary);">Enable 18+ on my profile</div>
                <p class="text-xs mt-1" style="color: var(--text-muted);">
                    @if($isEnabled)
                        Enabled {{ $user->adult_content_enabled_at?->diffForHumans() }}.
                        Last age affirmation {{ $user->age_verified_at?->diffForHumans() }}.
                    @else
                        Off &mdash; your profile is treated as SFW.
                    @endif
                </p>
            </div>
            <button type="button" @click="open = !open"
                    :class="open ? 'bg-rose-600' : 'bg-slate-300'"
                    class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors">
                <span :class="open ? 'translate-x-6' : 'translate-x-1'"
                      class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"></span>
            </button>
        </div>

        <form method="POST" action="{{ route('user.adult-content.update') }}" class="mt-4">
            @csrf
            <input type="hidden" name="enable" :value="open ? '1' : '0'">

            <template x-if="open && !{{ $isEnabled ? 'true' : 'false' }}">
                <div class="space-y-3 mt-3 p-4 rounded-xl bg-rose-50/50 border border-rose-100">
                    <p class="text-sm font-semibold text-rose-900">
                        Please confirm all three statements:
                    </p>
                    <label class="flex items-start gap-3 text-sm" style="color: var(--text-secondary);">
                        <input type="checkbox" x-model="age" name="confirm_age" value="1"
                               class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                        <span>I am at least <strong>18 years old</strong> (or the age of majority in
                            my jurisdiction) and have legal authority to publish adult content.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm" style="color: var(--text-secondary);">
                        <input type="checkbox" x-model="legal" name="confirm_legal" value="1"
                               class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                        <span>The content I will publish does <strong>NOT include illegal material
                            or minors</strong>, and complies with the laws of my jurisdiction.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm" style="color: var(--text-secondary);">
                        <input type="checkbox" x-model="processor" name="confirm_processor" value="1"
                               class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                        <span>I understand my <strong>default payout will be locked to an
                            adult-friendly processor</strong> (CCBill or Segpay), and Stripe / PayPal
                            / Razorpay can't accept adult merchant accounts.</span>
                    </label>
                </div>
            </template>

            <template x-if="open && {{ $isEnabled ? 'true' : 'false' }}">
                <input type="hidden" name="confirm_age" value="1"><!-- already affirmed -->
            </template>

            <div class="mt-5 flex items-center gap-3 flex-wrap">
                <button type="submit"
                        :class="open ? 'bg-rose-600 hover:bg-rose-500' : 'bg-slate-700 hover:bg-slate-600'"
                        class="px-5 py-2.5 rounded-lg text-white text-sm font-semibold">
                    <span x-show="open && !{{ $isEnabled ? 'true' : 'false' }}">
                        <i class="fas fa-check"></i> Enable 18+ on my profile
                    </span>
                    <span x-show="open && {{ $isEnabled ? 'true' : 'false' }}">
                        <i class="fas fa-check"></i> Save changes
                    </span>
                    <span x-show="!open">
                        <i class="fas fa-power-off"></i> Disable 18+ on my profile
                    </span>
                </button>
                <a href="{{ route('user.payouts.show') }}"
                   class="text-xs inline-flex items-center gap-1" style="color: var(--text-muted);">
                    Manage payout providers <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </form>
    </div>

    {{-- Adult-friendly processors callout --}}
    <div class="mt-6 p-5 rounded-2xl border" style="background: var(--bg-glass); border-color: var(--border-glass);">
        <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);">Adult-friendly processors</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($adultProviders as $p)
                <div class="p-3 rounded-lg border" style="background: var(--bg-card); border-color: var(--border-glass);">
                    <div class="font-semibold inline-flex items-center gap-2" style="color: var(--text-secondary);">
                        <i class="{{ $p['icon'] }}" style="color: {{ $p['tint'] }};"></i>
                        {{ $p['name'] }}
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-muted);">{{ $p['short'] }}</p>
                    <p class="text-[11px] mt-2" style="color: var(--text-muted);">{{ $p['fees'] }}</p>
                </div>
            @endforeach
        </div>
        @if(!$hasAdultProvider && $isEnabled)
            <a href="{{ route('user.payouts.show') }}"
               class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold">
                <i class="fas fa-plug"></i> Connect an adult-friendly processor
            </a>
        @endif
    </div>
</div>
@endsection
