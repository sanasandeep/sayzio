@extends('admin.layouts.app')
@section('title', 'Cloudflare Turnstile (captcha)')
@section('page-title', 'Cloudflare Turnstile (captcha)')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'ak-tone-green bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'ak-tone-amber bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'ak-tone-red bg-red-500/10 border-red-500/20 text-red-300',
            default => 'ak-tone-neutral bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.integrations.index') }}" class="ak-note inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Integrations
    </a>

    <p class="ak-muted text-sm text-white/50">
        Cloudflare Turnstile protects the <strong>web sign-up form</strong> and the <strong>one-time-code send &amp; resend</strong>
        flows (email and WhatsApp) from bots, invisibly, so real visitors never solve a puzzle. The secret key is
        encrypted at rest and never displayed back &mdash; leave the field blank to keep the stored value. With no keys
        configured, or with enforcement switched off, nothing changes for users and no Cloudflare script is loaded.
        Social sign-in and the mobile app are not affected.
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to get Turnstile keys</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Log in to the <a class="underline" href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare dashboard</a> (a free account is enough).</li>
                <li>Open <strong>Turnstile</strong> in the sidebar and click <strong>Add widget</strong>.</li>
                <li>Enter your site\'s domain and choose the <strong>Invisible</strong> (or Managed) widget mode.</li>
                <li>Copy the generated <strong>Site key</strong> and <strong>Secret key</strong> into the fields below, then switch enforcement on.</li>
            </ol>',
    ])

    @if(session('success'))
        <div class="ak-green p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="ak-red p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($toggleOn && !$enforcing)
        @include('admin.partials.help-note', [
            'type' => 'warn',
            'body' => 'Enforcement is switched on but a site key and/or secret key is missing, so the captcha stays <strong>inactive</strong> until both keys are saved.',
        ])
    @endif

    <form method="POST" action="{{ route('admin.integrations.turnstile.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="ak-amber fas fa-shield-halved text-amber-400"></i> Turnstile keys
                    </h3>
                    <p class="ak-note text-xs text-white/40">From your Turnstile widget in the Cloudflare dashboard.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">Site key</label>
                <input type="text" name="site_key" value="{{ old('site_key', $siteKey) }}" placeholder="0x4AAAAAAA..."
                       class="ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white" autocomplete="off">
                <p class="ak-note text-[11px] text-white/30 mt-1">Public: rendered into the sign-up and login pages when enforcement is on.</p>
                @if($siteKey)
                    <label class="ak-muted mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_site_key" value="0">
                        <input type="checkbox" name="clear_site_key" value="1" class="accent-red-500">
                        Remove the stored site key
                    </label>
                @endif
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">Secret key</label>
                @if($hasSecret)
                    <p class="ak-muted text-xs text-white/60 mb-1">Stored: <span class="ak-amber font-mono text-amber-300">{{ $maskedSecret }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'secret_key',
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasSecret ? 'Paste a new key to replace' : '••••••••',
                    'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                <p class="ak-note text-[11px] text-white/30 mt-1">Stored encrypted. Used server-side to verify tokens against Cloudflare's siteverify endpoint.</p>
                @if($hasSecret)
                    <label class="ak-muted mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_secret_key" value="0">
                        <input type="checkbox" name="clear_secret_key" value="1" class="accent-red-500">
                        Remove the stored secret key
                    </label>
                @endif
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                <i class="ak-amber fas fa-toggle-on text-amber-400"></i> Enforcement
            </h3>
            <label class="ak-muted inline-flex items-center gap-2 text-sm text-white/70">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="accent-blue-500" @checked($toggleOn)>
                Require Turnstile verification on web sign-up and OTP send/resend
            </label>
            <p class="ak-note text-[11px] text-white/30">
                Only takes effect once both keys are saved. When off (or keys are missing) the flows behave exactly
                as before and no Cloudflare script loads.
            </p>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
