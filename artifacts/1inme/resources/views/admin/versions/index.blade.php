@extends('admin.layouts.app')
@section('title', 'Versions & Releases')
@section('page-title', 'Versions & Releases')

@section('content')
<style>
    .release-notes > * + * { margin-top: 0.5rem; }
    .release-notes ul { list-style: disc; padding-left: 1.25rem; }
    .release-notes ol { list-style: decimal; padding-left: 1.25rem; }
    .release-notes li + li { margin-top: 0.2rem; }
    .release-notes h3, .release-notes h4 { font-weight: 600; color: rgba(255,255,255,0.85); }
    .release-notes h3 { font-size: 0.8rem; }
    .release-notes h4 { font-size: 0.75rem; }
    .release-notes a { color: rgb(147 197 253); text-decoration: underline; }
    .release-notes a:hover { color: rgb(191 219 254); }
    .release-notes code { font-family: ui-monospace, monospace; font-size: 0.7rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.25rem; padding: 0.05rem 0.3rem; }
    .release-notes blockquote { border-left: 2px solid rgba(255,255,255,0.2); padding-left: 0.75rem; color: rgba(255,255,255,0.45); }
    .release-notes strong { color: rgba(255,255,255,0.8); }
    html.light-mode .release-notes h3, html.light-mode .release-notes h4,
    html.light-mode .release-notes strong { color: rgba(15,23,42,0.85); }
    html.light-mode .release-notes a { color: rgb(37 99 235); }
    html.light-mode .release-notes a:hover { color: rgb(29 78 216); }
    html.light-mode .release-notes code { background: rgba(15,23,42,0.06); border-color: rgba(15,23,42,0.12); }
    html.light-mode .release-notes blockquote { border-left-color: rgba(15,23,42,0.2); color: rgba(15,23,42,0.55); }
</style>
<div class="max-w-5xl space-y-6" x-data="{ open: null, editing: null, adding: null }">

    {{-- Session flash --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-400 shrink-0 ak-green"></i>
            <p class="text-sm text-emerald-200 ak-green">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center gap-3">
            <i class="fas fa-triangle-exclamation text-red-400 shrink-0 ak-red"></i>
            <p class="text-sm text-red-200 ak-red">{{ session('error') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <p class="text-sm text-red-200 ak-red">{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-white ak-strong">Product surfaces</h2>
            <p class="text-sm text-white/50 mt-1 ak-muted">
                Current vs latest version for every surface, with per-surface changelogs.
                Zio Browser entries prefill automatically from GitHub releases.
            </p>
        </div>
        @if($snapshotGeneratedAt)
            <span class="shrink-0 text-[11px] text-white/40 ak-note mt-1">
                Snapshot: {{ \Illuminate\Support\Carbon::parse($snapshotGeneratedAt)->diffForHumans() }}
            </span>
        @endif
    </div>

    {{-- Surface cards --}}
    <div class="space-y-3">
        @foreach($surfaces as $surface)
            @php
                $badge = match($surface['status']) {
                    'up_to_date'       => ['label' => 'Up to date',       'cls' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 ak-green', 'icon' => 'fa-check-circle'],
                    'update_available' => ['label' => 'Update available', 'cls' => 'bg-blue-500/10 border-blue-500/30 text-blue-300 ak-blue',           'icon' => 'fa-circle-up'],
                    default            => ['label' => 'Unknown',          'cls' => 'bg-white/5 border-white/15 text-white/50 ak-note',                  'icon' => 'fa-circle-question'],
                };
                $key = $surface['key'];
            @endphp
            <div class="glass rounded-2xl border border-white/10 overflow-hidden">
                <button type="button"
                        class="w-full flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 text-left hover:bg-white/[0.03] transition-colors"
                        @click="open = (open === '{{ $key }}' ? null : '{{ $key }}')">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-white ak-strong">{{ $surface['label'] }}</p>
                        <p class="text-xs text-white/40 mt-0.5 ak-note">
                            @if($surface['current'])
                                Current: <span class="font-mono text-white/70 ak-muted">{{ $surface['current'] }}</span>
                            @else
                                Current: unknown
                            @endif
                            @if($surface['latest'])
                                <span class="mx-1.5 text-white/20 ak-note">·</span>
                                Latest: <span class="font-mono text-white/70 ak-muted">{{ $surface['latest'] }}</span>
                            @endif
                            @if($surface['last_release_at'])
                                <span class="mx-1.5 text-white/20 ak-note">·</span>
                                Last release {{ $surface['last_release_at'] }}
                            @endif
                        </p>
                        @if($surface['detail'])
                            <p class="text-[11px] text-white/35 mt-1 ak-note">{{ $surface['detail'] }}</p>
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold {{ $badge['cls'] }}">
                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                    </span>
                    <i class="fas fa-chevron-down text-white/30 ak-note text-xs transition-transform"
                       :class="open === '{{ $key }}' ? 'rotate-180' : ''"></i>
                </button>

                {{-- Expandable changelog --}}
                <div x-show="open === '{{ $key }}'" x-cloak class="border-t border-white/10 px-5 py-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/40 ak-note">Changelog</p>
                        <button type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-300 hover:text-blue-200 ak-blue"
                                @click="adding = (adding === '{{ $key }}' ? null : '{{ $key }}'); editing = null">
                            <i class="fas fa-plus"></i> Add entry
                        </button>
                    </div>

                    {{-- Add form --}}
                    <form x-show="adding === '{{ $key }}'" x-cloak method="POST" action="{{ route('admin.versions.releases.store') }}"
                          class="p-4 rounded-xl bg-white/[0.03] border border-white/10 space-y-3">
                        @csrf
                        <input type="hidden" name="surface" value="{{ $key }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Version</label>
                                <input type="text" name="version" required maxlength="100" placeholder="e.g. 1.2.0"
                                       class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white focus:border-blue-400/50 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Release date</label>
                                <input type="date" name="released_at"
                                       class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white focus:border-blue-400/50 focus:outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Notes (markdown)</label>
                            <textarea name="notes" rows="4" placeholder="- What changed…"
                                      class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white font-mono focus:border-blue-400/50 focus:outline-none"></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-500/20 border border-blue-400/30 text-xs font-semibold text-blue-200 hover:bg-blue-500/30 ak-blue">Save entry</button>
                            <button type="button" class="px-4 py-2 rounded-lg border border-white/10 text-xs text-white/50 hover:text-white/80 ak-muted" @click="adding = null">Cancel</button>
                        </div>
                    </form>

                    @if($surface['releases']->isEmpty())
                        <p class="text-xs text-white/35 ak-note">No changelog entries yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($surface['releases'] as $release)
                                <div class="rounded-xl bg-white/[0.02] border border-white/10">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                                        <span class="font-mono text-sm text-white ak-strong">{{ $release->version }}</span>
                                        @if($release->released_at)
                                            <span class="text-xs text-white/40 ak-note">{{ $release->released_at->toDateString() }}</span>
                                        @endif
                                        @if($release->source === 'github')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/5 border border-white/15 text-[10px] text-white/50 ak-note"><i class="fab fa-github"></i> GitHub</span>
                                        @elseif($release->source === 'seed')
                                            <span class="px-2 py-0.5 rounded-full bg-white/5 border border-white/15 text-[10px] text-white/50 ak-note">Backfilled</span>
                                        @endif
                                        <span class="flex-1"></span>
                                        <button type="button" class="text-xs text-white/40 hover:text-blue-300 ak-note"
                                                @click="editing = (editing === {{ $release->id }} ? null : {{ $release->id }}); adding = null">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.versions.releases.destroy', $release) }}"
                                              onsubmit="return confirm('Delete this changelog entry?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-white/40 hover:text-red-300 ak-note"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                    @if($release->notes)
                                        <div class="px-4 pb-3 -mt-1" x-show="editing !== {{ $release->id }}">
                                            <div class="release-notes text-xs text-white/55 ak-muted">{!! \App\Services\SafeHtml::render($release->notes) !!}</div>
                                        </div>
                                    @endif

                                    {{-- Edit form --}}
                                    <form x-show="editing === {{ $release->id }}" x-cloak method="POST"
                                          action="{{ route('admin.versions.releases.update', $release) }}"
                                          class="px-4 pb-4 pt-1 space-y-3 border-t border-white/10">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="surface" value="{{ $release->surface }}">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Version</label>
                                                <input type="text" name="version" required maxlength="100" value="{{ $release->version }}"
                                                       class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white focus:border-blue-400/50 focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Release date</label>
                                                <input type="date" name="released_at" value="{{ $release->released_at?->toDateString() }}"
                                                       class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white focus:border-blue-400/50 focus:outline-none">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-white/50 mb-1 ak-note">Notes (markdown)</label>
                                            <textarea name="notes" rows="4"
                                                      class="w-full px-3 py-2 rounded-lg bg-black/25 border border-white/10 text-sm text-white font-mono focus:border-blue-400/50 focus:outline-none">{{ $release->notes }}</textarea>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-500/20 border border-blue-400/30 text-xs font-semibold text-blue-200 hover:bg-blue-500/30 ak-blue">Save changes</button>
                                            <button type="button" class="px-4 py-2 rounded-lg border border-white/10 text-xs text-white/50 hover:text-white/80 ak-muted" @click="editing = null">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Sync status panel --}}
    <div class="glass rounded-2xl border border-white/10 p-5">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="text-base font-semibold text-white ak-strong">Sync status</h2>
                <p class="text-xs text-white/45 mt-1 ak-muted">
                    Last recorded result of each parity guard. Guards run automatically at CI/post-merge time —
                    this panel is read-only and never triggers a run.
                </p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($guards as $guard)
                <div class="rounded-xl bg-white/[0.02] border border-white/10 px-4 py-3 flex items-center gap-3">
                    @if($guard['status'] === 'pass')
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-emerald-500/15 flex items-center justify-center"><i class="fas fa-check text-emerald-400 ak-green"></i></span>
                    @elseif($guard['status'] === 'fail')
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-red-500/15 flex items-center justify-center"><i class="fas fa-xmark text-red-400 ak-red"></i></span>
                    @else
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-white/5 flex items-center justify-center"><i class="fas fa-question text-white/40 ak-note"></i></span>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white ak-strong">{{ $guard['label'] }}</p>
                        <p class="text-[11px] text-white/40 ak-note">
                            @if($guard['status'] && $guard['ran_at'])
                                {{ ucfirst($guard['status']) }}ed {{ \Illuminate\Support\Carbon::parse($guard['ran_at'])->diffForHumans() }}
                                @if($guard['note']) — {{ $guard['note'] }} @endif
                            @else
                                Not run recently — no result recorded yet.
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
