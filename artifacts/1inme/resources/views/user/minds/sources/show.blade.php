@extends('user.layouts.app')
@section('title', $source->title)

@section('content')
@php
    use App\Modules\User\Models\AiMindSource;
    $faqs = null;
    if ($source->type === AiMindSource::TYPE_FAQ) {
        $decoded = json_decode((string) $source->body, true);
        if (is_array($decoded)) $faqs = $decoded;
    }
@endphp
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <div>
        <a href="{{ route('user.minds.edit', $mind) }}" class="text-xs text-white/40 hover:text-white/60">
            <i class="fas fa-arrow-left"></i> Back to {{ $mind->name }}
        </a>
        <h1 class="text-2xl font-bold text-white mt-1">{{ $source->title }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-white/50">
            <span class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 uppercase tracking-wider">{{ $source->type }}</span>
            <span>·</span>
            <span>Mind: {{ $mind->name }}</span>
            @if($source->status)
                <span>·</span>
                <span>{{ $source->status }}</span>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        @switch($source->type)
            @case(AiMindSource::TYPE_LINK)
                <p class="text-sm text-white/60 mb-2">Synced from URL:</p>
                <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer"
                   class="text-cyan-300 hover:underline break-all">{{ $source->url }}</a>
                @if(!empty($source->body))
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Last fetched content</p>
                        <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{{ $source->body }}</pre>
                    </div>
                @endif
                @break

            @case(AiMindSource::TYPE_DOCUMENT)
                <p class="text-sm text-white/60">
                    Uploaded document
                    @if($source->mime) <span class="text-white/40">({{ $source->mime }})</span>@endif
                    @if($source->size_bytes) <span class="text-white/40">· {{ number_format($source->size_bytes / 1024, 1) }} KB</span>@endif
                </p>
                @if(!empty($source->body))
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Extracted text</p>
                        <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{{ $source->body }}</pre>
                    </div>
                @endif
                @break

            @case(AiMindSource::TYPE_FAQ)
                @if(!empty($faqs))
                    <ul class="space-y-4">
                        @foreach($faqs as $row)
                            <li>
                                <p class="text-sm font-semibold text-white">Q: {{ $row['q'] ?? '' }}</p>
                                <p class="mt-1 text-sm text-white/70 whitespace-pre-wrap">A: {{ $row['a'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-white/50">No FAQ entries.</p>
                @endif
                @break

            @case(AiMindSource::TYPE_FEATURE)
                <p class="text-sm text-white/60">
                    Live 1INME feature snapshot
                    @if($source->feature_key)
                        <span class="text-white/40">— <code>{{ $source->feature_key }}</code></span>
                    @endif
                </p>
                <p class="mt-2 text-xs text-white/40">
                    Feature sources read live data from your account at query time, so there is no stored body to display here.
                </p>
                @break

            @default
                @if(!empty($source->body))
                    <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{{ $source->body }}</pre>
                @else
                    <p class="text-sm text-white/50">This source has no stored body.</p>
                @endif
        @endswitch
    </div>
</div>
@endsection
