@extends('user.layouts.app')
@section('title', 'Inbox')

@section('content')
@php
    $exportQuery = http_build_query(array_filter([
        'source' => $filters['source'] ?? null,
        'form_id' => $filters['form_id'] ?? null,
        'link_id' => $filters['link_id'] ?? null,
        'unread' => !empty($filters['unread']) ? 1 : null,
        'starred' => !empty($filters['starred']) ? 1 : null,
        'spam' => !empty($filters['spam']) ? 1 : null,
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'q' => $filters['q'] ?? null,
    ], fn($v) => $v !== null && $v !== ''));
@endphp
<div class="max-w-7xl mx-auto" x-data="{ selected: [], get allChecked() { return this.selected.length > 0 } }">
    @include('user.partials.page-hero', [
        'title' => 'Inbox',
        'subtitle' => 'Every message from every form, link & block',
        'icon' => 'fa-inbox',
        'chips' => [
            ['icon' => 'fa-envelope text-blue-400', 'text' => number_format($unread) . ' unread'],
        ],
        'actions' => [
            ['label' => 'Forwarding rules', 'url' => route('user.inbox.forwards.index'), 'icon' => 'fa-share-from-square', 'class' => 'btn-ghost'],
            ['label' => 'Spam settings', 'url' => route('user.inbox.spam-settings'), 'icon' => 'fa-shield-alt', 'class' => 'btn-ghost'],
            ['label' => 'Export filtered (CSV)', 'url' => route('user.inbox.export') . ($exportQuery ? '?' . $exportQuery : ''), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <div class="grid lg:grid-cols-[260px_1fr] gap-5">
        {{-- Filters sidebar --}}
        <aside class="card-premium p-4 h-fit">
            <form method="GET" class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="email, name, phone, text…"
                           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>

                <div class="flex flex-col gap-1.5">
                    @foreach([
                        '' => ['All sources', 'fa-layer-group'],
                        'form_submission' => ['Form submissions', 'fa-clipboard-list'],
                        'email_subscribe' => ['Newsletter signups', 'fa-envelope-open-text'],
                        'email_collector' => ['Email collectors', 'fa-envelope'],
                        'phone_collector' => ['Phone collectors', 'fa-phone'],
                        'contact_form' => ['Contact forms', 'fa-paper-plane'],
                        'whatsapp_channel' => ['WhatsApp channel', 'fa-bullhorn'],
                        'whatsapp_number' => ['WhatsApp number', 'fa-comment-dots'],
                    ] as $val => $meta)
                        @php $active = ($filters['source'] ?? '') === $val; @endphp
                        <a href="?{{ http_build_query(array_merge(request()->except(['source','page']), $val ? ['source' => $val] : [])) }}"
                           class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                           style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white;' : 'color: var(--text-secondary);' }}">
                            <i class="fas {{ $meta[1] }} w-4 text-center"></i>{{ $meta[0] }}
                        </a>
                    @endforeach
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Specific form</label>
                    <select name="form_id" class="w-full px-2 py-1.5 rounded-lg text-xs"
                            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <option value="">Any form</option>
                        @foreach($forms as $f)
                            <option value="{{ $f->id }}" {{ ($filters['form_id'] ?? '') == $f->id ? 'selected' : '' }}>{{ $f->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Specific Link in Bio</label>
                    <select name="link_id" class="w-full px-2 py-1.5 rounded-lg text-xs"
                            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <option value="">Any link</option>
                        @foreach($links as $l)
                            <option value="{{ $l->id }}" {{ ($filters['link_id'] ?? '') == $l->id ? 'selected' : '' }}>/{{ $l->alias }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                        <input type="checkbox" name="unread" value="1" {{ !empty($filters['unread']) ? 'checked' : '' }}> Unread only
                    </label>
                    <label class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                        <input type="checkbox" name="starred" value="1" {{ !empty($filters['starred']) ? 'checked' : '' }}> Starred only
                    </label>
                    <label class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                        <input type="checkbox" name="spam" value="1" {{ !empty($filters['spam']) ? 'checked' : '' }}> Spam
                    </label>
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Date range</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                               class="w-1/2 px-2 py-1.5 rounded-lg text-xs"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                               class="w-1/2 px-2 py-1.5 rounded-lg text-xs"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">Apply</button>
                    <a href="{{ route('user.inbox.index') }}" class="px-3 py-2 rounded-lg text-xs" style="color: var(--text-muted);">Reset</a>
                </div>
            </form>
        </aside>

        {{-- List --}}
        <div>
            <form method="POST" action="{{ route('user.inbox.bulk') }}" id="bulk-form">@csrf
                <div class="card-premium p-3 mb-3 flex items-center gap-2 flex-wrap" x-show="allChecked" x-cloak>
                    <span class="text-xs font-semibold" style="color: var(--text-secondary);" x-text="selected.length + ' selected'"></span>
                    @canInWorkspace('inbox.edit')
                    <button name="action" value="read"     class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-envelope-open mr-1"></i>Mark read</button>
                    <button name="action" value="unread"   class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-envelope mr-1"></i>Mark unread</button>
                    <button name="action" value="star"     class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-star mr-1 text-amber-400"></i>Star</button>
                    <button name="action" value="unstar"   class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="far fa-star mr-1"></i>Unstar</button>
                    <button name="action" value="spam"     class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(234,88,12,0.1); border: 1px solid rgba(234,88,12,0.2); color: #fb923c;"><i class="fas fa-ban mr-1"></i>Spam</button>
                    <button name="action" value="not_spam" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-shield-alt mr-1"></i>Not spam</button>
                    <button name="action" value="not_spam_trust" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #4ade80;" title="Mark not spam and add the sender to your trusted list."><i class="fas fa-user-shield mr-1"></i>Not spam &amp; trust</button>
                    @endcanInWorkspace
                    <button name="action" value="export"   class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-file-csv mr-1"></i>Export</button>
                    @canInWorkspace('inbox.delete')
                    <button name="action" value="delete"   onclick="return window.themedConfirmAction(this, {title: 'Delete selected items?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})" class="px-3 py-1.5 rounded-lg text-xs" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash mr-1"></i>Delete</button>
                    @else
                    <button type="button" disabled class="px-3 py-1.5 rounded-lg text-xs cursor-not-allowed opacity-50" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);" title="Your role doesn't allow deleting inbox items"><i class="fas fa-lock mr-1"></i>Delete</button>
                    @endcanInWorkspace
                </div>

                @if($items->isEmpty())
                    <div class="card-premium p-12 text-center">
                        <i class="fas fa-inbox text-4xl mb-3" style="color: var(--text-faint);"></i>
                        <p class="text-sm" style="color: var(--text-muted);">No messages match these filters.</p>
                    </div>
                @else
                <div class="card-premium overflow-hidden">
                    <div class="divide-y" style="border-color: var(--border-glass);">
                        @foreach($items as $row)
                            @php
                                $routeType = $row->source_type === 'form_submission' ? 'form_submission' : 'subscriber';
                                $token = $routeType . ':' . $row->item_id;
                            @endphp
                            <div class="flex items-center gap-3 p-4 hover:bg-blue-500/5 transition-colors {{ !$row->is_read ? 'bg-blue-500/5' : '' }}">
                                <input type="checkbox" name="items[]" value="{{ $token }}" form="bulk-form" x-model="selected" class="flex-shrink-0">
                                <button type="submit" form="row-star-{{ $token }}" class="text-base flex-shrink-0" title="{{ $row->is_starred ? 'Unstar' : 'Star' }}">
                                    <i class="fa{{ $row->is_starred ? 's' : 'r' }} fa-star {{ $row->is_starred ? 'text-amber-400' : '' }}" style="color: {{ $row->is_starred ? '' : 'var(--text-faint)' }};"></i>
                                </button>
                                <form id="row-star-{{ $token }}" method="POST" action="{{ route('user.inbox.update', [$routeType, $row->item_id]) }}" class="hidden">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $row->is_starred ? 'unstar' : 'star' }}">
                                </form>
                                <a href="{{ route('user.inbox.show', [$routeType, $row->item_id]) }}" class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                         style="background: linear-gradient(135deg, #5c83ff, #ec4899); color: white;">
                                        {{ strtoupper(substr($row->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-{{ $row->is_read ? 'medium' : 'bold' }} truncate" style="color: var(--text-primary);">{{ $row->name }}</span>
                                            @unless($row->is_read)<span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>@endunless
                                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
                                                  style="background: rgba(92,131,255,0.15); color: #90acff;">
                                                {{ $row->source_label }}
                                            </span>
                                            @if($row->is_spam)
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(234,88,12,0.15); color: #fb923c;">Spam</span>
                                                @php $reasonLabel = \App\Modules\User\Services\SpamChecker::reasonLabel($row->spam_reason ?? null); @endphp
                                                @if($reasonLabel)
                                                    <span class="text-[9px] font-semibold uppercase px-1.5 py-0.5 rounded" style="background: rgba(234,88,12,0.08); color: #fdba74;" title="The spam filter rule that flagged this message.">{{ $reasonLabel }}</span>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="text-[11px] truncate mt-0.5" style="color: var(--text-faint);">{{ $row->preview }}</div>
                                    </div>
                                </a>
                                <div class="text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                                    {{ $row->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-6">{{ $items->links() }}</div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
