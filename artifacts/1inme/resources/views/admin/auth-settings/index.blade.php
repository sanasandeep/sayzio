@extends('admin.layouts.app')
@section('title', 'Login & OTP')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-xl font-semibold text-white">Login &amp; OTP</h1>
        <p class="text-sm text-white/60 mt-1">
            Choose how users sign in. You can independently enable <strong class="text-white/80">email + password</strong> and a one-time code sent over <strong class="text-white/80">email</strong> — at least one of these must stay on. You can additionally let users sign in with a one-time code sent over <strong class="text-white/80">WhatsApp</strong>, restricted to an allow-list of country dialling codes.
        </p>
    </div>

    @if($errors->any())
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.auth-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Email login methods</h2>
                <p class="text-xs text-white/50">At least one of these must stay enabled so users can always reach their account by email.</p>
            </div>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="email_password_enabled" value="0">
                <input type="checkbox"
                       name="email_password_enabled"
                       value="1"
                       @checked(old('email_password_enabled', $emailPasswordEnabled))
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Enable email + password login</span>
                        @if($emailPasswordEnabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        Users set a password when they sign up and sign in with their email + password. (Password reset is not yet available.)
                    </p>
                </div>
            </label>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="email_otp_enabled" value="0">
                <input type="checkbox"
                       name="email_otp_enabled"
                       value="1"
                       @checked(old('email_otp_enabled', $emailOtpEnabled))
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Enable email one-time-code login</span>
                        @if($emailOtpEnabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        Users request a 6-digit code emailed to them each time they sign in — no password required.
                    </p>
                </div>
            </label>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="email_verification_required" value="0">
                <input type="checkbox"
                       name="email_verification_required"
                       value="1"
                       @checked(old('email_verification_required', $emailVerificationRequired))
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Require email verification at sign-up</span>
                        @if($emailVerificationRequired)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        When on, new users must enter the 6-digit code emailed to them before reaching their dashboard. Turn it off to sign new users in immediately after they register — they can verify later.
                    </p>
                    <p class="text-xs text-amber-300/80 mt-1">
                        Only applies when <strong>email + password login</strong> is enabled. With one-time-code login only, the emailed code is the sole way to sign in, so verification can't be skipped.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">New registrations</h2>
                <p class="text-xs text-white/50">Temporarily stop creating brand-new accounts. Existing users are never affected — they keep signing in and using everything as normal.</p>
            </div>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="registration_paused" value="0">
                <input type="checkbox"
                       name="registration_paused"
                       value="1"
                       @checked(old('registration_paused', $registrationPaused))
                       class="mt-1 w-5 h-5 accent-amber-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Pause new registrations</span>
                        @if($registrationPaused)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/15 border border-amber-400/30 text-amber-300">Paused</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        When on, new visitors trying to sign up (via the register form, a one-time code for an unknown email/phone, social sign-in for an unlinked account, or the mobile app) see a branded &ldquo;we&rsquo;re upgrading&rdquo; page instead of getting an account. Takes effect immediately.
                    </p>
                    <p class="text-xs text-amber-300/80 mt-1">
                        Existing users keep logging in normally. You can always reach this page to switch it back off.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Demo mode</h2>
                <p class="text-xs text-white/50">For demos and reviews where a real inbox or phone isn't available.</p>
            </div>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="demo_reveal_otp_enabled" value="0">
                <input type="checkbox"
                       name="demo_reveal_otp_enabled"
                       value="1"
                       @checked(old('demo_reveal_otp_enabled', $demoRevealOtpEnabled))
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Reveal one-time codes on screen</span>
                        @if($demoRevealOtpEnabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        When on, every verification code is shown to the user on the screen right after it's sent (login, sign-up, account merge, and adding a new email/phone). Codes are still emailed/messaged as usual — this only also displays them.
                    </p>
                    <p class="text-xs text-amber-300/80 mt-1">
                        Turn this <strong>off</strong> for production: while on, anyone reaching a verification screen can read the live code.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">WhatsApp login</h2>
                <p class="text-xs text-white/50">When off, email is the only way to sign in or reset access.</p>
            </div>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                <input type="hidden" name="mobile_login_enabled" value="0">
                <input type="checkbox"
                       name="mobile_login_enabled"
                       value="1"
                       @checked(old('mobile_login_enabled', $mobileLoginEnabled))
                       class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Enable WhatsApp (mobile) login</span>
                        @if($mobileLoginEnabled)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">On</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        Users can request a 6-digit code delivered to their WhatsApp number (Meta WhatsApp Cloud API).
                    </p>
                </div>
            </label>

            <div class="flex items-start gap-2 p-3 rounded-xl border {{ $credsConfigured ? 'border-emerald-400/30 bg-emerald-500/10' : 'border-amber-400/30 bg-amber-500/10' }}">
                <i class="fab fa-whatsapp mt-0.5 {{ $credsConfigured ? 'text-emerald-300' : 'text-amber-300' }}"></i>
                <div class="text-xs {{ $credsConfigured ? 'text-emerald-200' : 'text-amber-200' }}">
                    @if($credsConfigured)
                        WhatsApp Cloud API credentials are configured — codes will be delivered live.
                    @else
                        WhatsApp Cloud API credentials are not configured. The provider runs in <strong>preview mode</strong>: codes are written to the application log instead of being sent. Set <code>WHATSAPP_PHONE_NUMBER_ID</code> and <code>WHATSAPP_ACCESS_TOKEN</code> to go live.
                    @endif
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Allowed country codes</h2>
                <p class="text-xs text-white/50">One dialling code per line (or comma-separated), e.g. <code>+91</code>, <code>+1</code>. Only numbers starting with these codes can use WhatsApp login. Leaving it empty restores the defaults.</p>
            </div>
            <div>
                <textarea name="allowed_country_codes" rows="5"
                          placeholder="+91&#10;+1"
                          class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">{{ old('allowed_country_codes', $allowedCodesText) }}</textarea>
                @error('allowed_country_codes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition">
                <i class="fas fa-save mr-1.5"></i> Save settings
            </button>
        </div>
    </form>
</div>
@endsection
