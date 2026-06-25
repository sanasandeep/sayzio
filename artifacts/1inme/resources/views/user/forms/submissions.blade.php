@extends('user.layouts.app')
@section('title', 'Submissions · ' . $form->title)

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
@endphp
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Submissions',
        'subtitle' => $form->title,
        'icon' => 'fa-inbox',
        'back' => route('user.forms.show', $form),
        'chips' => [
            ['icon' => 'fa-database text-pink-400', 'text' => number_format($form->total_submissions) . ' total'],
        ],
        'actions' => [
            ['label' => 'Export CSV', 'url' => route('user.forms.submissions.export', $form), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'],
        ],
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @if($__can('inbox.delete'))
    {{-- Erase a single submitter's history (GDPR-style takedown). Matches by
         email or any other identifier captured by your form fields, across
         every form this creator owns. --}}
    <div class="card-premium p-5 mb-6">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(239,68,68,0.15); color: #ef4444;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Erase a submitter's history</div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Removes every submission tied to a single submitter across all of your forms. Search by email, user id, or any other value captured in your form fields.
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('user.forms.submissions.erase-submitter', $form) }}"
              class="flex flex-col sm:flex-row gap-2"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Erase every submission from this submitter?', message: 'Every submission matching this submitter, across all your forms, will be permanently deleted. This cannot be undone.', confirmText: 'Erase submissions', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
            @csrf
            <input type="text" name="identifier" required maxlength="255"
                   placeholder="email@example.com, user id, or fingerprint"
                   class="flex-1 px-3 py-2 rounded-lg text-sm"
                   style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5"
                    style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                <i class="fas fa-eraser"></i> Erase submitter
            </button>
        </form>
    </div>
    @endif

    {{-- Filter pills --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        @foreach(['' => 'All', 'unread' => 'Unread', 'starred' => 'Starred', 'spam' => 'Spam'] as $val => $label)
            @php $active = (request('filter') ?? '') === $val; @endphp
            <a href="?filter={{ $val }}" class="text-xs px-3 py-1.5 rounded-full font-semibold" style="{{ $active ? 'background: linear-gradient(135deg,#8b5cf6,#6d28d9); color:white;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($submissions->isEmpty())
        <div class="card-premium p-12 text-center">
            <i class="fas fa-inbox text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p class="text-sm" style="color: var(--text-muted);">No submissions match this filter yet.</p>
        </div>
    @else
        <div class="card-premium overflow-hidden">
            <div class="divide-y" style="border-color: var(--border-glass);">
                @foreach($submissions as $s)
                    @php $name = $s->data['name'] ?? $s->data['email'] ?? '#' . $s->id; @endphp
                    <div class="flex items-center gap-3 p-4 hover:bg-violet-500/5 transition-colors {{ !$s->is_read ? 'bg-violet-500/5' : '' }}">
                        @if($__can('inbox.edit'))
                        <form method="POST" action="{{ route('user.forms.submissions.star', [$form, $s]) }}">@csrf
                            <button class="text-base {{ $s->is_starred ? 'text-amber-400' : '' }}" style="color: {{ $s->is_starred ? '' : 'var(--text-faint)' }};" title="Star">
                                <i class="fa{{ $s->is_starred ? 's' : 'r' }} fa-star"></i>
                            </button>
                        </form>
                        @else
                        <span class="text-base cursor-not-allowed opacity-50" style="color: var(--text-faint);" title="Your role doesn't allow starring submissions — ask a workspace admin">
                            <i class="fa{{ $s->is_starred ? 's' : 'r' }} fa-star"></i>
                        </span>
                        @endif
                        <a href="{{ route('user.forms.submissions.show', [$form, $s]) }}" class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg, #8b5cf6, #ec4899); color: white;">
                                {{ strtoupper(substr($name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-{{ $s->is_read ? 'medium' : 'bold' }} truncate" style="color: var(--text-primary);">{{ $name }}</span>
                                    @unless($s->is_read)<span class="w-2 h-2 rounded-full bg-violet-500 flex-shrink-0"></span>@endunless
                                </div>
                                <div class="text-[11px] truncate" style="color: var(--text-faint);">
                                    @php $preview = collect($s->data)->reject(fn($v,$k) => in_array($k, ['name','email']) || is_array($v))->take(3)->map(fn($v,$k) => "$k: $v")->implode(' · '); @endphp
                                    {{ $preview ?: $s->data['email'] ?? 'No content' }}
                                </div>
                            </div>
                        </a>
                        <div class="text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);">
                            {{ $s->created_at->diffForHumans() }}<br>
                            <span class="font-mono">{{ $s->ip ?? '' }}</span>
                            @if($s->isPaid())
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold" style="background: rgba(16,185,129,0.15); color: #34d399;">
                                    Paid {{ $s->amount_cents !== null ? strtoupper($s->currency ?? 'USD') . ' ' . number_format($s->amount_cents / 100, 2) : '' }}
                                </span>
                            @elseif($s->isRefunded())
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold" style="background: rgba(148,163,184,0.18); color: #94a3b8;">
                                    Refunded
                                </span>
                            @endif
                        </div>
                        @if($s->isRefundable() && $__can('inbox.delete'))
                        <form method="POST" action="{{ route('user.forms.submissions.refund', [$form, $s]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Refund this payment?', message: 'This refunds {{ $s->amount_cents !== null ? strtoupper($s->currency ?? 'USD') . ' ' . number_format($s->amount_cents / 100, 2) : 'the charge' }} to the customer. This cannot be undone.', confirmText: 'Refund', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                            @csrf
                            <button type="submit" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(245,158,11,0.12); color: #fbbf24;" title="Refund this payment"><i class="fas fa-rotate-left"></i></button>
                        </form>
                        @endif
                        @if($__can('inbox.delete'))
                        <form method="POST" action="{{ route('user.forms.submissions.destroy', [$form, $s]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this submission?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash"></i></button>
                        </form>
                        @else
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow deleting submissions — ask a workspace admin"><i class="fas fa-lock"></i></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-6">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
