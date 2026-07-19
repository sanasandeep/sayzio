@extends('admin.layouts.app')
@section('title', 'Google Places (reviews)')
@section('page-title', 'Google Places (reviews)')

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
        The Google Places API key is used to import reviews from Google Business Profiles. The key is encrypted
        at rest and never displayed back &mdash; leave the field blank to keep the stored value. Until you save a
        key here the platform falls back to the <span class="font-mono">GOOGLE_PLACES_API_KEY</span> environment
        variable; with no key at all, review import runs in preview mode (no live data fetched).
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to get a Google Places API key</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Open <a class="underline" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → APIs &amp; Services → Credentials</a>.</li>
                <li>Click <strong>Create Credentials → API Key</strong>. Restrict the key to <em>Places API (New)</em> to limit exposure.</li>
                <li>Enable <strong>Places API (New)</strong> under <a class="underline" href="https://console.cloud.google.com/apis/library" target="_blank" rel="noopener">APIs &amp; Services → Library</a>.</li>
                <li>Your Google Cloud project must have a <strong>billing account attached</strong>, the Places API has a free monthly credit but requires billing to be enabled.</li>
                <li>Paste the generated key (<code>AIza…</code>) in the field below.</li>
            </ol>',
    ])

    @include('admin.partials.help-note', [
        'type' => 'warn',
        'body' => 'Without this key, the Reviews feature will show platform-native reviews only. Google Business Profile reviews will not be imported or displayed.',
    ])

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.google-places.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-google text-sky-400"></i> API key
                    </h3>
                    <p class="text-xs text-white/40">From Google Cloud Console &rarr; APIs &amp; Services &rarr; Credentials.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Google Places API key</label>
                @if($hasValue)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $masked }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'api_key',
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasValue ? 'Paste a new key to replace' : 'AIza…',
                    'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                <p class="text-[11px] text-white/30 mt-1">Encrypted at rest. Never displayed back.</p>
                @if($hasValue)
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_api_key" value="0">
                        <input type="checkbox" name="clear_api_key" value="1" class="accent-red-500">
                        Remove the stored key (revert to env / preview mode)
                    </label>
                @endif
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
