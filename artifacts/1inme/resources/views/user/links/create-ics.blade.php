@extends('user.layouts.app')
@section('title', 'Create Event')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create Event</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-violet-400 hover:underline">change type</a></p>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('icsCreateForm', function () {
            return {
                allDay: @json(old('all_day', false) ? true : false),
                freq: @json(old('recurrence_freq', '')),
                byday: @json(old('recurrence_byday', [])),
                endMode: @json(old('recurrence_count') ? 'count' : (old('recurrence_until') ? 'until' : 'none')),
                extras: @json(old('extra_schedules', [])),
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

    <form method="POST" action="{{ route('user.links.ics.store') }}" x-data="icsCreateForm">
        @csrf
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Event Name <span class="text-red-500">*</span></label>
                <input type="text" name="event_name" value="{{ old('event_name', $prefillTitle ?? '') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                @error('event_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Location</label>
                <input type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Conference Room A" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-white/70">
                <input type="hidden" name="all_day" value="0">
                <input type="checkbox" name="all_day" value="1" x-model="allDay"
                       class="rounded text-violet-400 focus:ring-violet-500/40">
                All-day event
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Starts <span class="text-red-500">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="start_date" value="{{ old('start_date') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                    @error('start_date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Ends <span class="text-red-500">*</span></label>
                    <input :type="allDay ? 'date' : 'datetime-local'" name="end_date" value="{{ old('end_date') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                    @error('end_date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Timezone <span class="text-red-500">*</span></label>
                <select name="timezone" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                    @foreach($timezones ?? ['UTC'] as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Organizer Name</label>
                    <input type="text" name="organizer" value="{{ old('organizer') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Organizer Email</label>
                    <input type="email" name="organizer_email" value="{{ old('organizer_email') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Event URL</label>
                <input type="url" name="url" value="{{ old('url') }}" placeholder="https://..." class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        {{-- Repeat / Recurrence --}}
        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
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
                               value="{{ old('recurrence_interval', 1) }}"
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
                       value="{{ old('recurrence_count') }}"
                       class="w-full md:w-48 border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
            <div x-show="freq && endMode === 'until'" x-cloak>
                <label class="block text-sm font-medium text-white/60 mb-1">End date</label>
                <input type="date" name="recurrence_until"
                       value="{{ old('recurrence_until') }}"
                       class="w-full md:w-48 border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        {{-- Multi-scheduling --}}
        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Additional Schedules</h2>
                    <p class="text-xs text-white/40 mt-0.5">Add more event instances. Each becomes its own VEVENT in the .ics file.</p>
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

        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
            <h2 class="text-lg font-semibold text-white">Link Settings</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Custom Alias</label>
                    <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated" minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}" maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}" pattern="[A-Za-z0-9_\-]+" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 mt-4 flex items-start gap-3">
            <input type="hidden" name="show_preview_page" value="0">
            <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                <input type="checkbox" name="show_preview_page" value="1" {{ old('show_preview_page') ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
            <div class="text-sm">
                <div class="text-white/80 font-medium">Show preview page before download</div>
                <p class="text-xs text-white/40 mt-0.5">Renders an event preview that fires marketing pixels and tracks visitor dwell time before the .ics file is delivered.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Create Event</button>
        </div>
    </form>
</div>
@endsection
