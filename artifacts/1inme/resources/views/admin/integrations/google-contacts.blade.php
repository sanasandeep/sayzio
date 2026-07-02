@extends('admin.layouts.app')
@section('title', 'Google Contacts OAuth')
@section('page-title', 'Google Contacts OAuth')

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
<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.integrations.index') }}" class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Integrations
    </a>

    <p class="text-sm text-white/50">
        OAuth client credentials powering two-way Google Contacts sync (Google People API). The client secret is
        encrypted at rest and never displayed back &mdash; leave it blank to keep the stored value. Both the client
        ID and secret are required for the connect flow to turn on; until then the platform falls back to the
        <span class="font-mono">GOOGLE_CONTACTS_CLIENT_ID</span> / <span class="font-mono">GOOGLE_CONTACTS_CLIENT_SECRET</span>
        environment variables.
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to create a Google OAuth client for Contacts sync</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Open <a class="underline" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → APIs &amp; Services → Credentials</a>.</li>
                <li>Click <strong>Create Credentials → OAuth client ID</strong> and choose <em>Web application</em> as the application type.</li>
                <li>Under <strong>Authorised redirect URIs</strong>, add the callback URL shown below.</li>
                <li>Enable the <strong>People API</strong> under <a class="underline" href="https://console.cloud.google.com/apis/library" target="_blank" rel="noopener">APIs &amp; Services → Library</a>.</li>
                <li>Configure the <strong>OAuth consent screen</strong> (External or Internal). Required scopes: <code>https://www.googleapis.com/auth/contacts</code> and <code>https://www.googleapis.com/auth/contacts.readonly</code>. If your app is External and not verified, add test users manually until Google verifies it.</li>
                <li>Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> into the form below.</li>
            </ol>',
    ])

    @include('admin.partials.help-note', [
        'type' => 'warn',
        'body' => '<strong>OAuth consent screen verification:</strong> If your consent screen is set to "External" and not yet verified by Google, users outside your test-user list will see a warning page when connecting. Add your creator test accounts to the test-user list during development, then submit for verification before going to production.',
    ])

    {{-- Required scopes --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-xs space-y-1.5">
        <div class="text-[10px] uppercase tracking-wider text-white/40">Required OAuth scopes — add these to your consent screen</div>
        <div class="flex flex-wrap gap-1.5">
            <code class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">https://www.googleapis.com/auth/contacts</code>
            <code class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">https://www.googleapis.com/auth/contacts.readonly</code>
        </div>
    </div>

    {{-- Redirect URI --}}
    @include('admin.partials.copy-uri', [
        'label' => 'Authorised redirect URI — add this exact value in Google Cloud Console → OAuth client',
        'value' => route('contacts.google.callback'),
    ])

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.google-contacts.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-google text-sky-400"></i> OAuth client
                    </h3>
                    <p class="text-xs text-white/40">From Google Cloud Console &rarr; APIs &amp; Services &rarr; OAuth client (Web application).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Client ID</label>
                <input type="text" name="client_id" value="{{ old('client_id', $clientId) }}" autocomplete="off"
                       placeholder="1234567890-abc.apps.googleusercontent.com"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <p class="text-[11px] text-white/30 mt-1">Plain configuration (not a secret) — ends in <code>.apps.googleusercontent.com</code>.</p>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Client secret</label>
                @if($hasSecret)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedSecret }}</span></p>
                @endif
                <input type="password" name="client_secret" autocomplete="new-password"
                       placeholder="{{ $hasSecret ? 'Paste a new secret to replace' : 'GOCSPX-…' }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                @if($hasSecret)
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_client_secret" value="0">
                        <input type="checkbox" name="clear_client_secret" value="1" class="accent-red-500">
                        Remove the stored secret (revert to env)
                    </label>
                @endif
                <p class="text-[11px] text-white/30 mt-1">Encrypted at rest with the application key. Never displayed back.</p>
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
