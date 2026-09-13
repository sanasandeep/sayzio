@extends('user.layouts.app')
@section('title', 'My Links')

@push('styles')
    {{-- Reuse the exact bento command-center look from the Dashboard. --}}
    @include('user.partials.bento-styles')
@endpush

@section('content')
@php
    $__heroActions = [];
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__canCreateLink = $__ws && auth()->check() && auth()->user()->canInWorkspace($__ws, 'links.create');
    if ($__canCreateLink) {
        $__heroActions[] = ['label' => 'Create Link', 'url' => route('user.links.create'), 'icon' => 'fa-plus', 'class' => 'btn-primary'];
        $__heroActions[] = ['label' => 'Bulk links', 'url' => route('user.links.url.bulk'), 'icon' => 'fa-layer-group', 'class' => 'btn-ghost'];
        $__heroActions[] = ['label' => 'Bulk pages', 'url' => route('user.links.biolink.bulk'), 'icon' => 'fa-table', 'class' => 'btn-ghost'];
    }
    // Export the currently-filtered list as CSV. Available to anyone who can
    // view links (viewers included) — it's the same data already on screen.
    $__heroActions[] = ['label' => 'Export CSV', 'url' => route('user.links.export', request()->only('search', 'type', 'project_id', 'status')), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'];
    // Move-to-workspace: only the workspace owner can move links, and only
    // makes sense if they own more than one workspace.
    $__moveTargets = collect();
    if ($__ws && auth()->check() && (int) $__ws->owner_user_id === auth()->id()) {
        $__moveTargets = auth()->user()->ownedWorkspaces()
            ->where('id', '!=', $__ws->id)
            ->orderBy('is_personal', 'desc')
            ->orderBy('name')
            ->get();
    }
    $__canMove = $__moveTargets->isNotEmpty();
    // Bulk delete: gated on the same links.delete workspace permission as
    // single delete; per-link ownership is re-checked server-side.
    $__canBulkDelete = $__ws && auth()->check() && auth()->user()->canInWorkspace($__ws, 'links.delete');
    // Bulk move-to-folder: same links.edit permission as the per-row folder
    // menu; only useful when at least one folder exists.
    $__canBulkFolder = $__ws && auth()->check() && auth()->user()->canInWorkspace($__ws, 'links.edit') && $projects->isNotEmpty();
    // Admin-granted cross-account transfer: capability + link ownership are
    // re-checked server-side; this only controls action visibility.
    $__canTransfer = auth()->check() && auth()->user()->canTransferAssets();
    $__summary = $summary ?? ['total' => 0, 'active' => 0, 'clicks' => 0];
@endphp

<div class="bento-stage">

    {{-- ===================== HEADER =====================
         Six boxes used to stand here: two chips, a ring, and three metric
         tiles. Between them they printed the same two numbers up to three
         times each, and pushed the first link about a thousand pixels down a
         page whose entire job is listing links. Every figure is still on
         screen -- as one line of text -- and the space the ring occupied now
         carries something the total beside it cannot say: the last seven
         days. ============================================================ --}}
    <div class="links-head">
        @include('common.partials.card-ribbon')
        <div class="min-w-0 cribbon-copy">
            <h1 class="links-title">My Links</h1>
            <p class="links-facts">
                <strong>{{ number_format($__summary['total']) }}</strong> {{ Str::plural('link', $__summary['total']) }}
                <span class="sep">&middot;</span>
                <strong>{{ number_format($__summary['active']) }}</strong> active
                <span class="sep">&middot;</span>
                <a href="{{ route('user.projects.index') }}">
                    <strong>{{ number_format($projects->count()) }}</strong> {{ Str::plural('folder', $projects->count()) }}
                </a>
            </p>
            @if(!empty($__heroActions))
            <div class="links-actions">
                @foreach($__heroActions as $a)
                    <a href="{{ $a['url'] ?? '#' }}" class="{{ $a['class'] ?? 'btn-primary' }} text-xs py-2">
                        @if(!empty($a['icon']))<i class="fas {{ $a['icon'] }} text-[10px]"></i>@endif
                        {{ $a['label'] ?? '' }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>

        @php
            $__days  = $trend['days'] ?? [];
            $__peak  = max(1, (int) ($trend['max'] ?? 0));
            // One scale for the whole drawing: 0 sits on the baseline, the
            // busiest day touches the top. Points are spaced across the full
            // width so the last one lands on the emphasised endpoint.
            $__pts = [];
            foreach ($__days as $__i => $__n) {
                $__x = count($__days) > 1 ? round(3 + ($__i * (214 / (count($__days) - 1))), 1) : 110;
                $__y = round(42 - (($__n / $__peak) * 34), 1);
                $__pts[] = $__x . ' ' . $__y;
            }
            $__line = implode(' L ', $__pts);
        @endphp
        <div class="links-trend cribbon-copy">
            <p class="k">Total clicks</p>
            <p class="n">{{ number_format($__summary['clicks']) }}</p>
            @if(($trend['total'] ?? 0) > 0)
                <p class="d"><strong>+{{ number_format($trend['total']) }}</strong> in the last 7 days</p>
            @else
                <p class="d">No clicks in the last 7 days</p>
            @endif

            {{-- A flat line through zero is not a trend, it is noise. --}}
            @if(count($__pts) > 1 && ($trend['total'] ?? 0) > 0)
            <svg class="spark" width="220" height="46" viewBox="0 0 220 46" role="img"
                 aria-label="Clicks per day over the last seven days. Busiest day {{ number_format($trend['max']) }}.">
                <defs>
                    <linearGradient id="linksSpark" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="currentColor" stop-opacity=".26"/>
                        <stop offset="100%" stop-color="currentColor" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <path d="M {{ $__line }} L 217 44 L 3 44 Z" fill="url(#linksSpark)"/>
                <path d="M {{ $__line }}" fill="none" stroke="currentColor" stroke-width="1.75"
                      stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="{{ explode(' ', end($__pts))[0] }}" cy="{{ explode(' ', end($__pts))[1] }}" r="3" fill="currentColor"/>
            </svg>
            @endif

            <a href="{{ route('user.stats.index') }}" class="links-stats-link">
                View stats <i class="fas fa-arrow-right text-[9px]"></i>
            </a>
        </div>
    </div>

@unless($__canCreateLink)
<div class="mb-4 px-3 py-2 rounded-lg text-xs flex items-center gap-2" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
    <i class="fas fa-lock"></i>
    <span>Your role in this workspace can view links but not create new ones.</span>
</div>
@endunless

{{-- The five filters used to sit in their own card, each under a stacked
     uppercase label, which made a permanent 120px block out of controls most
     visits never touch. Same form, same fields, one row. --}}
<form method="GET" class="links-toolbar">
    <div class="links-search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="text" name="search" value="{{ request('search') }}"
               aria-label="Search links"
               placeholder="Search {{ number_format($__summary['total']) }} {{ Str::plural('link', $__summary['total']) }}&hellip;">
    </div>

    <select name="type" class="links-pill" aria-label="Filter by type" onchange="this.form.submit()">
        <option value="" class="bg-[#0a0612]">All types</option>
        @foreach(\App\Modules\User\Support\LinkTypeCategories::categories() as $__typeCat)
            <optgroup label="{{ $__typeCat['label'] }}">
                @foreach($__typeCat['types'] as $__type)
                    <option value="{{ $__type['value'] }}" {{ request('type') === $__type['value'] ? 'selected' : '' }} class="bg-[#0a0612]">{{ $__type['label'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>

    <select name="project_id" class="links-pill" aria-label="Filter by folder" onchange="this.form.submit()">
        <option value="" class="bg-[#0a0612]">All folders</option>
        @foreach($projects as $project)
            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0a0612]">{{ $project->name }}</option>
        @endforeach
    </select>

    <select name="status" class="links-pill" aria-label="Filter by status" onchange="this.form.submit()">
        <option value="" class="bg-[#0a0612]">Any status</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }} class="bg-[#0a0612]">Active</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }} class="bg-[#0a0612]">Inactive</option>
    </select>

    <select name="sort" class="links-pill" aria-label="Sort" onchange="this.form.submit()">
        @foreach([
            'newest'      => 'Newest first',
            'oldest'      => 'Oldest first',
            'clicks_desc' => 'Most clicks',
            'clicks_asc'  => 'Fewest clicks',
            'title_asc'   => 'Title A to Z',
            'title_desc'  => 'Title Z to A',
        ] as $sortValue => $sortLabel)
            <option value="{{ $sortValue }}" @selected(($sort ?? 'newest') === $sortValue) class="bg-[#0a0612]">{{ $sortLabel }}</option>
        @endforeach
    </select>

    {{-- The selects submit on change; this is the keyboard path and the
         fallback with JavaScript off. --}}
    <button type="submit" class="links-pill links-pill--go">
        <i class="fas fa-search text-[10px]"></i> Search
    </button>
</form>

@if($links->isEmpty())
<div class="card-premium p-14 text-center">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.12);">
        <i class="fas fa-link text-blue-400 text-xl"></i>
    </div>
    <h3 class="text-base font-bold mb-1.5" style="color: var(--text-primary);">No links yet</h3>
    <p class="text-xs mb-5" style="color: var(--text-dimmed);">Create your first link to start tracking clicks, or let our wizard build a Link in Bio for you in under a minute.</p>
    @canInWorkspace('links.create')
    <div class="flex items-center justify-center gap-2 flex-wrap">
        <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2.5">
            <i class="fas fa-magic text-[10px]"></i> Build with wizard
        </a>
        <a href="{{ route('user.links.create') }}" class="text-xs py-2.5 px-4 rounded-xl border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <i class="fas fa-plus text-[10px]"></i> Create Link
        </a>
        <a href="{{ route('user.links.url.bulk') }}" class="text-xs py-2.5 px-4 rounded-xl border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <i class="fas fa-layer-group text-[10px]"></i> Bulk links
        </a>
        <a href="{{ route('user.links.biolink.bulk') }}" class="text-xs py-2.5 px-4 rounded-xl border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <i class="fas fa-table text-[10px]"></i> Bulk pages
        </a>
    </div>
    @else
    <p class="text-[11px]" style="color: var(--text-faint);"><i class="fas fa-lock mr-1"></i>Ask a workspace admin to create the first link.</p>
    @endcanInWorkspace
</div>
@else
<div x-data="{
        selected: [],
        moveOpen: false,
        moveTarget: '',
        view: localStorage.getItem('sayzio_links_view') === 'grid' ? 'grid' : 'list',
        setView(v) { this.view = v; localStorage.setItem('sayzio_links_view', v); },
        toggleAll(e) {
            const ids = Array.from(document.querySelectorAll('[data-link-id]')).map(el => parseInt(el.dataset.linkId, 10));
            this.selected = e.target.checked ? ids : [];
        },
    }">

{{-- Row action dropdowns (move to folder / workspace / transfer) must escape
     the row: a row later in the DOM would otherwise paint over an open menu. --}}
<style>
    /* ── Header ─────────────────────────────────────────────────────────── */
    /* The header is the page's highlight, so it is the card that carries the
       ribbon and the lattice -- which means it has to become a surface. It
       was a bare flex row before: no ground, no edge, nothing for a ribbon to
       bleed off. The card treatment comes from the same tokens every other
       card uses, so it is one of them rather than a special case. */
    .links-head {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 28px; flex-wrap: wrap; margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        padding: 22px 24px;
        border-radius: var(--lg-radius, 14px);
        border: 1px solid var(--border-glass);
        background: var(--bg-card);
    }
    .links-title {
        margin: 0 0 6px; font-size: clamp(1.5rem, 3.2vw, 1.9rem); line-height: 1.1;
        font-weight: 750; letter-spacing: -.03em; color: var(--text-primary);
    }
    .links-facts {
        margin: 0; font-size: 13px; color: var(--text-muted);
        font-variant-numeric: tabular-nums;
    }
    .links-facts strong { color: var(--text-primary); font-weight: 650; }
    .links-facts a { color: inherit; text-decoration: none; }
    .links-facts a:hover strong { color: var(--accent); }
    .links-facts .sep { color: var(--text-faint); margin: 0 5px; }
    .links-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 16px; }

    .links-trend { text-align: right; min-width: 232px; color: var(--accent); }
    .links-trend .k {
        margin: 0; font-size: 10px; letter-spacing: .14em; text-transform: uppercase;
        font-weight: 700; color: var(--text-faint);
    }
    .links-trend .n {
        margin: 4px 0 2px; font-size: 34px; font-weight: 750; line-height: 1.05;
        letter-spacing: -.035em; color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }
    .links-trend .d { margin: 0; font-size: 12px; color: var(--text-muted); }
    .links-trend .d strong { color: #34d399; font-weight: 650; }
    .links-trend .spark { display: block; margin: 8px 0 0 auto; max-width: 100%; }
    .links-stats-link {
        display: inline-flex; align-items: center; gap: 5px; margin-top: 8px;
        font-size: 11px; font-weight: 600; color: var(--accent); text-decoration: none;
    }

    /* ── Toolbar ────────────────────────────────────────────────────────── */
    .links-toolbar {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding-bottom: 14px; margin-bottom: 6px;
        border-bottom: 1px solid var(--border-glass, rgba(128,128,128,0.16));
    }
    .links-search { position: relative; flex: 1 1 220px; min-width: 170px; max-width: 340px; }
    .links-search i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        font-size: 11px; color: var(--text-faint); pointer-events: none;
    }
    .links-search input {
        width: 100%; font-size: 13px; color: var(--text-primary);
        background: var(--bg-card); border: 1px solid var(--border-strong);
        border-radius: 9px; padding: 8px 11px 8px 30px;
    }
    .links-search input::placeholder { color: var(--text-faint); }
    .links-pill {
        font-size: 12.5px; font-weight: 550; color: var(--text-secondary);
        background: var(--bg-card); border: 1px solid var(--border-strong);
        border-radius: 8px; padding: 8px 11px; cursor: pointer; max-width: 190px;
    }
    .links-pill--go { color: var(--text-muted); }
    .links-search input:focus, .links-pill:focus-visible {
        outline: 2px solid var(--accent); outline-offset: -1px;
    }

    /* ── Rows ───────────────────────────────────────────────────────────── */
    .links-list { display: flex; flex-direction: column; }
    .link-row {
        padding: 11px 10px; border-radius: 10px;
        border-bottom: 1px solid var(--border-glass, rgba(128,128,128,0.12));
        transition: background-color .12s ease;
    }
    .link-row:hover { background: var(--bg-card); }
    .link-row:has([data-menu-open="true"]) { position: relative; z-index: 40; }

    .link-row-meta {
        display: flex; align-items: center; gap: 5px; margin-top: 2px;
        font-size: 11.5px; color: var(--text-muted); min-width: 0;
    }
    .link-row-meta .url { color: var(--accent); }
    .link-row-meta .copy { flex: none; color: var(--text-faint); }
    .link-row-meta .copy:hover { color: var(--accent); }
    .link-row-meta .sep { color: var(--text-faint); }
    .link-row-meta .folder { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
    .link-row-meta .folder .dot { width: 6px; height: 6px; border-radius: 50%; }
    .link-row-meta .age { color: var(--text-faint); white-space: nowrap; }

    .link-row-clicks { text-align: right; font-variant-numeric: tabular-nums; }
    .link-row-clicks b {
        display: block; font-size: 14px; font-weight: 650;
        letter-spacing: -.01em; color: var(--text-primary);
    }
    .link-row-clicks span {
        font-size: 10px; letter-spacing: .08em; text-transform: uppercase;
        color: var(--text-faint);
    }

    .link-row-acts { display: flex; align-items: center; gap: 1px; }
    @media (hover: hover) and (min-width: 900px) {
        .link-row-acts { opacity: 0; transition: opacity .12s ease; }
        .link-row:hover .link-row-acts,
        .link-row:focus-within .link-row-acts,
        .link-row:has([data-menu-open="true"]) .link-row-acts { opacity: 1; }
    }

    @media (max-width: 700px) {
        .links-trend { text-align: left; min-width: 0; }
        .links-trend .spark { margin-left: 0; }
        .link-row-meta .folder, .link-row-meta .age,
        .link-row-meta .sep { display: none; }
    }
</style>

{{-- ===== View toggle: list (rows) vs grid (folder-coloured icon tiles) ===== --}}
<div class="flex items-center justify-end mb-3">
    <div class="inline-flex items-center rounded-xl border p-0.5" style="border-color: var(--border-strong); background: var(--bg-card);" role="group" aria-label="View mode">
        <button type="button" @click="setView('list')"
                :class="view === 'list' ? 'bg-blue-500/15 text-blue-400' : ''"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors"
                :style="view === 'list' ? '' : 'color: var(--text-faint);'"
                :aria-pressed="view === 'list' ? 'true' : 'false'" title="List view">
            <i class="fas fa-list text-[10px]"></i> List
        </button>
        <button type="button" @click="setView('grid')"
                :class="view === 'grid' ? 'bg-blue-500/15 text-blue-400' : ''"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors"
                :style="view === 'grid' ? '' : 'color: var(--text-faint);'"
                :aria-pressed="view === 'grid' ? 'true' : 'false'" title="Grid view">
            <i class="fas fa-border-all text-[10px]"></i> Grid
        </button>
    </div>
</div>

@if($__canMove || $__canBulkDelete || $__canBulkFolder)
<div x-show="selected.length > 0" x-cloak
     class="card-premium p-3 mb-3 flex flex-wrap items-center gap-3">
    <span class="text-sm font-semibold" style="color: var(--text-primary);">
        <span x-text="selected.length"></span> selected
    </span>
    <div class="flex flex-wrap items-center gap-2 ml-auto">
        @if($__canBulkFolder)
        <form method="POST" action="{{ route('user.links.move-to-folder-bulk') }}" class="flex items-center gap-2">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="link_ids[]" :value="id">
            </template>
            <select name="project_id" required class="theme-input text-xs py-1.5">
                <option value="" class="bg-[#0a0612]">Move to folder…</option>
                @foreach($projects as $__fp)
                    <option value="{{ $__fp->id }}" class="bg-[#0a0612]">{{ $__fp->name }}</option>
                @endforeach
                <option value="none" class="bg-[#0a0612]">No folder (remove)</option>
            </select>
            <button type="submit" class="btn-primary text-xs py-1.5">
                <i class="fas fa-folder-open text-[10px]"></i> Move
            </button>
        </form>
        @endif
        @if($__canMove)
        <form method="POST" action="{{ route('user.links.move-bulk') }}" class="flex items-center gap-2">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="link_ids[]" :value="id">
            </template>
            <select name="workspace_id" required class="theme-input text-xs py-1.5">
                <option value="" class="bg-[#0a0612]">Move to workspace…</option>
                @foreach($__moveTargets as $t)
                    <option value="{{ $t->id }}" class="bg-[#0a0612]">
                        {{ $t->name }} ({{ $t->is_personal ? 'Personal' : 'Team' }})
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary text-xs py-1.5">
                <i class="fas fa-arrow-right text-[10px]"></i> Move
            </button>
        </form>
        @endif
        @if($__canBulkDelete)
        <form method="POST" action="{{ route('user.links.delete-bulk') }}" class="flex items-center"
              onsubmit="var n = this.querySelectorAll('input[name^=link_ids]').length; return window.themedConfirmSubmit(this, {title: 'Delete ' + n + ' link' + (n === 1 ? '' : 's') + '?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="link_ids[]" :value="id">
            </template>
            <button type="submit" class="text-xs py-1.5 px-3 rounded-xl font-semibold transition-all border"
                    style="background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.25); color: #f87171;">
                <i class="fas fa-trash text-[10px]"></i> Delete
            </button>
        </form>
        @endif
        <button type="button" @click="selected = []" class="btn-ghost text-xs py-1.5">Clear</button>
    </div>
</div>
@endif

{{-- Icon, label and the tile's three colours all come from one resolver, so
     the list rows and the grid tiles below cannot disagree about what a
     Slides link looks like. See LinkTileStyle for why they used to. --}}
<div class="links-list" x-show="view === 'list'">
    @foreach($links as $link)
    @php $ts = \App\Modules\User\Support\LinkTileStyle::for($link); @endphp
    <div class="link-row group" data-link-id="{{ $link->id }}">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                @if($__canMove || $__canBulkDelete || $__canBulkFolder)
                <label class="flex-shrink-0 cursor-pointer" title="Select link">
                    <input type="checkbox" :value="{{ $link->id }}" x-model.number="selected"
                           class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40">
                </label>
                @endif
                <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center" style="background: {{ $ts['bg'] }}; border: 1px solid {{ $ts['border'] }};">
                    <i class="fas {{ $ts['icon'] }} text-xs" style="color: {{ $ts['color'] }};"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('user.links.show', $link) }}" class="text-sm font-semibold truncate transition-colors hover:text-blue-400" style="color: var(--text-primary);">
                            {{ $link->title ?: $link->alias }}
                        </a>
                        <span class="badge" style="background: {{ $ts['bg'] }}; color: {{ $ts['color'] }}; border: 1px solid {{ $ts['border'] }};">{{ $ts['label'] }}</span>
                        @if(!$link->is_active)
                            <span class="badge" style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.12);">Inactive</span>
                        @endif
                        @if($link->is_password_protected)
                            <i class="fas fa-lock text-[9px]" style="color: var(--text-faint);" title="Password protected"></i>
                        @endif
                        @if($link->expires_at)
                            <i class="fas fa-clock text-[9px]" style="color: var(--text-faint);" title="Expires {{ $link->expires_at->format('M d, Y') }}"></i>
                        @endif
                    </div>
                    <div class="link-row-meta" x-data="{ copied: false }">
                        <span class="url truncate">{{ $link->getShortUrl() }}</span>
                        <button type="button" aria-label="Copy link"
                                @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="copy">
                            <i x-show="!copied" class="fas fa-copy text-[10px]"></i>
                            <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-[10px]"></i>
                        </button>
                        @if($link->project)
                        <span class="sep">&middot;</span>
                        <span class="folder">
                            <span class="dot" style="background-color: {{ $link->project->color ?: '#3b82f6' }}"></span>{{ $link->project->name }}
                        </span>
                        @endif
                        <span class="sep">&middot;</span>
                        <span class="age">{{ $link->created_at->diffForHumans() }}</span>
                    </div>

                </div>
            </div>

            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="link-row-clicks">
                    <b>{{ number_format($link->total_clicks) }}</b>
                    <span>{{ Str::plural('click', $link->total_clicks) }}</span>
                </div>
                {{-- On a pointer device these arrive on hover; on touch, where
                     there is no hover to arrive on, they stay put. --}}
                <div class="link-row-acts">
                    <a href="{{ route('user.links.show', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-blue-500/10" style="color: var(--text-faint);" title="View">
                        <i class="fas fa-chart-bar text-xs hover:text-blue-400"></i>
                    </a>
                    @canInWorkspace('links.edit')
                        @if($link->type === 'biolink')
                        <a href="{{ route('user.links.blocks.editor', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-pink-500/10" style="color: var(--text-faint);" title="Edit Blocks">
                            <i class="fas fa-th-large text-xs hover:text-pink-400"></i>
                        </a>
                        @endif
                        <a href="{{ route('user.links.edit', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-blue-500/10" style="color: var(--text-faint);" title="Edit">
                            <i class="fas fa-edit text-xs hover:text-blue-400"></i>
                        </a>
                    @else
                        <span class="p-1.5 rounded-md opacity-50 cursor-not-allowed" style="color: var(--text-faint);" title="Your role doesn't allow editing links">
                            <i class="fas fa-lock text-xs"></i>
                        </span>
                    @endcanInWorkspace
                    @canInWorkspace('links.create')
                    <form action="{{ route('user.links.duplicate', $link) }}" method="POST">
                        @csrf
                        <button class="p-1.5 rounded-md transition-all hover:bg-cyan-500/10" style="color: var(--text-faint);" title="Duplicate">
                            <i class="fas fa-copy text-xs hover:text-cyan-400"></i>
                        </button>
                    </form>
                    @endcanInWorkspace
                    @canInWorkspace('links.edit')
                    <div class="relative" x-data="{ open: false, saving: false }" :data-menu-open="open ? 'true' : 'false'">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                class="p-1.5 rounded-md transition-all hover:bg-yellow-500/10"
                                style="color: var(--text-faint);" title="Move to folder">
                            <i class="fas fa-folder-open text-xs hover:text-yellow-400"></i>
                        </button>
                        <div x-show="open" x-cloak
                             class="absolute right-0 top-full mt-1 w-56 rounded-lg border shadow-lg z-20 overflow-hidden max-h-72 overflow-y-auto"
                             style="background: var(--bg-card); border-color: var(--border-strong);">
                            <div class="px-3 py-2 text-[10px] uppercase tracking-wider font-bold border-b" style="color: var(--text-faint); border-color: var(--border-strong);">Move to folder</div>
                            @foreach($projects as $__fp)
                                <button type="button"
                                        @click="saving = true; fetch('{{ route('user.links.move-to-folder', $link) }}', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({project_id: {{ $__fp->id }}})}).then(r => r.ok ? window.location.reload() : (saving = false))"
                                        :disabled="saving"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-black/5 flex items-center gap-2 {{ $link->project_id == $__fp->id ? 'opacity-50' : '' }}" style="color: var(--text-primary);">
                                    <i class="fas fa-folder text-[10px]" style="color: {{ $__fp->color ?: '#3b82f6' }}"></i>
                                    <span class="truncate">{{ $__fp->name }}</span>
                                    @if($link->project_id == $__fp->id)<i class="fas fa-check text-[9px] ml-auto opacity-60"></i>@endif
                                </button>
                            @endforeach
                            @if($link->project_id)
                                <button type="button"
                                        @click="saving = true; fetch('{{ route('user.links.move-to-folder', $link) }}', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({project_id: null})}).then(r => r.ok ? window.location.reload() : (saving = false))"
                                        :disabled="saving"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-black/5 flex items-center gap-2 border-t" style="color: var(--text-primary); border-color: var(--border-strong);">
                                    <i class="fas fa-folder-minus text-[10px] opacity-60"></i>
                                    <span>Remove from folder</span>
                                </button>
                            @endif
                            <a href="{{ route('user.projects.create') }}" class="w-full text-left px-3 py-2 text-sm hover:bg-black/5 flex items-center gap-2 border-t block" style="color: var(--text-faint); border-color: var(--border-strong);">
                                <i class="fas fa-plus text-[10px]"></i>
                                <span>New folder</span>
                            </a>
                        </div>
                    </div>
                    @endcanInWorkspace
                    @if($__canMove)
                    <div class="relative" x-data="{ open: false }" :data-menu-open="open ? 'true' : 'false'">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                class="p-1.5 rounded-md transition-all hover:bg-amber-500/10"
                                style="color: var(--text-faint);" title="Move to another workspace">
                            <i class="fas fa-arrow-right-arrow-left text-xs hover:text-amber-400"></i>
                        </button>
                        <div x-show="open" x-cloak
                             class="absolute right-0 top-full mt-1 w-56 rounded-lg border shadow-lg z-20 overflow-hidden"
                             style="background: var(--bg-card); border-color: var(--border-strong);">
                            <div class="px-3 py-2 text-[10px] uppercase tracking-wider font-bold border-b" style="color: var(--text-faint); border-color: var(--border-strong);">Move to workspace</div>
                            @foreach($__moveTargets as $t)
                                <form method="POST" action="{{ route('user.links.move', $link) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_id" value="{{ $t->id }}">
                                    <button type="submit" class="w-full text-left px-3 py-2 text-sm hover:bg-black/5 flex items-center gap-2" style="color: var(--text-primary);">
                                        <i class="fas {{ $t->is_personal ? 'fa-user' : 'fa-users' }} text-[10px] opacity-60"></i>
                                        <span class="truncate">{{ $t->name }}</span>
                                        <span class="ml-auto text-[9px] opacity-60 uppercase">{{ $t->is_personal ? 'Personal' : 'Team' }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @if($__canTransfer && (int) $link->user_id === auth()->id())
                    <div class="relative" x-data="{ open: false }" :data-menu-open="open ? 'true' : 'false'">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                class="p-1.5 rounded-md transition-all hover:bg-blue-500/10"
                                style="color: var(--text-faint);" title="Transfer to another user">
                            <i class="fas fa-paper-plane text-xs hover:text-blue-400"></i>
                        </button>
                        <div x-show="open" x-cloak
                             class="absolute right-0 top-full mt-1 w-64 rounded-lg border shadow-lg z-20 overflow-hidden"
                             style="background: var(--bg-card); border-color: var(--border-strong);">
                            <div class="px-3 py-2 text-[10px] uppercase tracking-wider font-bold border-b" style="color: var(--text-faint); border-color: var(--border-strong);">Transfer to another user</div>
                            <form method="POST" action="{{ route('user.links.transfer', $link) }}" class="p-3 space-y-2"
                                  onsubmit="return window.themedConfirmSubmit ? window.themedConfirmSubmit(this, {title: 'Transfer this link? This is instant and cannot be undone.', confirmText: 'Transfer', confirmIcon: 'fa-paper-plane', iconClass: 'fa-paper-plane'}) : confirm('Transfer this link? This cannot be undone.')">
                                @csrf
                                <input type="email" name="recipient_email" required placeholder="Recipient's account email"
                                       class="w-full px-2.5 py-1.5 rounded-lg border text-xs"
                                       style="background: var(--bg-input, transparent); border-color: var(--border-strong); color: var(--text-primary);">
                                <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 transition">
                                    <i class="fas fa-paper-plane mr-1"></i> Transfer ownership
                                </button>
                                <p class="text-[10px] leading-snug" style="color: var(--text-faint);">Moves this link and all its data to the recipient's account instantly.</p>
                            </form>
                        </div>
                    </div>
                    @endif
                    @canInWorkspace('links.delete')
                    <form action="{{ route('user.links.destroy', $link) }}" method="POST" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this link?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button class="p-1.5 rounded-md transition-all hover:bg-red-500/10" style="color: var(--text-faint);" title="Delete">
                            <i class="fas fa-trash text-xs hover:text-red-400"></i>
                        </button>
                    </form>
                    @else
                    <span class="p-1.5 rounded-md opacity-50 cursor-not-allowed" style="color: var(--text-faint);" title="Your role doesn't allow deleting links">
                        <i class="fas fa-trash text-xs"></i>
                    </span>
                    @endcanInWorkspace
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ===== GRID VIEW: Finder-style icon tiles, tinted by the LINK TYPE ===== --}}
{{-- The tile used to take the folder's colour, so the same Slides link was
     fuchsia in the list and blue here, and every link outside a folder was
     blue whatever it was. The folder is still shown, by its dot and name
     under the title, which is where a folder belongs. --}}
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" x-show="view === 'grid'" x-cloak>
    @foreach($links as $link)
    @php
        $ts = \App\Modules\User\Support\LinkTileStyle::for($link);
        // The folder still colours its own dot below.
        $__pcHex = $link->project?->color ?: '#3b82f6';
        $__pcHex = preg_match('/^#[0-9a-fA-F]{6}$/', $__pcHex) ? $__pcHex : '#3b82f6';
    @endphp
    <a href="{{ route('user.links.show', $link) }}"
       class="card-premium p-4 flex flex-col items-center text-center group relative transition-transform hover:-translate-y-0.5"
       title="{{ $link->title ?: $link->alias }}">
        @if(!$link->is_active)
            <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-400" title="Inactive"></span>
        @endif
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-2.5"
             style="background: {{ $ts['bg'] }}; border: 1px solid {{ $ts['border'] }};"
             title="{{ $ts['label'] }}">
            <i class="fas {{ $ts['icon'] }} text-xl" style="color: {{ $ts['color'] }};"></i>
        </div>
        <p class="text-xs font-semibold w-full truncate" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</p>
        <p class="text-[10px] w-full truncate mt-0.5 text-blue-400/60">{{ $link->getShortUrl() }}</p>
        <div class="flex items-center gap-1.5 mt-1.5 text-[10px]" style="color: var(--text-faint);">
            @if($link->project)
                <span class="flex items-center gap-1 min-w-0">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $__pcHex }}"></span>
                    <span class="truncate max-w-[90px]">{{ $link->project->name }}</span>
                </span>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ number_format($link->total_clicks) }} clicks</span>
        </div>
    </a>
    @endforeach
</div>

<div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
    <div class="flex items-center gap-3 text-xs" style="color: var(--text-faint);">
        <span>
            Showing
            <strong style="color: var(--text-secondary);">{{ number_format($links->firstItem() ?? 0) }}–{{ number_format($links->lastItem() ?? 0) }}</strong>
            of <strong style="color: var(--text-secondary);">{{ number_format($links->total()) }}</strong>
        </span>
        <form method="GET" class="flex items-center gap-1.5">
            @foreach(request()->except(['per_page', 'page']) as $__k => $__v)
                <input type="hidden" name="{{ $__k }}" value="{{ $__v }}">
            @endforeach
            <label for="per_page" class="whitespace-nowrap">Per page</label>
            <select id="per_page" name="per_page" onchange="this.form.submit()" class="theme-input appearance-none pr-7 py-1 text-xs">
                @foreach([15, 30, 50, 100] as $__pp)
                    <option value="{{ $__pp }}" {{ (int) request('per_page', 15) === $__pp ? 'selected' : '' }} class="bg-[#0a0612]">{{ $__pp }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div>{{ $links->onEachSide(1)->links() }}</div>
</div>
</div>{{-- /x-data wrapper --}}
@endif

</div>{{-- /bento-stage --}}
@endsection
