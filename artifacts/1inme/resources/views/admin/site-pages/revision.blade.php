@extends('admin.layouts.app')
@section('title', 'Revision #' . $revision->id . ' — ' . $page->title)
@section('content')
@php
    $current = [
        'title'            => (string) $page->title,
        'meta_description' => (string) ($page->meta_description ?? ''),
        'intro'            => (string) ($page->intro ?? ''),
        'last_updated_at'  => $page->last_updated_at ? $page->last_updated_at->toDateString() : null,
        'show_toc'         => (bool) ($page->show_toc ?? true),
        'sections'         => is_array($page->sections) ? $page->sections : [],
    ];
    $rev = [
        'title'            => (string) ($revision->title ?? ''),
        'meta_description' => (string) ($revision->meta_description ?? ''),
        'intro'            => (string) ($revision->intro ?? ''),
        'last_updated_at'  => $revision->last_updated_at ? $revision->last_updated_at->toDateString() : null,
        'show_toc'         => (bool) ($revision->show_toc ?? true),
        'sections'         => is_array($revision->sections) ? $revision->sections : [],
    ];
    $editor = $revision->editor();
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.edit', $page->slug) }}" class="text-xs text-violet-400 hover:underline">
        <i class="fas fa-arrow-left mr-1"></i>Back to editor
    </a>

    <div class="glass rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white">Revision #{{ $revision->id }}</h2>
                <p class="text-xs text-white/60 mt-1">
                    Saved {{ $revision->created_at->format('F j, Y g:i a') }}
                    @if($editor || $revision->editor_name)
                        by <span class="text-white/80">{{ $editor?->name ?? $revision->editor_name }}</span>
                    @endif
                </p>
                @if($revision->summary)
                    <p class="text-sm text-white/80 mt-2">{{ $revision->summary }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.site-pages.revisions.restore', [$page->slug, $revision->id]) }}"
                  onsubmit="return confirm('Restore this revision? Your current content will be saved as a new revision first.')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-medium">
                    <i class="fas fa-clock-rotate-left mr-1"></i> Restore this revision
                </button>
            </form>
        </div>
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Side-by-side preview</h3>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-white/40 mb-2">This revision</p>
                <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-3 text-sm text-white/80">
                    <div><span class="text-white/40 text-xs">Title:</span> <span @class(['text-amber-300' => $rev['title'] !== $current['title']])>{{ $rev['title'] ?: '—' }}</span></div>
                    <div><span class="text-white/40 text-xs">Meta description:</span> <span @class(['text-amber-300' => $rev['meta_description'] !== $current['meta_description']])>{{ $rev['meta_description'] ?: '—' }}</span></div>
                    <div><span class="text-white/40 text-xs">Intro:</span> <span @class(['text-amber-300' => $rev['intro'] !== $current['intro']])>{{ $rev['intro'] ?: '—' }}</span></div>
                    <div><span class="text-white/40 text-xs">Last updated:</span> <span @class(['text-amber-300' => $rev['last_updated_at'] !== $current['last_updated_at']])>{{ $rev['last_updated_at'] ?: '—' }}</span></div>
                    <div><span class="text-white/40 text-xs">Show TOC:</span> <span @class(['text-amber-300' => $rev['show_toc'] !== $current['show_toc']])>{{ $rev['show_toc'] ? 'yes' : 'no' }}</span></div>
                    <div>
                        <p class="text-white/40 text-xs mb-1">Sections ({{ count($rev['sections']) }})</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($rev['sections'] as $i => $s)
                                @php $cur = $current['sections'][$i] ?? null; @endphp
                                <li @class(['text-amber-300' => json_encode($s) !== json_encode($cur)])>
                                    {{ $s['heading'] ?? '(untitled)' }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-white/40 mb-2">Current page</p>
                <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-3 text-sm text-white/80">
                    <div><span class="text-white/40 text-xs">Title:</span> {{ $current['title'] ?: '—' }}</div>
                    <div><span class="text-white/40 text-xs">Meta description:</span> {{ $current['meta_description'] ?: '—' }}</div>
                    <div><span class="text-white/40 text-xs">Intro:</span> {{ $current['intro'] ?: '—' }}</div>
                    <div><span class="text-white/40 text-xs">Last updated:</span> {{ $current['last_updated_at'] ?: '—' }}</div>
                    <div><span class="text-white/40 text-xs">Show TOC:</span> {{ $current['show_toc'] ? 'yes' : 'no' }}</div>
                    <div>
                        <p class="text-white/40 text-xs mb-1">Sections ({{ count($current['sections']) }})</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($current['sections'] as $s)
                                <li>{{ $s['heading'] ?? '(untitled)' }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-3">Section bodies (this revision)</h3>
        <div class="space-y-4">
            @foreach($rev['sections'] as $s)
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <p class="text-sm font-semibold text-white mb-2">{{ $s['heading'] ?? '(untitled)' }}</p>
                    <pre class="whitespace-pre-wrap text-xs text-white/70 font-mono">{{ $s['body'] ?? '' }}</pre>
                </div>
            @endforeach
            @if(empty($rev['sections']))
                <p class="text-xs text-white/40 text-center py-4">No sections in this revision.</p>
            @endif
        </div>
    </div>
</div>
@endsection
