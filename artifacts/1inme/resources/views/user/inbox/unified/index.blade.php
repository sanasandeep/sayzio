@extends('user.layouts.app')
@section('title', 'Inbox 2.0')

@section('content')
<div class="max-w-[1400px] mx-auto" x-data="{ selected: [], filtersOpen: {{ request()->hasAny(['q','channel','category','assignee','starred','overdue','review']) ? 'true' : 'false' }} }">
    @include('user.partials.page-hero', [
        'title' => 'Inbox 2.0',
        'subtitle' => 'Every channel in one triaged stream',
        'icon' => 'fa-inbox',
        'chips' => array_values(array_filter([
            ['icon' => 'fa-envelope text-blue-400', 'text' => number_format($counts['unread']) . ' unread'],
            ['icon' => 'fa-clock text-amber-400',    'text' => number_format($counts['overdue']) . ' overdue'],
            ($counts['review'] ?? 0) > 0 ? ['icon' => 'fa-robot text-blue-400', 'text' => number_format($counts['review']) . ' to review'] : null,
        ])),
        'actions' => [
            ['label' => 'AI Inbox Agent',   'url' => route('user.inbox.unified.agent'),          'icon' => 'fa-robot', 'class' => 'btn-ghost'],
            ['label' => 'Snippets',         'url' => route('user.inbox.unified.snippets.index'), 'icon' => 'fa-bolt',  'class' => 'btn-ghost'],
            ['label' => 'Classic inbox',    'url' => route('user.inbox.index'),                  'icon' => 'fa-arrow-rotate-left', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #f87171;">
            <i class="fas fa-circle-exclamation mr-1.5"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Mobile: filters collapse behind a toggle so threads show first --}}
    <button type="button" class="lg:hidden w-full mb-4 px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-between"
            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
            @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" data-inbox-filters-toggle>
        <span><i class="fas fa-sliders mr-2" style="color:#5c83ff;"></i>Filters &amp; search</span>
        <i class="fas fa-chevron-down transition-transform" :class="filtersOpen ? 'rotate-180' : ''"></i>
    </button>

    <div class="grid lg:grid-cols-[240px_1fr] gap-5">
        {{-- Filters sidebar --}}
        <aside class="card-premium p-4 h-fit space-y-4"
               :class="filtersOpen ? '' : 'hidden lg:block'">
            <form method="GET">
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search threads…"
                    class="w-full px-3 py-2 rounded-lg text-sm outline-none mb-3"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

                <div class="space-y-1 mb-3">
                    @php $statuses = ['open' => 'fa-inbox', 'snoozed' => 'fa-clock', 'archived' => 'fa-box-archive', 'all' => 'fa-list']; @endphp
                    @foreach($statuses as $val => $icon)
                        @php $active = $filters['status'] === $val; @endphp
                        <a href="?{{ http_build_query(array_merge(request()->except(['status','page']), ['status' => $val])) }}"
                           class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                           style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white;' : 'color: var(--text-secondary);' }}">
                            <i class="fas {{ $icon }} w-4 text-center"></i> {{ ucfirst($val) }}
                        </a>
                    @endforeach
                </div>

                <div class="text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Channels</div>
                <div class="space-y-1 mb-3">
                    @foreach(\App\Modules\User\Models\InboxThread::CHANNEL_LABELS as $key => [$lbl, $icon, $color])
                        @php $active = ($filters['channel'] ?? null) === $key; @endphp
                        <a href="?{{ http_build_query(array_merge(request()->except(['channel','page']), $active ? [] : ['channel' => $key])) }}"
                           class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                           style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white;' : 'color: var(--text-secondary);' }}">
                            <i class="{{ $icon }} w-4 text-center" style="color: {{ $active ? 'white' : $color }};"></i> {{ $lbl }}
                        </a>
                    @endforeach
                </div>

                <div class="text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Categories</div>
                <div class="space-y-1 mb-3">
                    @foreach(\App\Modules\User\Models\InboxThread::CATEGORY_LABELS as $key => [$lbl, $color])
                        @php $active = ($filters['category'] ?? null) === $key; $count = $byCategory[$key] ?? 0; @endphp
                        <a href="?{{ http_build_query(array_merge(request()->except(['category','page']), $active ? [] : ['category' => $key])) }}"
                           class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg text-xs"
                           style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white;' : 'color: var(--text-secondary);' }}">
                            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full" style="background: {{ $color }};"></span>{{ $lbl }}</span>
                            <span class="text-[10px] opacity-70">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Assignee</div>
                <select name="assignee" onchange="this.form.submit()" class="w-full px-2 py-1.5 rounded-lg text-xs mb-3"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="">Anyone</option>
                    <option value="me"        {{ $filters['assignee'] === 'me' ? 'selected' : '' }}>Just me</option>
                    <option value="unassigned" {{ $filters['assignee'] === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                    @foreach($teammates as $t)
                        <option value="{{ $t['id'] }}" {{ (string) $filters['assignee'] === (string) $t['id'] ? 'selected' : '' }}>{{ $t['name'] }}</option>
                    @endforeach
                </select>

                <label class="flex items-center gap-2 text-xs mb-1" style="color: var(--text-secondary);">
                    <input type="checkbox" name="starred" value="1" {{ $filters['starred'] ? 'checked' : '' }} onchange="this.form.submit()"> Starred only
                </label>
                <label class="flex items-center gap-2 text-xs mb-1" style="color: var(--text-secondary);">
                    <input type="checkbox" name="overdue" value="1" {{ $filters['overdue'] ? 'checked' : '' }} onchange="this.form.submit()"> Overdue SLA only
                </label>
                <label class="flex items-center gap-2 text-xs mb-3" style="color: var(--text-secondary);">
                    <input type="checkbox" name="review" value="1" {{ ($filters['review'] ?? false) ? 'checked' : '' }} onchange="this.form.submit()">
                    <span><i class="fas fa-robot mr-1" style="color:#5c83ff;"></i>Awaiting AI review @if(($counts['review'] ?? 0) > 0)<span class="ml-1 text-[10px] opacity-70">({{ $counts['review'] }})</span>@endif</span>
                </label>

                <button class="w-full px-3 py-2 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">Apply</button>
            </form>
        </aside>

        {{-- Threads list --}}
        <div>
            <form method="POST" action="{{ route('user.inbox.unified.bulk') }}" id="bulk-form">@csrf
                <div class="card-premium p-3 mb-3 flex items-center gap-2 flex-wrap" x-show="selected.length > 0" x-cloak>
                    <span class="text-xs font-semibold" style="color: var(--text-secondary);" x-text="selected.length + ' selected'"></span>
                    <button name="action" value="mark_read" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-envelope-open mr-1"></i>Mark read</button>
                    <button name="action" value="archive"   class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-box-archive mr-1"></i>Archive</button>
                    <select name="category" class="px-2 py-1 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        @foreach(\App\Modules\User\Models\InboxThread::CATEGORY_LABELS as $key => [$lbl, $_])<option value="{{ $key }}">{{ $lbl }}</option>@endforeach
                    </select>
                    <button name="action" value="set_category" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">Re-categorise</button>
                </div>

                @if($threads->isEmpty())
                    <div class="card-premium p-12 text-center">
                        <i class="fas fa-inbox text-4xl mb-3" style="color: var(--text-faint);"></i>
                        <p class="text-sm" style="color: var(--text-muted);">Nothing here yet. As soon as a form, DM or sponsorship comes in, it'll land here.</p>
                    </div>
                @else
                    <div class="card-premium overflow-hidden">
                        <div class="divide-y" style="border-color: var(--border-glass);">
                            @foreach($threads as $t)
                                <div class="flex items-center gap-2.5 sm:gap-3 p-3 sm:p-4 hover:bg-blue-500/5 transition-colors {{ !$t->is_read ? 'bg-blue-500/5' : '' }}">
                                    <input type="checkbox" name="thread_ids[]" value="{{ $t->id }}" form="bulk-form" x-model="selected" class="flex-shrink-0">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 overflow-hidden"
                                         style="background: linear-gradient(135deg, {{ $t->channelColor() }}aa, {{ $t->categoryColor() }}aa); color: white;">
                                        @if($t->sender_avatar)
                                            <img src="{{ $t->sender_avatar }}" alt="" class="w-full h-full object-cover">
                                        @else
                                            {{ strtoupper(mb_substr($t->sender_name ?: '?', 0, 1)) }}
                                        @endif
                                    </div>
                                    <a href="{{ route('user.inbox.unified.show', $t->id) }}" class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-{{ $t->is_read ? 'medium' : 'bold' }} truncate" style="color: var(--text-primary);">{{ $t->sender_name ?: 'Unknown' }}</span>
                                            @if(!$t->is_read)<span class="w-2 h-2 rounded-full bg-blue-500"></span>@endif
                                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1"
                                                  style="background: rgba(0,0,0,0.25); color: {{ $t->channelColor() }};">
                                                <i class="{{ $t->channelIcon() }}"></i> {{ $t->channelLabel() }}
                                            </span>
                                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: {{ $t->categoryColor() }}22; color: {{ $t->categoryColor() }};">{{ $t->categoryLabel() }}</span>
                                            @if(in_array($t->priority, ['high','urgent'], true))
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: {{ $t->priorityColor() }}22; color: {{ $t->priorityColor() }};"><i class="fas fa-flag mr-1"></i>{{ $t->priorityLabel() }}</span>
                                            @endif
                                            @if($t->wasSentByAi())
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(92,131,255,0.15); color: #5c83ff;" title="Replied automatically by the AI Inbox Agent"><i class="fas fa-robot mr-1"></i>Sent by AI</span>
                                            @elseif($t->needsReview())
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(92,131,255,0.15); color: #5c83ff;" title="The AI Inbox Agent drafted a reply awaiting your review"><i class="fas fa-robot mr-1"></i>AI draft</span>
                                            @endif
                                            @if($t->isOverdue())
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #f87171;"><i class="fas fa-clock mr-1"></i>Overdue</span>
                                            @elseif($t->sla_due_at)
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,0.1); color: #fbbf24;" title="Respond by {{ $t->sla_due_at->format('Y-m-d H:i') }}"><i class="fas fa-stopwatch mr-1"></i>{{ $t->sla_due_at->diffForHumans() }}</span>
                                            @endif
                                            @if($t->assignee_user_id && $t->assignee)
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(59,130,246,0.1); color: #60a5fa;"><i class="fas fa-user mr-1"></i>{{ $t->assignee->name }}</span>
                                            @endif
                                        </div>
                                        <div class="text-xs truncate mt-0.5" style="color: var(--text-muted);">{{ $t->subject }}</div>
                                        @if($t->summary)
                                            <div class="text-[11px] truncate mt-0.5" style="color: var(--text-muted);" title="AI summary"><i class="fas fa-wand-magic-sparkles mr-1" style="color:#5c83ff;"></i>{{ $t->summary }}</div>
                                        @else
                                            <div class="text-[11px] truncate mt-0.5" style="color: var(--text-faint);">{{ $t->preview }}</div>
                                        @endif
                                    </a>
                                    <div class="text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);" title="{{ $t->last_message_at?->format('Y-m-d H:i') }}">
                                        {{ $t->last_message_at?->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-6">{{ $threads->links() }}</div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
