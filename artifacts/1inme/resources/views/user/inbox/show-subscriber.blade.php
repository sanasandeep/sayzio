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

    @if(!empty($replyTo))
        <div class="card-premium p-6 mb-4">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">
                <i class="fas fa-reply mr-2 text-violet-400"></i>Reply by email
            </h3>
            <p class="text-xs mb-4" style="color: var(--text-faint);">
                Sending to <span class="font-mono" style="color: var(--text-secondary);">{{ $replyTo }}</span>
                using your configured From / SMTP settings.
            </p>
            @if(session('error'))
                <div class="mb-3 px-3 py-2 rounded-lg text-xs" style="background: rgba(239,68,68,0.1); color: #f87171;">
                    {{ session('error') }}
                </div>
            @endif
            <form method="POST" action="{{ route('user.inbox.reply', ['subscriber', $subscriber->id]) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Subject</label>
                    <input type="text" name="subject" required maxlength="300"
                        value="{{ old('subject', 'Re: ' . ($subscriber->name ?: 'Hello')) }}"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    @error('subject')<p class="text-xs text-rose-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Message</label>
                    <textarea name="body" rows="6" required maxlength="20000"
                        class="w-full px-3 py-2 rounded-lg text-sm font-mono"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">{{ old('body') }}</textarea>
                    @error('body')<p class="text-xs text-rose-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold" style="background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff;">
                        <i class="fas fa-paper-plane mr-1"></i>Send reply
                    </button>
                </div>
            </form>
        </div>

        @if($replies->isNotEmpty())
            <div class="card-premium p-6 mb-4">
                <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2 text-violet-400"></i>Previous replies ({{ $replies->count() }})
                </h3>
                <div class="space-y-3">
                    @foreach($replies as $r)
                        <div class="p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-xs" style="color: var(--text-faint);">
                                    <i class="fas fa-clock mr-1"></i>{{ ($r->sent_at ?? $r->created_at)->format('M d, Y H:i') }}
                                    · to <span class="font-mono">{{ $r->to_email }}</span>
                                </div>
                                @if($r->status === 'sent')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400">Sent</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-400">Failed</span>
                                @endif
                            </div>
                            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);">{{ $r->subject }}</div>
                            <div class="text-sm whitespace-pre-line" style="color: var(--text-secondary);">{{ $r->body }}</div>
                            @if($r->error)
                                <div class="mt-2 text-xs text-rose-400 font-mono">{{ $r->error }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

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
