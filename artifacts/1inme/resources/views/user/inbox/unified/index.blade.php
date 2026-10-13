@extends('user.layouts.app')
@section('title', 'Inbox 2.0')

@section('content')
<div class="unified-inbox max-w-[1400px] 2xl:max-w-[1720px] mx-auto" x-data="{ selected: [], filtersOpen: false }">
    @include('user.partials.page-hero', [
        'title' => 'Inbox 2.0',
        'subtitle' => 'Forms, messages and enquiries — organised by channel, priority and response deadline',
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
        <span><i class="fas fa-sliders mr-2" style="color:#5c83ff;"></i>Filters &amp; search @if($filters['q'] || $filters['channel'] || $filters['category'] || $filters['assignee'] || $filters['starred'] || $filters['overdue'] || $filters['review'])<span class="inbox-unread">Active filters</span>@endif</span>
        <i class="fas fa-chevron-down transition-transform" :class="filtersOpen ? 'rotate-180' : ''"></i>
    </button>

    <div class="inbox-layout">
        {{-- Filters sidebar --}}
        <aside class="inbox-filters card-premium p-4 h-fit space-y-4"
               :class="filtersOpen ? '' : 'hidden lg:block'">
            <form method="GET" class="inbox-filter-form">
                <h2 class="inbox-filter-title">Refine your inbox</h2>
                <label for="inbox-search" class="inbox-field-label">Search</label>
                @foreach(['status','channel','category'] as $key)
                @if(!empty($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
                @endforeach
                <input id="inbox-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search threads…"
                    class="w-full px-3 py-2 rounded-lg text-sm outline-none mb-3"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

                <div class="space-y-1 mb-3">
                    @php $statuses = ['open' => 'fa-inbox', 'snoozed' => 'fa-clock', 'archived' => 'fa-box-archive', 'all' => 'fa-list']; @endphp
                    @foreach($statuses as $val => $icon)
                        @php $active = $filters['status'] === $val; @endphp
                        <a href="?{{ http_build_query(array_merge(request()->except(['status','page','overdue']), ['status' => $val])) }}"
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
                <select aria-label="Assigned to" name="assignee" onchange="this.form.submit()" class="w-full px-2 py-1.5 rounded-lg text-xs mb-3"
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
                    <input type="checkbox" name="overdue" value="1" {{ $filters['overdue'] ? 'checked' : '' }} onchange="this.form.submit()"> Past response deadline
                </label>
                <label class="flex items-center gap-2 text-xs mb-3" style="color: var(--text-secondary);">
                    <input type="checkbox" name="review" value="1" {{ ($filters['review'] ?? false) ? 'checked' : '' }} onchange="this.form.submit()">
                    <span><i class="fas fa-robot mr-1" style="color:#5c83ff;"></i>Awaiting AI review @if(($counts['review'] ?? 0) > 0)<span class="ml-1 text-[10px] opacity-70">({{ $counts['review'] }})</span>@endif</span>
                </label>

                <button class="w-full px-3 py-2 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);">Apply filters</button>
                <a class="inbox-reset" href="{{ route('user.inbox.unified.index') }}">Reset filters</a>
                <p class="inbox-help">Category counts cover open threads. Overdue means an open thread has passed its response deadline.</p>
            </form>
        </aside>

        {{-- Threads list --}}
        <div class="inbox-results">
            <div class="inbox-results-head"><div><h2>{{ ucfirst($filters['status'] ?: 'all') }} conversations <span>{{ number_format($threads->total()) }}</span></h2><p>Overdue first, then latest activity. Open a conversation to review and reply.</p>
            <p class="inbox-active-context">@if($filters['q'])Search: “{{ $filters['q'] }}” · @endif{{ $filters['channel'] ? (\App\Modules\User\Models\InboxThread::CHANNEL_LABELS[$filters['channel']][0] ?? $filters['channel']) : 'All channels' }} · {{ $filters['category'] ? ucfirst($filters['category']) : 'All categories' }}</p></div><span class="inbox-results-range">{{ $threads->firstItem() ?? 0 }}–{{ $threads->lastItem() ?? 0 }} of {{ number_format($threads->total()) }}</span></div>
            <form method="POST" action="{{ route('user.inbox.unified.bulk') }}" id="bulk-form">@csrf
                @if($threads->isNotEmpty())<button type="button" class="inbox-select-page" @click="selected = selected.length === {{ $threads->count() }} ? [] : {{ json_encode($threads->pluck('id')->map(fn($id)=>(string)$id)->values()->all()) }}" x-text="selected.length === {{ $threads->count() }} ? 'Clear selection' : 'Select this page'">Select this page</button>@endif
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
                        <p class="text-sm" style="color: var(--text-muted);">No conversations match these filters. Try another search or reset filters to see your open inbox.</p>
                    </div>
                @else
                    <div class="card-premium overflow-hidden">
                        <div class="inbox-thread-list">
                            @foreach($threads as $t)
                                <div class="inbox-thread-row flex items-center gap-2.5 sm:gap-3 p-3 sm:p-4 hover:bg-blue-500/5 transition-colors {{ !$t->is_read ? 'bg-blue-500/5' : '' }}">
                                    <input type="checkbox" name="thread_ids[]" value="{{ $t->id }}" form="bulk-form" x-model="selected" aria-label="Select conversation {{ $t->id }}" class="flex-shrink-0">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 overflow-hidden"
                                         style="background: linear-gradient(135deg, {{ $t->channelColor() }}aa, {{ $t->categoryColor() }}aa); color: white;">
                                        @if($t->sender_avatar)
                                            <img src="{{ $t->sender_avatar }}" alt="" class="w-full h-full object-cover">
                                        @else
                                            {{ strtoupper(mb_substr($t->sender_name ?: '?', 0, 1)) }}
                                        @endif
                                    </div>
                                    <a href="{{ route('user.inbox.unified.show', $t->id) }}" class="inbox-thread-content flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="inbox-sender text-sm {{ $t->is_read ? 'font-medium' : 'font-bold' }} truncate" style="color: var(--text-primary);">{{ $t->sender_name ?: 'Unknown' }}</span>
                                            @if(!$t->is_read)<span class="inbox-unread">Unread</span>@endif
                                            @if($t->is_starred)<span title="Starred" aria-label="Starred">★</span>@endif
                                            @if($t->is_private)<span class="inbox-private">Private</span>@endif
                                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1 inb-badge-dim"
                                                  style="color: {{ $t->channelColor() }};">
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
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #f87171;"><i class="fas fa-clock mr-1"></i>Reply overdue</span>
                                            @elseif($t->sla_due_at && $t->status === 'open')
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded inb-sla-soon" title="Respond by {{ \App\Support\PlatformTimezone::format($t->sla_due_at,'Y-m-d H:i',true,\App\Support\PlatformTimezone::forUser(auth()->user())) }}"><i class="fas fa-stopwatch mr-1"></i>{{ $t->sla_due_at->diffForHumans() }}</span>
                                            @endif
                                            @if($t->assignee_user_id && $t->assignee)
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(59,130,246,0.1); color: #60a5fa;"><i class="fas fa-user mr-1"></i>{{ $t->assignee->name }}</span>
                                            @endif
                                        </div>
                                        <div class="text-xs truncate mt-0.5" style="color: var(--text-muted);">{{ $t->subject ?: 'No subject' }}</div>
                                        @if($t->summary)
                                            <div class="text-[11px] truncate mt-0.5" style="color: var(--text-muted);" title="AI summary"><span class="inbox-summary-label">AI summary · </span><i class="fas fa-wand-magic-sparkles mr-1" style="color:#5c83ff;"></i>{{ $t->summary }}</div>
                                        @else
                                            <div class="text-[11px] truncate mt-0.5" style="color: var(--text-faint);">{{ $t->preview }}</div>
                                        @endif
                                        <div class="inbox-thread-meta">#{{ $t->id }} · {{ ucfirst($t->status) }} · {{ $t->assignee?->name ?: 'Unassigned' }}@if($t->sender_email) · {{ $t->sender_email }}@endif</div>
                                    </a>
                                    <div class="inbox-thread-time text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);" title="{{ \App\Support\PlatformTimezone::format($t->last_message_at,'Y-m-d H:i',true,\App\Support\PlatformTimezone::forUser(auth()->user())) }}">
                                        <time datetime="{{ $t->last_message_at?->toIso8601String() }}">{{ $t->last_message_at?->diffForHumans() ?: 'No activity date' }}</time>
                                        @if($t->sla_due_at && $t->status === 'open')<span class="inbox-deadline">Response due {{ \App\Support\PlatformTimezone::format($t->sla_due_at,'M j, H:i',true,\App\Support\PlatformTimezone::forUser(auth()->user())) }}</span>@endif
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
<style>
.unified-inbox{min-width:0}.inbox-layout{display:grid;grid-template-columns:248px minmax(0,1fr);gap:20px;align-items:start}.inbox-filters{min-width:0}.inbox-filter-title{font-size:14px;font-weight:700;margin:0 0 16px}.inbox-field-label{display:block;font-size:12px;margin-bottom:6px;color:var(--text-muted)}.inbox-filter-form input[type=search]{min-width:0;width:100%}.inbox-filter-form a:focus-visible,.inbox-thread-content:focus-visible{outline:2px solid var(--accent);outline-offset:3px}.inbox-reset{display:block;text-align:center;font-size:12px;padding:10px;color:var(--accent)}.inbox-help{font-size:11px;line-height:1.6;color:var(--text-muted);margin-top:8px}.inbox-results{min-width:0}.inbox-results-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.inbox-results-head h2{font-size:17px;font-weight:700}.inbox-results-head h2 span{font-size:12px;background:var(--bg-glass-input);border:1px solid var(--border-glass);padding:3px 8px;border-radius:20px;margin-left:6px}.inbox-results-head p{font-size:12px;color:var(--text-muted);margin-top:5px;line-height:1.5}.inbox-results-range{font-size:11px;color:var(--text-muted);white-space:nowrap;padding-top:4px}.inbox-thread-row{border-bottom:1px solid var(--border-glass);min-width:0}.inbox-thread-row:last-child{border-bottom:0}.inbox-thread-content{display:block;min-width:0}.inbox-sender{max-width:100%;overflow-wrap:anywhere}.inbox-thread-meta{font-size:10px;color:var(--text-muted);margin-top:6px;overflow-wrap:anywhere}.inbox-select-page{display:block;margin-bottom:12px;padding:8px 12px;border:1px solid var(--border-glass);border-radius:10px;color:var(--text-secondary);font-size:12px;background:var(--bg-card);cursor:pointer}.inbox-summary-label{font-weight:600;color:var(--accent)}.inbox-unread,.inbox-private{font-size:9px;border-radius:20px;padding:2px 6px;background:var(--bg-glass-input);color:var(--accent);font-weight:700}.inbox-thread-time{max-width:145px;line-height:1.6}.inbox-deadline{display:block;margin-top:6px;color:var(--text-muted);font-size:10px}.inbox-filter-form input:focus-visible,.inbox-filter-form select:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
@media(max-width:1023px){.inbox-layout{grid-template-columns:minmax(0,1fr)}.inbox-filters{width:100%}}@media(max-width:640px){.inbox-thread-row{display:grid!important;grid-template-columns:18px 32px minmax(0,1fr);gap:10px!important;padding:14px!important}.inbox-thread-row>div:first-of-type{width:32px;height:32px}.inbox-thread-time{grid-column:3;max-width:none;text-align:left;font-size:10px}.inbox-deadline{display:inline;margin-left:8px}.inbox-results-head{flex-direction:column;gap:6px}.inbox-thread-content>div:first-child{gap:5px}.inbox-sender{flex-basis:100%;white-space:normal}.inbox-filter-form .space-y-1{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.inbox-filter-form .space-y-1 a{min-width:0}.inbox-thread-meta{line-height:1.6}}
</style>
@endsection
