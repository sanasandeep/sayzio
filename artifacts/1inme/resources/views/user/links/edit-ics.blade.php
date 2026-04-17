@extends('user.layouts.app')
@section('title', 'Edit Event Invite')

@section('content')
@php
    $s = (array) ($link->settings ?? []);
    $extrasRaw = is_array($ics->extra_schedules) ? $ics->extra_schedules : [];
    $byday = $ics->recurrence_byday ? array_values(array_filter(array_map('trim', explode(',', $ics->recurrence_byday)))) : [];
    $fmtDt = function ($v) {
        if (!$v) return '';
        try { return (new \DateTime((string) $v))->format('Y-m-d\TH:i'); } catch (\Throwable $e) { return ''; }
    };
    $fmtDate = function ($v) {
        if (!$v) return '';
        try { return (new \DateTime((string) $v))->format('Y-m-d'); } catch (\Throwable $e) { return ''; }
    };
    $extrasJs = [];
    foreach ($extrasRaw as $x) {
        $extrasJs[] = [
            'start'    => $fmtDt($x['start'] ?? null),
            'end'      => $fmtDt($x['end'] ?? null),
            'label'    => $x['label'] ?? '',
            'location' => $x['location'] ?? '',
        ];
    }
    $startDtVal = old('start_date', $fmtDt($ics->start_date));
    $endDtVal = old('end_date', $fmtDt($ics->end_date));
    $endModeVal = $ics->recurrence_count ? 'count' : ($ics->recurrence_until ? 'until' : 'none');
    $base = rtrim(config('app.url', url('/')), '/');
@endphp

<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Edit Event Invite',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => 'fa-calendar',
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
            ['icon' => 'fa-calendar', 'text' => 'Event Invite'],
        ],
    ])

    <script>
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('icsEditForm', function () {
            return {
                allDay: @json((bool) $ics->all_day),
                freq: @json($ics->recurrence_freq ?? ''),
                byday: @json($byday),
                endMode: @json($endModeVal),
                extras: @json($extrasJs),
                hasDay: function (d) { return this.byday.indexOf(d) >= 0; },
                toggleDay: function (d) {
                    var i = this.byday.indexOf(d);
                    if (i >= 0) this.byday.splice(i, 1);
                    else this.byday.push(d);
                },
                addExtra: function () {
                    this.extras.push({ start: '', end: '', label: '', location: '' });
                },
                removeExtra: function (i) { this.extras.splice(i, 1); }
            };
        });
    });
    </script>

    <form method="POST" action="{{ route('user.links.ics.update', $link) }}" x-data="icsEditForm">
        @csrf @method('PUT')

        {{-- Event details --}}
        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Event Details</h2>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Event Name <span class="text-red-500">*</span></label>
                <input type="text" name="event_name" value="{{ old('event_name', $ics->event_name) }}" required
                       class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                @error('event_name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">{{ old('description', $ics->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Location</label>
                <input type="text" name="location" value="{{ old('location', $ics->location) }}" placeholder="e.g. Conference Room A or 123 Main St"
                       class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-white/70">
                <input type="hidden" name="all_day" value="0">
                <input type="checkbox" name="all_day" value="1" x-model="allDay"
                       class="rounded text-violet-400 focus:ring-violet-500/40">
                All-day event
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">
                        Starts <span class="text-red-500">*</span>
                    </label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="start_date"
                           value="{{ old('start_date', $fmtDt($ics->start_date)) }}" required
                           class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                    @error('start_date') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">
                        Ends <span class="text-red-500">*</span>
                    </label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="end_date"
                           value="{{ old('end_date', $fmtDt($ics->end_date)) }}" required
                           class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                    @error('end_date') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Timezone <span class="text-red-500">*</span></label>
                <select name="timezone" required
                        class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                    @foreach($timezones as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', $ics->timezone ?: 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Organizer Name</label>
                    <input type="text" name="organizer" value="{{ old('organizer', $ics->organizer) }}"
                           class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Organizer Email</label>
                    <input type="email" name="organizer_email" value="{{ old('organizer_email', $ics->organizer_email) }}"
                           class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Event URL</label>
                <input type="url" name="url" value="{{ old('url', $ics->url) }}" placeholder="https://..."
                       class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        {{-- Repeat / Recurrence --}}
        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Repeat <span class="text-xs font-normal text-white/40 ml-1">(optional)</span></h2>
                <span class="text-xs text-white/40">Generates an RRULE in the .ics file</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Frequency</label>
                    <select name="recurrence_freq" x-model="freq"
                            class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">Does not repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div x-show="freq" x-cloak>
                    <label class="block text-sm font-medium text-white/60 mb-1">Every</label>
                    <div class="flex items-stretch rounded-xl bg-white/5 border border-white/10 overflow-hidden">
                        <input type="number" name="recurrence_interval" min="1" max="365"
                               value="{{ old('recurrence_interval', $ics->recurrence_interval ?: 1) }}"
                               class="w-20 bg-transparent px-3 py-2 text-sm text-white outline-none">
                        <span class="flex items-center px-3 text-sm text-white/50 border-l border-white/10"
                              x-text="freq === 'daily' ? 'day(s)' : freq === 'weekly' ? 'week(s)' : freq === 'monthly' ? 'month(s)' : 'year(s)'"></span>
                    </div>
                </div>
                <div x-show="freq" x-cloak>
                    <label class="block text-sm font-medium text-white/60 mb-1">Ends</label>
                    <select x-model="endMode"
                            class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="none">Never</option>
                        <option value="count">After N occurrences</option>
                        <option value="until">On date</option>
                    </select>
                </div>
            </div>

            <div x-show="freq === 'weekly'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-2">Repeat on</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'] as $code => $name)
                        <button type="button" @click="toggleDay('{{ $code }}')"
                                :class="hasDay('{{ $code }}') ? 'border-violet-500 bg-violet-500/20 text-violet-200' : 'border-white/10 text-white/60 hover:bg-white/5'"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors">{{ $name }}</button>
                    @endforeach
                </div>
                <template x-for="d in byday" :key="d">
                    <input type="hidden" name="recurrence_byday[]" :value="d">
                </template>
            </div>

            <div x-show="freq && endMode === 'count'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1">Number of occurrences</label>
                <input type="number" name="recurrence_count" min="1" max="999"
                       value="{{ old('recurrence_count', $ics->recurrence_count) }}"
                       class="w-full md:w-48 border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
            <div x-show="freq && endMode === 'until'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1">End date</label>
                <input type="date" name="recurrence_until"
                       value="{{ old('recurrence_until', $fmtDate($ics->recurrence_until)) }}"
                       class="w-full md:w-48 border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        {{-- Multi-scheduling --}}
        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Additional Schedules</h2>
                    <p class="text-xs text-white/40 mt-0.5">Add more event instances. Each becomes its own VEVENT in the .ics file (use this for multi-session workshops, irregular dates, etc).</p>
                </div>
                <button type="button" @click="addExtra"
                        class="text-xs px-3 py-1.5 rounded-lg bg-violet-500/20 text-violet-300 hover:bg-violet-500/30">
                    <i class="fas fa-plus mr-1"></i> Add schedule
                </button>
            </div>

            <template x-if="extras.length === 0">
                <div class="text-center py-6 text-sm text-white/40 border border-dashed border-white/10 rounded-xl">
                    No additional schedules.
                </div>
            </template>

            <template x-for="(ex, i) in extras" :key="i">
                <div class="border border-white/10 rounded-xl p-4 bg-white/[0.02] space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-white/40" x-text="'Schedule #' + (i + 1)"></span>
                        <button type="button" @click="removeExtra(i)" class="text-red-400/70 hover:text-red-400 text-xs"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Starts</label>
                            <input :type="allDay ? 'date' : 'datetime-local'"
                                   :name="'extra_schedules[' + i + '][start]'"
                                   x-model="ex.start"
                                   class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Ends</label>
                            <input :type="allDay ? 'date' : 'datetime-local'"
                                   :name="'extra_schedules[' + i + '][end]'"
                                   x-model="ex.end"
                                   class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Label (optional)</label>
                            <input type="text"
                                   :name="'extra_schedules[' + i + '][label]'"
                                   x-model="ex.label"
                                   placeholder="e.g. Day 2 Workshop"
                                   class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Location override (optional)</label>
                            <input type="text"
                                   :name="'extra_schedules[' + i + '][location]'"
                                   x-model="ex.location"
                                   placeholder="defaults to main event location"
                                   class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Link settings --}}
        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Link Settings</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Custom Alias</label>
                    <div class="flex items-stretch rounded-xl bg-white/5 border border-white/10 overflow-hidden">
                        <span class="flex items-center px-3 text-sm text-white/40 bg-white/[0.03] border-r border-white/10">{{ parse_url($base, PHP_URL_HOST) }}/</span>
                        <input type="text" name="alias" value="{{ old('alias', $link->alias) }}" pattern="[A-Za-z0-9_\-]+"
                               class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white outline-none">
                    </div>
                    @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id"
                            class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id', $link->project_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="show_preview_page" value="0">
                <input type="checkbox" name="show_preview_page" value="1" {{ old('show_preview_page', !empty($s['show_preview_page'])) ? 'checked' : '' }}
                       class="mt-1 rounded text-violet-400 focus:ring-violet-500/40">
                <div class="text-sm">
                    <div class="text-white/80 font-medium">Show preview page before download</div>
                    <p class="text-xs text-white/40 mt-0.5">Render an event preview that fires marketing pixels and tracks visitor dwell time before delivering the .ics file.</p>
                </div>
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.show', $link) }}" class="px-5 py-2.5 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
