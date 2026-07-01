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

    <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-xs text-white/50">
        Redirect / callback URL: <span class="font-mono text-white/70">{{ route('connected-apps.callback') }}</span>
    </div>

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
                <p class="text-[11px] text-white/30 mt-1">Plain configuration (not a secret).</p>
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
                <p class="text-[11px] text-white/30 mt-1">Encrypted at rest with the application key.</p>
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
