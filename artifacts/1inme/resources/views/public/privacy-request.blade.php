@extends('public.layouts.site')
@section('title', 'Privacy data request')

@php
    $shareTitle = 'Privacy data request';
    $shareDescription = 'Request a copy of your data or the permanent deletion of your account.';
    $isDeletion = ($type ?? 'export') === 'deletion';
    $verifyResult = $verifyResult ?? null;
    $submitted = $submitted ?? false;
    $autoVerified = $autoVerified ?? false;
@endphp

@section('content')
<section class="relative pt-24 pb-20 lg:pt-32 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center" data-anim="fade-up">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-400/20 text-xs text-emerald-300 uppercase tracking-wider font-semibold">
                <i class="fas fa-user-shield text-[10px]"></i> Privacy
            </span>
            <h1 class="mt-5 text-3xl sm:text-4xl font-bold tracking-tight">
                {{ $isDeletion ? 'Delete your account & data' : 'Export your data' }}
            </h1>
            <p class="mt-4 text-gray-400 leading-relaxed">
                @if($isDeletion)
                    Request permanent deletion of your {{ config('app.name') }} account and the personal data
                    associated with it. We'll confirm it's really you, then a team member reviews the request.
                @else
                    Request a downloadable copy of all the data {{ config('app.name') }} holds about you. We'll
                    confirm it's really you, then prepare a secure archive and email you a link.
                @endif
            </p>
        </div>

        {{-- ---- Email-verification result states ---- --}}
        @if($verifyResult === 'ok')
            <div class="mt-8 rounded-2xl px-5 py-5 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200" data-anim="fade-up">
                <div class="font-bold flex items-center gap-2"><i class="fas fa-circle-check"></i> Email confirmed</div>
                <p class="text-sm mt-1.5 text-emerald-100/90">
                    Thanks — your request is now verified and queued for review. We'll email you as soon as a
                    decision is made. You can close this page.
                </p>
            </div>
        @elseif($verifyResult === 'expired')
            <div class="mt-8 rounded-2xl px-5 py-5 bg-amber-500/10 border border-amber-400/30 text-amber-200" data-anim="fade-up">
                <div class="font-bold flex items-center gap-2"><i class="fas fa-clock"></i> Link expired</div>
                <p class="text-sm mt-1.5 text-amber-100/90">
                    That verification link has expired. Please submit a new request below and we'll send a fresh link.
                </p>
            </div>
        @elseif($verifyResult === 'invalid')
            <div class="mt-8 rounded-2xl px-5 py-5 bg-red-500/10 border border-red-400/30 text-red-200" data-anim="fade-up">
                <div class="font-bold flex items-center gap-2"><i class="fas fa-circle-xmark"></i> Link not valid</div>
                <p class="text-sm mt-1.5 text-red-100/90">
                    This verification link is invalid or has already been used. If you still need to make a
                    request, you can submit a new one below.
                </p>
            </div>
        @endif

        {{-- ---- Post-submit confirmation ---- --}}
        @if($submitted)
            <div class="mt-8 rounded-2xl px-5 py-6 bg-white/[0.03] border border-white/10" data-anim="fade-up">
                <div class="w-12 h-12 rounded-full bg-emerald-500/15 border border-emerald-400/30 flex items-center justify-center text-emerald-300 mx-auto">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h2 class="text-lg font-bold text-white text-center mt-4">Request received</h2>
                @if($autoVerified)
                    <p class="text-sm text-gray-400 text-center mt-2 leading-relaxed">
                        Because you're signed in, we've already confirmed your identity. Your request is now queued
                        for review and you'll be emailed at each step.
                    </p>
                @else
                    <p class="text-sm text-gray-400 text-center mt-2 leading-relaxed">
                        If the email you entered matches an account, we've sent a verification link to it. Please open
                        that email and confirm the request. The link expires in 48 hours. For your security we don't
                        reveal whether an account exists.
                    </p>
                @endif
                <div class="text-center mt-5">
                    <a href="{{ route('site.contact') }}" class="text-sm text-blue-300 hover:text-blue-200 font-semibold">
                        <i class="fas fa-arrow-left mr-1"></i> Back to contact
                    </a>
                </div>
            </div>
        @else
            {{-- ---- Request form ---- --}}
            @if($errors->any())
                <div class="mt-8 rounded-xl px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-300" data-anim="fade-up">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('privacy.submit') }}"
                  class="mt-8 space-y-5 bg-white/[0.03] border border-white/10 rounded-2xl p-6" data-anim="fade-up"
                  x-data="{ type: '{{ $isDeletion ? 'deletion' : 'export' }}' }">
                @csrf
                {{-- honeypot --}}
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-gray-400">Request type</label>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                               :class="type === 'export' ? 'border-blue-400/50 bg-blue-500/10' : 'border-white/10 bg-white/[0.02]'">
                            <input type="radio" name="type" value="export" x-model="type" class="mt-1 accent-blue-500">
                            <span>
                                <span class="block text-sm font-bold text-white">Export my data</span>
                                <span class="block text-xs text-gray-400 mt-0.5">Download a copy of your data.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                               :class="type === 'deletion' ? 'border-red-400/50 bg-red-500/10' : 'border-white/10 bg-white/[0.02]'">
                            <input type="radio" name="type" value="deletion" x-model="type" class="mt-1 accent-red-500">
                            <span>
                                <span class="block text-sm font-bold text-white">Delete my account</span>
                                <span class="block text-xs text-gray-400 mt-0.5">Permanently remove everything.</span>
                            </span>
                        </label>
                    </div>
                </div>

                @if($currentUser)
                    <div class="rounded-xl px-4 py-3 text-sm bg-emerald-500/10 border border-emerald-400/30 text-emerald-200">
                        <i class="fas fa-circle-check mr-1.5"></i>
                        You're signed in as <span class="font-semibold">{{ $currentUser->email }}</span>. This request will apply to your account and skip email verification.
                    </div>
                    <input type="hidden" name="email" value="{{ $currentUser->email }}">
                @else
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Your account email</label>
                        <input type="email" name="email" required value="{{ old('email') }}"
                               placeholder="you@example.com"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                        <p class="text-xs text-gray-500 mt-1.5">We'll send a verification link here to confirm it's you.</p>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Reason <span class="text-gray-600 normal-case">(optional)</span></label>
                    <textarea name="reason" rows="3" maxlength="2000"
                              placeholder="Anything you'd like us to know"
                              class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('reason') }}</textarea>
                </div>

                <div x-show="type === 'deletion'" x-cloak
                     class="rounded-xl px-4 py-3 text-xs bg-red-500/10 border border-red-500/30 text-red-200 leading-relaxed">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    Account deletion is permanent and cannot be undone. Once approved, your account is removed after a
                    {{ \App\Modules\Common\Models\PrivacyRequest::DELETION_GRACE_DAYS }}-day cooling-off period.
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-lg text-sm font-bold text-white transition"
                        :class="type === 'deletion' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'">
                    Submit request
                </button>
                <p class="text-[11px] text-gray-500 text-center leading-relaxed">
                    We process verified requests within 30 days, as required by data-protection law.
                </p>
            </form>
        @endif
    </div>
</section>
@endsection
