@extends('user.layouts.app')
@section('title', 'My Calendar')

@section('content')
@php
    use Illuminate\Support\Carbon;

    $inputClass = 'w-full h-10 border border-white/10 rounded-xl px-3 py-0 text-sm leading-none focus:ring-2 focus:ring-blue-500/40';

    // Query helpers — all view/filter links go through these so every existing
    // filter rides along when the view, date or a chip changes.
    $baseQ = request()->query();
    $linkTo = function (array $over) use ($baseQ) {
        $q = array_merge($baseQ, $over);
        // Drop empties + stale pagination so toggling a chip resets cleanly.
        $q = array_filter($q, fn ($v) => $v !== null && $v !== '' && $v !== []);
        unset($q['page']);
        return route('user.calendars.mine', $q);
    };

    $views = ['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'];

    // Period label + prev/next/today targets per view.
    $unit = match ($view) { 'month' => 'month', 'week' => 'week', 'day' => 'day', default => null };
    if ($view === 'month') {
        $periodLabel = $focusDate->isoFormat('MMMM YYYY');
    } elseif ($view === 'week') {
        $ws = $focusDate->copy()->startOfWeek(Carbon::SUNDAY);
        $we = $focusDate->copy()->endOfWeek(Carbon::SUNDAY);
        $periodLabel = $ws->isoFormat('MMM D') . ' – ' . ($ws->month === $we->month ? $we->isoFormat('D, YYYY') : $we->isoFormat('MMM D, YYYY'));
    } elseif ($view === 'day') {
        $periodLabel = $focusDate->isoFormat('dddd, MMMM D, YYYY');
    } else {
        $periodLabel = null;
    }

    $prevDate = $unit ? $focusDate->copy()->sub($unit, 1)->format('Y-m-d') : null;
    $nextDate = $unit ? $focusDate->copy()->add($unit, 1)->format('Y-m-d') : null;
    $todayDate = Carbon::now($userTz)->format('Y-m-d');

    // Export URL — carries all current filters through.
    $exportBaseQ = array_filter(request()->query(), fn ($v) => $v !== null && $v !== '' && $v !== []);
    unset($exportBaseQ['page']);
    $exportIcsUrl = route('user.calendars.mine.export', array_merge($exportBaseQ, ['format' => 'ics']));
    $exportCsvUrl = route('user.calendars.mine.export', array_merge($exportBaseQ, ['format' => 'csv']));
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    {{-- ── Page header ─────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">My Calendar</h1>
            <p class="text-xs text-white/40 mt-0.5">
                @if(($filters['ws'] ?? '') === 'all')
                    Everything from the calendars you own and follow, across all workspaces.
                @else
                    Calendars from your active workspace, plus everything you follow.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Subscribe (live ICS feed) --}}
            <div class="relative" x-data="{ open: false, copied: false, url: @js($feedUrl) }"
                 @keydown.escape.window="open = false" @click.outside="open = false">
                <button @click="open = !open" type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                    <i class="fas fa-rss text-xs"></i> Subscribe
                    <i class="fas fa-chevron-down text-[10px] opacity-60" :class="open ? 'rotate-180' : ''" style="transition:transform .15s"></i>
                </button>
                <div x-show="open" x-transition
                     class="cal-pop absolute right-0 mt-1.5 w-80 rounded-xl border border-white/10 shadow-xl z-30 p-4" x-cloak>
                    <p class="text-sm font-semibold text-white">Subscribe to your calendar</p>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">
                        Paste this link into Google Calendar, Apple Calendar, or Outlook to keep it in
                        sync automatically, new events appear without downloading a new file.
                    </p>
                    <div class="mt-3 flex items-stretch gap-1.5">
                        <input type="text" readonly :value="url" x-ref="feedInput"
                               @focus="$event.target.select()"
                               class="flex-1 min-w-0 h-9 border border-white/10 rounded-lg px-2.5 text-xs text-white/80 bg-black/30 focus:ring-2 focus:ring-blue-500/40" />
                        <button type="button"
                                @click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1800); }).catch(() => { $refs.feedInput.select(); document.execCommand('copy'); copied = true; setTimeout(() => copied = false, 1800); })"
                                class="shrink-0 h-9 px-3 rounded-lg text-xs font-medium bg-blue-600 hover:bg-blue-500 text-white transition">
                            <span x-show="!copied"><i class="far fa-copy mr-1"></i>Copy</span>
                            <span x-show="copied" x-cloak><i class="fas fa-check mr-1"></i>Copied</span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('user.calendars.mine.feed.reset') }}"
                          onsubmit="return confirm('Reset your subscription link? Any calendar app already subscribed with the old link will stop updating until you re-subscribe.');"
                          class="mt-3 pt-3 border-t border-white/5">
                        @csrf
                        <button type="submit" class="text-xs text-white/50 hover:text-red-300 transition">
                            <i class="fas fa-rotate mr-1"></i> Reset link
                        </button>
                        <p class="text-[11px] text-white/30 mt-1 leading-snug">Use this if the link was shared by mistake. It can't be undone.</p>
                    </form>
                </div>
            </div>

            {{-- Task #6477 — task/reminder mirror toggles --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button @click="open = !open" type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                    <i class="fas fa-arrows-rotate text-xs"></i> Sync
                    <i class="fas fa-chevron-down text-[10px] opacity-60" :class="open ? 'rotate-180' : ''" style="transition:transform .15s"></i>
                </button>
                <div x-show="open" x-transition
                     class="cal-pop absolute right-0 mt-1.5 w-80 rounded-xl border border-white/10 shadow-xl z-30 p-4" x-cloak>
                    <p class="text-sm font-semibold text-white">Tasks &amp; Reminders</p>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">
                        Show your task board due dates and note reminders here as calendar events.
                        They flow into your subscription feed and exports too.
                    </p>
                    <form method="POST" action="{{ route('user.calendars.mine.mirror-preferences') }}" class="mt-3 space-y-2.5">
                        @csrf
                        <label class="flex items-center gap-2.5 text-sm text-white/70 cursor-pointer">
                            <input type="checkbox" name="task_due_dates" value="1" @checked($mirrorPrefs['task_due_dates'] ?? true)
                                   class="rounded text-blue-500 bg-black/30 border-white/20">
                            Task due dates
                        </label>
                        <label class="flex items-center gap-2.5 text-sm text-white/70 cursor-pointer">
                            <input type="checkbox" name="note_reminders" value="1" @checked($mirrorPrefs['note_reminders'] ?? true)
                                   class="rounded text-blue-500 bg-black/30 border-white/20">
                            Note &amp; checklist reminders
                        </label>
                        <button type="submit" class="w-full mt-1 h-9 rounded-lg text-xs font-medium bg-blue-600 hover:bg-blue-500 text-white transition">
                            Save preferences
                        </button>
                    </form>
                </div>
            </div>

            {{-- Export dropdown --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button @click="open = !open" type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30 transition">
                    <i class="fas fa-download text-xs"></i> Export
                    <i class="fas fa-chevron-down text-[10px] opacity-60" :class="open ? 'rotate-180' : ''" style="transition:transform .15s"></i>
                </button>
                <div x-show="open" x-transition
                     class="cal-pop absolute right-0 mt-1.5 w-44 rounded-xl border border-white/10 shadow-xl z-30">
                    <a href="{{ $exportIcsUrl }}"
                       class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-t-xl transition">
                        <i class="far fa-calendar-alt w-4 text-center text-blue-400"></i>
                        ICS / iCal
                    </a>
                    <a href="{{ $exportCsvUrl }}"
                       class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-b-xl transition border-t border-white/5">
                        <i class="fas fa-table w-4 text-center text-emerald-400"></i>
                        CSV Spreadsheet
                    </a>
                </div>
            </div>
            <a href="{{ route('user.calendars.create') }}" class="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition">
                <i class="fas fa-plus mr-1"></i> New calendar
            </a>
        </div>
    </div>

    {{-- ── View switcher + period navigation ─────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex p-1 rounded-xl glass">
            @foreach($views as $key => $label)
                <a href="{{ $linkTo(['view' => $key, 'date' => $focusDate->format('Y-m-d')]) }}"
                   class="px-3.5 py-1.5 rounded-lg text-sm font-medium transition {{ $view === $key ? 'bg-blue-600 text-white shadow-sm' : 'text-white/60 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if($periodLabel)
        <div class="flex items-center gap-2">
            <a href="{{ $linkTo(['view' => $view, 'date' => $todayDate]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition">Today</a>
            <a href="{{ $linkTo(['view' => $view, 'date' => $prevDate]) }}" class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition"><i class="fas fa-chevron-left text-xs"></i></a>
            <span class="text-sm font-semibold text-white min-w-[10rem] text-center">{{ $periodLabel }}</span>
            <a href="{{ $linkTo(['view' => $view, 'date' => $nextDate]) }}" class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition"><i class="fas fa-chevron-right text-xs"></i></a>
        </div>
        @endif
    </div>

    {{-- ── Filters ───────────────────────────────────────────── --}}
    <style>
        /* Native date pickers default to the dark glass theme, but follow the
           light theme when the dashboard is toggled to light mode. */
        /* Header popovers (Subscribe / Sync / Export): dark glass by default,
           paired light-mode overrides so they never render dark-on-light. */
        .cal-pop { background: rgba(20,18,35,0.97); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        html.light-mode .cal-pop { background: rgba(255,255,255,0.98); border-color: rgba(15,23,42,.12); box-shadow: 0 12px 32px rgba(15,23,42,.14); }
        html.light-mode .cal-pop .text-white { color: #0f172a; }
        /* Primary (blue) buttons keep white labels in light mode. */
        html.light-mode .cal-pop .bg-blue-600, html.light-mode .cal-pop .bg-blue-600 * { color: #ffffff; }
        html.light-mode .cal-pop .text-white\/80 { color: #1e293b; }
        html.light-mode .cal-pop .text-white\/70 { color: #334155; }
        html.light-mode .cal-pop .text-white\/50 { color: #64748b; }
        html.light-mode .cal-pop .text-white\/30 { color: #94a3b8; }
        html.light-mode .cal-pop .hover\:text-white:hover { color: #0f172a; }
        html.light-mode .cal-pop .hover\:text-red-300:hover { color: #dc2626; }
        html.light-mode .cal-pop .bg-black\/30 { background: rgba(15,23,42,.04); }
        html.light-mode .cal-pop .border-white\/10, html.light-mode .cal-pop .border-white\/20 { border-color: rgba(15,23,42,.15); }
        html.light-mode .cal-pop .border-white\/5 { border-color: rgba(15,23,42,.08); }
        html.light-mode .cal-pop .hover\:bg-white\/5:hover { background: rgba(15,23,42,.05); }
        .mycal-filters input, .mycal-filters select { color-scheme: dark; }
        html.light-mode .mycal-filters input, html.light-mode .mycal-filters select { color-scheme: light; }
    </style>
    <form method="GET" action="{{ route('user.calendars.mine') }}" class="mycal-filters glass rounded-2xl p-5">
        {{-- Carry view + focus date so applying filters doesn't reset them. --}}
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="hidden" name="date" value="{{ $focusDate->format('Y-m-d') }}">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
            <div class="md:col-span-2">
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search events…" class="{{ $inputClass }}">
            </div>
            <div>
                <select name="source" class="{{ $inputClass }}">
                    <option value="all" @selected($filters['source'] === 'all')>All sources</option>
                    <option value="owned" @selected($filters['source'] === 'owned')>Owned by me</option>
                    <option value="followed" @selected($filters['source'] === 'followed')>Following</option>
                </select>
            </div>
            <div>
                <select name="calendar" class="{{ $inputClass }}" title="Filter by calendar">
                    <option value="">All calendars</option>
                    @foreach($calendars as $cal)
                        <option value="{{ $cal->id }}" @selected((string) ($filters['calendar'] ?? '') === (string) $cal->id)>{{ $cal->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="{{ $inputClass }}" title="From">
            </div>
            <div>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="{{ $inputClass }}" title="To">
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 mt-3">
            @if($filters['tag'])
                <span class="text-[11px] px-2 py-1 rounded-full bg-blue-500/10 text-blue-300">#{{ $filters['tag'] }}</span>
                <input type="hidden" name="tag" value="{{ $filters['tag'] }}">
            @endif
            <label class="flex items-center gap-2 text-xs text-white/50 cursor-pointer">
                <input type="checkbox" name="past" value="1" {{ $filters['past'] ? 'checked' : '' }} class="rounded text-blue-500"> Include past events
            </label>
            {{-- Task #6619 — default = active workspace only; toggle restores the cross-workspace aggregate. --}}
            <label class="flex items-center gap-2 text-xs text-white/50 cursor-pointer" title="Show calendars from every workspace, not just the active one">
                <input type="checkbox" name="ws" value="all" {{ ($filters['ws'] ?? '') === 'all' ? 'checked' : '' }} class="rounded text-blue-500"> All workspaces
            </label>
            <div class="ml-auto flex gap-2">
                <a href="{{ route('user.calendars.mine', array_filter(['view' => $view, 'ws' => $filters['ws'] ?? ''])) }}" class="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white transition">Reset</a>
                <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition">Apply</button>
            </div>
        </div>
    </form>

    {{-- ── Visual chip filters (calendars + tags) ────────────── --}}
    @if($calendars->isNotEmpty() || $availableTags->isNotEmpty())
    <div class="space-y-3">
        @if($calendars->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[11px] uppercase tracking-wide text-white/30 mr-1">Calendars</span>
            @foreach($calendars as $cal)
                @php
                    $isOwned = $ownedIds->contains($cal->id);
                    $isActive = (string) ($filters['calendar'] ?? '') === (string) $cal->id;
                    $accent = $cal->accent_color ?: '#3d6bff';
                @endphp
                <a href="{{ $linkTo(['calendar' => $isActive ? null : $cal->id]) }}"
                   class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border transition {{ $isActive ? 'border-blue-400/60 bg-blue-500/15 text-white' : 'border-white/10 text-white/60 hover:text-white hover:border-white/30' }}"
                   title="{{ $isActive ? 'Clear this filter' : 'Show only this calendar' }}">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $accent }}"></span>
                    {{ $cal->title }}
                    <span class="text-white/30">{{ $cal->events_count }} · {{ $isOwned ? 'owned' : 'following' }}</span>
                    @if($isActive)<i class="fas fa-xmark text-[10px] text-white/50"></i>@endif
                </a>
            @endforeach
        </div>
        @endif

        @if($availableTags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[11px] uppercase tracking-wide text-white/30 mr-1">Tags</span>
            @foreach($availableTags as $t)
                @php $tagActive = (string) ($filters['tag'] ?? '') === (string) $t; @endphp
                <a href="{{ $linkTo(['tag' => $tagActive ? null : $t]) }}"
                   class="text-[11px] px-2.5 py-1 rounded-full border transition {{ $tagActive ? 'border-blue-400/60 bg-blue-500/20 text-white' : 'border-white/10 bg-blue-500/5 text-blue-300 hover:bg-blue-500/15' }}">
                    #{{ $t }}@if($tagActive) <i class="fas fa-xmark text-[10px] ml-0.5"></i>@endif
                </a>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- ── Selected view ─────────────────────────────────────── --}}
    @if($view === 'month')
        @include('user.calendars.partials.calendar-month')
    @elseif($view === 'week' || $view === 'day')
        @include('user.calendars.partials.calendar-timegrid')
    @else
        @include('user.calendars.partials.calendar-agenda')
    @endif
</div>
@endsection
