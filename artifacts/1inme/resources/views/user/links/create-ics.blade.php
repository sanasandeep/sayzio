@extends('user.layouts.app')
@section('title', 'Create Event')

@section('content')
@php
    $base = rtrim(config('app.url', url('/')), '/');
@endphp

<style>
    [x-cloak] { display: none !important; }
    .ics-input { width: 100%; background-color: var(--bg-glass-input); border: 1px solid var(--border-glass);
        border-radius: 0.75rem; padding: 0.625rem 1rem; font-size: 0.875rem; color: var(--text-primary); }
    .ics-input:focus { outline: 2px solid transparent; background-color: var(--bg-glass-input-focus);
        border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-glow); }
    .ics-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); margin-bottom: 0.375rem; }
    .ics-help { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .ics-section { border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.25rem; background-color: var(--bg-card); border: 1px solid var(--border-glass); }
    .ics-section-head { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-subtle); }
    .ics-section-icon { width: 2.25rem; height: 2.25rem; border-radius: 0.75rem; background-color: var(--c-primary-soft); color: var(--c-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ics-section-title { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
    .ics-section-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.125rem; }
    .day-chip { width: 2.75rem; height: 2.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; }
    .day-chip-off { background: var(--bg-glass); border-color: var(--border-glass); color: var(--text-muted); }
    .day-chip-on { background-color: #5c83ff; border-color: #5c83ff; color: #fff; }
    .ics-freq-off { background: var(--bg-glass); border-color: var(--border-glass); color: var(--text-muted); }
    .ics-freq-on { background-color: rgba(92,131,255,0.15); border-color: #5c83ff; color: #fff; }
    .ics-tile { background: var(--bg-glass); border: 1px solid var(--border-glass); border-radius: 0.75rem; }
    .ics-tile-strong { background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: 0.75rem; }
    .ics-pill { display: flex; align-items: stretch; background-color: var(--bg-glass-input); border: 1px solid var(--border-glass); border-radius: 0.75rem; overflow: hidden; }
    .ics-pill > input { background: transparent; color: var(--text-primary); outline: none; }
    .ics-pill > .ics-pill-suffix { display: flex; align-items: center; padding: 0 1rem; font-size: 0.875rem; color: var(--text-muted); border-left: 1px solid var(--border-glass); background-color: var(--bg-glass); }
    .sync-tile { border:1px solid var(--border-glass); border-radius:0.75rem; padding:0.85rem; cursor:pointer; transition:all .15s; }
    .sync-tile.is-on { border-color:#5c83ff; background:rgba(92,131,255,0.1); }
</style>

<div class="max-w-3xl mx-auto pb-12">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Create Event</h1>
            <p class="text-xs mt-0.5" style="color: var(--text-muted);">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-blue-400 hover:underline">change type</a></p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
            <div class="font-semibold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Please fix the highlighted fields below.</div>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-red-300/80">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <script>
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('icsCreateForm', function () {
            @php $icsSlots = old('slots') ?: [['start' => '', 'end' => '', 'label' => '', 'location' => '']]; @endphp
            return {
                allDay: @json(old('all_day', false) ? true : false),
                freq: @json(old('recurrence_freq', '')),
                byday: @json(old('recurrence_byday', [])),
                monthlyMode:    @json(old('monthly_mode', 'day_of_month')),
                monthlyOrdinal: @json((string) old('monthly_weekday_ordinal', '1')),
                yearlyMonth:    @json((int) old('yearly_month', 0)),
                endMode: @json(old('recurrence_count') ? 'count' : (old('recurrence_until') ? 'until' : 'none')),
                slots: @json($icsSlots),
                rsvpEnabled: @json(old('rsvp_enabled', false) ? true : false),
                syncMode: @json(old('calendar_sync_mode', 'off')),
                questions: @json(old('rsvp_questions', [])),
                hasDay: function (d) { return this.byday.indexOf(d) >= 0; },
                toggleDay: function (d) {
                    var i = this.byday.indexOf(d);
                    if (i >= 0) this.byday.splice(i, 1); else this.byday.push(d);
                },
                addSlot: function () { this.slots.push({ start: '', end: '', label: '', location: '' }); },
                removeSlot: function (i) {
                    if (this.slots.length <= 1) { this.slots[0] = { start: '', end: '', label: '', location: '' }; return; }
                    this.slots.splice(i, 1);
                },
                addQuestion: function () { if (this.questions.length < 10) this.questions.push({ label: '', type: 'text', required: false, options: '' }); },
                removeQuestion: function (i) { this.questions.splice(i, 1); },
                // Task #5023 — agenda
                agendaItems: @json(old('agenda', [])),
                addAgendaItem: function () {
                    if (this.agendaItems.length >= 100) return;
                    this.agendaItems.push({ time: '', end_time: '', title: '', description: '', day: '' });
                },
                removeAgendaItem: function (i) { this.agendaItems.splice(i, 1); },
                // Task #5023 — documents
                documents: @json(old('documents', [])),
                showFilePicker: false,
                pickerFiles: [],
                pickerLoading: false,
                pickerPage: 1,
                pickerHasMore: false,
                uploadingDoc: false,
                loadPickerFiles: function (page) {
                    var self = this;
                    self.pickerLoading = true;
                    self.pickerPage = page || 1;
                    fetch('/user/files?page=' + self.pickerPage, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            self.pickerFiles = d.files || [];
                            self.pickerHasMore = (d.pagination || {}).current_page < (d.pagination || {}).last_page;
                            self.pickerLoading = false;
                        }).catch(function () { self.pickerLoading = false; });
                },
                openFilePicker: function () {
                    this.showFilePicker = true;
                    if (!this.pickerFiles.length) this.loadPickerFiles(1);
                },
                pickFile: function (file) {
                    var already = this.documents.some(function (d) { return d.file_id == file.id; });
                    if (!already && this.documents.length < 20) {
                        this.documents.push({ file_id: file.id, label: file.original_name || file.filename, filename: file.filename, size_bytes: file.size_bytes, mime: file.mime_type });
                    }
                    this.showFilePicker = false;
                },
                uploadDocument: function (event) {
                    var self = this;
                    var file = event.target.files[0];
                    if (!file) return;
                    if (self.documents.length >= 20) { alert('Maximum 20 documents per event.'); return; }
                    self.uploadingDoc = true;
                    var fd = new FormData();
                    fd.append('file', file);
                    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    fetch('/user/files/upload', { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.success && d.file) {
                                self.documents.push({ file_id: d.file.id, label: d.file.original_name || d.file.filename, filename: d.file.filename, size_bytes: d.file.size_bytes, mime: d.file.mime_type });
                            } else { alert((d.message || d.error) || 'Upload failed.'); }
                            self.uploadingDoc = false;
                        }).catch(function () { self.uploadingDoc = false; alert('Upload failed.'); });
                    event.target.value = '';
                },
                removeDocument: function (i) { this.documents.splice(i, 1); },
                pickFreq: function (v) {
                    this.freq = v;
                    if (v === 'weekdays') this.byday = ['MO','TU','WE','TH','FR'];
                },
                summary: function () {
                    if (!this.freq) return 'This event happens once.';
                    if (this.freq === 'weekdays') return 'Repeats every weekday (Mon–Fri).';
                    var every = parseInt(document.querySelector('[name=recurrence_interval]')?.value || 1, 10);
                    var unit = this.freq === 'daily' ? 'day' : this.freq === 'weekly' ? 'week' : this.freq === 'monthly' ? 'month' : 'year';
                    var s = 'Repeats every ' + (every === 1 ? unit : every + ' ' + unit + 's');
                    if (this.freq === 'weekly' && this.byday.length) {
                        var names = { MO:'Mon', TU:'Tue', WE:'Wed', TH:'Thu', FR:'Fri', SA:'Sat', SU:'Sun' };
                        var ordered = ['MO','TU','WE','TH','FR','SA','SU'].filter(function (d) { return this.byday.indexOf(d) >= 0; }, this).map(function (d) { return names[d]; });
                        s += ' on ' + ordered.join(', ');
                    }
                    if (this.freq === 'monthly' && this.monthlyMode === 'weekday_ordinal') {
                        var ord = { '1':'first', '2':'second', '3':'third', '4':'fourth', '-1':'last' }[this.monthlyOrdinal] || 'first';
                        s += ' — on the ' + ord + ' weekday';
                    }
                    return s + '.';
                }
            };
        });
    });
    </script>

    <form method="POST" action="{{ route('user.links.ics.store') }}" x-data="icsCreateForm">
        @csrf

        {{-- Event details --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-info-circle"></i></div>
                <div><h2 class="ics-section-title">About the event</h2><p class="ics-section-sub">The basics — what it's called, where, and what it's about.</p></div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="ics-label">Event name <span class="text-red-400">*</span></label>
                    <input type="text" name="event_name" value="{{ old('event_name') }}" required placeholder="e.g. Spring Product Launch" class="ics-input">
                    @error('event_name') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ics-label">What's it about? <span class="text-white/30 font-normal">(optional)</span></label>
                    <textarea name="description" rows="3" placeholder="A short description that will appear in calendar apps." class="ics-input">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="ics-label">Where is it? <span class="text-white/30 font-normal">(optional)</span></label>
                    <input type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Zoom, 123 Main St, or a venue name" class="ics-input">
                </div>
            </div>
        </div>

        {{-- When --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-clock"></i></div>
                <div><h2 class="ics-section-title">When is it?</h2><p class="ics-section-sub">Set the primary date and time. Cross-midnight events (e.g. 9pm – 1am) work too.</p></div>
            </div>
            <label class="ics-tile flex items-center gap-3 mb-4 p-3 cursor-pointer">
                <input type="hidden" name="all_day" value="0">
                <input type="checkbox" name="all_day" value="1" x-model="allDay" class="w-4 h-4 rounded text-blue-500">
                <div class="flex-1">
                    <div class="text-sm font-medium" style="color: var(--text-primary);">All-day event</div>
                    <div class="text-xs" style="color: var(--text-muted);">Use for full-day events like conferences or holidays.</div>
                </div>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="ics-label">Starts <span class="text-red-400">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="start_date" value="{{ old('start_date') }}" required class="ics-input">
                    @error('start_date') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ics-label">Ends <span class="text-red-400">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="end_date" value="{{ old('end_date') }}" required class="ics-input">
                    @error('end_date') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4">
                <label class="ics-label">Time zone <span class="text-red-400">*</span></label>
                <select name="timezone" required class="ics-input">
                    @foreach($timezones as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Repeat --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-redo"></i></div>
                <div><h2 class="ics-section-title">Does it repeat?</h2><p class="ics-section-sub">Set up a recurring schedule like daily standups or monthly meetings.</p></div>
            </div>
            <div>
                <label class="ics-label">How often?</label>
                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2">
                    @foreach([
                        '' => ['label' => 'Just once', 'icon' => 'fa-calendar-day'],
                        'daily' => ['label' => 'Daily', 'icon' => 'fa-sun'],
                        'weekdays' => ['label' => 'Weekdays', 'icon' => 'fa-business-time'],
                        'weekly' => ['label' => 'Weekly', 'icon' => 'fa-calendar-week'],
                        'monthly' => ['label' => 'Monthly', 'icon' => 'fa-calendar-alt'],
                        'yearly' => ['label' => 'Yearly', 'icon' => 'fa-calendar'],
                    ] as $val => $meta)
                        <button type="button" @click="pickFreq(@js($val))"
                                :class="freq === @js($val) ? 'ics-freq-on' : 'ics-freq-off'"
                                class="flex flex-col items-center justify-center gap-1.5 px-3 py-3 rounded-xl border text-xs font-medium">
                            <i class="fas {{ $meta['icon'] }} text-base"></i>{{ $meta['label'] }}
                        </button>
                    @endforeach
                </div>
                <input type="hidden" name="recurrence_freq" :value="freq">
            </div>

            <div x-show="freq && freq !== 'weekdays'" x-cloak class="mt-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="ics-label">Repeat every</label>
                        <div class="ics-pill">
                            <input type="number" name="recurrence_interval" min="1" max="365" value="{{ old('recurrence_interval', 1) }}" class="w-20 px-3 py-2.5 text-sm">
                            <span class="ics-pill-suffix" x-text="freq === 'daily' ? 'day(s)' : freq === 'weekly' ? 'week(s)' : freq === 'monthly' ? 'month(s)' : 'year(s)'"></span>
                        </div>
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
                            <button type="button" @click="toggleDay('{{ $code }}')" :class="hasDay('{{ $code }}') ? 'day-chip-on' : 'day-chip-off'" class="day-chip">{{ $letter }}</button>
                        @endforeach
                    </div>
                    <template x-for="d in byday" :key="d"><input type="hidden" name="recurrence_byday[]" :value="d"></template>
                </div>

                <div x-show="freq === 'monthly'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="ics-label">Monthly pattern</label>
                        <select name="monthly_mode" x-model="monthlyMode" class="ics-input">
                            <option value="day_of_month">On the same day each month</option>
                            <option value="weekday_ordinal">On a weekday position (e.g. 2nd Tuesday)</option>
                        </select>
                    </div>
                    <div x-show="monthlyMode === 'weekday_ordinal'" x-cloak>
                        <label class="ics-label">Which one?</label>
                        <select name="monthly_weekday_ordinal" x-model="monthlyOrdinal" class="ics-input">
                            <option value="1">First</option><option value="2">Second</option>
                            <option value="3">Third</option><option value="4">Fourth</option>
                            <option value="-1">Last</option>
                        </select>
                    </div>
                </div>

                <div x-show="freq === 'yearly'" x-cloak>
                    <label class="ics-label">Which month?</label>
                    <select name="yearly_month" x-model.number="yearlyMonth" class="ics-input md:w-64">
                        <option value="0">— Same month as the start date —</option>
                        @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $n => $name)
                            <option value="{{ $n }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="endMode === 'count'" x-cloak>
                    <label class="ics-label">Number of times to repeat</label>
                    <div class="ics-pill md:w-64">
                        <input type="number" name="recurrence_count" min="1" max="999" value="{{ old('recurrence_count') }}" placeholder="e.g. 10" class="flex-1 px-3 py-2.5 text-sm">
                        <span class="ics-pill-suffix">times</span>
                    </div>
                </div>
                <div x-show="endMode === 'until'" x-cloak>
                    <label class="ics-label">Last date</label>
                    <input type="date" name="recurrence_until" value="{{ old('recurrence_until') }}" class="ics-input md:w-64">
                </div>
            </div>

            <div x-show="freq" x-cloak class="mt-5 flex items-center gap-3 p-3 rounded-xl bg-blue-500/10 border border-blue-500/20">
                <i class="fas fa-info-circle text-blue-300"></i>
                <span class="text-sm text-blue-100" x-text="summary()"></span>
            </div>
        </div>

        {{-- Time slots --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-layer-group"></i></div>
                <div class="flex-1">
                    <h2 class="ics-section-title">Time slots</h2>
                    <p class="ics-section-sub">Each slot becomes its own VEVENT in the .ics file. The first slot drives the recurrence rule.</p>
                </div>
                <button type="button" @click="addSlot" class="text-xs px-3 py-2 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 font-medium">
                    <i class="fas fa-plus mr-1.5"></i>Add slot
                </button>
            </div>
            <div class="space-y-3">
                <template x-for="(ex, i) in slots" :key="i">
                    <div class="ics-tile-strong p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-wide font-semibold" style="color: var(--text-muted);" x-text="(i === 0 ? 'Primary slot' : 'Slot #' + (i + 1))"></span>
                            <button type="button" @click="removeSlot(i)" class="text-red-400/70 hover:text-red-400 text-xs px-2 py-1 rounded hover:bg-red-500/10"><i class="fas fa-trash mr-1"></i>Remove</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div><label class="ics-label text-xs">Starts</label>
                                <input :type="allDay ? 'date' : 'datetime-local'" :name="'slots[' + i + '][start]'" x-model="ex.start" class="ics-input"></div>
                            <div><label class="ics-label text-xs">Ends</label>
                                <input :type="allDay ? 'date' : 'datetime-local'" :name="'slots[' + i + '][end]'" x-model="ex.end" class="ics-input"></div>
                            <div><label class="ics-label text-xs">Name <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="text" :name="'slots[' + i + '][label]'" x-model="ex.label" placeholder="e.g. Workshop" class="ics-input"></div>
                            <div><label class="ics-label text-xs">Different location <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="text" :name="'slots[' + i + '][location]'" x-model="ex.location" placeholder="leave blank to use main location" class="ics-input"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Task #5023: Agenda --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-list-ul"></i></div>
                <div><h2 class="ics-section-title">Agenda</h2><p class="ics-section-sub">Optional — add a schedule of sessions or activities. Shown on your public event page.</p></div>
            </div>
            <template x-if="agendaItems.length === 0">
                <p class="text-xs mb-3" style="color: var(--text-muted);">No agenda items yet. Add sessions, talks, or activities below.</p>
            </template>
            <div class="space-y-2 mb-3">
                <template x-for="(item, i) in agendaItems" :key="i">
                    <div class="ics-tile-strong p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide" style="color: var(--text-muted);" x-text="'Item #' + (i + 1)"></span>
                            <button type="button" @click="removeAgendaItem(i)" class="text-red-400/70 hover:text-red-400 text-xs px-2 py-1 rounded hover:bg-red-500/10"><i class="fas fa-trash mr-1"></i>Remove</button>
                        </div>
                        <div>
                            <label class="ics-label text-xs">Title <span class="text-red-400">*</span></label>
                            <input type="text" :name="'agenda[' + i + '][title]'" x-model="item.title" placeholder="e.g. Opening Keynote" class="ics-input" maxlength="255">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <div>
                                <label class="ics-label text-xs">Start time <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="time" :name="'agenda[' + i + '][time]'" x-model="item.time" class="ics-input">
                            </div>
                            <div>
                                <label class="ics-label text-xs">End time <span style="color: var(--text-faint);">(optional)</span></label>
                                <input type="time" :name="'agenda[' + i + '][end_time]'" x-model="item.end_time" class="ics-input">
                            </div>
                            <div>
                                <label class="ics-label text-xs">Day # <span style="color: var(--text-faint);">(multi-day only)</span></label>
                                <input type="number" :name="'agenda[' + i + '][day]'" x-model="item.day" min="1" max="99" placeholder="e.g. 1, 2" class="ics-input">
                            </div>
                        </div>
                        <div>
                            <label class="ics-label text-xs">Description <span style="color: var(--text-faint);">(optional)</span></label>
                            <textarea :name="'agenda[' + i + '][description]'" x-model="item.description" rows="2" class="ics-input" placeholder="A brief description of this session" maxlength="2000"></textarea>
                        </div>
                    </div>
                </template>
            </div>
            <button type="button" @click="addAgendaItem()" class="text-sm px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 border border-blue-500/20">
                <i class="fas fa-plus mr-1.5"></i>Add agenda item
            </button>
        </div>

        {{-- Task #5023: Documents --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-file-download"></i></div>
                <div><h2 class="ics-section-title">Documents</h2><p class="ics-section-sub">Optional — attach PDFs, slides, or other files for attendees to download. Max 20 files.</p></div>
            </div>
            <template x-if="documents.length === 0 && !showFilePicker">
                <p class="text-xs mb-3" style="color: var(--text-muted);">No documents attached yet.</p>
            </template>
            <div class="space-y-2 mb-3">
                <template x-for="(doc, i) in documents" :key="doc.file_id">
                    <div class="ics-tile-strong p-3 flex items-center gap-3">
                        <input type="hidden" :name="'documents[' + i + '][file_id]'" :value="doc.file_id">
                        <input type="hidden" :name="'documents[' + i + '][filename]'" :value="doc.filename">
                        <input type="hidden" :name="'documents[' + i + '][size_bytes]'" :value="doc.size_bytes">
                        <input type="hidden" :name="'documents[' + i + '][mime]'" :value="doc.mime">
                        <i class="fas fa-file-alt text-lg flex-shrink-0" style="color: var(--c-primary); width: 1.2rem; text-align: center;"></i>
                        <div class="flex-1 min-w-0">
                            <input type="text" :name="'documents[' + i + '][label]'" x-model="doc.label" placeholder="Display label" class="ics-input py-1.5 text-sm" maxlength="255">
                            <div class="text-xs mt-0.5" style="color: var(--text-muted);" x-text="doc.filename"></div>
                        </div>
                        <button type="button" @click="removeDocument(i)" class="text-red-400/70 hover:text-red-400 flex-shrink-0 text-xs px-2 py-1 rounded hover:bg-red-500/10"><i class="fas fa-times"></i></button>
                    </div>
                </template>
            </div>
            {{-- File picker panel --}}
            <div x-show="showFilePicker" x-cloak class="ics-tile p-3 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold" style="color: var(--text-secondary);">Pick from your Files</span>
                    <button type="button" @click="showFilePicker = false" class="text-xs" style="color: var(--text-muted);"><i class="fas fa-times"></i></button>
                </div>
                <div x-show="pickerLoading" class="text-xs py-3 text-center" style="color: var(--text-muted);">Loading…</div>
                <div x-show="!pickerLoading && !pickerFiles.length" class="text-xs py-3 text-center" style="color: var(--text-muted);">No files found. Upload a file first.</div>
                <div class="grid grid-cols-1 gap-1 max-h-48 overflow-y-auto">
                    <template x-for="file in pickerFiles" :key="file.id">
                        <button type="button" @click="pickFile(file)" class="flex items-center gap-2 text-left p-2 rounded hover:bg-white/5 w-full">
                            <i class="fas fa-file-alt flex-shrink-0" style="color: var(--c-primary); width: 1rem;"></i>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs truncate" style="color: var(--text-primary);" x-text="file.original_name || file.filename"></div>
                                <div class="text-xs" style="color: var(--text-muted);" x-text="file.size_human"></div>
                            </div>
                        </button>
                    </template>
                </div>
                <div x-show="pickerHasMore && !pickerLoading" class="mt-2 text-center">
                    <button type="button" @click="loadPickerFiles(pickerPage + 1)" class="text-xs text-blue-400 hover:text-blue-300">Load more</button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openFilePicker()" x-show="!showFilePicker" class="text-sm px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 border border-blue-500/20">
                    <i class="fas fa-folder-open mr-1.5"></i>Pick from Files
                </button>
                <label class="cursor-pointer text-sm px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 border border-blue-500/20" :class="uploadingDoc ? 'opacity-60 pointer-events-none' : ''">
                    <span x-show="!uploadingDoc"><i class="fas fa-upload mr-1.5"></i>Upload file</span>
                    <span x-show="uploadingDoc"><i class="fas fa-spinner fa-spin mr-1.5"></i>Uploading…</span>
                    <input type="file" class="sr-only" @change="uploadDocument($event)" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
                </label>
            </div>
        </div>

        {{-- Organizer --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-user"></i></div>
                <div><h2 class="ics-section-title">Who's organizing?</h2><p class="ics-section-sub">Optional — shown to guests in their calendar app.</p></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="ics-label">Your name</label><input type="text" name="organizer" value="{{ old('organizer') }}" class="ics-input"></div>
                <div><label class="ics-label">Reply-to email</label><input type="email" name="organizer_email" value="{{ old('organizer_email') }}" class="ics-input"></div>
                <div class="md:col-span-2"><label class="ics-label">Event link</label><input type="url" name="url" value="{{ old('url') }}" placeholder="https://… (Zoom, registration, etc.)" class="ics-input"></div>
            </div>
        </div>

        {{-- Link settings --}}
        <div class="ics-section">
            <div class="ics-section-head">
                <div class="ics-section-icon"><i class="fas fa-link"></i></div>
                <div><h2 class="ics-section-title">Link settings</h2><p class="ics-section-sub">Control your shareable link.</p></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @include('user.links.partials.alias-checker')
                <div x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
                    <label class="ics-label">Custom short link</label>
                    <div class="ics-pill">
                        <span class="ics-pill-suffix" style="border-left:0; border-right:1px solid var(--border-glass);">{{ \App\Modules\Common\Support\PlatformHosts::currentRequestHost() ?: \App\Modules\Common\Support\PlatformHosts::primary() }}/</span>
                        <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}"
                               minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
                               maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
                               pattern="[A-Za-z0-9_\-]+" placeholder="auto"
                               autocomplete="off" spellcheck="false"
                               @input.debounce.400ms="check($event.target.value)"
                               class="flex-1 px-3 py-2.5 text-sm">
                        <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
                            <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-white/40 text-sm"></i>
                            <i x-show="state === 'available'" class="fas fa-circle-check text-emerald-400 text-sm"></i>
                            <i x-show="isError" class="fas fa-circle-xmark text-red-400 text-sm"></i>
                        </span>
                    </div>
                    <p aria-live="polite" x-show="message && state && state !== 'empty'" x-cloak
                       class="text-xs mt-1.5"
                       :class="state === 'available' ? 'text-emerald-400' : (isError ? 'text-red-400' : 'text-white/40')"
                       x-text="message"></p>
                    <p class="text-xs mt-1.5" style="color: var(--text-faint);">
                        <i class="fas fa-info-circle mr-1"></i>{{ ($aliasLimits ?? ['min'=>3])['min'] }}–{{ ($aliasLimits ?? ['max'=>50])['max'] }} characters · letters, numbers, hyphens &amp; underscores
                    </p>
                    @error('alias') <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ics-label">Folder <span style="color: var(--text-faint);">(optional)</span></label>
                    <select name="project_id" class="ics-input">
                        <option value="">No folder</option>
                        @foreach($projects as $p)<option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    @include('user.links.partials.visibility-field', ['visInputClass' => 'ics-input'])
                </div>
            </div>

            <label class="ics-tile flex items-start gap-3 p-4 cursor-pointer mb-4">
                <input type="hidden" name="show_preview_page" value="0">
                <input type="checkbox" name="show_preview_page" value="1" {{ old('show_preview_page') ? 'checked' : '' }} class="mt-0.5 w-4 h-4 rounded text-blue-500">
                <div>
                    <div class="text-sm font-medium" style="color: var(--text-primary);">Show a preview page first</div>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">Visitors see your event on a landing page before downloading.</p>
                </div>
            </label>

            <div class="ics-tile p-4 mb-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="text-sm font-semibold" style="color: var(--text-primary);"><i class="fas fa-calendar-check mr-1.5" style="color: var(--c-primary);"></i>Collect RSVPs</div>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">Show an RSVP form on the event page.</p>
                    </div>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="hidden" name="rsvp_enabled" value="0">
                        <input type="checkbox" name="rsvp_enabled" value="1" class="sr-only peer" x-model="rsvpEnabled">
                        <div class="w-10 h-6 rounded-full relative peer-checked:bg-blue-500" style="background: var(--bg-glass-input-focus);">
                            <span class="absolute top-0.5 left-0.5 bg-white w-5 h-5 rounded-full peer-checked:translate-x-4 transition"></span>
                        </div>
                    </label>
                </div>
                <div x-show="rsvpEnabled" x-cloak class="text-xs" style="color: var(--text-muted);">
                    Save the event first — capacity, deadlines, and custom questions can be configured on the next screen.
                </div>
            </div>

            {{-- Calendar sync --}}
            <div class="ics-tile p-4">
                <div class="text-sm font-semibold mb-2" style="color: var(--text-primary);"><i class="fas fa-cloud-upload-alt mr-1.5" style="color: var(--c-primary);"></i>Calendar sync</div>
                <p class="text-xs mb-3" style="color: var(--text-muted);">Choose how this event interacts with your connected calendars.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                    @foreach([
                        'off' => ['Don\'t sync', 'Just create the .ics file.', 'fa-circle-xmark'],
                        'one_time' => ['Push once', 'Save it now. Future edits won\'t auto-update.', 'fa-arrow-up-from-bracket'],
                        'keep_in_sync' => ['Keep in sync', 'Push now and re-sync on every save.', 'fa-rotate'],
                    ] as $mode => $meta)
                        <label class="sync-tile" :class="syncMode === @js($mode) ? 'is-on' : ''">
                            <input type="radio" name="calendar_sync_mode" value="{{ $mode }}" x-model="syncMode" class="sr-only">
                            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);"><i class="fas {{ $meta[2] }} mr-1.5"></i>{{ $meta[0] }}</div>
                            <p class="text-xs" style="color: var(--text-muted);">{{ $meta[1] }}</p>
                        </label>
                    @endforeach
                </div>

                <div x-show="syncMode !== 'off'" x-cloak>
                    @if($calAccounts->isEmpty())
                        <p class="text-xs" style="color: #f59e0b">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            No calendars connected. <a href="{{ route('user.calendar.index') }}" class="text-blue-400 hover:text-blue-300">Connect one</a> to use sync.
                        </p>
                    @else
                        <label class="ics-label">Push to which calendar?</label>
                        <select name="push_calendar_account_id" class="ics-input">
                            <option value="">— pick a calendar —</option>
                            @foreach($calAccounts as $a)
                                <option value="{{ $a->id }}" {{ (string)old('push_calendar_account_id') === (string)$a->id ? 'selected' : '' }}>
                                    {{ ucfirst($a->provider) }} · {{ $a->display_name ?: $a->external_account_id }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-xl">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-blue-500/20">
                <i class="fas fa-check mr-1.5"></i>Create event
            </button>
        </div>
    </form>
</div>
@endsection
