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

    $highlightChunk = $highlightChunk ?? null;
    $highlightContent = $highlightChunk ? (string) $highlightChunk->content : '';

    /**
     * Whitespace-tolerant chunk highlighter. The chunker collapses all
     * whitespace into single spaces before embedding, so chunk content
     * rarely matches the body verbatim. We build a regex from the
     * chunk's word sequence with `\s+` between words, locate it in the
     * raw body, then assemble an HTML-escaped version with the matched
     * range wrapped in <mark>. Returns [html, matched].
     */
    $renderHighlightedBody = function (string $body, string $chunkContent): array {
        if ($chunkContent === '' || $body === '') {
            return [e($body), false];
        }
        $words = preg_split('/\s+/u', trim($chunkContent), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return [e($body), false];
        $pattern = '/' . implode('\s+', array_map(fn ($w) => preg_quote($w, '/'), $words)) . '/u';
        if (preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return [e($body), false];
        }
        $start = $m[0][1];
        $len   = strlen($m[0][0]);
        $before = substr($body, 0, $start);
        $hit    = substr($body, $start, $len);
        $after  = substr($body, $start + $len);
        $html = e($before)
            . '<mark id="chunk-highlight" class="bg-yellow-300/30 text-white rounded px-0.5 ring-1 ring-yellow-300/40">'
            . e($hit)
            . '</mark>'
            . e($after);
        return [$html, true];
    };

    $highlightMatched = false;
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

    @if($highlightChunk)
        <div class="rounded-xl border border-yellow-300/30 bg-yellow-300/[0.05] px-4 py-3 text-xs text-yellow-100/90 flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mt-0.5 shrink-0 text-yellow-300" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.94 6.94a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06l1.72-1.72H6.75a.75.75 0 010-1.5h3.91L8.94 8a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>
            <span>Showing the passage cited by the AI &mdash; highlighted below.</span>
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        @switch($source->type)
            @case(AiMindSource::TYPE_LINK)
                <p class="text-sm text-white/60 mb-2">Synced from URL:</p>
                <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer"
                   class="text-cyan-300 hover:underline break-all">{{ $source->url }}</a>
                @if(!empty($source->body))
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Last fetched content</p>
                        @php
                            [$bodyHtml, $matched] = $renderHighlightedBody((string) $source->body, $highlightContent);
                            $highlightMatched = $highlightMatched || $matched;
                        @endphp
                        <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{!! $bodyHtml !!}</pre>
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
                        @php
                            [$bodyHtml, $matched] = $renderHighlightedBody((string) $source->body, $highlightContent);
                            $highlightMatched = $highlightMatched || $matched;
                        @endphp
                        <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{!! $bodyHtml !!}</pre>
                    </div>
                @endif
                @break

            @case(AiMindSource::TYPE_FAQ)
                @if(!empty($faqs))
                    @php
                        // Decide which FAQ rows the cited chunk covers by
                        // checking whether each row's question or answer
                        // text appears (whitespace-collapsed) inside the
                        // chunk. Multiple short rows can fit in one chunk,
                        // so we may highlight more than one.
                        $normalizedChunk = $highlightContent !== ''
                            ? preg_replace('/\s+/u', ' ', $highlightContent)
                            : '';
                        $faqMatches = [];
                        $faqAnchorIdx = null;
                        if ($normalizedChunk !== '') {
                            foreach ($faqs as $idx => $row) {
                                $q = trim((string) ($row['q'] ?? ''));
                                $a = trim((string) ($row['a'] ?? ''));
                                $qN = preg_replace('/\s+/u', ' ', $q);
                                $aN = preg_replace('/\s+/u', ' ', $a);
                                $hit = ($qN !== '' && mb_stripos($normalizedChunk, $qN) !== false)
                                    || ($aN !== '' && mb_stripos($normalizedChunk, $aN) !== false);
                                if ($hit) {
                                    $faqMatches[$idx] = true;
                                    $highlightMatched = true;
                                    if ($faqAnchorIdx === null) $faqAnchorIdx = $idx;
                                }
                            }
                        }
                    @endphp
                    <ul class="space-y-4">
                        @foreach($faqs as $idx => $row)
                            @php $isHit = !empty($faqMatches[$idx]); @endphp
                            <li
                                @if($idx === $faqAnchorIdx) id="chunk-highlight" @endif
                                @class([
                                    'rounded-lg p-3 -mx-3',
                                    'bg-yellow-300/10 ring-1 ring-yellow-300/40' => $isHit,
                                ])>
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
                    Live Sayzio feature snapshot
                    @if($source->feature_key)
                        <span class="text-white/40"> - <code>{{ $source->feature_key }}</code></span>
                    @endif
                </p>
                <p class="mt-2 text-xs text-white/40">
                    Feature sources read live data from your account at query time, so there is no stored body to display here.
                </p>
                @break

            @default
                @if(!empty($source->body))
                    @php
                        [$bodyHtml, $matched] = $renderHighlightedBody((string) $source->body, $highlightContent);
                        $highlightMatched = $highlightMatched || $matched;
                    @endphp
                    <pre class="whitespace-pre-wrap text-sm text-white/80 font-sans">{!! $bodyHtml !!}</pre>
                @else
                    <p class="text-sm text-white/50">This source has no stored body.</p>
                @endif
        @endswitch

        @if($highlightChunk && !$highlightMatched)
            {{-- Couldn't pinpoint the chunk inside the body (whitespace
                 mismatch, source body not stored, or content drifted since
                 ingest). Fall back to showing the raw chunk so creators
                 still see exactly what the AI quoted. --}}
            <div id="chunk-highlight" class="mt-4 pt-4 border-t border-white/10">
                <p class="text-xs uppercase tracking-wider text-yellow-200/80 mb-2">Cited passage</p>
                <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans bg-yellow-300/[0.06] border border-yellow-300/20 rounded-lg p-3">{{ $highlightContent }}</pre>
            </div>
        @endif
    </div>
</div>

@if($highlightChunk)
    <script>
        (function () {
            var scroll = function () {
                var el = document.getElementById('chunk-highlight');
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', scroll);
            } else {
                scroll();
            }
        })();
    </script>
@endif
@endsection
