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
            ['label' => 'Create project', 'url' => route('user.delivery-projects.create', ['source_type' => 'form_submission', 'source_id' => $submission->id]), 'icon' => 'fa-diagram-project', 'class' => 'btn-ghost'],
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

    <div class="card-premium p-6 mb-6">
        <h3 class="text-sm font-bold mb-5" style="color: var(--text-primary);">Submitted Data</h3>
        <dl class="space-y-3">
            @foreach($submission->data ?? [] as $k => $v)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-3 border-b" style="border-color: var(--border-subtle);">
                    <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-faint);">{{ str_replace('_', ' ', $k) }}</dt>
                    <dd class="sm:col-span-2 text-sm" style="color: var(--text-primary);">
                        @if(is_array($v) && !empty($v['_repeatable_group']))
                            @foreach(array_values($v['copies'] ?? []) as $repIdx => $repCopy)
                                <div class="mb-2 p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                    <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Copy {{ $repIdx + 1 }}</div>
                                    @foreach((array) $repCopy as $ck => $cv)
                                        <div class="flex gap-2 text-xs mb-1 flex-wrap">
                                            <span class="font-medium shrink-0" style="color: var(--text-muted);">{{ str_replace('_', ' ', $ck) }}:</span>
                                            @if(is_array($cv))
                                                <span style="color: var(--text-primary);">{{ implode(', ', array_map('strval', $cv)) }}</span>
                                            @elseif(is_bool($cv))
                                                <span style="color: var(--text-primary);">{{ $cv ? 'Yes' : 'No' }}</span>
                                            @else
                                                <span style="color: var(--text-primary);">{{ $cv }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @elseif(is_array($v) && !empty($v['_pricing']))
                            @php
                                $pcur = $v['currency'] ?? 'USD';
                                $fmtCents = fn ($c) => number_format(((int) $c) / 100, 2) . ' ' . $pcur;
                            @endphp
                            <div class="space-y-1">
                                @if(!empty($v['option']))
                                    <div class="flex items-center justify-between gap-3">
                                        <span>{{ $v['option']['label'] ?? '—' }}</span>
                                        <span style="color: var(--text-muted);">{{ $fmtCents($v['option']['price_cents'] ?? 0) }}</span>
                                    </div>
                                @endif
                                @foreach(($v['addons'] ?? []) as $ad)
                                    <div class="flex items-center justify-between gap-3 text-xs" style="color: var(--text-muted);">
                                        <span>+ {{ $ad['label'] ?? '—' }}</span>
                                        <span>{{ $fmtCents($ad['price_cents'] ?? 0) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex items-center justify-between gap-3 pt-1 mt-1 border-t font-bold" style="border-color: var(--border-subtle);">
                                    <span>Total</span>
                                    <span>{{ $fmtCents($v['total_cents'] ?? 0) }}</span>
                                </div>
                            </div>
                        @elseif(is_array($v))
                            <ul class="list-disc pl-4">@foreach($v as $vv)<li>{{ is_array($vv) ? json_encode($vv) : $vv }}</li>@endforeach</ul>
                        @elseif(is_bool($v))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $v ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">{{ $v ? 'Yes' : 'No' }}</span>
                        @elseif(filter_var($v, FILTER_VALIDATE_EMAIL))
                            <a href="mailto:{{ $v }}" class="text-blue-400 hover:underline">{{ $v }}</a>
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
                    @php
                        $userFile = \App\Modules\User\Models\UserFile::fromServeUrl($url);
                        $status   = $userFile?->scan_status ?? 'clean';
                        $reason   = $userFile?->scanReasonLabel();
                        $highRisk = $userFile?->isHighRiskExtension() ?? false;
                        $href     = $url;
                        if ($userFile && $userFile->isFlagged()) {
                            $href = $userFile->url_path . '?confirm=1';
                        }
                        $disabled = $userFile && $userFile->isPendingScan();
                    @endphp
                    <div class="p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-paperclip text-blue-400"></i>
                            <span class="text-sm flex-1 min-w-0 truncate" style="color: var(--text-primary);">{{ $submission->data[$field] ?? $field }}</span>

                            @if($disabled)
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1"
                                      style="background: rgba(56,189,248,0.15); color: #38bdf8;">
                                    <i class="fas fa-shield-virus fa-spin"></i>Scanning…
                                </span>
                            @elseif($status === 'flagged')
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1"
                                      style="background: rgba(239,68,68,0.15); color: #f87171;">
                                    <i class="fas fa-shield-exclamation"></i>Quarantined
                                </span>
                            @else
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1"
                                      style="background: rgba(16,185,129,0.12); color: #34d399;">
                                    <i class="fas fa-shield-check"></i>Clean
                                </span>
                            @endif

                            @if($disabled)
                                <span class="px-2 py-1 rounded text-[11px]" style="background: rgba(0,0,0,0.2); color: var(--text-faint);">
                                    <i class="fas fa-clock"></i>
                                </span>
                            @elseif($status === 'flagged')
                                <a href="{{ $userFile->url_path }}"
                                   onclick="return confirm({{ $highRisk ? "'This file type can run code on your computer. Continue with download warning?'" : "'This attachment was flagged. View the warning page?'" }});"
                                   class="px-2 py-1 rounded text-[11px] font-semibold text-white"
                                   style="background: linear-gradient(135deg,#ef4444,#b91c1c);">
                                    Review &amp; download
                                </a>
                            @else
                                <a href="{{ $href }}" target="_blank" class="px-2 py-1 rounded text-[11px] text-white"
                                   style="background: linear-gradient(135deg,#5c83ff,#2342c7);">
                                    <i class="fas fa-download mr-1"></i>Open
                                </a>
                            @endif
                        </div>
                        @if($status === 'flagged' && $reason)
                            <div class="mt-2 text-[11px]" style="color: #fca5a5;">
                                <i class="fas fa-circle-info mr-1"></i>{{ $reason }}
                                @if($highRisk) · <strong class="uppercase tracking-wider">High-risk file type</strong>@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @php
        $__lineItems = $submission->line_items ?? [];
        $__payCur = strtoupper($submission->currency ?? ($form->settings['payment']['currency'] ?? 'USD')) ?: 'USD';
        $__fmtCents = fn ($c) => number_format(((int) $c) / 100, 2) . ' ' . $__payCur;
    @endphp
    @if(!empty($__lineItems) || (int) ($submission->amount_cents ?? 0) > 0)
        <div class="card-premium p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-receipt mr-2 text-emerald-400"></i>Payment
                </h3>
                @if(($submission->payment_status ?? null))
                    @php
                        $__ps = $submission->payment_status;
                        $__psClass = $__ps === 'paid' ? 'bg-emerald-500/15 text-emerald-400' : ($__ps === 'pending' ? 'bg-amber-500/15 text-amber-400' : 'bg-rose-500/15 text-rose-400');
                    @endphp
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $__psClass }}">{{ $__ps }}</span>
                @endif
            </div>

            @if(!empty($__lineItems))
                <div class="space-y-2 mb-4">
                    @foreach($__lineItems as $li)
                        @php $__isBase = ($li['field'] ?? null) === '__base__'; @endphp
                        <div class="flex items-start justify-between gap-3 text-sm py-1.5 border-b" style="border-color: var(--border-subtle);">
                            <div class="min-w-0">
                                <span style="color: var(--text-primary);">{{ $__isBase ? 'Base fee' : ($li['label'] ?? $li['field'] ?? 'Item') }}</span>
                                @if(!empty($li['detail']))
                                    <span class="text-xs ml-1" style="color: var(--text-faint);">{{ $li['detail'] }}</span>
                                @endif
                            </div>
                            <span class="font-mono whitespace-nowrap" style="color: var(--text-secondary);">{{ $__fmtCents($li['amount_cents'] ?? 0) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between text-sm font-bold pt-1">
                <span style="color: var(--text-primary);">Total</span>
                <span class="font-mono" style="color: var(--text-primary);">{{ $__fmtCents($submission->amount_cents ?? 0) }}</span>
            </div>

            @if($submission->isPaid() || $submission->isRefunded())
                @php $__amt = $__fmtCents($submission->amount_cents ?? 0); @endphp
                <div class="flex items-center justify-between gap-3 flex-wrap mt-4 pt-4 border-t" style="border-color: var(--border-subtle);">
                    <div class="text-xs" style="color: var(--text-faint);">
                        @if($submission->isPaid())
                            Charged{{ $submission->paid_at ? ' on ' . $submission->paid_at->format('M d, Y H:i') : '' }}
                        @else
                            Refunded{{ $submission->refunded_at ? ' on ' . $submission->refunded_at->format('M d, Y H:i') : '' }}
                        @endif
                    </div>
                    @if($submission->isPaid() && $__can('inbox.delete'))
                        <form method="POST" action="{{ route('user.forms.submissions.refund', [$form, $submission]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Refund this payment?', message: 'This refunds {{ $__amt }} to the customer. This cannot be undone.', confirmText: 'Refund', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.25); color: #fbbf24;">
                                <i class="fas fa-rotate-left"></i> Refund payment
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if(!empty($replyTo) && $__can('inbox.reply'))
        <div class="card-premium p-6 mb-6">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">
                <i class="fas fa-reply mr-2 text-blue-400"></i>Reply by email
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
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold" style="background: linear-gradient(135deg,#5c83ff,#6366f1); color: #fff;">
                        <i class="fas fa-paper-plane mr-1"></i>Send reply
                    </button>
                </div>
            </form>
        </div>

        @if(!empty($replies) && $replies->isNotEmpty())
            <div class="card-premium p-6 mb-6">
                <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2 text-blue-400"></i>Previous replies ({{ $replies->count() }})
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
