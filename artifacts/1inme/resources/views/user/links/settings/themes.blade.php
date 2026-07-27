@extends('user.layouts.app')
@section('title', 'Scheduled Themes - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php($activeSettingsTab = 'themes')
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-6xl mx-auto">
    @include('user.links.partials.editor-header')
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => 'themes'])

    <div id="settings-tab-content">
        <div x-data="biolinkThemesPage({
            themes: {{ json_encode($themes->map(fn($t) => ['id'=>$t->id,'name'=>$t->name,'created_at'=>optional($t->created_at)->toIso8601String()])) }},
            schedules: {{ json_encode($schedules->map(fn($s) => [
                'id' => $s->id,
                'theme_id' => $s->theme_id,
                'theme_name' => $s->theme?->name ?? 'Deleted theme',
                'starts_at' => optional($s->starts_at)->toIso8601String(),
                'ends_at' => optional($s->ends_at)->toIso8601String(),
                'timezone' => $s->timezone,
                'status' => $s->status,
            ])) }},
            activeId: {{ $activeId ? (int)$activeId : 'null' }},
            tzList: {!! json_encode(array_values($tzList)) !!},
            csrf: '{{ csrf_token() }}',
            urls: {
                saveTheme:     '{{ route('user.links.themes.store', $link) }}',
                deleteTheme:   '{{ url('user/links/'.$link->id.'/themes') }}',
                schedule:      '{{ route('user.links.themes.schedules.store', $link) }}',
                updateSchedule:'{{ url('user/links/'.$link->id.'/themes/schedules') }}',
                cancelSchedule:'{{ url('user/links/'.$link->id.'/themes/schedules') }}',
            }
        })" class="space-y-6">

            <!-- Save the current look as a named theme -->
            <section class="rounded-2xl p-5" style="background: var(--bg-glass-card); border: 1px solid var(--border-glass);">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-base font-semibold" style="color: var(--text-primary);">Save current look as a theme</h2>
                        <p class="text-xs mt-1" style="color: var(--text-faint);">
                            Captures colors, hero image, header copy and background. Schedule it later to launch on a date and revert automatically.
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 items-center">
                    <input x-model="newName" type="text" maxlength="120" placeholder="e.g. Holiday 2026, Album launch"
                           class="theme-input flex-1 min-w-[220px]">
                    <button type="button"
                            @click="saveTheme()"
                            :disabled="!newName.trim() || saving"
                            class="px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50"
                            style="background: linear-gradient(135deg,#90acff,#67e8f9); color:#0a0612;">
                        <i class="fas fa-bookmark mr-1"></i>
                        <span x-text="saving ? 'Saving…' : 'Save as theme'"></span>
                    </button>
                </div>
            </section>

            <!-- Saved themes -->
            <section class="rounded-2xl p-5" style="background: var(--bg-glass-card); border: 1px solid var(--border-glass);">
                <h2 class="text-base font-semibold mb-3" style="color: var(--text-primary);">Your themes</h2>
                <template x-if="!themes.length">
                    <p class="text-xs" style="color: var(--text-faint);">No saved themes yet. Save the current look above to get started.</p>
                </template>
                <ul class="divide-y" style="border-color: var(--border-subtle);">
                    <template x-for="t in themes" :key="t.id">
                        <li class="py-3 flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold truncate" style="color: var(--text-primary);" x-text="t.name"></div>
                                <div class="text-[11px]" style="color: var(--text-faint);" x-text="'Saved ' + fmtDate(t.created_at)"></div>
                            </div>
                            <button type="button" @click="openSchedulePicker(t)" class="text-xs px-3 py-1.5 rounded-md font-semibold"
                                    style="background: rgba(144,172,255,0.18); color: #90acff;">
                                <i class="fas fa-calendar-plus mr-1"></i>Schedule
                            </button>
                            <button type="button" @click="deleteTheme(t)" class="text-xs px-2 py-1.5 rounded-md"
                                    style="color: var(--text-faint);" title="Delete theme">
                                <i class="fas fa-trash"></i>
                            </button>
                        </li>
                    </template>
                </ul>
            </section>

            <!-- Schedule picker / editor (inline) -->
            <section x-show="picker.theme || picker.editingId" x-transition class="rounded-2xl p-5"
                     style="background: var(--bg-glass-card); border: 1px solid rgba(144,172,255,0.4);">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold" style="color: var(--text-primary);">
                        <template x-if="picker.editingId">
                            <span>Edit window for <span class="text-blue-300" x-text="picker.theme?.name"></span></span>
                        </template>
                        <template x-if="!picker.editingId">
                            <span>Schedule <span class="text-blue-300" x-text="picker.theme?.name"></span></span>
                        </template>
                    </h2>
                    <button type="button" @click="resetPicker()" class="text-xs"
                            style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium block mb-1" style="color: var(--text-muted);">Starts</span>
                        <input x-model="picker.starts" type="datetime-local" class="theme-input w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium block mb-1" style="color: var(--text-muted);">Ends</span>
                        <input x-model="picker.ends" type="datetime-local" class="theme-input w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium block mb-1" style="color: var(--text-muted);">Timezone</span>
                        <select x-model="picker.tz" class="theme-input w-full">
                            <template x-for="tz in tzList" :key="tz">
                                <option :value="tz" x-text="tz"></option>
                            </template>
                        </select>
                    </label>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="button" @click="schedule()" :disabled="!canSchedule || scheduling"
                            class="px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50"
                            style="background: linear-gradient(135deg,#90acff,#67e8f9); color:#0a0612;">
                        <span x-text="scheduling ? 'Saving…' : (picker.editingId ? 'Save changes' : 'Schedule theme')"></span>
                    </button>
                </div>
                <p x-show="schedError" class="text-[11px] mt-2 text-red-400" x-text="schedError"></p>
            </section>

            <!-- Timeline of upcoming + active schedules -->
            <section class="rounded-2xl p-5" style="background: var(--bg-glass-card); border: 1px solid var(--border-glass);">
                <h2 class="text-base font-semibold mb-3" style="color: var(--text-primary);">Timeline</h2>
                <template x-if="!schedules.length">
                    <p class="text-xs" style="color: var(--text-faint);">No upcoming or active scheduled themes. Pick a theme above and schedule it.</p>
                </template>
                <ul class="space-y-2">
                    <template x-for="s in schedules" :key="s.id">
                        <li class="rounded-xl p-3 flex flex-wrap items-center gap-3"
                            :style="s.id === activeId
                                ? 'background: rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.3);'
                                : 'background: var(--bg-glass-input); border:1px solid var(--border-glass);'">
                            <div class="flex-1 min-w-[200px]">
                                <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                    <span x-text="s.theme_name"></span>
                                    <span x-show="s.id === activeId"
                                          class="ml-2 text-[10px] px-2 py-0.5 rounded-full"
                                          style="background: #22c55e; color: #062b14;">LIVE NOW</span>
                                    <span x-show="s.id !== activeId && s.status === 'pending'"
                                          class="ml-2 text-[10px] px-2 py-0.5 rounded-full"
                                          style="background: rgba(255,255,255,0.08); color: var(--text-muted);">UPCOMING</span>
                                </div>
                                <div class="text-[11px] mt-0.5" style="color: var(--text-faint);">
                                    <span x-text="fmtRange(s)"></span>
                                </div>
                            </div>
                            <template x-if="s.status === 'pending' && s.id !== activeId">
                                <button type="button" @click="editSchedule(s)" class="text-xs px-3 py-1.5 rounded-md font-semibold"
                                        style="background: rgba(144,172,255,0.18); color:#90acff;">
                                    <i class="fas fa-pen mr-1"></i>Edit
                                </button>
                            </template>
                            <button type="button" @click="cancelSchedule(s)" class="text-xs px-3 py-1.5 rounded-md font-semibold"
                                    style="background: rgba(239,68,68,0.15); color:#fca5a5;">
                                <span x-text="s.id === activeId ? 'End now' : 'Cancel'"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </section>
        </div>
    </div>
</div>

<script>
function biolinkThemesPage(initial) {
    const browserTz = (Intl && Intl.DateTimeFormat && Intl.DateTimeFormat().resolvedOptions().timeZone) || 'UTC';
    return {
        themes: initial.themes || [],
        schedules: initial.schedules || [],
        activeId: initial.activeId || null,
        tzList: initial.tzList || ['UTC'],
        csrf: initial.csrf,
        urls: initial.urls,
        newName: '',
        saving: false,
        scheduling: false,
        schedError: null,
        browserTz,
        picker: { theme: null, editingId: null, starts: '', ends: '', tz: browserTz },

        resetPicker() {
            this.picker = { theme: null, editingId: null, starts: '', ends: '', tz: this.browserTz };
            this.schedError = null;
        },

        get canSchedule() {
            if ((!this.picker.theme && !this.picker.editingId) || !this.picker.starts || !this.picker.ends) return false;
            return new Date(this.picker.ends) > new Date(this.picker.starts);
        },

        fmtDate(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleString(); } catch (e) { return iso; }
        },
        fmtRange(s) {
            const fmt = (iso) => { try { return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return iso; } };
            return fmt(s.starts_at) + '  →  ' + fmt(s.ends_at) + '  (' + (s.timezone || 'UTC') + ')';
        },

        async post(url, body, method='POST') {
            const r = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : null,
            });
            if (!r.ok) {
                let msg = 'Request failed';
                try { const j = await r.json(); msg = j.message || msg; } catch (e) {}
                throw new Error(msg);
            }
            try { return await r.json(); } catch (e) { return {}; }
        },

        async saveTheme() {
            const name = this.newName.trim();
            if (!name) return;
            this.saving = true;
            try {
                const j = await this.post(this.urls.saveTheme, { name });
                this.themes.unshift(j.theme);
                this.newName = '';
            } catch (e) { alert(e.message); }
            finally { this.saving = false; }
        },

        async deleteTheme(t) {
            if (!confirm('Delete theme "' + t.name + '"? Active schedules using it will be cancelled.')) return;
            try {
                await this.post(this.urls.deleteTheme + '/' + t.id, null, 'DELETE');
                this.themes = this.themes.filter(x => x.id !== t.id);
                this.schedules = this.schedules.filter(x => x.theme_id !== t.id);
            } catch (e) { alert(e.message); }
        },

        openSchedulePicker(t) {
            const now = new Date();
            const start = new Date(now.getTime() + 60 * 60 * 1000); // +1h
            const end = new Date(now.getTime() + 25 * 60 * 60 * 1000); // +25h
            this.picker = {
                theme: t,
                editingId: null,
                starts: this.toLocalInput(start),
                ends: this.toLocalInput(end),
                tz: this.browserTz,
            };
            this.schedError = null;
        },

        editSchedule(s) {
            // Only pending schedules are editable; once a window has
            // activated the prev_settings snapshot is locked in and
            // re-timing it would race the cron's revert pass.
            if (s.status !== 'pending') {
                alert('Only upcoming schedules can be edited. Cancel and create a new one instead.');
                return;
            }
            this.picker = {
                theme: { id: s.theme_id, name: s.theme_name },
                editingId: s.id,
                starts: this.toLocalInput(new Date(s.starts_at)),
                ends:   this.toLocalInput(new Date(s.ends_at)),
                tz:     s.timezone || this.browserTz,
            };
            this.schedError = null;
        },

        toLocalInput(d) {
            const pad = (n) => String(n).padStart(2,'0');
            return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
                 + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        },

        async schedule() {
            if (!this.canSchedule) return;
            this.scheduling = true;
            this.schedError = null;
            try {
                if (this.picker.editingId) {
                    const j = await this.post(
                        this.urls.updateSchedule + '/' + this.picker.editingId,
                        { starts_at: this.picker.starts, ends_at: this.picker.ends, timezone: this.picker.tz },
                        'PATCH',
                    );
                    const idx = this.schedules.findIndex(x => x.id === this.picker.editingId);
                    if (idx >= 0) this.schedules.splice(idx, 1, j.schedule);
                } else {
                    const j = await this.post(this.urls.schedule, {
                        theme_id: this.picker.theme.id,
                        starts_at: this.picker.starts,
                        ends_at: this.picker.ends,
                        timezone: this.picker.tz,
                    });
                    this.schedules.push(j.schedule);
                }
                this.schedules.sort((a,b) => new Date(a.starts_at) - new Date(b.starts_at));
                this.resetPicker();
            } catch (e) { this.schedError = e.message; }
            finally { this.scheduling = false; }
        },

        async cancelSchedule(s) {
            const verb = s.id === this.activeId ? 'End this scheduled theme now and revert?' : 'Cancel this scheduled theme?';
            if (!confirm(verb)) return;
            try {
                await this.post(this.urls.cancelSchedule + '/' + s.id + '/cancel', null, 'POST');
                this.schedules = this.schedules.filter(x => x.id !== s.id);
                if (s.id === this.activeId) this.activeId = null;
            } catch (e) { alert(e.message); }
        },
    };
}
</script>
@endsection
