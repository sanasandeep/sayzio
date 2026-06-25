@extends('admin.layouts.app')
@section('title', 'Email / SMTP')
@section('page-title', 'Email / SMTP')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'bg-red-500/10 border-red-500/20 text-red-300',
            default => 'bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="max-w-3xl space-y-6">

    <p class="text-sm text-white/50">
        Configure the platform's outbound mail transport. These settings drive <strong>all</strong>
        outgoing email &mdash; notifications, newsletters and login/verification email OTPs. The SMTP
        password is encrypted at rest and never displayed back &mdash; leave it blank to keep the stored
        value. Each field falls back to the environment configuration until you save a value here.
    </p>

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Main settings form                                           --}}
    {{-- ============================================================ --}}
    <form method="POST" action="{{ route('admin.mail-settings.update') }}" class="space-y-6"
          x-data="{ mailer: '{{ old('mailer', $mailer) }}' }">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-server text-sky-400"></i> Mail transport
                    </h3>
                    <p class="text-xs text-white/40">Pick the mailer and, for SMTP, its connection details.</p>
                </div>
                <div class="shrink-0 flex flex-col items-end gap-1">
                    <span class="px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                        {{ $status['label'] }}
                    </span>
                    @if($verifiedAt)
                        <span class="text-[11px] text-emerald-300/80 flex items-center gap-1"
                              title="Last successful SMTP handshake: {{ $verifiedAt->toDayDateTimeString() }}">
                            <i class="fas fa-check-circle"></i>
                            Verified OK {{ $verifiedAt->diffForHumans() }}
                        </span>
                    @else
                        <span class="text-[11px] text-white/30 flex items-center gap-1">
                            <i class="fas fa-circle-question"></i> Connection not verified yet
                        </span>
                    @endif
                </div>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Mailer / driver</label>
                <select name="mailer" x-model="mailer"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @foreach($mailers as $m)
                        <option value="{{ $m }}" {{ old('mailer', $mailer) === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-white/30 mt-1">Use <span class="font-mono">smtp</span> to send via a server. <span class="font-mono">log</span> writes mail to the log instead of sending.</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4" x-show="mailer === 'smtp'" x-cloak>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">SMTP host</label>
                    <input type="text" name="host" value="{{ old('host', $host) }}" autocomplete="off"
                           placeholder="smtp.mailgun.org"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">SMTP port</label>
                    <input type="number" name="port" value="{{ old('port', $port) }}" autocomplete="off"
                           placeholder="587" min="1" max="65535"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Encryption</label>
                    <select name="encryption"
                            class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                        @foreach($encryptionOptions as $opt)
                            <option value="{{ $opt }}" {{ old('encryption', $encryption) === $opt ? 'selected' : '' }}>
                                {{ $opt === 'tls' ? 'TLS / STARTTLS (587)' : ($opt === 'ssl' ? 'SSL (465)' : 'None') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Username</label>
                    <input type="text" name="username" value="{{ old('username', $username) }}" autocomplete="off"
                           placeholder="postmaster@mg.example.com"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Password</label>
                    @if($hasPassword)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedPassword }}</span></p>
                    @endif
                    <input type="password" name="password" autocomplete="new-password"
                           placeholder="{{ $hasPassword ? 'Paste a new password to replace' : '••••••••' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($hasPassword)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_password" value="0">
                            <input type="checkbox" name="clear_password" value="1" class="accent-red-500">
                            Remove the stored password (revert to env)
                        </label>
                    @endif
                </div>
            </div>
            <p class="text-[11px] text-white/30">Password is encrypted at rest with the application key. Other fields are plain configuration.</p>
        </div>

        {{-- "From" identity ---------------------------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div>
                <h3 class="font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-id-badge text-violet-400"></i> Default &ldquo;from&rdquo; identity
                </h3>
                <p class="text-xs text-white/40">The sender shown on every outgoing email.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">From address</label>
                    <input type="email" name="from_address" value="{{ old('from_address', $fromAddress) }}" autocomplete="off"
                           placeholder="hello@1in.me"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">From name</label>
                    <input type="text" name="from_name" value="{{ old('from_name', $fromName) }}" autocomplete="off"
                           placeholder="Sayzio"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">
                <i class="fas fa-save mr-1"></i> Save settings
            </button>
            <span class="text-xs text-white/30">Saving runs a connection check automatically.</span>
        </div>
    </form>

    {{-- ============================================================ --}}
    {{-- Verify connection (handshake/auth only, no message sent)     --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white text-sm flex items-center gap-2">
            <i class="fas fa-plug text-amber-400"></i> Verify connection
        </h3>
        <p class="text-xs text-white/40">
            Opens an SMTP handshake and authenticates against the saved transport &mdash; without sending a message &mdash;
            so bad credentials or hosts surface immediately. Save your changes first so the check uses the latest values.
        </p>
        <form method="POST" action="{{ route('admin.mail-settings.verify') }}">
            @csrf
            <button type="submit" class="px-3 py-2 bg-amber-600 text-white rounded-xl text-xs font-medium hover:bg-amber-700 whitespace-nowrap">
                <i class="fas fa-plug mr-1"></i> Verify connection
            </button>
        </form>
    </div>

    {{-- ============================================================ --}}
    {{-- Test email                                                   --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white text-sm">Send a test email</h3>
        <p class="text-xs text-white/40">Sends a sample message through the saved transport and reports the result.</p>
        <form method="POST" action="{{ route('admin.mail-settings.test') }}" class="flex gap-2">
            @csrf
            <input type="email" name="test_email" required placeholder="you@example.com"
                   class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
            <button type="submit" class="px-3 py-2 bg-emerald-600 text-white rounded-xl text-xs font-medium hover:bg-emerald-700 whitespace-nowrap">
                <i class="fas fa-paper-plane mr-1"></i> Send test
            </button>
        </form>
    </div>

</div>
@endsection
