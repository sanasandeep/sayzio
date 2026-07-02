@extends('admin.layouts.app')
@section('title', $meta['label'] . ' OAuth')
@section('page-title', $meta['label'] . ' OAuth')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'bg-red-500/10 border-red-500/20 text-red-300',
            default => 'bg-white/5 border-white/10 text-white/50',
        };
    };

    // Per-provider developer console URL and scope notes.
    $providerMeta = [
        'salesforce' => [
            'console_url'  => 'https://help.salesforce.com/s/articleView?id=sf.connected_app_create.htm',
            'console_label'=> 'Salesforce Setup → App Manager → New Connected App',
            'app_type'     => 'Connected App (Web, server-side OAuth)',
            'scopes_note'  => 'Required scopes: <code>api</code>, <code>refresh_token</code>, <code>offline_access</code>. Select "Perform requests at any time" (enables refresh tokens).',
            'gotcha'       => 'The Connected App must have OAuth enabled and the callback URL added before users can connect. Salesforce may take a few minutes to propagate a newly created app.',
        ],
        'hubspot' => [
            'console_url'  => 'https://developers.hubspot.com/docs/api/creating-an-app',
            'console_label'=> 'HubSpot Developer Account → Apps → Create App',
            'app_type'     => 'Public App (not Private App — Private Apps cannot do OAuth)',
            'scopes_note'  => 'Required scopes: <code>crm.objects.contacts.read</code>, <code>crm.objects.contacts.write</code>, <code>oauth</code>. Add these under the Auth tab of your app.',
            'gotcha'       => 'Use a <strong>Public App</strong> — HubSpot Private Apps use a different bearer-token flow and do not support OAuth redirects.',
        ],
        'zoho' => [
            'console_url'  => 'https://api-console.zoho.com/',
            'console_label'=> 'Zoho API Console → Add Client → Server-based Applications',
            'app_type'     => 'Server-based Applications (Web Client)',
            'scopes_note'  => 'Required scopes: <code>ZohoCRM.modules.ALL</code>, <code>ZohoCRM.users.READ</code>, <code>AaaServer.profile.READ</code>. Enter these in the "Scope" field when creating the client.',
            'gotcha'       => 'Choose <strong>Server-based Applications</strong>, not Self Client — Self Client tokens expire in 10 minutes. Zoho data centres differ; if your users are on .eu or .in, make sure your API calls target the matching region.',
        ],
    ];
    $pm = $providerMeta[$provider] ?? null;

    // Scopes from the registry definition (the canonical source).
    $oauthScopes = $meta['oauth']['scopes'] ?? [];
@endphp

@section('content')
<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.integrations.index') }}" class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Integrations
    </a>

    <p class="text-sm text-white/50">
        OAuth client credentials powering two-way {{ $meta['label'] }} sync. The client secret is encrypted at rest
        and never displayed back &mdash; leave it blank to keep the stored value. Both the client ID and secret are
        required before creators can connect; until then {{ $meta['label'] }} shows as &ldquo;coming soon&rdquo; in
        the creator Connected Apps area. The platform falls back to the
        <span class="font-mono">{{ strtoupper($provider) }}_CLIENT_ID</span> /
        <span class="font-mono">{{ strtoupper($provider) }}_CLIENT_SECRET</span> environment variables.
    </p>

    {{-- Per-provider setup guidance --}}
    @if($pm)
        @include('admin.partials.help-note', [
            'body' => '<strong>Setup steps for ' . $meta['label'] . '</strong>
                <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                    <li>Go to <a class="underline" href="' . $pm['console_url'] . '" target="_blank" rel="noopener">' . $pm['console_label'] . '</a>.</li>
                    <li>Create a <strong>' . $pm['app_type'] . '</strong>.</li>
                    <li>Add the redirect / callback URL below to the app\'s allowed redirect URIs.</li>
                    <li>' . $pm['scopes_note'] . '</li>
                    <li>Copy the Client ID and Client Secret into the form below.</li>
                </ol>'
                . ($pm['gotcha'] ? '<p class="mt-1.5 font-medium"><i class="fas fa-triangle-exclamation text-amber-400 mr-1"></i>' . $pm['gotcha'] . '</p>' : ''),
        ])
    @else
        @include('admin.partials.help-note', [
            'body' => 'Create a <strong>Web / server-side OAuth app</strong> in the ' . $meta['label'] . ' developer console, add the redirect URL below to its allowed redirect URIs, and paste the Client ID and Secret here.',
        ])
    @endif

    {{-- Scopes the OAuth app must grant --}}
    @if(count($oauthScopes))
        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-xs space-y-1.5">
            <div class="text-[10px] uppercase tracking-wider text-white/40">Required OAuth scopes — add these to your app</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($oauthScopes as $scope)
                    <code class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-white/80 text-[11px] font-mono">{{ $scope }}</code>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Redirect URI with copy affordance --}}
    @include('admin.partials.copy-uri', [
        'label' => 'Redirect / callback URL — register this exact value in the ' . $meta['label'] . ' developer console',
        'value' => route('connected-apps.callback'),
    ])

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.connected-app.update', $provider) }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="{{ $meta['icon'] }}" style="color: {{ $meta['color'] }};"></i> OAuth client
                    </h3>
                    <p class="text-xs text-white/40">From the {{ $meta['label'] }} developer console (Web / server OAuth app).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Client ID</label>
                <input type="text" name="client_id" value="{{ old('client_id', $clientId) }}" autocomplete="off"
                       placeholder="Client / consumer key"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <p class="text-[11px] text-white/30 mt-1">Plain configuration (not a secret) — safe to store unencrypted.</p>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Client secret</label>
                @if($hasSecret)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedSecret }}</span></p>
                @endif
                <input type="password" name="client_secret" autocomplete="new-password"
                       placeholder="{{ $hasSecret ? 'Paste a new secret to replace' : 'Client / consumer secret' }}"
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
