@extends('user.layouts.app')
@section('title', $form->title . ' · Form')

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__showActions = [
        ['label' => 'View Public', 'url' => $form->getPublicUrl(), 'icon' => 'fa-external-link-alt', 'class' => 'btn-ghost', 'target' => '_blank'],
    ];
    if ($__can('inbox.edit')) {
        array_unshift($__showActions, ['label' => 'Edit Fields', 'url' => route('user.forms.builder', $form), 'icon' => 'fa-pen', 'class' => 'btn-primary']);
    }
@endphp
<div class="max-w-6xl mx-auto" x-data="{ copied: false }">
    @include('user.partials.page-hero', [
        'title' => $form->title,
        'subtitle' => $form->description,
        'icon' => 'fa-wpforms',
        'back' => route('user.forms.index'),
        'url' => $form->getPublicUrl(),
        'chips' => [
            ['icon' => 'fa-circle ' . ($form->is_active ? 'text-emerald-400' : 'text-gray-400'), 'text' => $form->is_active ? 'Active' : 'Disabled'],
            ['icon' => 'fa-database text-pink-400', 'text' => number_format($form->total_submissions) . ' submissions'],
            ['icon' => 'fa-eye text-violet-400', 'text' => number_format($form->total_views) . ' views'],
        ],
        'actions' => $__showActions,
    ])

    @if(!$__can('inbox.edit') && !$__can('inbox.delete'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm flex items-center gap-2" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
        <i class="fas fa-lock"></i>
        <span>Your role is view-only on forms in this workspace. Editing, enabling/disabling and deleting are reserved for admins.</span>
    </div>
    @endif

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    @include('user.forms._tabs')

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @foreach([
            ['Today',  $stats['today'],  'fa-bolt',         '#f59e0b'],
            ['7 days', $stats['week'],   'fa-calendar-day', '#8b5cf6'],
            ['30 days',$stats['month'],  'fa-calendar',     '#6366f1'],
            ['Unread', $stats['unread'], 'fa-envelope',     '#ec4899'],
            ['Conv. %',$stats['conversion'] . '%', 'fa-percentage', '#10b981'],
        ] as [$label, $val, $icon, $color])
            <div class="card-premium p-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: {{ $color }}22;">
                        <i class="fas {{ $icon }} text-sm" style="color: {{ $color }};"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">{{ $label }}</div>
                        <div class="text-lg font-bold leading-none mt-1" style="color: var(--text-primary);">{{ $val }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="card-premium p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Recent Submissions</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Latest 5 submissions to this form.</p>
                    </div>
                    <a href="{{ route('user.forms.submissions', $form) }}" class="text-xs font-semibold" style="color: var(--accent-light);">View all →</a>
                </div>
                @if($recent->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-3xl mb-3" style="color: var(--text-faint);"></i>
                        <p class="text-sm" style="color: var(--text-muted);">No submissions yet. Share your form to start collecting.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($recent as $s)
                            <a href="{{ route('user.forms.submissions.show', [$form, $s]) }}"
                               class="flex items-center gap-3 p-3 rounded-lg hover:translate-x-1 transition-all"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg, #8b5cf6, #ec4899); color: white;">
                                    {{ strtoupper(substr($s->data['name'] ?? $s->data['email'] ?? '#', 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                        {{ $s->data['name'] ?? $s->data['email'] ?? '#' . $s->id }}
                                    </div>
                                    <div class="text-[11px] truncate" style="color: var(--text-faint);">
                                        {{ $s->created_at->diffForHumans() }} · {{ $s->ip ?? 'unknown ip' }}
                                    </div>
                                </div>
                                @unless($s->is_read)
                                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="card-premium p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Quick Actions</h4>
                <div class="space-y-2">
                    <a href="{{ route('user.forms.embed', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-code text-xs text-cyan-400"></i> Get embed code
                    </a>
                    <a href="{{ route('user.forms.submissions.export', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-file-csv text-xs text-emerald-400"></i> Export CSV
                    </a>
                    <a href="{{ route('user.forms.notifications', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-bell text-xs text-amber-400"></i> Configure notifications
                    </a>
                    @if($__can('inbox.edit'))
                    <form method="POST" action="{{ route('user.forms.toggle-active', $form) }}">@csrf
                        <button class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                            <i class="fas fa-power-off text-xs {{ $form->is_active ? 'text-amber-400' : 'text-emerald-400' }}"></i>
                            {{ $form->is_active ? 'Disable form' : 'Enable form' }}
                        </button>
                    </form>
                    @else
                    <button type="button" disabled class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow editing forms — ask a workspace admin">
                        <i class="fas fa-lock text-xs"></i> {{ $form->is_active ? 'Disable form' : 'Enable form' }}
                    </button>
                    @endif
                    @if($__can('inbox.delete'))
                    <form method="POST" action="{{ route('user.forms.destroy', $form) }}" onsubmit="return confirm('Delete this form and all its submissions? This cannot be undone.');">
                        @csrf @method('DELETE')
                        <button class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: rgba(239,68,68,0.08); color: #f87171;">
                            <i class="fas fa-trash text-xs"></i> Delete form
                        </button>
                    </form>
                    @else
                    <button type="button" disabled class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow deleting forms — ask a workspace admin">
                        <i class="fas fa-lock text-xs"></i> Delete form
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
