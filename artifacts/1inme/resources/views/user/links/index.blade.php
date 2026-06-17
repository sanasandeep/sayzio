@extends('user.layouts.app')
@section('title', 'My Links')

@section('content')
@php
    $__heroActions = [];
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__canCreateLink = $__ws && auth()->check() && auth()->user()->canInWorkspace($__ws, 'links.create');
    if ($__canCreateLink) {
        $__heroActions[] = ['label' => 'Create Link', 'url' => route('user.links.create'), 'icon' => 'fa-plus', 'class' => 'btn-primary'];
        $__heroActions[] = ['label' => 'Bulk create', 'url' => route('user.links.url.bulk'), 'icon' => 'fa-layer-group', 'class' => 'btn-ghost'];
    }
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
@endphp
@include('user.partials.page-hero', [
    'title'    => 'My Links',
    'subtitle' => 'Manage and track all your shortened links.',
    'icon'     => 'fa-link',
    'chips'    => [
        ['icon' => 'fa-layer-group', 'text' => ($links->total() ?? $links->count()) . ' total'],
    ],
    'actions'  => $__heroActions,
])
@unless($__canCreateLink)
<div class="mb-4 px-3 py-2 rounded-lg text-xs flex items-center gap-2" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
    <i class="fas fa-lock"></i>
    <span>Your role in this workspace can view links but not create new ones.</span>
</div>
@endunless

<div class="card-premium mb-5">
    <form method="GET" class="p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search links..."
                   class="theme-input w-full">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Type</label>
            <select name="type" class="theme-input appearance-none pr-8">
                <option value="" class="bg-[#0a0612]">All Types</option>
                <option value="url" {{ request('type') === 'url' ? 'selected' : '' }} class="bg-[#0a0612]">Short Link</option>
                <option value="biolink" {{ request('type') === 'biolink' ? 'selected' : '' }} class="bg-[#0a0612]">Link in Bio</option>
                <option value="file" {{ request('type') === 'file' ? 'selected' : '' }} class="bg-[#0a0612]">File Share</option>
                <option value="ics" {{ request('type') === 'ics' ? 'selected' : '' }} class="bg-[#0a0612]">Event</option>
                <option value="vcf" {{ request('type') === 'vcf' ? 'selected' : '' }} class="bg-[#0a0612]">Contact Card</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Project</label>
            <select name="project_id" class="theme-input appearance-none pr-8">
                <option value="" class="bg-[#0a0612]">All Projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0a0612]">{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Status</label>
            <select name="status" class="theme-input appearance-none pr-8">
                <option value="" class="bg-[#0a0612]">All</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }} class="bg-[#0a0612]">Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }} class="bg-[#0a0612]">Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn-ghost text-xs py-2">
            <i class="fas fa-search text-[10px]"></i> Filter
        </button>
    </form>
</div>

@if($links->isEmpty())
<div class="card-premium p-14 text-center">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.12);">
        <i class="fas fa-link text-violet-400 text-xl"></i>
    </div>
    <h3 class="text-base font-bold mb-1.5" style="color: var(--text-primary);">No links yet</h3>
    <p class="text-xs mb-5" style="color: var(--text-dimmed);">Create your first link to start tracking clicks — or let our wizard build a Link in Bio for you in under a minute.</p>
    @canInWorkspace('links.create')
    <div class="flex items-center justify-center gap-2 flex-wrap">
        <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2.5">
            <i class="fas fa-magic text-[10px]"></i> Build with wizard
        </a>
        <a href="{{ route('user.links.create') }}" class="text-xs py-2.5 px-4 rounded-xl border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <i class="fas fa-plus text-[10px]"></i> Create Link
        </a>
        <a href="{{ route('user.links.url.bulk') }}" class="text-xs py-2.5 px-4 rounded-xl border border-white/10 text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <i class="fas fa-layer-group text-[10px]"></i> Bulk create
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
        toggleAll(e) {
            const ids = Array.from(document.querySelectorAll('[data-link-id]')).map(el => parseInt(el.dataset.linkId, 10));
            this.selected = e.target.checked ? ids : [];
        },
    }">

@if($__canMove)
<div x-show="selected.length > 0" x-cloak
     class="card-premium p-3 mb-3 flex flex-wrap items-center gap-3">
    <span class="text-sm font-semibold" style="color: var(--text-primary);">
        <span x-text="selected.length"></span> selected
    </span>
    <form method="POST" action="{{ route('user.links.move-bulk') }}" class="flex items-center gap-2 ml-auto">
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
        <button type="button" @click="selected = []" class="btn-ghost text-xs py-1.5">Clear</button>
    </form>
</div>
@endif

<div class="space-y-2.5">
    @foreach($links as $link)
    @php
        $typeStyles = [
            'url'     => ['icon' => 'fa-link',         'bg' => 'rgba(124,58,237,0.08)', 'border' => 'rgba(124,58,237,0.12)', 'color' => '#a78bfa', 'label' => 'Short Link'],
            'biolink' => ['icon' => 'fa-id-card',      'bg' => 'rgba(236,72,153,0.08)', 'border' => 'rgba(236,72,153,0.12)', 'color' => '#f472b6', 'label' => 'Link in Bio'],
            'file'    => ['icon' => 'fa-file',         'bg' => 'rgba(16,185,129,0.08)', 'border' => 'rgba(16,185,129,0.12)', 'color' => '#34d399', 'label' => 'File Share'],
            'ics'     => ['icon' => 'fa-calendar',     'bg' => 'rgba(245,158,11,0.08)', 'border' => 'rgba(245,158,11,0.12)', 'color' => '#fbbf24', 'label' => 'Event'],
            'vcf'     => ['icon' => 'fa-address-card', 'bg' => 'rgba(6,182,212,0.08)',  'border' => 'rgba(6,182,212,0.12)',  'color' => '#22d3ee', 'label' => 'Contact Card'],
            'reviews' => ['icon' => 'fa-star',         'bg' => 'rgba(234,179,8,0.08)',  'border' => 'rgba(234,179,8,0.12)',  'color' => '#fde047', 'label' => 'Reviews Page'],
            'resume'  => ['icon' => 'fa-file-lines',   'bg' => 'rgba(99,102,241,0.08)', 'border' => 'rgba(99,102,241,0.12)', 'color' => '#a5b4fc', 'label' => 'Resume / Portfolio'],
        ];
        $ts = $typeStyles[$link->type] ?? $typeStyles['url'];

        // For File Share links, swap in an extension-aware icon + colour so
        // a PDF looks like a PDF, an image looks like an image, etc.
        if ($link->type === 'file' && $link->fileLink) {
            $ext = strtolower(pathinfo($link->fileLink->original_name ?? '', PATHINFO_EXTENSION));
            $fileIconMap = [
                'pdf'                                  => ['fa-file-pdf',        '#ef4444', 'rgba(239,68,68,0.08)',  'rgba(239,68,68,0.12)'],
                'doc'  => 'word', 'docx' => 'word', 'rtf' => 'word', 'odt' => 'word',
                'xls'  => 'excel','xlsx' => 'excel','csv' => 'excel','ods' => 'excel',
                'ppt'  => 'ppt',  'pptx' => 'ppt',  'odp' => 'ppt',
                'jpg'  => 'img',  'jpeg' => 'img',  'png' => 'img',  'gif' => 'img', 'webp' => 'img', 'svg' => 'img', 'bmp' => 'img', 'avif' => 'img',
                'mp4'  => 'video','mov'  => 'video','avi' => 'video','webm'=> 'video','mkv' => 'video',
                'mp3'  => 'audio','wav'  => 'audio','ogg' => 'audio','flac'=> 'audio', 'm4a' => 'audio',
                'zip'  => 'zip',  'rar'  => 'zip',  '7z'  => 'zip',  'tar' => 'zip', 'gz' => 'zip',
                'txt'  => 'text', 'md'   => 'text', 'log' => 'text',
                'js'   => 'code', 'ts'   => 'code', 'php' => 'code', 'py' => 'code', 'html' => 'code', 'css' => 'code', 'json' => 'code', 'xml' => 'code',
            ];
            $fileGroups = [
                'word'  => ['fa-file-word',        '#3b82f6', 'rgba(59,130,246,0.08)', 'rgba(59,130,246,0.12)'],
                'excel' => ['fa-file-excel',       '#10b981', 'rgba(16,185,129,0.08)', 'rgba(16,185,129,0.12)'],
                'ppt'   => ['fa-file-powerpoint',  '#f97316', 'rgba(249,115,22,0.08)', 'rgba(249,115,22,0.12)'],
                'img'   => ['fa-file-image',       '#ec4899', 'rgba(236,72,153,0.08)', 'rgba(236,72,153,0.12)'],
                'video' => ['fa-file-video',       '#8b5cf6', 'rgba(139,92,246,0.08)', 'rgba(139,92,246,0.12)'],
                'audio' => ['fa-file-audio',       '#06b6d4', 'rgba(6,182,212,0.08)',  'rgba(6,182,212,0.12)'],
                'zip'   => ['fa-file-zipper',      '#eab308', 'rgba(234,179,8,0.08)',  'rgba(234,179,8,0.12)'],
                'text'  => ['fa-file-lines',       '#94a3b8', 'rgba(148,163,184,0.08)','rgba(148,163,184,0.12)'],
                'code'  => ['fa-file-code',        '#a855f7', 'rgba(168,85,247,0.08)', 'rgba(168,85,247,0.12)'],
            ];
            $hit = $fileIconMap[$ext] ?? null;
            if (is_array($hit)) {
                [$icon, $color, $bg, $border] = $hit;
            } elseif (is_string($hit) && isset($fileGroups[$hit])) {
                [$icon, $color, $bg, $border] = $fileGroups[$hit];
            } else {
                $icon = $color = $bg = $border = null;
            }
            if ($icon) {
                $ts = ['icon' => $icon, 'color' => $color, 'bg' => $bg, 'border' => $border, 'label' => strtoupper($ext ?: 'FILE')];
            }
        }
    @endphp
    <div class="card-premium p-4 group" data-link-id="{{ $link->id }}">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3.5 flex-1 min-w-0">
                @if($__canMove)
                <label class="flex-shrink-0 pt-1.5 cursor-pointer" title="Select to move">
                    <input type="checkbox" :value="{{ $link->id }}" x-model.number="selected"
                           class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/40">
                </label>
                @endif
                <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center" style="background: {{ $ts['bg'] }}; border: 1px solid {{ $ts['border'] }};">
                    <i class="fas {{ $ts['icon'] }} text-sm" style="color: {{ $ts['color'] }};"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <a href="{{ route('user.links.show', $link) }}" class="text-sm font-semibold truncate transition-colors hover:text-violet-400" style="color: var(--text-primary);">
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
                    <div class="flex items-center gap-1.5 text-xs text-violet-400/60 mb-0.5" x-data="{ copied: false }">
                        <span class="truncate">{{ $link->getShortUrl() }}</span>
                        <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="flex-shrink-0 transition-colors hover:text-violet-400" style="color: var(--text-faint);">
                            <i x-show="!copied" class="fas fa-copy text-[10px]"></i>
                            <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-[10px]"></i>
                        </button>
                    </div>
                    @if($link->long_url)
                    <p class="text-[11px] truncate" style="color: var(--text-faint);">{{ $link->long_url }}</p>
                    @endif
                    <div class="flex items-center gap-3 mt-1.5 text-[10px]" style="color: var(--text-faint);">
                        @if($link->project)
                            <span class="flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $link->project->color }}"></span>
                                {{ $link->project->name }}
                            </span>
                        @endif
                        <span>{{ $link->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 ml-4">
                <div class="text-center">
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</div>
                    <div class="text-[10px]" style="color: var(--text-faint);">clicks</div>
                </div>
                <div class="flex items-center gap-0.5 opacity-40 group-hover:opacity-100 transition-opacity">
                    <a href="{{ route('user.links.show', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-violet-500/10" style="color: var(--text-faint);" title="View">
                        <i class="fas fa-chart-bar text-xs hover:text-violet-400"></i>
                    </a>
                    @canInWorkspace('links.edit')
                        @if($link->type === 'biolink')
                        <a href="{{ route('user.links.blocks.editor', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-pink-500/10" style="color: var(--text-faint);" title="Edit Blocks">
                            <i class="fas fa-th-large text-xs hover:text-pink-400"></i>
                        </a>
                        @endif
                        <a href="{{ route('user.links.edit', $link) }}" class="p-1.5 rounded-md transition-all hover:bg-violet-500/10" style="color: var(--text-faint);" title="Edit">
                            <i class="fas fa-edit text-xs hover:text-violet-400"></i>
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
                    @if($__canMove)
                    <div class="relative" x-data="{ open: false }">
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

<div class="mt-5">{{ $links->links() }}</div>
</div>{{-- /x-data wrapper --}}
@endif
@endsection
