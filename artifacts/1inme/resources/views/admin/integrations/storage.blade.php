@extends('admin.layouts.app')
@section('title', 'S3 / CloudFront storage')
@section('page-title', 'S3 / CloudFront storage')

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
        Choose where user uploads and public assets are stored. When S3 is off, the local public disk is used.
        When on, the user-content disks (public, user files and admin assets) are all served from your S3 bucket.
        The access key and secret are encrypted at rest and never displayed back &mdash; leave them blank to keep the
        stored values. Each field falls back to the corresponding <span class="font-mono">AWS_*</span> environment
        variable until you save a value here. <strong>The bucket should have ACLs disabled</strong> (no object-level
        visibility is set).
    </p>

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.storage.update') }}" class="space-y-6"
          x-data="{ enabled: {{ old('s3_enabled', $enabled) ? 'true' : 'false' }} }">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-aws text-amber-400"></i> Storage backend
                    </h3>
                    <p class="text-xs text-white/40">Toggle S3 on to move user content off the local disk.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="s3_enabled" value="0">
                <input type="checkbox" name="s3_enabled" value="1" x-model="enabled" class="accent-blue-500 w-4 h-4">
                <span class="text-sm text-white/80">Use S3 for user content</span>
            </label>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5" x-show="enabled" x-cloak>
            <h3 class="font-semibold text-white text-sm flex items-center gap-2">
                <i class="fas fa-key text-blue-400"></i> Credentials &amp; bucket
            </h3>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Access key ID</label>
                    @if($hasKey)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedKey }}</span></p>
                    @endif
                    <input type="password" name="s3_key" autocomplete="new-password"
                           placeholder="{{ $hasKey ? 'Paste a new key to replace' : 'AKIA…' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($hasKey)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_s3_key" value="0">
                            <input type="checkbox" name="clear_s3_key" value="1" class="accent-red-500">
                            Remove (revert to env)
                        </label>
                    @endif
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Secret access key</label>
                    @if($hasSecret)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedSecret }}</span></p>
                    @endif
                    <input type="password" name="s3_secret" autocomplete="new-password"
                           placeholder="{{ $hasSecret ? 'Paste a new secret to replace' : '••••••••' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($hasSecret)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_s3_secret" value="0">
                            <input type="checkbox" name="clear_s3_secret" value="1" class="accent-red-500">
                            Remove (revert to env)
                        </label>
                    @endif
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Region</label>
                    <input type="text" name="s3_region" value="{{ old('s3_region', $region) }}" autocomplete="off"
                           placeholder="us-east-1"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Bucket</label>
                    <input type="text" name="s3_bucket" value="{{ old('s3_bucket', $bucket) }}" autocomplete="off"
                           placeholder="my-bucket"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Public URL (CDN / CloudFront)</label>
                    <input type="text" name="s3_url" value="{{ old('s3_url', $url) }}" autocomplete="off"
                           placeholder="https://cdn.example.com"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="text-[11px] text-white/30 mt-1">Optional. Base URL used when generating public links to stored objects.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Custom endpoint</label>
                    <input type="text" name="s3_endpoint" value="{{ old('s3_endpoint', $endpoint) }}" autocomplete="off"
                           placeholder="https://s3.eu-central-1.amazonaws.com"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="text-[11px] text-white/30 mt-1">Optional. For S3-compatible providers (R2, MinIO, Spaces).</p>
                </div>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="s3_use_path_style" value="0">
                <input type="checkbox" name="s3_use_path_style" value="1" {{ old('s3_use_path_style', $usePathStyle) ? 'checked' : '' }} class="accent-blue-500 w-4 h-4">
                <span class="text-sm text-white/80">Use path-style endpoint</span>
                <span class="text-[11px] text-white/30">(required by some S3-compatible providers)</span>
            </label>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

    {{-- Connectivity check --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white text-sm flex items-center gap-2">
            <i class="fas fa-plug text-amber-400"></i> Connectivity check
        </h3>
        <p class="text-xs text-white/40">
            Writes, reads and deletes a tiny probe object in the configured bucket so bad credentials or a wrong
            bucket/region surface immediately. Save your changes first so the check uses the latest values.
        </p>
        @if(!$configured)
            <p class="text-[11px] text-amber-300/80"><i class="fas fa-triangle-exclamation mr-1"></i> Provide a key, secret, bucket and region first.</p>
        @endif
        <form method="POST" action="{{ route('admin.integrations.storage.test') }}">
            @csrf
            <button type="submit" {{ $configured ? '' : 'disabled' }}
                    class="px-3 py-2 bg-amber-600 text-white rounded-xl text-xs font-medium hover:bg-amber-700 whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fas fa-plug mr-1"></i> Run check
            </button>
        </form>
    </div>

</div>
@endsection
