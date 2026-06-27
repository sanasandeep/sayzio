@extends('user.layouts.app')
@section('title', 'My Calendar')

@section('content')
@php
    $inputClass = 'w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40';
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">My Calendar</h1>
            <p class="text-xs text-white/40 mt-0.5">Everything from the calendars you own and follow, in one agenda.</p>
        </div>
        <a href="{{ route('user.calendars.create') }}" class="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">
            <i class="fas fa-plus mr-1"></i> New calendar
        </a>
    </div>

    {{-- ── Filters ───────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('user.calendars.mine') }}" class="glass rounded-2xl p-5">
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
                <a href="{{ route('user.calendars.mine') }}" class="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white">Reset</a>
                <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Apply</button>
            </div>
        </div>
    </form>

    {{-- ── My calendars summary ──────────────────────────────── --}}
    @if($calendars->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach($calendars as $cal)
            @php $isOwned = $ownedIds->contains($cal->id); @endphp
            <span class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-white/10 text-white/60">
                <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $cal->accent_color ?: '#3d6bff' }}"></span>
                {{ $cal->title }}
                <span class="text-white/30">{{ $cal->events_count }} &middot; {{ $isOwned ? 'owned' : 'following' }}</span>
            </span>
        @endforeach
    </div>
    @endif

    {{-- ── Agenda ────────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse($events as $event)
            @php $tz = $event->timezone ?: ($event->calendar?->effectiveTimezone() ?? 'UTC'); @endphp
            <div class="glass rounded-2xl p-5 flex items-start gap-4">
                <div class="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2" style="background: {{ ($event->calendar?->accent_color ?: '#3d6bff') }}1a;">
                    <span class="text-[10px] uppercase tracking-wide text-white/50">{{ $event->start_at?->timezone($tz)->format('M') }}</span>
                    <span class="text-xl font-bold text-white leading-none">{{ $event->start_at?->timezone($tz)->format('j') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-white truncate">{{ $event->title }}</h3>
                        @if($event->all_day)<span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">All day</span>@endif
                    </div>
                    <p class="text-xs text-white/40 mt-1">
                        <i class="far fa-clock mr-1"></i>
                        {{ $event->start_at?->timezone($tz)->format('D, g:i A') }}
                        @if($event->end_at) &ndash; {{ $event->end_at->timezone($tz)->format('g:i A') }} @endif
                        @if($event->calendar)
                            <span class="text-white/25">&middot; {{ $event->calendar->title }}</span>
                        @endif
                    </p>
                    @if($event->location)<p class="text-xs text-white/40 mt-0.5"><i class="fas fa-location-dot mr-1"></i>{{ $event->location }}</p>@endif
                    @if(!empty($event->hashtags))
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach($event->hashtags as $tag)
                                <a href="{{ route('user.calendars.mine', array_merge(request()->query(), ['tag' => $tag])) }}" class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300 hover:bg-blue-500/20">#{{ $tag }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if($event->payment_url)
                    <a href="{{ $event->payment_url }}" target="_blank" rel="noopener" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                        <i class="fas fa-ticket mr-1"></i> Tickets
                    </a>
                @endif
            </div>
        @empty
            <div class="glass rounded-2xl p-10 text-center text-white/40">
                <i class="far fa-calendar text-3xl mb-3 block"></i>
                No events match. Follow a public calendar or create your own.
            </div>
        @endforelse
    </div>

    @if($events->hasPages())
        <div>{{ $events->links() }}</div>
    @endif
</div>
@endsection
