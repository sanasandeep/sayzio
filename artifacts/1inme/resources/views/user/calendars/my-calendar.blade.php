@extends('user.layouts.app')
@section('title', 'My Calendar')

@section('content')
@php
    use Illuminate\Support\Carbon;

    $inputClass = 'w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40';

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
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">My Calendar</h1>
            <p class="text-xs text-white/40 mt-0.5">Everything from the calendars you own and follow, in one place.</p>
        </div>
        <a href="{{ route('user.calendars.create') }}" class="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">
            <i class="fas fa-plus mr-1"></i> New calendar
        </a>
    </div>

    {{-- ── View switcher + period navigation ─────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex p-1 rounded-xl glass">
            @foreach($views as $key => $label)
                <a href="{{ $linkTo(['view' => $key, 'date' => $focusDate->format('Y-m-d')]) }}"
                   class="px-3.5 py-1.5 rounded-lg text-sm font-medium transition {{ $view === $key ? 'bg-blue-600 text-white' : 'text-white/60 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if($periodLabel)
        <div class="flex items-center gap-2">
            <a href="{{ $linkTo(['view' => $view, 'date' => $todayDate]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/60 hover:text-white hover:border-white/30">Today</a>
            <a href="{{ $linkTo(['view' => $view, 'date' => $prevDate]) }}" class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30"><i class="fas fa-chevron-left text-xs"></i></a>
            <span class="text-sm font-semibold text-white min-w-[10rem] text-center">{{ $periodLabel }}</span>
            <a href="{{ $linkTo(['view' => $view, 'date' => $nextDate]) }}" class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30"><i class="fas fa-chevron-right text-xs"></i></a>
        </div>
        @endif
    </div>

    {{-- ── Filters ───────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('user.calendars.mine') }}" class="glass rounded-2xl p-5">
        {{-- Carry view + focus date so applying filters doesn't reset them. --}}
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="hidden" name="date" value="{{ $focusDate->format('Y-m-d') }}">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
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
            <label class="flex items-center gap-2 text-xs text-white/50">
                <input type="checkbox" name="past" value="1" {{ $filters['past'] ? 'checked' : '' }} class="rounded text-blue-500"> Include past events
            </label>
            <div class="ml-auto flex gap-2">
                <a href="{{ route('user.calendars.mine', ['view' => $view]) }}" class="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white">Reset</a>
                <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Apply</button>
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
                    <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $accent }}"></span>
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
