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

    @if($subscriber->is_spam)
        @php $reasonLabel = \App\Modules\User\Services\SpamChecker::reasonLabel($subscriber->spam_reason ?? null); @endphp
        <div class="mb-4 px-4 py-3 rounded-xl text-xs flex items-center gap-2 flex-wrap" style="background: rgba(234,88,12,0.1); border: 1px solid rgba(234,88,12,0.2); color: #fb923c;">
            <i class="fas fa-shield-alt"></i>
            <span class="font-bold uppercase tracking-wider">Flagged as spam</span>
            @if($reasonLabel)
                <span class="px-1.5 py-0.5 rounded font-semibold" style="background: rgba(234,88,12,0.15);">{{ $reasonLabel }}</span>
            @endif
            @if(($subscriber->spam_reason ?? null) && str_starts_with($subscriber->spam_reason, 'blocked_keyword:'))
                @php
                    $blockedKw = trim(substr($subscriber->spam_reason, strlen('blocked_keyword:')));
                    $kwHits = $blockedKw !== ''
                        ? \App\Modules\User\Services\SpamChecker::countKeywordHits(auth()->id(), $blockedKw, 30)
                        : 0;
                    $kwHitsLabel = $kwHits === 1
                        ? 'This keyword has flagged 1 message in the last 30 days.'
                        : 'This keyword has flagged ' . $kwHits . ' messages in the last 30 days.';
                @endphp
                @if($blockedKw !== '')
                    <span class="px-1.5 py-0.5 rounded font-semibold" style="background: rgba(234,88,12,0.15);" title="Past inbox items flagged by this same keyword">
                        <i class="fas fa-history mr-1"></i>{{ $kwHitsLabel }}
                    </span>
                @endif
                <div class="ml-auto flex items-center gap-2">
                    @if($blockedKw !== '')
                        @php
                            $confirmMsg = 'Stop blocking “' . $blockedKw . '”? Future submissions matching it won’t be flagged.'
                                . ($kwHits > 0 ? ' Heads up: ' . $kwHitsLabel . ' Those would have landed in your inbox.' : '');
                        @endphp
                        <form method="POST" action="{{ route('user.inbox.spam-settings.disable-keyword') }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Stop blocking this keyword?', message: @js($confirmMsg), confirmText: 'Stop blocking', confirmIcon: 'fa-shield-halved', iconClass: 'fa-shield-halved'})">
                            @csrf
                            <input type="hidden" name="keyword" value="{{ $blockedKw }}">
                            <button type="submit" class="px-2 py-0.5 rounded font-semibold underline" style="background: rgba(234,88,12,0.15);" title="Stop blocking this keyword for all future submissions">
                                <i class="fas fa-times mr-1"></i>Stop blocking “{{ $blockedKw }}”
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('user.inbox.spam-settings') }}" class="underline opacity-80 hover:opacity-100">Manage keywords</a>
                </div>
            @endif
        </div>
    @endif

    @php
        $contactMessage = ($subscriber->type === 'contact_form' && is_array($subscriber->metadata ?? null))
            ? trim((string)($subscriber->metadata['message'] ?? ''))
            : '';
    @endphp

    @if($contactMessage !== '')
        <div class="card-premium p-6 mb-4">
            <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">
                <i class="fas fa-comment-dots mr-2 text-violet-400"></i>Message
            </h3>
            <div class="text-sm whitespace-pre-line p-4 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">{{ $contactMessage }}</div>
        </div>
    @endif

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
            @include('user.cloud-files._attach-picker')
            <div x-data="cloudAttachPicker({ mode: 'form' })">
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

                    <template x-for="f in picked" :key="f.id">
                        <input type="hidden" name="cloud_file_ids[]" :value="f.id">
                    </template>
                    <div x-show="picked.length > 0" class="flex flex-wrap gap-2">
                        <template x-for="f in picked" :key="'rchip' + f.id">
                            <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full"
                                  style="background: rgba(139,92,246,0.15); color: var(--text-primary);">
                                <i :class="f.provider_icon" class="text-[11px]" style="color: var(--text-muted);"></i>
                                <span x-text="f.name" class="max-w-[200px] truncate"></span>
                                <button type="button" @click="remove(f.id)" class="text-[11px]" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                            </span>
                        </template>
                    </div>

                    <div class="flex justify-between items-center">
                        <button type="button" @click="show()" class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
                                style="border-color: var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-cloud mr-1"></i> Attach from Cloud Files
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold" style="background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff;">
                            <i class="fas fa-paper-plane mr-1"></i>Send reply
                        </button>
                    </div>
                </form>
                @include('user.cloud-files._attach-modal', ['confirmLabel' => 'Add to reply'])
            </div>
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
                            @if($r->cloudAttachments->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($r->cloudAttachments as $att)
                                        @php($cf = $att->cloudFile)
                                        @if($cf)
                                            <a href="{{ $cf->link }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border hover:underline"
                                               style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"
                                               title="{{ $cf->providerLabel() }} · {{ $cf->humanSize() }}">
                                                <i class="{{ $cf->providerIcon() }}" style="color: var(--text-muted);"></i>
                                                <span class="max-w-[200px] truncate">{{ $cf->name }}</span>
                                                <i class="fas fa-arrow-up-right-from-square text-[10px]" style="color: var(--text-faint);"></i>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
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
        @if($subscriber->is_spam)
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}">@csrf
            <button name="action" value="not_spam_trust" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #4ade80;" title="Mark not spam and add this sender to your trusted list.">
                <i class="fas fa-user-shield mr-1"></i>Not spam &amp; trust sender
            </button>
        </form>
        @endif
        <form method="POST" action="{{ route('user.inbox.update', ['subscriber', $subscriber->id]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this item?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf
            <button name="action" value="delete" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(239,68,68,0.1); color: #f87171;">
                <i class="fas fa-trash mr-1"></i>Delete
            </button>
        </form>
    </div>
</div>
@endsection
