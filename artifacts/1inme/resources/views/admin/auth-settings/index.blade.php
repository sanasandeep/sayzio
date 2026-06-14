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
            Email is always accepted as a login and account-recovery identifier. You can additionally let users sign in with a one-time code sent over <strong class="text-white/80">WhatsApp</strong>, restricted to an allow-list of country dialling codes.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.auth-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

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
                       class="mt-1 w-5 h-5 accent-violet-500 cursor-pointer">
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
                    class="px-5 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold transition">
                <i class="fas fa-save mr-1.5"></i> Save settings
            </button>
        </div>
    </form>
</div>
@endsection
