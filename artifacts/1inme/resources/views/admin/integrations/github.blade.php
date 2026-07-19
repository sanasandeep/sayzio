@extends('admin.layouts.app')
@section('title', 'GitHub Token')
@section('page-title', 'GitHub Token')

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
        A single GitHub personal access token shared by two systems: the post-publish
        <strong>code push</strong> to <span class="font-mono">{{ $repo }}</span>, and the
        <strong>Zio Browser release refresh</strong> (authenticated GitHub API calls raise the rate limit
        from 60 to 5,000 requests/hour). The token is encrypted at rest and never displayed back &mdash;
        leave the field blank to keep the stored value. Until you save a token here the platform falls back
        to the <span class="font-mono">GITHUB_TOKEN</span> environment variable.
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>How to get a GitHub personal access token</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li>Go to <a class="underline" href="https://github.com/settings/personal-access-tokens" target="_blank" rel="noopener">GitHub → Settings → Developer settings → Fine-grained tokens</a>.</li>
                <li>Create a fine-grained token scoped to the mirror repository with <strong>Contents: Read and write</strong> permission (read-only is enough if you only need the release refresh rate limit).</li>
                <li>Fine-grained tokens expire (~90-day default). The daily <span class="font-mono">github:check-token</span> probe alerts ops admins before expiry.</li>
                <li>Paste the token below &mdash; it takes effect immediately at the next boot, no redeploy or env change needed.</li>
            </ol>',
    ])

    @include('admin.partials.help-note', [
        'type' => 'warn',
        'body' => 'Without a token, post-publish pushes to GitHub cannot authenticate and the Zio Browser release check runs against the anonymous 60 requests/hour GitHub API limit (it may show as rate-limited).',
    ])

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.github.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-github text-white/70"></i> Personal access token
                    </h3>
                    <p class="text-xs text-white/40">Used for the repo push sync and authenticated GitHub API calls.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            @php
                $probeTone = null;
                if ($lastProbe) {
                    $probeTone = match ($lastProbe['status']) {
                        'ok'                    => 'green',
                        'expiring'              => 'amber',
                        'missing', 'rejected'   => 'red',
                        default                 => 'slate',
                    };
                }
            @endphp
            @if ($lastProbe)
                <div class="rounded-xl border p-3 text-xs space-y-1 {{ $toneClass($probeTone) }}">
                    <p class="font-medium">
                        <i class="fas {{ $lastProbe['status'] === 'ok' ? 'fa-check-circle' : ($lastProbe['status'] === 'inconclusive' ? 'fa-question-circle' : 'fa-exclamation-triangle') }} mr-1"></i>
                        Last checked {{ \Carbon\Carbon::parse($lastProbe['checked_at'])->diffForHumans() }}
                        ({{ $lastProbe['source'] === 'manual' ? 'via Verify token' : 'scheduled check' }})
                        &mdash; {{ ucfirst($lastProbe['status']) }}
                    </p>
                    <p class="text-white/60">{{ $lastProbe['detail'] }}</p>
                    @if ($lastProbe['expires_at'])
                        <p class="text-white/60">Token expires {{ \Carbon\Carbon::parse($lastProbe['expires_at'])->toFormattedDateString() }} ({{ \Carbon\Carbon::parse($lastProbe['expires_at'])->diffForHumans() }}).</p>
                    @endif
                </div>
            @else
                <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/50">
                    <i class="fas fa-question-circle mr-1"></i>
                    Never verified yet &mdash; use <strong>Verify token</strong> below or wait for the daily
                    <span class="font-mono">github:check-token</span> probe.
                </div>
            @endif

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">GitHub token</label>
                @if($hasValue)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $masked }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'token',
                    'autocomplete' => 'new-password',
                    'placeholder' => $hasValue ? 'Paste a new token to replace' : 'github_pat_… or ghp_…',
                    'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                <p class="text-[11px] text-white/30 mt-1">Stored encrypted. Overrides the <span class="font-mono">GITHUB_TOKEN</span> environment variable when set.</p>
                @if($hasValue)
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_token" value="0">
                        <input type="checkbox" name="clear_token" value="1" class="accent-red-500">
                        Remove the stored token (revert to the env variable, if any)
                    </label>
                @endif
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

    <form method="POST" action="{{ route('admin.integrations.github.test') }}" class="pt-1">
        @csrf
        <button type="submit" class="px-4 py-2 bg-white/10 border border-white/10 text-white rounded-xl text-sm font-medium hover:bg-white/20">
            <i class="fas fa-plug mr-1"></i> Verify token
        </button>
        <p class="text-[11px] text-white/30 mt-1">Runs a live check against GitHub and records the result above. Limited to a few checks per minute.</p>
    </form>

</div>
@endsection
