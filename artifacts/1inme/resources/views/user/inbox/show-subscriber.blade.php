@extends('user.layouts.app')
@section('title', 'Inbox · ' . ($subscriber->name ?? $subscriber->email ?? '#' . $subscriber->id))

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => $subscriber->name ?: ($subscriber->email ?: ('#' . $subscriber->id)),
        'subtitle' => ucfirst(str_replace('_', ' ', $subscriber->type)) . ($subscriber->link ? ' · /' . $subscriber->link->alias : ''),
        'icon' => 'fa-user',
        'back' => route('user.inbox.index'),
        'chips' => [
            ['icon' => 'fa-clock', 'text' => ($subscriber->subscribed_at ?? $subscriber->created_at)?->format('M d, Y H:i')],
        ],
        'actions' => [
            ['label' => 'Back to inbox', 'url' => route('user.inbox.index'), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    <div class="card-premium p-6 mb-4">
        <h3 class="text-sm font-bold mb-5" style="color: var(--text-primary);">Captured Information</h3>
        <dl class="space-y-3">
            @foreach(array_filter([
                'Name' => $subscriber->name,
                'Email' => $subscriber->email,
                'Phone' => $subscriber->phone,
                'Channel URL' => $subscriber->channel_url,
                'Type' => $subscriber->type,
                'Source' => $subscriber->source,
                'Status' => $subscriber->status,
            ], fn($v) => $v !== null && $v !== '') as $k => $v)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-3 border-b" style="border-color: var(--border-subtle);">
                    <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-faint);">{{ $k }}</dt>
                    <dd class="sm:col-span-2 text-sm" style="color: var(--text-primary);">
                        @if($k === 'Email')
                            <a href="mailto:{{ $v }}" class="text-violet-400 hover:underline">{{ $v }}</a>
                        @elseif($k === 'Channel URL')
                            <a href="{{ $v }}" target="_blank" class="text-violet-400 hover:underline">{{ $v }}</a>
                        @else
                            <span class="whitespace-pre-line">{{ $v }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
            @if(!empty($subscriber->metadata))
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-3 border-b" style="border-color: var(--border-subtle);">
                    <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-faint);">Metadata</dt>
                    <dd class="sm:col-span-2 text-xs font-mono break-all" style="color: var(--text-secondary);">{{ json_encode($subscriber->metadata) }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="card-premium p-4 flex items-center gap-2 flex-wrap">
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}">@csrf
            <button name="action" value="{{ $subscriber->is_starred ? 'unstar' : 'star' }}" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                <i class="fa{{ $subscriber->is_starred ? 's' : 'r' }} fa-star {{ $subscriber->is_starred ? 'text-amber-400' : '' }} mr-1"></i>{{ $subscriber->is_starred ? 'Unstar' : 'Star' }}
            </button>
        </form>
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}">@csrf
            <button name="action" value="unread" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                <i class="fas fa-envelope mr-1"></i>Mark unread
            </button>
        </form>
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}">@csrf
            <button name="action" value="{{ $subscriber->is_spam ? 'not_spam' : 'spam' }}" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(234,88,12,0.1); border: 1px solid rgba(234,88,12,0.2); color: #fb923c;">
                <i class="fas fa-ban mr-1"></i>{{ $subscriber->is_spam ? 'Not spam' : 'Mark spam' }}
            </button>
        </form>
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}" onsubmit="return confirm('Delete this item?')">@csrf
            <button name="action" value="delete" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(239,68,68,0.1); color: #f87171;">
                <i class="fas fa-trash mr-1"></i>Delete
            </button>
        </form>
    </div>
</div>
@endsection
