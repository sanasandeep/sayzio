@extends('user.layouts.app')
@section('title', 'Calendar - ' . ($calendar->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
{{-- Shared "drop a pin to fill address + lat/lng" map picker (lazy-loads Leaflet itself). --}}
<script src="{{ asset('js/map-pin-picker.js') }}"></script>
<style>
    .mpp-map .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    html.light-mode .mpp-map .leaflet-container { background:#e6e9f0 !important; }
    .mpp-map .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    .mpp-map .leaflet-control-attribution a { color:#90acff !important; }
    .mpp-map .leaflet-control-zoom a { background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important; }
    .mpp-map .leaflet-control-zoom a:hover { background:#3d6bff !important; }
    .mpp-marker { width:30px; height:40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); }
    .mpp-marker svg { width:100%; height:100%; display:block; }
    [x-cloak]{ display:none !important; }
</style>

@php
    $inputClass = 'w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40';
    $labelClass = 'block text-sm font-medium text-white/60 mb-1';
@endphp

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $calendar->title }}</h1>
            <p class="text-xs text-white/40 mt-0.5">
                {{ $events->count() }} event{{ $events->count() === 1 ? '' : 's' }} &middot;
                {{ (int) $calendar->followers_count }} follower{{ (int) $calendar->followers_count === 1 ? '' : 's' }} &middot;
                <span class="{{ $calendar->is_public ? 'text-green-400' : 'text-amber-400' }}">{{ $calendar->is_public ? 'Public' : 'Private' }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $link->public_url ?? url('/' . $link->alias) }}" target="_blank" class="px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                <i class="fas fa-arrow-up-right-from-square mr-1"></i> View page
            </a>
            <a href="{{ route('user.calendars.mine') }}" class="px-4 py-2 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                <i class="fas fa-calendar-days mr-1"></i> My Calendar
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 text-sm bg-green-500/10 border border-green-500/20 text-green-300">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 text-sm bg-red-500/10 border border-red-500/20 text-red-300">{{ session('error') }}</div>
    @endif

    {{-- ── Calendar settings ─────────────────────────────────── --}}
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Calendar settings</h2>
        <form method="POST" action="{{ route('user.calendars.settings', $link->id) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Title</label>
                    <input type="text" name="title" value="{{ old('title', $calendar->title) }}" class="{{ $inputClass }}" required>
                    @error('title') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Default timezone</label>
                    <select name="timezone" class="{{ $inputClass }}">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $calendar->timezone) === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="{{ $labelClass }}">Description</label>
                <textarea name="description" rows="2" class="{{ $inputClass }}">{{ old('description', $calendar->description) }}</textarea>
            </div>
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-white/60">Accent</label>
                    <input type="color" name="accent_color" value="{{ old('accent_color', $calendar->accent_color ?: '#3d6bff') }}" class="h-9 w-14 border border-white/10 rounded-lg bg-transparent cursor-pointer">
                </div>
                <label class="flex items-center gap-2 text-sm text-white/60">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" {{ old('is_public', $calendar->is_public) ? 'checked' : '' }} class="rounded text-blue-500">
                    Public &amp; followable
                </label>
                <button type="submit" class="ml-auto px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Save settings</button>
            </div>
        </form>
    </div>

    {{-- ── Subscribe / sync ──────────────────────────────────── --}}
    <div class="glass rounded-2xl p-6" x-data="{ copied: false }">
        <h2 class="text-lg font-semibold text-white mb-1">Subscribe &amp; sync</h2>
        <p class="text-xs text-white/40 mb-4">Share this feed so followers can subscribe in Apple Calendar, Outlook or Google Calendar. New events sync automatically.</p>
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" readonly value="{{ $icsUrl }}" x-ref="ics" class="{{ $inputClass }} flex-1 min-w-[240px] font-mono text-xs">
            <button type="button" @click="navigator.clipboard.writeText($refs.ics.value); copied = true; setTimeout(() => copied = false, 1500)" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                <span x-show="!copied"><i class="fas fa-copy mr-1"></i> Copy ICS</span>
                <span x-show="copied" x-cloak class="text-green-400"><i class="fas fa-check mr-1"></i> Copied</span>
            </button>
            <a href="https://calendar.google.com/calendar/r?cid={{ urlencode($icsUrl) }}" target="_blank" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                <i class="fab fa-google mr-1"></i> Add to Google
            </a>
        </div>

        {{-- Two-way push sync to a connected Google account (plan-gated). --}}
        <div class="mt-4 pt-4 border-t border-white/10">
            @if(!$canSync)
                <div class="flex flex-wrap items-center gap-2 text-xs text-white/40">
                    <i class="fas fa-lock"></i>
                    <span>Push these events straight into your Google Calendar with two-way sync.</span>
                    <a href="{{ route('user.upgrade') }}" class="text-blue-300 hover:text-blue-200 font-medium">Upgrade to unlock</a>
                </div>
            @elseif(!$syncAccount)
                <div class="flex flex-wrap items-center gap-2 text-xs text-white/40">
                    <i class="fab fa-google"></i>
                    <span>Connect a Google Calendar (with sync enabled) to push these events automatically.</span>
                    <a href="{{ route('user.calendar.index') }}" class="text-blue-300 hover:text-blue-200 font-medium">Connect Google Calendar</a>
                </div>
            @else
                <form method="POST" action="{{ route('user.calendars.sync', $link->id) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-[11px] text-white/40 mb-1">From (optional)</label>
                        <input type="date" name="from" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="block text-[11px] text-white/40 mb-1">To (optional)</label>
                        <input type="date" name="to" class="{{ $inputClass }}">
                    </div>
                    <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">
                        <i class="fas fa-sync-alt mr-1"></i> Sync with {{ $syncAccount->providerLabel() }}
                    </button>
                    <span class="text-[11px] text-white/40">Two-way: pushes your events up and pulls your Google edits back in. Leave dates empty to sync the whole calendar.</span>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Add event ─────────────────────────────────────────── --}}
    <div class="glass rounded-2xl p-6" x-data="{ open: {{ $errors->any() && !$eventQuotaReached ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-white">Events</h2>
                {{-- Per-plan event allowance. Finite caps only (unlimited plans pass -1 and show nothing). --}}
                @if($showEventQuota)
                    <span class="inline-flex items-center gap-1.5 text-xs {{ $eventQuotaReached ? 'text-amber-300' : 'text-white/40' }}">
                        <i class="fas {{ $eventQuotaReached ? 'fa-triangle-exclamation' : 'fa-chart-simple' }}"></i>
                        {{ $eventsUsed }} / {{ $eventCap }} event{{ $eventCap === 1 ? '' : 's' }} used
                    </span>
                @endif
            </div>
            @if($eventQuotaReached)
                <a href="{{ route('user.upgrade') }}" class="px-4 py-2 rounded-xl text-sm font-semibold bg-amber-500/15 border border-amber-500/30 text-amber-200 hover:bg-amber-500/25">
                    <i class="fas fa-lock mr-1"></i> Event limit reached — Upgrade
                </a>
            @else
                <button type="button" @click="open = !open" class="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">
                    <i class="fas fa-plus mr-1"></i> Add event
                </button>
            @endif
        </div>

        @if($eventQuotaReached)
            <p class="mt-3 text-xs text-white/40">
                You've reached the {{ $eventCap }}-event limit for a calendar on your current plan.
                <a href="{{ route('user.upgrade') }}" class="text-blue-300 hover:text-blue-200 font-medium">Upgrade</a> to add more events.
            </p>
        @else
            <div x-show="open" x-cloak class="mt-4">
                <form method="POST" action="{{ route('user.calendars.events.store', $link->id) }}"
                      x-data="mapPinPicker({ address: '', lat: '', lng: '' })" class="space-y-4 border-t border-white/5 pt-4">
                    @csrf
                    @include('user.calendars.partials.event-fields', ['inputClass' => $inputClass, 'labelClass' => $labelClass, 'calendar' => $calendar, 'timezones' => $timezones, 'event' => null])
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="open = false" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Add event</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    {{-- ── Event list ────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse($events->sortBy('start_at') as $event)
            <div class="glass rounded-2xl p-5" x-data="{ editing: false }">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-white truncate">{{ $event->title }}</h3>
                            @if($event->all_day)<span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">All day</span>@endif
                        </div>
                        <p class="text-xs text-white/40 mt-1">
                            <i class="far fa-clock mr-1"></i>
                            {{ $event->start_at?->timezone($event->timezone ?: $calendar->effectiveTimezone())->format('D, M j, Y g:i A') }}
                            @if($event->end_at) &ndash; {{ $event->end_at->timezone($event->timezone ?: $calendar->effectiveTimezone())->format('g:i A') }} @endif
                            <span class="text-white/25">({{ $event->timezone ?: $calendar->effectiveTimezone() }})</span>
                        </p>
                        @if($event->location)<p class="text-xs text-white/40 mt-0.5"><i class="fas fa-location-dot mr-1"></i>{{ $event->location }}</p>@endif
                        @if(!empty($event->hashtags))
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($event->hashtags as $tag)<span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300">#{{ $tag }}</span>@endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" @click="editing = !editing" class="w-9 h-9 rounded-lg border border-white/10 text-white/60 hover:text-white hover:border-white/30" title="Edit"><i class="fas fa-pen text-xs"></i></button>
                        <form method="POST" action="{{ route('user.calendars.events.destroy', [$link->id, $event->id]) }}" onsubmit="return confirm('Delete this event?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-9 h-9 rounded-lg border border-white/10 text-white/60 hover:text-red-400 hover:border-red-400/30" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </div>
                </div>

                <div x-show="editing" x-cloak class="mt-4 border-t border-white/5 pt-4">
                    <form method="POST" action="{{ route('user.calendars.events.update', [$link->id, $event->id]) }}"
                          x-data="mapPinPicker({ address: {{ \Illuminate\Support\Js::from($event->location ?? '') }}, lat: {{ \Illuminate\Support\Js::from((string)($event->lat ?? '')) }}, lng: {{ \Illuminate\Support\Js::from((string)($event->lng ?? '')) }} })" class="space-y-4">
                        @csrf @method('PUT')
                        @include('user.calendars.partials.event-fields', ['inputClass' => $inputClass, 'labelClass' => $labelClass, 'calendar' => $calendar, 'timezones' => $timezones, 'event' => $event])
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="editing = false" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Save event</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="glass rounded-2xl p-10 text-center text-white/40">
                <i class="far fa-calendar-plus text-3xl mb-3 block"></i>
                No events yet. Add your first event above.
            </div>
        @endforelse
    </div>
</div>
@endsection
