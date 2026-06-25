@extends('user.layouts.app')
@section('title', 'Forms')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'max_forms', 'kind' => 'limit', 'current' => isset($forms) ? (method_exists($forms, 'total') ? $forms->total() : (is_countable($forms) ? count($forms) : 0)) : 0, 'label' => 'Forms'])
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
@endphp
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Forms',
        'subtitle' => 'Build branded forms — embed anywhere, collect leads, automate replies.',
        'icon' => 'fa-wpforms',
        'chips' => [
            ['icon' => 'fa-database text-pink-400', 'text' => $forms->total() . ' total'],
        ],
        'actions' => $__can('inbox.create') ? [
            ['label' => 'New Form', 'url' => route('user.forms.create'), 'icon' => 'fa-plus', 'class' => 'btn-primary'],
        ] : [],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Filter bar --}}
    <form method="GET" class="card-premium p-4 mb-6 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[200px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search forms by title or slug…"
                   class="theme-input w-full pl-9 text-sm">
        </div>
        <select name="status" class="theme-input text-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        @if($projects->isNotEmpty())
        <select name="project_id" class="theme-input text-sm" onchange="this.form.submit()">
            <option value="">All projects</option>
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        @endif
        <button type="submit" class="btn-primary text-xs px-4 py-2"><i class="fas fa-filter text-[10px] mr-1"></i> Filter</button>
    </form>

    @if($forms->isEmpty())
        <div class="card-premium p-12 text-center">
            <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center mb-5" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(92,131,255,0.18));">
                <i class="fas fa-wpforms text-3xl text-pink-400"></i>
            </div>
            <h3 class="text-xl font-bold mb-2" style="color: var(--text-primary);">No forms yet</h3>
            <p class="text-sm mb-6" style="color: var(--text-muted); max-width: 32rem; margin: 0 auto;">
                Build a form once and use it everywhere — share a public link, embed it in any website, or drop it into a Link in Bio page as a block.
            </p>
            @if($__can('inbox.create'))
                <a href="{{ route('user.forms.create') }}" class="btn-primary inline-flex items-center gap-2 px-6 py-3">
                    <i class="fas fa-plus text-xs"></i> Create your first form
                </a>
            @else
                <p class="text-xs inline-flex items-center gap-2 px-3 py-2 rounded-lg" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
                    <i class="fas fa-lock"></i> Your role doesn't allow creating forms — ask a workspace admin.
                </p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($forms as $form)
                @php
                    $accent = $form->design['accent'] ?? '#5c83ff';
                @endphp
                <div class="card-premium p-5 group" style="--nav-tint: {{ $accent }};">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $accent }}22; border: 1px solid {{ $accent }}44;">
                                <i class="fas fa-wpforms text-lg" style="color: {{ $accent }};"></i>
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('user.forms.show', $form) }}" class="text-sm font-bold truncate block" style="color: var(--text-primary);">{{ $form->title }}</a>
                                <div class="text-[11px] mt-0.5 truncate" style="color: var(--text-faint);">/f/{{ $form->slug }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @if($form->is_active)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold" style="background: rgba(16,185,129,0.12); color: #10b981;">Active</span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold" style="background: rgba(148,163,184,0.12); color: var(--text-faint);">Off</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="text-center p-2 rounded-lg" style="background: var(--bg-glass-input);">
                            <div class="text-base font-bold" style="color: var(--text-primary);">{{ number_format($form->submissions_count) }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">Submissions</div>
                        </div>
                        <div class="text-center p-2 rounded-lg" style="background: var(--bg-glass-input);">
                            <div class="text-base font-bold" style="color: var(--text-primary);">{{ number_format($form->total_views) }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">Views</div>
                        </div>
                        <div class="text-center p-2 rounded-lg" style="background: var(--bg-glass-input);">
                            <div class="text-base font-bold" style="color: var(--text-primary);">{{ count($form->fields ?? []) }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">Fields</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        @if($__can('inbox.edit'))
                            <a href="{{ route('user.forms.builder', $form) }}" class="text-[11px] px-3 py-1.5 rounded-lg font-semibold" style="background: {{ $accent }}; color: white;">
                                <i class="fas fa-pen text-[9px] mr-1"></i> Edit
                            </a>
                        @else
                            <span class="text-[11px] px-3 py-1.5 rounded-lg font-semibold cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow editing forms — ask a workspace admin">
                                <i class="fas fa-lock text-[9px] mr-1"></i> Edit
                            </span>
                        @endif
                        <a href="{{ route('user.forms.submissions', $form) }}" class="text-[11px] px-3 py-1.5 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-inbox text-[9px] mr-1"></i> Inbox
                        </a>
                        <a href="{{ route('user.forms.embed', $form) }}" class="text-[11px] px-3 py-1.5 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-code text-[9px] mr-1"></i> Embed
                        </a>
                        <a href="{{ $form->getPublicUrl() }}" target="_blank" class="text-[11px] px-3 py-1.5 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-external-link-alt text-[9px] mr-1"></i> Open
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $forms->links() }}</div>
    @endif
</div>
@endsection
