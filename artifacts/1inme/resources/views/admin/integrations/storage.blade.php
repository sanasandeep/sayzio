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

    @if(!$configured)
        <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/30 flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-400 mt-0.5"></i>
            <div class="space-y-1">
                <p class="text-sm font-semibold text-red-300">S3 storage is misconfigured — user uploads are failing</p>
                <p class="text-xs text-red-300/80">
                    Missing: <span class="font-medium">{{ implode(', ', $missing) }}</span>.
                    User content is S3-only with no local-disk fallback, so every file upload fails with an error
                    until this is fixed. Fill in the missing values below (or set the corresponding
                    <span class="font-mono">AWS_*</span> environment variables). Ops admins are alerted
                    automatically (in-app + email) while this remains broken.
                </p>
            </div>
        </div>
    @endif

    <p class="text-sm text-white/50">
        User uploads and public assets are always stored on S3 &mdash; the user-content disks (public, user files and
        admin assets) are permanently backed by your S3 bucket and cannot be switched back to local disk. The access
        key and secret are encrypted at rest and never displayed back &mdash; leave them blank to keep the stored
        values. Each field falls back to the corresponding <span class="font-mono">AWS_*</span> environment variable
        until you save a value here.
    </p>

    @include('admin.partials.help-note', [
        'body' => '<strong>S3 bucket setup checklist</strong>
            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                <li><strong>IAM credentials</strong> — create a dedicated IAM user in <a class="underline" href="https://console.aws.amazon.com/iam/home#/users" target="_blank" rel="noopener">AWS IAM</a> with programmatic access. Attach a policy granting <code>s3:GetObject</code>, <code>s3:PutObject</code>, <code>s3:DeleteObject</code>, and <code>s3:ListBucket</code> on your bucket ARN only.</li>
                <li><strong>ACLs disabled</strong> — create the bucket with <em>Object Ownership: Bucket owner enforced</em> (ACLs disabled). The platform never sets per-object ACLs; enabling them is not required and can break uploads.</li>
                <li><strong>Public access</strong> — for public user content, either attach a bucket policy granting <code>s3:GetObject</code> to <code>*</code>, or front the bucket with a CloudFront distribution and enter the distribution domain as the Public URL.</li>
                <li><strong>CORS</strong> — if the browser uploads directly (presigned URLs), add a CORS rule allowing <code>PUT</code> / <code>GET</code> from your domain. If all uploads go server-side, CORS is not required.</li>
                <li><strong>Region</strong> — use the short-form region code, e.g. <code>us-east-1</code>, <code>eu-west-2</code>.</li>
                <li><strong>S3-compatible providers</strong> (Cloudflare R2, MinIO, DigitalOcean Spaces) — enter the provider\'s S3-compatible endpoint and enable path-style if needed.</li>
            </ol>',
    ])

    @include('admin.partials.help-note', [
        'type' => 'warn',
        'body' => '<strong>S3 is mandatory:</strong> user content storage cannot be switched back to local disk. If credentials below are missing or incomplete, uploads will fail with a clear error instead of silently falling back to local storage.',
    ])

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.integrations.storage.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-aws text-amber-400"></i> Storage backend
                    </h3>
                    <p class="text-xs text-white/40">User content is always stored on S3 &mdash; this cannot be disabled.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <h3 class="font-semibold text-white text-sm flex items-center gap-2">
                <i class="fas fa-key text-blue-400"></i> Credentials &amp; bucket
            </h3>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Access key ID</label>
                    @if($hasKey)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $maskedKey }}</span></p>
                    @endif
                    @include('common.partials.password-field', [
                        'name' => 's3_key',
                        'autocomplete' => 'new-password',
                        'placeholder' => $hasKey ? 'Paste a new key to replace' : 'AKIA…',
                        'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                    ])
                    <p class="text-[11px] text-white/30 mt-1">IAM access key ID — starts with <code>AKIA</code>.</p>
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
                    @include('common.partials.password-field', [
                        'name' => 's3_secret',
                        'autocomplete' => 'new-password',
                        'placeholder' => $hasSecret ? 'Paste a new secret to replace' : '••••••••',
                        'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                    ])
                    <p class="text-[11px] text-white/30 mt-1">IAM secret key — encrypted at rest, never displayed back.</p>
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
                    <p class="text-[11px] text-white/30 mt-1">AWS region short code, e.g. <code>us-east-1</code>, <code>eu-west-2</code>.</p>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Bucket</label>
                    <input type="text" name="s3_bucket" value="{{ old('s3_bucket', $bucket) }}" autocomplete="off"
                           placeholder="my-bucket"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="text-[11px] text-white/30 mt-1">Bucket name only — no <code>s3://</code> prefix or path.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Public URL (CDN / CloudFront)</label>
                    <input type="text" name="s3_url" value="{{ old('s3_url', $url) }}" autocomplete="off"
                           placeholder="https://cdn.example.com"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="text-[11px] text-white/30 mt-1">Optional. Base URL used when generating public links to stored objects — use your CloudFront or CDN domain here to avoid direct S3 traffic costs.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Custom endpoint</label>
                    <input type="text" name="s3_endpoint" value="{{ old('s3_endpoint', $endpoint) }}" autocomplete="off"
                           placeholder="https://s3.eu-central-1.amazonaws.com"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="text-[11px] text-white/30 mt-1">Optional. For S3-compatible providers (Cloudflare R2, MinIO, DigitalOcean Spaces). Leave blank for standard AWS S3.</p>
                </div>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="s3_use_path_style" value="0">
                <input type="checkbox" name="s3_use_path_style" value="1" {{ old('s3_use_path_style', $usePathStyle) ? 'checked' : '' }} class="accent-blue-500 w-4 h-4">
                <span class="text-sm text-white/80">Use path-style endpoint</span>
                <span class="text-[11px] text-white/30">(required by MinIO and some other S3-compatible providers; not needed for AWS S3 or Cloudflare R2)</span>
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
