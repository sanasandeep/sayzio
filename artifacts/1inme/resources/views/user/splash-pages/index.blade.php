@extends('user.layouts.app')
@section('title', 'Intros')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'splash_pages', 'kind' => 'flag', 'label' => 'Splash pages'])
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canEdit = $__can('links.edit');
@endphp
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Intros',
        'subtitle' => 'Reusable transition pages — attach one to any link.',
        'icon'     => 'fa-rocket',
        'chips'    => [
            ['icon' => 'fa-layer-group', 'text' => $splashPages->total() . ' total'],
        ],
        'actions'  => $__canEdit ? [
            ['url' => route('user.splash-pages.create'), 'label' => 'New Intro', 'icon' => 'fa-plus', 'class' => 'btn-primary'],
        ] : [],
    ])

    <div class="card-premium p-4 mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex items-center gap-2 flex-1">
            <div class="relative flex-1 max-w-md">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search intros…"
                       class="w-full pl-9 pr-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            </div>
            @if($projects->count())
                <select name="project_id" class="px-3 py-2 text-sm rounded-lg outline-none"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                        onchange="this.form.submit()">
                    <option value="">All projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="px-4 py-2 text-sm rounded-lg font-semibold" style="background: var(--accent); color: #fff;">Filter</button>
        </form>
    </div>

    @if($splashPages->isEmpty())
        <div class="card-premium p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: var(--c-primary-soft);">
                <i class="fas fa-rocket text-2xl" style="color: var(--c-primary);"></i>
            </div>
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No intros yet</h3>
            @if($__canEdit)
                <p class="text-sm mb-5" style="color: var(--text-muted);">Create a reusable transition page that visitors see before reaching their destination.</p>
                <a href="{{ route('user.splash-pages.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold"
                   style="background: var(--accent); color: #fff;">
                    <i class="fas fa-plus"></i> Create your first
                </a>
            @else
                <p class="text-sm mb-3" style="color: var(--text-muted);">No intros have been set up in this workspace yet.</p>
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold" style="background: rgba(245,158,11,0.08); color: #b45309;">
                    <i class="fas fa-lock"></i> Ask a workspace admin to create one for you.
                </div>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($splashPages as $sp)
                <div class="card-premium p-5 flex flex-col">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden" style="background: var(--c-primary-soft);">
                            @if($sp->logo)
                                <img src="{{ $sp->logo }}" alt="" class="max-w-full max-h-full object-contain">
                            @else
                                <i class="fas fa-rocket text-lg" style="color: var(--c-primary);"></i>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            @if($__canEdit)
                                <a href="{{ route('user.splash-pages.edit', $sp) }}" class="font-bold text-base hover:underline truncate block" style="color: var(--text-primary);">{{ $sp->name }}</a>
                            @else
                                <span class="font-bold text-base truncate block" style="color: var(--text-primary);">{{ $sp->name }}</span>
                            @endif
                            @if($sp->title && $sp->title !== $sp->name)
                                <div class="text-xs truncate" style="color: var(--text-muted);">{{ $sp->title }}</div>
                            @endif
                        </div>
                    </div>
                    @if($sp->description)
                        <p class="text-xs mb-4 line-clamp-2" style="color: var(--text-muted);">{{ $sp->description }}</p>
                    @endif
                    <div class="flex items-center gap-3 text-[11px] mb-4 mt-auto" style="color: var(--text-dimmed);">
                        <span class="inline-flex items-center gap-1"><i class="fas fa-link"></i> {{ $sp->links_count }} link{{ $sp->links_count === 1 ? '' : 's' }}</span>
                        @if($sp->auto_redirect)
                            <span class="inline-flex items-center gap-1"><i class="fas fa-clock"></i> Auto · {{ $sp->countdown }}s</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 pt-3 border-t" style="border-color: var(--border-subtle);">
                        @if($__canEdit)
                            <a href="{{ route('user.splash-pages.edit', $sp) }}" class="flex-1 text-center px-3 py-1.5 text-xs rounded-lg font-semibold" style="background: var(--bg-glass-hover); color: var(--text-primary);"><i class="fas fa-pen mr-1"></i> Edit</a>
                        @else
                            <span class="flex-1 text-center px-3 py-1.5 text-xs rounded-lg font-semibold cursor-not-allowed opacity-60" style="background: var(--bg-glass-hover); color: var(--text-faint);" title="Your role doesn't allow editing intros — ask a workspace admin"><i class="fas fa-lock mr-1"></i> Edit</span>
                        @endif
                        <a href="{{ route('user.splash-pages.preview', $sp) }}" target="_blank" class="px-3 py-1.5 text-xs rounded-lg" style="background: var(--bg-glass-hover); color: var(--text-secondary);" title="Preview"><i class="fas fa-eye"></i></a>
                        @if($__canEdit)
                            <form method="POST" action="{{ route('user.splash-pages.destroy', $sp) }}" onsubmit="return confirm('Delete this intro? Links using it will lose their intro.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg" style="background: var(--bg-glass-hover); color: var(--c-danger);" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        @else
                            <span class="px-3 py-1.5 text-xs rounded-lg cursor-not-allowed opacity-60" style="background: var(--bg-glass-hover); color: var(--text-faint);" title="Your role doesn't allow deleting intros — ask a workspace admin"><i class="fas fa-lock"></i></span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $splashPages->links() }}</div>
    @endif
</div>
@endsection
