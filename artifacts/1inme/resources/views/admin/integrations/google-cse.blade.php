@extends('admin.layouts.app')
@section('title', 'Google Image Search')
@section('page-title', 'Google Image Search')

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
        Google Custom Search JSON API credentials powering image suggestions in the AI Link-in-Bio builder.
        The API key is encrypted at rest and never displayed back &mdash; leave it blank to keep the stored value.
        Both the API key and the Programmable Search Engine ID are required for image search to turn on; until then
        the platform falls back to the <span class="font-mono">GOOGLE_CSE_API_KEY</span> /
        <span class="font-mono">GOOGLE_CSE_ENGINE_ID</span> environment variables, and when neither is set the
        search option is hidden from creators (preview mode).
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to set up Google Custom Search for image suggestions</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Open <a class="underline" href="https://programmablesearchengine.google.com/controlpanel/all" target="_blank" rel="noopener">Programmable Search Engine</a> and create a new search engine that searches the entire web, with <strong>Image search</strong> turned on.</li>
                <li>Copy the <strong>Search engine ID</strong> (the <code>cx</code> value) into the form below.</li>
                <li>In <a class="underline" href="https://console.cloud.google.com/apis/library/customsearch.googleapis.com" target="_blank" rel="noopener">Google Cloud Console</a>, enable the <strong>Custom Search API</strong> and create an <strong>API key</strong> under APIs &amp; Services &rarr; Credentials.</li>
                <li>Paste the API key below. Note Google\'s free tier is 100 queries/day; billing raises that limit.</li>
            </ol>',
    ])

    @if ($errors->any())
        <div class="ak-red p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Usage: daily CSE query counters (every search costs Google quota) --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                    <i class="ak-blue fas fa-chart-line text-sky-400"></i> Usage
                </h3>
                <p class="ak-note text-xs text-white/40">Every image search costs one Google CSE query ({{ $freeTier }}/day on the free tier).</p>
            </div>
            @php $overFree = $todayQueries >= $freeTier; @endphp
            <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($overFree ? 'red' : ($todayQueries >= (int) ($freeTier * 0.8) ? 'amber' : 'green')) }}">
                Today: {{ number_format($todayQueries) }} / {{ $freeTier }}
            </span>
        </div>

        @if (count($recentDaily))
            <div>
                <p class="ak-note text-[11px] uppercase tracking-wider text-white/30 mb-1.5">Last 7 days</p>
                <div class="space-y-1">
                    @foreach ($recentDaily as $row)
                        <div class="flex items-center justify-between text-xs">
                            <span class="ak-muted text-white/50">{{ \Illuminate\Support\Carbon::parse($row['day'])->format('D, M j') }}</span>
                            <span class="ak-strong font-mono text-white/80">{{ number_format($row['queries']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="ak-muted text-xs text-white/40">No queries recorded yet.</p>
        @endif

        @if (count($topUsers))
            <div>
                <p class="ak-note text-[11px] uppercase tracking-wider text-white/30 mb-1.5">Heaviest users today</p>
                <div class="space-y-1">
                    @foreach ($topUsers as $row)
                        <div class="flex items-center justify-between text-xs">
                            <a href="{{ route('admin.users.edit', $row['user_id']) }}" class="ak-blue text-sky-400 hover:underline">User #{{ $row['user_id'] }}</a>
                            <span class="ak-strong font-mono text-white/80">{{ number_format($row['queries']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.integrations.google-cse.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="ak-blue fab fa-google text-sky-400"></i> Custom Search credentials
                    </h3>
                    <p class="ak-note text-xs text-white/40">From Programmable Search Engine (engine ID) + Google Cloud Console (API key).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">Search engine ID (cx)</label>
                <input type="text" name="engine_id" value="{{ old('engine_id', $engineId) }}" autocomplete="off"
                       placeholder="a1b2c3d4e5f6g7h8i"
                       class="ak-strong ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <p class="ak-note text-[11px] text-white/30 mt-1">Plain configuration (not a secret), from the Programmable Search Engine control panel.</p>
            </div>

            <div>
                <label class="ak-note text-xs uppercase tracking-wider text-white/40 mb-1 block">API key</label>
                @if($hasKey)
                    <p class="ak-muted text-xs text-white/60 mb-1">Stored: <span class="ak-amber font-mono text-amber-300">{{ $maskedKey }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'api_key',
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasKey ? 'Paste a new key to replace' : 'AIza…',
                    'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                @if($hasKey)
                    <label class="ak-muted mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_api_key" value="0">
                        <input type="checkbox" name="clear_api_key" value="1" class="accent-red-500">
                        Remove the stored key (revert to env)
                    </label>
                @endif
                <p class="ak-note text-[11px] text-white/30 mt-1">Encrypted at rest with the application key. Never displayed back.</p>
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
