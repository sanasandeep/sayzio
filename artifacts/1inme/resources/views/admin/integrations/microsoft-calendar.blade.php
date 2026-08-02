@extends('admin.layouts.app')
@section('title', 'Microsoft Calendar OAuth')
@section('page-title', 'Microsoft Calendar OAuth')

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
        OAuth client credentials powering two-way Microsoft Outlook / 365 calendar sync via Microsoft Graph &mdash;
        events imported from a connected calendar are mirrored as Event links, and pushed invites appear on the
        creator&rsquo;s Outlook calendar. The client secret is encrypted at rest and never displayed back &mdash; leave it
        blank to keep the stored value. Both the client ID and secret are required for the connect flow to turn on; until
        then the platform falls back to the
        <span class="font-mono">MICROSOFT_CALENDAR_CLIENT_ID</span> / <span class="font-mono">MICROSOFT_CALENDAR_CLIENT_SECRET</span>
        environment variables.
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to create a Microsoft Entra (Azure AD) app for calendar sync</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Open <a class="underline" href="https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener">Microsoft Entra admin center → App registrations</a>.</li>
                <li>Click <strong>New registration</strong>. Under <em>Supported account types</em> choose <em>Accounts in any organizational directory and personal Microsoft accounts</em> (the <span class="font-mono">common</span> tenant).</li>
                <li>Under <strong>Redirect URI</strong> pick <em>Web</em> and add the callback URL shown below.</li>
                <li>Go to <strong>Certificates &amp; secrets → New client secret</strong> and copy the <em>Value</em> (shown once).</li>
                <li>Go to <strong>API permissions → Add a permission → Microsoft Graph → Delegated</strong> and add the scopes listed below, then grant admin consent if required.</li>
                <li>Copy the <strong>Application (client) ID</strong> and the secret <em>Value</em> into the form below.</li>
            </ol>',
    ])

    @include('admin.partials.help-note', [
        'type' => 'warn',
        'body' => '<strong>Tenant:</strong> This integration uses the <span class="font-mono">common</span> tenant so both work and personal Microsoft accounts can connect. Override with the <span class="font-mono">MICROSOFT_CALENDAR_TENANT</span> env var if you need a single-tenant app.',
    ])

    {{-- Required scopes --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-xs space-y-1.5">
        <div class="ak-note text-[10px] uppercase tracking-wider text-white/40">Required Microsoft Graph delegated scopes</div>
        <div class="flex flex-wrap gap-1.5">
            <code class="ak-strong px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">offline_access</code>
            <code class="ak-strong px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">Calendars.ReadWrite</code>
            <code class="ak-strong px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">User.Read</code>
        </div>
    </div>

    {{-- Redirect URI --}}
    @include('admin.partials.copy-uri', [
        'label' => 'Redirect URI, add this exact value in Entra → App registration → Authentication (Web)',
        'value' => route('user.calendar.callback', ['provider' => 'microsoft']),
    ])

    @if ($errors->any())
        <div class="ak-red p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.microsoft-calendar.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="ak-blue fab fa-microsoft text-sky-400"></i> OAuth client
                    </h3>
                    <p class="ak-note text-xs text-white/40">From Microsoft Entra &rarr; App registrations (Web platform).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">Application (client) ID</label>
                <input type="text" name="client_id" value="{{ old('client_id', $clientId) }}" autocomplete="off"
                       placeholder="00000000-0000-0000-0000-000000000000"
                       class="ak-strong ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <p class="ak-note text-[11px] text-white/30 mt-1">Plain configuration (not a secret) &mdash; the app registration&rsquo;s GUID.</p>
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">Client secret</label>
                @if($hasSecret)
                    <p class="ak-muted text-xs text-white/60 mb-1">Stored: <span class="ak-amber font-mono text-amber-300">{{ $maskedSecret }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'client_secret',
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasSecret ? 'Paste a new secret to replace' : 'Secret Value from Entra',
                    'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                @if($hasSecret)
                    <label class="ak-muted mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_client_secret" value="0">
                        <input type="checkbox" name="clear_client_secret" value="1" class="accent-red-500">
                        Remove the stored secret (revert to env)
                    </label>
                @endif
                <p class="ak-note text-[11px] text-white/30 mt-1">Use the secret <strong>Value</strong> (not the Secret ID). Encrypted at rest with the application key. Never displayed back.</p>
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
