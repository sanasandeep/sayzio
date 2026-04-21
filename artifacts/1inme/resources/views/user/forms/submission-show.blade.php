@extends('user.layouts.app')
@section('title', 'Submission #' . $submission->id)

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
@endphp
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Submission #' . $submission->id,
        'subtitle' => $form->title,
        'icon' => 'fa-envelope-open-text',
        'back' => route('user.forms.submissions', $form),
        'chips' => [
            ['icon' => 'fa-clock', 'text' => $submission->created_at->format('M d, Y H:i')],
            ['icon' => 'fa-network-wired', 'text' => $submission->ip ?? 'unknown ip'],
        ],
        'actions' => [
            ['label' => 'Back to inbox', 'url' => route('user.forms.submissions', $form), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    @if($submission->is_spam)
        @php $reasonLabel = \App\Modules\User\Services\SpamChecker::reasonLabel($submission->spam_reason ?? null); @endphp
        <div class="mb-6 px-4 py-3 rounded-xl text-xs flex items-center gap-2 flex-wrap" style="background: rgba(234,88,12,0.1); border: 1px solid rgba(234,88,12,0.2); color: #fb923c;">
            <i class="fas fa-shield-alt"></i>
            <span class="font-bold uppercase tracking-wider">Flagged as spam</span>
            @if($reasonLabel)
                <span class="px-1.5 py-0.5 rounded font-semibold" style="background: rgba(234,88,12,0.15);">{{ $reasonLabel }}</span>
            @endif
            @if(($submission->spam_reason ?? null) && str_starts_with($submission->spam_reason, 'blocked_keyword:'))
                @php
                    $blockedKw = trim(substr($submission->spam_reason, strlen('blocked_keyword:')));
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
                    @if($blockedKw !== '' && $__can('inbox.edit'))
                        @php
                            $confirmMsg = 'Stop blocking “' . $blockedKw . '”? Future submissions matching it won’t be flagged.'
                                . ($kwHits > 0 ? ' Heads up: ' . $kwHitsLabel . ' Those would have landed in your inbox.' : '');
                        @endphp
                        <form method="POST" action="{{ route('user.inbox.spam-settings.disable-keyword') }}" onsubmit="return confirm(@js($confirmMsg))">
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

    <div class="card-premium p-6 mb-6">
        <h3 class="text-sm font-bold mb-5" style="color: var(--text-primary);">Submitted Data</h3>
        <dl class="space-y-3">
            @foreach($submission->data ?? [] as $k => $v)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-3 border-b" style="border-color: var(--border-subtle);">
                    <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-faint);">{{ str_replace('_', ' ', $k) }}</dt>
                    <dd class="sm:col-span-2 text-sm" style="color: var(--text-primary);">
                        @if(is_array($v))
                            <ul class="list-disc pl-4">@foreach($v as $vv)<li>{{ $vv }}</li>@endforeach</ul>
                        @elseif(is_bool($v))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $v ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">{{ $v ? 'Yes' : 'No' }}</span>
                        @elseif(filter_var($v, FILTER_VALIDATE_EMAIL))
                            <a href="mailto:{{ $v }}" class="text-violet-400 hover:underline">{{ $v }}</a>
                        @else
                            <span class="whitespace-pre-line">{{ $v }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @if(!empty($submission->files))
            <h4 class="text-xs font-bold uppercase tracking-wider mt-6 mb-3" style="color: var(--text-faint);">Attachments</h4>
            <div class="space-y-2">
                @foreach($submission->files as $field => $url)
                    <a href="{{ $url }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <i class="fas fa-paperclip text-violet-400"></i>
                        <span class="text-sm flex-1" style="color: var(--text-primary);">{{ $submission->data[$field] ?? $field }}</span>
                        <i class="fas fa-download text-xs" style="color: var(--text-faint);"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if(!empty($replyTo) && $__can('inbox.reply'))
        <div class="card-premium p-6 mb-6">
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
            <form method="POST" action="{{ route('user.inbox.reply', ['form_submission', $submission->id]) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Subject</label>
                    <input type="text" name="subject" required maxlength="300"
                        value="{{ old('subject', 'Re: ' . $form->title) }}"
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

        @if(!empty($replies) && $replies->isNotEmpty())
            <div class="card-premium p-6 mb-6">
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

    <div class="card-premium p-5">
        <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Metadata</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div><dt style="color: var(--text-faint);">IP Address</dt><dd class="font-mono" style="color: var(--text-secondary);">{{ $submission->ip ?? '—' }}</dd></div>
            <div><dt style="color: var(--text-faint);">Country</dt><dd style="color: var(--text-secondary);">{{ $submission->country ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt style="color: var(--text-faint);">User Agent</dt><dd class="break-all" style="color: var(--text-secondary);">{{ $submission->user_agent ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt style="color: var(--text-faint);">Referrer</dt><dd class="break-all" style="color: var(--text-secondary);">{{ $submission->referrer ?? '—' }}</dd></div>
        </dl>
    </div>
</div>
@endsection
