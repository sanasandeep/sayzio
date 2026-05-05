@extends('user.layouts.app')
@section('title', 'Edit Event')

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

<style>
    [x-cloak] { display: none !important; }
    .ics-input {
        width: 100%;
        background-color: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 0.75rem;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: var(--text-primary);
        transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }
    .ics-input::placeholder { color: var(--text-faint); }
    .ics-input:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
        background-color: var(--bg-glass-input-focus);
        border-color: var(--accent);
        box-shadow: 0 0 0 2px var(--accent-glow);
    }
    .ics-label {
        display: block;
        font-size: 0.875rem;
        line-height: 1.25rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 0.375rem;
    }
    .ics-help {
        font-size: 0.75rem;
        line-height: 1rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }
    .ics-section {
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    .ics-section-head {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-subtle);
    }
    .ics-section-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.75rem;
        background-color: var(--c-primary-soft);
        color: var(--c-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        line-height: 1.5rem;
        flex-shrink: 0;
    }
    .ics-section-title {
        font-size: 1rem;
        line-height: 1.25;
        font-weight: 600;
        color: var(--text-primary);
    }
    .ics-section-sub {
        font-size: 0.75rem;
        line-height: 1rem;
        color: var(--text-muted);
        margin-top: 0.125rem;
    }
    .day-chip {
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        line-height: 1rem;
        font-weight: 600;
        border: 1px solid transparent;
        transition-property: all;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .day-chip-off {
        background: var(--bg-glass);
        border-color: var(--border-glass);
        color: var(--text-muted);
    }
    .day-chip-off:hover {
        border-color: var(--border-strong);
        color: var(--text-primary);
    }
    .day-chip-on {
        background-color: #8b5cf6;
        border-color: #8b5cf6;
        color: #ffffff;
        box-shadow: 0 10px 15px -3px rgba(139,92,246,0.20), 0 4px 6px -4px rgba(139,92,246,0.20);
    }
    .ics-freq-off {
        background: var(--bg-glass);
        border-color: var(--border-glass);
        color: var(--text-muted);
    }
    .ics-freq-off:hover {
        background: var(--bg-glass-hover);
        color: var(--text-primary);
    }
    .ics-freq-on {
        background-color: rgba(139,92,246,0.15);
        border-color: #8b5cf6;
        color: var(--c-primary);
    }
    html:not(.light-mode) .ics-freq-on {
        color: #ffffff;
    }
    .ics-tile {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 0.75rem;
    }
    .ics-tile:hover { background: var(--bg-glass-hover); }
    .ics-tile-strong {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 0.75rem;
    }
    .ics-pill {
        display: flex;
        align-items: stretch;
        background-color: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .ics-pill:focus-within {
        box-shadow: 0 0 0 2px var(--accent-glow);
        border-color: var(--accent);
    }
    .ics-pill > input {
        background: transparent;
        color: var(--text-primary);
        outline: none;
    }
    .ics-pill > .ics-pill-suffix {
        display: flex;
        align-items: center;
        padding: 0 1rem;
        font-size: 0.875rem;
        color: var(--text-muted);
        border-left: 1px solid var(--border-glass);
        background-color: var(--bg-glass);
    }
    .ics-empty-dashed {
        border: 1px dashed var(--border-glass);
        border-radius: 0.75rem;
        text-align: center;
        padding: 2rem 0;
        color: var(--text-muted);
        font-size: 0.875rem;
    }
    .ics-empty-dashed .ics-empty-icon {
        display: block;
        font-size: 1.5rem;
        color: var(--text-faint);
        margin-bottom: 0.5rem;
    }
</style>

<div class="max-w-3xl mx-auto pb-12">
    @include('user.partials.page-hero', [
        'title'    => 'Edit Event',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => 'fa-calendar',
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
            ['icon' => 'fa-calendar', 'text' => 'Event'],
        ],
    ])

    @if($errors->any())
        <div class="mb-5 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
            <div class="font-semibold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Please fix the highlighted fields below.</div>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-red-300/80">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                removeExtra: function (i) { this.extras.splice(i, 1); },
                summary: function () {
                    if (!this.freq) return 'This event happens once.';
                    var every = parseInt(document.querySelector('[name=recurrence_interval]')?.value || 1, 10);
                    var unit = this.freq === 'daily' ? 'day' : this.freq === 'weekly' ? 'week' : this.freq === 'monthly' ? 'month' : 'year';
                    var s = 'Repeats every ' + (every === 1 ? unit : every + ' ' + unit + 's');
                    if (this.freq === 'weekly' && this.byday.length) {
                        var names = { MO:'Mon', TU:'Tue', WE:'Wed', TH:'Thu', FR:'Fri', SA:'Sat', SU:'Sun' };
                        var ordered = ['MO','TU','WE','TH','FR','SA','SU'].filter(function (d) {
                            return this.byday.indexOf(d) >= 0;
                        }, this).map(function (d) { return names[d]; });
                        s += ' on ' + ordered.join(', ');
                    }
                    if (this.endMode === 'count') {
                        var n = document.querySelector('[name=recurrence_count]')?.value;
                        if (n) s += ' — ends after ' + n + ' time' + (n == 1 ? '' : 's');
                    } else if (this.endMode === 'until') {
                        var u = document.querySelector('[name=recurrence_until]')?.value;
                        if (u) s += ' — until ' + u;
                    }
                    return s + '.';
                }
            };
        });
    });
    </script>

    <div class="mb-6">
        @include('user.links.partials.aliases-card', ['link' => $link])
    </div>

    <form method="POST" action="{{ route('user.links.ics.update', $link) }}" x-data="icsEditForm">
        @csrf @method('PUT')

        {{-- ==================== Event details ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-info-circle"></i></div>
                <div>
                    <h2 class="ics-section-title">About the event</h2>
                    <p class="ics-section-sub">The basics — what it's called, where, and what it's about.</p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="ics-label">Event name <span class="text-red-400">*</span></label>
                    <input type="text" name="event_name" value="{{ old('event_name', $ics->event_name) }}" required
                           placeholder="e.g. Spring Product Launch" class="ics-input">
                    @error('event_name') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ics-label">What's it about? <span class="text-white/30 font-normal">(optional)</span></label>
                    <textarea name="description" rows="3" placeholder="A short description that will appear in calendar apps."
                              class="ics-input">{{ old('description', $ics->description) }}</textarea>
                </div>

                <div>
                    <label class="ics-label">Where is it? <span class="text-white/30 font-normal">(optional)</span></label>
                    <input type="text" name="location" value="{{ old('location', $ics->location) }}"
                           placeholder="e.g. Zoom, 123 Main St, or a venue name" class="ics-input">
                    <p class="ics-help">Calendar apps will turn addresses into a tappable map link.</p>
                </div>
            </div>
        </div>

        {{-- ==================== When ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <h2 class="ics-section-title">When is it?</h2>
                    <p class="ics-section-sub">Choose the date and time, or mark it as a full-day event.</p>
                </div>
            </div>

            <label class="ics-tile flex items-center gap-3 mb-4 p-3 cursor-pointer">
                <input type="hidden" name="all_day" value="0">
                <input type="checkbox" name="all_day" value="1" x-model="allDay"
                       class="w-4 h-4 rounded text-violet-500 focus:ring-violet-500/40">
                <div class="flex-1">
                    <div class="text-sm font-medium" style="color: var(--text-primary);">All-day event</div>
                    <div class="text-xs" style="color: var(--text-muted);">Use this for full-day events like conferences or holidays.</div>
                </div>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="ics-label">Starts <span class="text-red-400">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="start_date"
                           value="{{ old('start_date', $fmtDt($ics->start_date)) }}" required
                           class="ics-input">
                    @error('start_date') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ics-label">Ends <span class="text-red-400">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="end_date"
                           value="{{ old('end_date', $fmtDt($ics->end_date)) }}" required
                           class="ics-input">
                    @error('end_date') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4">
                <label class="ics-label">Time zone <span class="text-red-400">*</span></label>
                <select name="timezone" required class="ics-input">
                    @foreach($timezones as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', $ics->timezone ?: 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
                <p class="ics-help">Pick the time zone where the event takes place. Calendar apps will adjust automatically for each guest.</p>
            </div>
        </div>

        {{-- ==================== Repeat ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-redo"></i></div>
                <div>
                    <h2 class="ics-section-title">Does it repeat?</h2>
                    <p class="ics-section-sub">Set up a recurring schedule like weekly classes or monthly meetings.</p>
                </div>
            </div>

            <div>
                <label class="ics-label">How often?</label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    @foreach([
                        '' => ['label' => 'Just once', 'icon' => 'fa-calendar-day'],
                        'daily' => ['label' => 'Daily', 'icon' => 'fa-sun'],
                        'weekly' => ['label' => 'Weekly', 'icon' => 'fa-calendar-week'],
                        'monthly' => ['label' => 'Monthly', 'icon' => 'fa-calendar-alt'],
                        'yearly' => ['label' => 'Yearly', 'icon' => 'fa-calendar'],
                    ] as $val => $meta)
                        <button type="button" @click="freq = @js($val)"
                                :class="freq === @js($val) ? 'ics-freq-on' : 'ics-freq-off'"
                                class="flex flex-col items-center justify-center gap-1.5 px-3 py-3 rounded-xl border text-xs font-medium transition-all">
                            <i class="fas {{ $meta['icon'] }} text-base"></i>
                            {{ $meta['label'] }}
                        </button>
                    @endforeach
                </div>
                <input type="hidden" name="recurrence_freq" :value="freq">
            </div>

            <div x-show="freq" x-cloak class="mt-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="ics-label">Repeat every</label>
                        <div class="ics-pill">
                            <input type="number" name="recurrence_interval" min="1" max="365"
                                   value="{{ old('recurrence_interval', $ics->recurrence_interval ?: 1) }}"
                                   class="w-20 px-3 py-2.5 text-sm">
                            <span class="ics-pill-suffix"
                                  x-text="freq === 'daily' ? 'day(s)' : freq === 'weekly' ? 'week(s)' : freq === 'monthly' ? 'month(s)' : 'year(s)'"></span>
                        </div>
                        <p class="ics-help" x-text="freq === 'daily' ? 'e.g. every 2 days' : freq === 'weekly' ? 'e.g. every 2 weeks' : freq === 'monthly' ? 'e.g. every 3 months' : 'e.g. every year'"></p>
                    </div>
                    <div>
                        <label class="ics-label">When does it stop?</label>
                        <select x-model="endMode" class="ics-input">
                            <option value="none">Never — keeps going forever</option>
                            <option value="count">After a certain number of times</option>
                            <option value="until">On a specific date</option>
                        </select>
                    </div>
                </div>

                <div x-show="freq === 'weekly'" x-cloak>
                    <label class="ics-label">On which days?</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['MO' => 'M', 'TU' => 'T', 'WE' => 'W', 'TH' => 'T', 'FR' => 'F', 'SA' => 'S', 'SU' => 'S'] as $code => $letter)
                            <button type="button" @click="toggleDay('{{ $code }}')"
                                    :class="hasDay('{{ $code }}') ? 'day-chip-on' : 'day-chip-off'"
                                    class="day-chip"
                                    title="{{ ['MO'=>'Monday','TU'=>'Tuesday','WE'=>'Wednesday','TH'=>'Thursday','FR'=>'Friday','SA'=>'Saturday','SU'=>'Sunday'][$code] }}">{{ $letter }}</button>
                        @endforeach
                    </div>
                    <p class="ics-help">Tap the days you want — leave blank to repeat on the same day each week.</p>
                    <template x-for="d in byday" :key="d">
                        <input type="hidden" name="recurrence_byday[]" :value="d">
                    </template>
                </div>

                <div x-show="endMode === 'count'" x-cloak>
                    <label class="ics-label">Number of times to repeat</label>
                    <div class="ics-pill md:w-64">
                        <input type="number" name="recurrence_count" min="1" max="999"
                               value="{{ old('recurrence_count', $ics->recurrence_count) }}"
                               placeholder="e.g. 10"
                               class="flex-1 px-3 py-2.5 text-sm">
                        <span class="ics-pill-suffix">times</span>
                    </div>
                </div>
                <div x-show="endMode === 'until'" x-cloak>
                    <label class="ics-label">Last date</label>
                    <input type="date" name="recurrence_until"
                           value="{{ old('recurrence_until', $fmtDate($ics->recurrence_until)) }}"
                           class="ics-input md:w-64">
                </div>

                {{-- Live human-readable summary --}}
                <div class="flex items-center gap-3 p-3 rounded-xl bg-violet-500/10 border border-violet-500/20">
                    <i class="fas fa-info-circle text-violet-300"></i>
                    <span class="text-sm text-violet-100" x-text="summary()"></span>
                </div>
            </div>
        </div>

        {{-- ==================== Additional Schedules ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="flex-1">
                    <h2 class="ics-section-title">Extra dates</h2>
                    <p class="ics-section-sub">Add more dates that don't follow the regular pattern — like a special preview or a make-up day.</p>
                </div>
                <button type="button" @click="addExtra"
                        class="text-xs px-3 py-2 rounded-lg bg-violet-500/20 text-violet-200 hover:bg-violet-500/30 font-medium transition shrink-0">
                    <i class="fas fa-plus mr-1.5"></i>Add a date
                </button>
            </div>

            <template x-if="extras.length === 0">
                <div class="ics-empty-dashed">
                    <i class="far fa-calendar ics-empty-icon"></i>
                    No extra dates yet — most events don't need any.
                </div>
            </template>

            <div class="space-y-3">
                <template x-for="(ex, i) in extras" :key="i">
                    <div class="ics-tile-strong p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-wide font-semibold" style="color: var(--text-muted);" x-text="'Date #' + (i + 1)"></span>
                            <button type="button" @click="removeExtra(i)" class="text-red-400/70 hover:text-red-400 text-xs px-2 py-1 rounded hover:bg-red-500/10">
                                <i class="fas fa-trash mr-1"></i>Remove
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="ics-label text-xs">Starts</label>
                                <input :type="allDay ? 'date' : 'datetime-local'"
                                       :name="'extra_schedules[' + i + '][start]'"
                                       x-model="ex.start" class="ics-input">
                            </div>
                            <div>
                                <label class="ics-label text-xs">Ends</label>
                                <input :type="allDay ? 'date' : 'datetime-local'"
                                       :name="'extra_schedules[' + i + '][end]'"
                                       x-model="ex.end" class="ics-input">
                            </div>
                            <div>
                                <label class="ics-label text-xs">Name <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="text"
                                       :name="'extra_schedules[' + i + '][label]'"
                                       x-model="ex.label" placeholder="e.g. Day 2 Workshop"
                                       class="ics-input">
                            </div>
                            <div>
                                <label class="ics-label text-xs">Different location <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="text"
                                       :name="'extra_schedules[' + i + '][location]'"
                                       x-model="ex.location" placeholder="leave blank to use main location"
                                       class="ics-input">
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ==================== Organizer ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-user"></i></div>
                <div>
                    <h2 class="ics-section-title">Who's organizing?</h2>
                    <p class="ics-section-sub">Optional — shown to guests in their calendar app.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="ics-label">Your name <span class="text-white/30 font-normal">(optional)</span></label>
                    <input type="text" name="organizer" value="{{ old('organizer', $ics->organizer) }}"
                           placeholder="e.g. Acme Inc." class="ics-input">
                </div>
                <div>
                    <label class="ics-label">Reply-to email <span class="text-white/30 font-normal">(optional)</span></label>
                    <input type="email" name="organizer_email" value="{{ old('organizer_email', $ics->organizer_email) }}"
                           placeholder="hello@yourdomain.com" class="ics-input">
                </div>
                <div class="md:col-span-2">
                    <label class="ics-label">Event link <span class="text-white/30 font-normal">(optional)</span></label>
                    <input type="url" name="url" value="{{ old('url', $ics->url) }}"
                           placeholder="https://… (Zoom, registration page, etc.)" class="ics-input">
                </div>
            </div>
        </div>

        {{-- ==================== Protection & scheduling ==================== --}}
        @include('user.links.partials.protection-scheduling', ['link' => $link])

        {{-- ==================== Smart redirect rules ==================== --}}
        @include('user.links.partials.smart-rules', ['link' => $link])

        {{-- ==================== Link settings ==================== --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-link"></i></div>
                <div>
                    <h2 class="ics-section-title">Link settings</h2>
                    <p class="ics-section-sub">Control your shareable link and how it behaves when someone visits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="ics-label">Custom short link</label>
                    <div class="ics-pill">
                        <span class="ics-pill-suffix" style="border-left: 0; border-right: 1px solid var(--border-glass);">{{ parse_url($base, PHP_URL_HOST) }}/</span>
                        <input type="text" name="alias" value="{{ old('alias', $link->alias) }}" pattern="[A-Za-z0-9_\-]+"
                               class="flex-1 px-3 py-2.5 text-sm">
                    </div>
                    @error('alias') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    <p class="ics-help">Letters, numbers, dashes and underscores only.</p>
                </div>
                <div>
                    <label class="ics-label">Folder <span style="color: var(--text-faint);" class="font-normal">(optional)</span></label>
                    <select name="project_id" class="ics-input">
                        <option value="">No folder</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id', $link->project_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="ics-tile flex items-start gap-3 p-4 cursor-pointer">
                <input type="hidden" name="show_preview_page" value="0">
                <input type="checkbox" name="show_preview_page" value="1"
                       {{ old('show_preview_page', !empty($s['show_preview_page'])) ? 'checked' : '' }}
                       class="mt-0.5 w-4 h-4 rounded text-violet-500 focus:ring-violet-500/40">
                <div>
                    <div class="text-sm font-medium" style="color: var(--text-primary);">Show a preview page first</div>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">Visitors will see your event on a landing page before downloading. Helps with tracking and looks more professional.</p>
                </div>
            </label>

            {{-- ===== RSVPs ===== --}}
            <div class="ics-tile mt-4 p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="text-sm font-semibold" style="color: var(--text-primary);"><i class="fas fa-calendar-check mr-1.5" style="color: var(--c-primary);"></i>Collect RSVPs</div>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">Show an RSVP form on the event page so guests can confirm attendance.</p>
                    </div>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="hidden" name="rsvp_enabled" value="0">
                        <input type="checkbox" name="rsvp_enabled" value="1" class="sr-only peer"
                               {{ old('rsvp_enabled', !empty($s['rsvp_enabled'])) ? 'checked' : '' }}>
                        <div class="w-10 h-6 rounded-full relative transition peer-checked:bg-violet-500" style="background: var(--bg-glass-input-focus);">
                            <span class="absolute top-0.5 left-0.5 bg-white w-5 h-5 rounded-full peer-checked:translate-x-4 transition toggle-knob"></span>
                        </div>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs" style="color: var(--text-secondary);">
                    <label class="flex items-center gap-2"><input type="hidden" name="rsvp_allow_plus_ones" value="0"><input type="checkbox" name="rsvp_allow_plus_ones" value="1" {{ old('rsvp_allow_plus_ones', !empty($s['rsvp_allow_plus_ones'])) ? 'checked':'' }}> Allow +1s</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="rsvp_collect_phone" value="0"><input type="checkbox" name="rsvp_collect_phone" value="1" {{ old('rsvp_collect_phone', !empty($s['rsvp_collect_phone'])) ? 'checked':'' }}> Ask for phone</label>
                </div>
                @if(!empty($s['rsvp_enabled']))
                    <a href="{{ route('user.links.rsvps.index', $link) }}" class="inline-block mt-3 text-xs text-violet-500 hover:text-violet-400">
                        <i class="fas fa-list mr-1"></i> View guest list →
                    </a>
                @endif
            </div>

            {{-- ===== Push to connected calendar ===== --}}
            @php
                $calAccounts = \App\Modules\User\Models\CalendarAccount::where('user_id', auth()->id())
                    ->where('push_enabled', true)->orderBy('provider')->get();
            @endphp
            <div class="ics-tile mt-4 p-4">
                <div class="text-sm font-semibold mb-2" style="color: var(--text-primary);"><i class="fas fa-cloud-upload-alt mr-1.5" style="color: var(--c-primary);"></i>Push to my calendar</div>
                <p class="text-xs mb-3" style="color: var(--text-muted);">Save this event to one of your connected calendars. Updates and deletions stay in sync.</p>
                @if($calAccounts->isEmpty())
                    <p class="text-xs" style="color: var(--text-muted);">No calendars connected. <a href="{{ route('user.calendar.index') }}" class="text-violet-500 hover:text-violet-400">Connect one</a>.</p>
                @else
                    <select name="push_calendar_account_id" class="ics-input">
                        <option value="">— Don't push —</option>
                        @foreach($calAccounts as $a)
                            <option value="{{ $a->id }}" {{ (string)old('push_calendar_account_id', $s['push_calendar_account_id'] ?? '') === (string)$a->id ? 'selected' : '' }}>
                                {{ ucfirst($a->provider) }} · {{ $a->display_name ?: $a->external_account_id }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        {{-- ==================== Save bar ==================== --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('user.links.show', $link) }}" class="px-5 py-2.5 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-violet-500/20 transition">
                <i class="fas fa-check mr-1.5"></i>Save changes
            </button>
        </div>
    </form>
</div>
@endsection
