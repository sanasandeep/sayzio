@extends('user.layouts.app')
@section('title', 'Thread · ' . ($thread->sender_name ?: 'Inbox'))

@section('content')
@php
    $availableBoards = \App\Modules\User\Models\TaskBoard::query()
        ->where('workspace_id', $thread->workspace_id)
        ->orderBy('name')->get();
@endphp
<div class="max-w-6xl mx-auto" x-data="{ replyText: '', showSnippets: false }">
    <a href="{{ url()->previous() }}" class="inline-flex items-center text-sm mb-4" style="color: var(--text-muted);">
        <i class="fas fa-arrow-left mr-2"></i> Back
    </a>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(16,185,129,0.1); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); color: #f87171;">{{ session('error') }}</div>@endif

    <div class="grid lg:grid-cols-[1fr_320px] gap-5">
        {{-- Thread reader --}}
        <div class="space-y-4">
            <div class="card-premium p-5">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full overflow-hidden flex items-center justify-center text-sm font-bold flex-shrink-0"
                         style="background: linear-gradient(135deg, {{ $thread->channelColor() }}, {{ $thread->categoryColor() }}); color: white;">
                        @if($thread->sender_avatar)<img src="{{ $thread->sender_avatar }}" class="w-full h-full object-cover">
                        @else {{ strtoupper(mb_substr($thread->sender_name ?: '?', 0, 1)) }}@endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-base font-bold" style="color: var(--text-primary);">{{ $thread->sender_name ?: '—' }}</div>
                        <div class="text-xs" style="color: var(--text-muted);">{{ $thread->sender_email }}</div>
                        <div class="flex items-center gap-2 flex-wrap mt-2">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1" style="background: rgba(0,0,0,0.25); color: {{ $thread->channelColor() }};"><i class="{{ $thread->channelIcon() }}"></i>{{ $thread->channelLabel() }}</span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded" style="background: {{ $thread->categoryColor() }}22; color: {{ $thread->categoryColor() }};">{{ $thread->categoryLabel() }}</span>
                            @if($thread->isOverdue())
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #f87171;"><i class="fas fa-clock mr-1"></i>Overdue {{ $thread->sla_due_at->diffForHumans() }}</span>
                            @elseif($thread->sla_due_at)
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded" style="background: rgba(245,158,11,0.1); color: #fbbf24;"><i class="fas fa-stopwatch mr-1"></i>SLA {{ $thread->sla_due_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('user.inbox.unified.update', $thread->id) }}" class="flex gap-2">@csrf
                        <button name="action" value="{{ $thread->is_starred ? 'unstar' : 'star' }}" class="px-2 py-1 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" title="Star">
                            <i class="fa{{ $thread->is_starred ? 's' : 'r' }} fa-star {{ $thread->is_starred ? 'text-amber-400' : '' }}"></i>
                        </button>
                        <button name="action" value="{{ $thread->status === 'archived' ? 'unarchive' : 'archive' }}" class="px-2 py-1 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);" title="Archive">
                            <i class="fas fa-box-archive"></i>
                        </button>
                    </form>
                </div>

                <div class="text-sm font-semibold mt-4" style="color: var(--text-primary);">{{ $thread->subject }}</div>
            </div>

            {{-- Messages --}}
            <div class="space-y-3">
                @foreach($thread->messages as $m)
                    <div class="card-premium p-4 {{ $m->direction === 'out' ? 'ml-12' : 'mr-12' }}" style="{{ $m->direction === 'out' ? 'background: rgba(92,131,255,0.05);' : '' }}">
                        <div class="flex items-center gap-2 mb-2 text-xs" style="color: var(--text-muted);">
                            <span class="font-semibold" style="color: var(--text-secondary);">{{ $m->sender_name ?: ($m->direction === 'out' ? 'You' : 'Them') }}</span>
                            <span>·</span>
                            <span>{{ optional($m->sent_at)->diffForHumans() }}</span>
                            @if($m->direction === 'out')<span class="ml-auto text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">Sent</span>@endif
                        </div>
                        <div class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">{{ $m->body }}</div>
                    </div>
                @endforeach
            </div>

            @if(!empty($attachments))
                <div class="card-premium p-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">
                        <i class="fas fa-paperclip mr-1"></i>Attachments ({{ count($attachments) }})
                    </div>
                    <div class="space-y-2">
                        @foreach($attachments as $att)
                            @php
                                $uf       = $att['userFile'];
                                $url      = $att['url'];
                                $label    = $att['label'];
                                $pending  = $uf && $uf->isPendingScan();
                                $flagged  = $uf && $uf->isFlagged();
                                $highRisk = $uf && $uf->isHighRiskExtension();
                            @endphp
                            <div class="p-2.5 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <i class="fas fa-paperclip text-blue-400"></i>
                                    <span class="text-sm flex-1 min-w-0 truncate" style="color: var(--text-primary);">{{ $label }}</span>

                                    @if($pending)
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1" style="background: rgba(56,189,248,0.15); color: #38bdf8;">
                                            <i class="fas fa-shield-virus fa-spin"></i>Scanning…
                                        </span>
                                        <span class="px-2 py-1 rounded text-[11px]" style="background: rgba(0,0,0,0.2); color: var(--text-faint);" title="Download disabled until scan finishes">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                    @elseif($flagged)
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1" style="background: rgba(239,68,68,0.15); color: #f87171;">
                                            <i class="fas fa-shield-exclamation"></i>Quarantined
                                        </span>
                                        <a href="{{ $uf->url_path }}"
                                           onclick="return confirm({{ $highRisk ? "'This file type can run code on your computer. Continue to the warning page?'" : "'This attachment was flagged. View the warning page?'" }});"
                                           class="px-2 py-1 rounded text-[11px] font-semibold text-white" style="background: linear-gradient(135deg,#ef4444,#b91c1c);">
                                            Review &amp; download
                                        </a>
                                    @else
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-flex items-center gap-1" style="background: rgba(16,185,129,0.12); color: #34d399;">
                                            <i class="fas fa-shield-check"></i>Clean
                                        </span>
                                        <a href="{{ $url }}" target="_blank" class="px-2 py-1 rounded text-[11px] text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">
                                            <i class="fas fa-download mr-1"></i>Open
                                        </a>
                                    @endif
                                </div>
                                @if($flagged)
                                    <div class="mt-2 text-[11px]" style="color: #fca5a5;">
                                        <i class="fas fa-circle-info mr-1"></i>{{ $uf->scanReasonLabel() }}
                                        @if($highRisk) · <strong class="uppercase tracking-wider">High-risk file type</strong>@endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Reply composer --}}
            @canInWorkspace('inbox.reply')
            <form method="POST" action="{{ route('user.inbox.unified.reply', $thread->id) }}" class="card-premium p-5 space-y-3">@csrf
                <div class="text-xs font-bold uppercase tracking-wider" style="color: var(--text-faint);">Reply via {{ $thread->channelLabel() }}</div>

                @if(!empty($suggestions))
                <div class="flex flex-wrap gap-2">
                    @foreach($suggestions as $s)
                        <button type="button" @click="replyText = @js($s)" class="px-3 py-1.5 rounded-lg text-xs text-left max-w-md truncate" style="background: rgba(92,131,255,0.1); color: #bccfff; border: 1px solid rgba(92,131,255,0.2);" title="{{ $s }}">
                            <i class="fas fa-magic-wand-sparkles mr-1"></i>{{ \Illuminate\Support\Str::limit($s, 90) }}
                        </button>
                    @endforeach
                </div>
                @endif

                <textarea name="body" x-model="replyText" rows="6" required maxlength="20000"
                          placeholder="Type your reply or pick an AI draft above… try /snippet for shortcuts"
                          class="w-full px-3 py-2 rounded-lg text-sm outline-none"
                          style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"></textarea>

                @if($snippets->isNotEmpty())
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Snippets</span>
                    @foreach($snippets as $sn)
                        <button type="button" @click="replyText = (replyText ? replyText + '\n\n' : '') + @js($sn->body)"
                                class="px-2 py-1 rounded text-[11px]" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"
                                title="{{ $sn->label }}">{{ $sn->shortcut }}</button>
                    @endforeach
                </div>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">
                        <i class="fas fa-paper-plane mr-1"></i> Send reply
                    </button>
                </div>
            </form>
            @endcanInWorkspace
        </div>

        {{-- Sidebar: triage + actions --}}
        <aside class="space-y-4">
            <div class="card-premium p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Triage</div>
                <form method="POST" action="{{ route('user.inbox.unified.update', $thread->id) }}" class="space-y-2">@csrf
                    <input type="hidden" name="action" value="set_category">
                    <select name="category" onchange="this.form.submit()" class="w-full px-2 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        @foreach(\App\Modules\User\Models\InboxThread::CATEGORY_LABELS as $key => [$lbl, $_])
                            <option value="{{ $key }}" {{ $thread->category === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @if($thread->category_source === 'auto' && $thread->category_confidence)
                        <div class="text-[10px]" style="color: var(--text-faint);">AI confidence: {{ number_format($thread->category_confidence * 100) }}%</div>
                    @elseif($thread->category_source === 'manual')
                        <div class="text-[10px]" style="color: #90acff;">Manual override (used as training feedback)</div>
                    @endif
                </form>

                <div class="text-[10px] font-bold uppercase tracking-wider mt-4 mb-2" style="color: var(--text-faint);">Assign</div>
                <form method="POST" action="{{ route('user.inbox.unified.update', $thread->id) }}" class="space-y-2">@csrf
                    <input type="hidden" name="action" value="assign">
                    <select name="assignee_user_id" class="w-full px-2 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <option value="">Unassigned</option>
                        @foreach($teammates as $t)
                            <option value="{{ $t['id'] }}" {{ (int) $thread->assignee_user_id === (int) $t['id'] ? 'selected' : '' }}>{{ $t['name'] }}</option>
                        @endforeach
                    </select>
                    <textarea name="note" rows="2" maxlength="500" placeholder="Handoff note (optional)…"
                              class="w-full px-2 py-1.5 rounded-lg text-xs"
                              style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"></textarea>
                    <button class="w-full px-2 py-1.5 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">
                        <i class="fas fa-user-plus mr-1"></i>Apply assignment
                    </button>
                </form>

                @if($assignments->isNotEmpty())
                    <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                        <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Assignment history</div>
                        <ul class="space-y-2">
                            @foreach($assignments as $a)
                                @php
                                    $verb = match($a->action) {
                                        'assign'   => 'assigned to',
                                        'unassign' => 'unassigned from',
                                        'reassign' => 'reassigned to',
                                        'resolved' => 'resolved by',
                                        default    => $a->action,
                                    };
                                    $who = $a->action === 'unassign'
                                        ? optional($a->fromUser)->name
                                        : optional($a->toUser)->name;
                                @endphp
                                <li class="text-[11px]" style="color: var(--text-muted);">
                                    <i class="fas fa-circle text-[6px] mr-1" style="color: var(--text-faint);"></i>
                                    <span class="font-semibold" style="color: var(--text-secondary);">{{ optional($a->actor)->name ?: 'Someone' }}</span>
                                    {{ $verb }}
                                    <span class="font-semibold" style="color: var(--text-secondary);">{{ $who ?: '—' }}</span>
                                    · {{ optional($a->created_at)->diffForHumans() }}
                                    @if($a->note)
                                        <div class="ml-3 mt-0.5 text-[11px] italic" style="color: var(--text-muted);">“{{ $a->note }}”</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="text-[10px] font-bold uppercase tracking-wider mt-4 mb-2" style="color: var(--text-faint);">Privacy</div>
                <form method="POST" action="{{ route('user.inbox.unified.update', $thread->id) }}" class="flex items-center gap-2">@csrf
                    <input type="hidden" name="action" value="set_private">
                    <input type="hidden" name="value" value="{{ $thread->is_private ? 0 : 1 }}">
                    <button class="w-full px-2 py-1.5 rounded-lg text-xs text-left" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                        <i class="fas {{ $thread->is_private ? 'fa-lock text-amber-400' : 'fa-lock-open' }} mr-2"></i>
                        {{ $thread->is_private ? 'Private — owner & assignee only' : 'Visible to whole workspace' }}
                    </button>
                </form>

                <div class="text-[10px] font-bold uppercase tracking-wider mt-4 mb-2" style="color: var(--text-faint);">SLA — respond within</div>
                <form method="POST" action="{{ route('user.inbox.unified.update', $thread->id) }}">@csrf
                    <input type="hidden" name="action" value="set_sla">
                    <div class="flex gap-1 flex-wrap">
                        @foreach([4, 12, 24, 48, 72, 0] as $h)
                            <button name="hours" value="{{ $h }}" class="px-2 py-1 rounded text-[11px]" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                                {{ $h ? $h . 'h' : 'Clear' }}
                            </button>
                        @endforeach
                    </div>
                </form>
            </div>

            <div class="card-premium p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Convert this thread</div>
                <div class="space-y-2">
                    <form method="POST" action="{{ route('user.inbox.unified.convert.kanban', $thread->id) }}" class="flex gap-1">@csrf
                        <select name="board_id" required class="flex-1 px-2 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            <option value="">Pick a board…</option>
                            @foreach($availableBoards as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                        <button class="px-2 py-1 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);" title="Create kanban card"><i class="fas fa-columns"></i></button>
                    </form>
                    <form method="POST" action="{{ route('user.inbox.unified.convert.contact', $thread->id) }}">@csrf
                        <button class="w-full px-3 py-2 rounded-lg text-xs text-left" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-address-book mr-2 text-blue-400"></i>Save as contact
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.inbox.unified.convert.vault', $thread->id) }}">@csrf
                        <button class="w-full px-3 py-2 rounded-lg text-xs text-left" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                            <i class="fas fa-vault mr-2 text-emerald-400"></i>Save as vault client
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.inbox.unified.convert.calendar', $thread->id) }}" class="flex gap-1">@csrf
                        <input type="datetime-local" name="when" required class="flex-1 px-2 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <button class="px-2 py-1 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg,#3b82f6,#1d4ed8);" title="Schedule"><i class="fas fa-calendar-plus"></i></button>
                    </form>
                </div>

                @if($conversions->isNotEmpty())
                    <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                        <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Already converted</div>
                        @foreach($conversions as $c)
                            <div class="text-[11px] flex items-center gap-2 mb-1" style="color: var(--text-muted);">
                                <i class="fas fa-check text-emerald-400"></i>{{ ucfirst($c->kind) }} #{{ $c->target_id }} · {{ $c->created_at->diffForHumans() }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
