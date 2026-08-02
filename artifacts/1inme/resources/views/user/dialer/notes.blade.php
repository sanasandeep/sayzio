@extends('user.layouts.app')

@section('title', 'Notes & reminders')

@section('content')
<div class="max-w-3xl mx-auto"
     id="dialer-notes-root"
     x-data="dialerNotes()"
     x-init="init()">

    @include('user.partials.page-hero', [
        'title'    => 'Notes & reminders',
        'subtitle' => 'Quick notes, to-do lists and reminders, synced with your dialer app, shareable by phone number.',
        'icon'     => 'fa-clipboard-list',
        'chips'    => [],
    ])

    <div class="flex items-center justify-between mb-5">
        <a href="{{ route('user.dialer.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium" style="color:var(--text-muted);">
            <i class="fas fa-arrow-left text-xs"></i> Back to dialer
        </a>
        <button type="button" @click="openCreate()" class="btn-primary inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl">
            <i class="fas fa-plus text-xs"></i> New
        </button>
    </div>

    {{-- Filter chips --}}
    <div class="flex flex-wrap gap-2 mb-5">
        <template x-for="f in filters" :key="f.key">
            <button type="button" @click="filter = f.key"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition"
                    :style="filter === f.key
                        ? 'background:var(--color-primary-600);color:#fff;'
                        : 'background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:var(--text-muted);'">
                <span x-text="f.label"></span>
            </button>
        </template>
    </div>

    {{-- Editor card (create / edit) --}}
    <div x-show="editing" x-cloak class="card-premium p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold" style="color:var(--text-primary);" x-text="form.id ? 'Edit' : 'New note or to-do'"></h3>
            <div class="inline-flex rounded-lg overflow-hidden" style="border:1px solid rgba(255,255,255,.12);">
                <button type="button" @click="form.kind = 'note'" class="px-3 py-1 text-xs font-medium"
                        :style="form.kind === 'note' ? 'background:var(--color-primary-600);color:#fff;' : 'color:var(--text-muted);'">Note</button>
                <button type="button" @click="switchToChecklist()" class="px-3 py-1 text-xs font-medium"
                        :style="form.kind === 'checklist' ? 'background:var(--color-primary-600);color:#fff;' : 'color:var(--text-muted);'">To-do list</button>
            </div>
        </div>

        <input type="text" x-model="form.title" placeholder="Title"
               class="w-full mb-3 px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">

        {{-- Plain note body --}}
        <template x-if="form.kind === 'note'">
            <textarea x-model="form.body" rows="4" placeholder="Write your note…"
                      class="w-full mb-3 px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);"></textarea>
        </template>

        {{-- Checklist items --}}
        <template x-if="form.kind === 'checklist'">
            <div class="mb-3 space-y-2">
                <template x-for="(item, idx) in form.checklist" :key="idx">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" x-model="item.done" class="rounded">
                        <input type="text" x-model="item.text" placeholder="To-do item…"
                               @keydown.enter.prevent="addItem(idx)"
                               class="flex-1 px-3 py-1.5 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        <button type="button" @click="form.checklist.splice(idx, 1)" class="text-xs px-2" style="color:var(--text-faint);"><i class="fas fa-times"></i></button>
                    </div>
                </template>
                <button type="button" @click="addItem()" class="text-xs font-medium inline-flex items-center gap-1" style="color:var(--color-primary-400);">
                    <i class="fas fa-plus text-[10px]"></i> Add item
                </button>
            </div>
        </template>

        {{-- Attached website (set by the Zio Browser / API; removable here) --}}
        <div x-show="form.attached_url" x-cloak class="flex items-center gap-2 mb-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);">
            <img :src="'https://www.google.com/s2/favicons?sz=32&domain=' + encodeURIComponent(attachmentHost(form.attached_url))" class="w-4 h-4 rounded" alt="">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium truncate" style="color:var(--text-primary);" x-text="form.attached_title || attachmentHost(form.attached_url)"></p>
                <p class="text-[10px] truncate" style="color:var(--text-faint);" x-text="form.attached_url"></p>
            </div>
            <button type="button" @click="form.attached_url = null; form.attached_title = null;" class="text-xs px-2 py-1 rounded-lg" style="color:var(--text-muted);background:rgba(255,255,255,.06);" title="Remove attached website">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
            <div>
                <label class="block text-xs mb-1" style="color:var(--text-faint);">Remind me at</label>
                <input type="datetime-local" x-model="form.remind_at_local"
                       class="w-full px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            </div>
            <div>
                <label class="block text-xs mb-1" style="color:var(--text-faint);">Share with phone numbers <span style="color:var(--text-faint);">(comma-separated)</span></label>
                <input type="text" x-model="form.share_phones_raw" placeholder="+15551234567, +4479…"
                       class="w-full px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex gap-1.5">
                <template x-for="c in colors" :key="c">
                    <button type="button" @click="form.color = (form.color === c ? null : c)"
                            class="w-6 h-6 rounded-full transition"
                            :style="'background:' + c + ';' + (form.color === c ? 'outline:2px solid #fff;outline-offset:1px;' : 'opacity:.7;')"></button>
                </template>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="closeEditor()" class="px-3 py-2 rounded-xl text-sm" style="color:var(--text-muted);">Cancel</button>
                <button type="button" @click="save()" :disabled="saving" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">
                    <span x-text="saving ? 'Saving…' : 'Save'"></span>
                </button>
            </div>
        </div>
        <p x-show="error" x-text="error" class="mt-2 text-xs" style="color:#ef4444;"></p>
    </div>

    {{-- Notes list --}}
    <div class="space-y-3" x-show="!loading">
        <template x-for="n in visible()" :key="(n.own ? 'o' : 's') + n.id">
            <div class="card-premium p-4"
                 :style="n.color ? 'border-left:3px solid ' + n.color + ';' : ''">
                <div class="flex items-start gap-3">
                    <button type="button" x-show="n.own" @click="toggleDone(n)"
                            class="mt-0.5 w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center"
                            :style="n.done ? 'background:var(--color-primary-600);color:#fff;' : 'border:1.5px solid rgba(255,255,255,.25);'">
                        <i class="fas fa-check text-[9px]" x-show="n.done"></i>
                    </button>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold" :class="n.done ? 'line-through opacity-60' : ''" style="color:var(--text-primary);" x-text="n.title || (n.kind === 'checklist' ? 'To-do list' : 'Note')"></span>
                            <span x-show="n.source_type" class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" style="background:rgba(99,102,241,.15);color:var(--color-primary-400);"
                                  x-text="n.source_type === 'event' ? 'Auto · Event' : 'Auto · Call-back'"></span>
                            <span x-show="!n.own" class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" style="background:rgba(255,255,255,.08);color:var(--text-muted);"
                                  x-text="'Shared by ' + (n.owner_name || 'someone')"></span>
                        </div>
                        <p x-show="n.kind === 'note' && n.body" class="text-xs mt-1 whitespace-pre-line" style="color:var(--text-muted);" x-text="n.body"></p>
                        {{-- Checklist preview with toggles --}}
                        <div x-show="n.kind === 'checklist'" class="mt-2 space-y-1">
                            <template x-for="(item, idx) in (n.checklist || [])" :key="idx">
                                <label class="flex items-center gap-2 text-xs" style="color:var(--text-muted);">
                                    <input type="checkbox" :checked="item.done" :disabled="!n.own" @change="toggleItem(n, idx)" class="rounded">
                                    <span :class="item.done ? 'line-through opacity-60' : ''" x-text="item.text"></span>
                                </label>
                            </template>
                        </div>
                        {{-- Attached website chip --}}
                        <a x-show="n.attached_url" :href="n.attached_url" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 mt-2 px-2 py-1 rounded-lg text-[11px] font-medium max-w-full"
                           style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--color-primary-400);">
                            <img :src="'https://www.google.com/s2/favicons?sz=32&domain=' + encodeURIComponent(attachmentHost(n.attached_url))" class="w-3.5 h-3.5 rounded" alt="">
                            <span class="truncate" x-text="n.attached_title || attachmentHost(n.attached_url)"></span>
                            <i class="fas fa-arrow-up-right-from-square text-[8px]" style="color:var(--text-faint);"></i>
                        </a>
                        <div class="flex items-center gap-3 mt-2 text-[11px]" style="color:var(--text-faint);">
                            <span x-show="n.remind_at" class="inline-flex items-center gap-1">
                                <i class="fas fa-bell text-[9px]"></i> <span x-text="formatWhen(n.remind_at)"></span>
                            </span>
                            <span x-show="n.number" class="inline-flex items-center gap-1">
                                <i class="fas fa-phone text-[9px]"></i> <span x-text="n.number"></span>
                            </span>
                            <span x-show="n.own && (n.share_phones || []).length" class="inline-flex items-center gap-1">
                                <i class="fas fa-share-nodes text-[9px]"></i> <span x-text="(n.share_phones || []).length + ' shared'"></span>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-1 flex-shrink-0" x-show="n.own">
                        <button type="button" @click="openEdit(n)" class="w-8 h-8 rounded-lg text-xs" style="color:var(--text-muted);background:rgba(255,255,255,.05);"><i class="fas fa-pen"></i></button>
                        <button type="button" @click="destroy(n)" class="w-8 h-8 rounded-lg text-xs" style="color:#ef4444;background:rgba(239,68,68,.08);"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="visible().length === 0" class="card-premium p-8 text-center">
            <i class="fas fa-clipboard-list text-2xl mb-3" style="color:var(--text-faint);"></i>
            <p class="text-sm" style="color:var(--text-muted);">Nothing here yet. Create a note or to-do; reminders you set (and events you RSVP to) show up automatically.</p>
        </div>
    </div>

    <div x-show="loading" class="card-premium p-8 text-center text-sm" style="color:var(--text-muted);">Loading…</div>
</div>

<script>
function dialerNotes() {
    const urls = {
        data:    @js(route('user.dialer.notes.data')),
        store:   @js(route('user.dialer.notes.store')),
        update:  @js(route('user.dialer.notes.update', ['id' => '__ID__'])),
        destroy: @js(route('user.dialer.notes.destroy', ['id' => '__ID__'])),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };

    return {
        loading: true,
        saving: false,
        editing: false,
        error: '',
        filter: 'all',
        filters: [
            { key: 'all', label: 'All' },
            { key: 'notes', label: 'Notes' },
            { key: 'todos', label: 'To-dos' },
            { key: 'reminders', label: 'Reminders' },
            { key: 'auto', label: 'Auto tasks' },
            { key: 'shared', label: 'Shared with me' },
        ],
        colors: ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'],
        notes: [],
        shared: [],
        form: {},

        async init() {
            await this.reload();
        },
        async reload() {
            this.loading = true;
            try {
                const res = await fetch(urls.data, { headers });
                const json = await res.json();
                this.notes = json.data?.notes || [];
                this.shared = json.data?.shared || [];
            } finally {
                this.loading = false;
            }
        },
        visible() {
            const all = [...this.notes.map(n => ({ ...n })), ...this.shared.map(n => ({ ...n }))];
            const list = all.filter(n => {
                switch (this.filter) {
                    case 'notes':     return n.kind !== 'checklist' && !n.source_type;
                    case 'todos':     return n.kind === 'checklist';
                    case 'reminders': return !!n.remind_at;
                    case 'auto':      return !!n.source_type;
                    case 'shared':    return !n.own;
                    default:          return true;
                }
            });
            return list.sort((a, b) => (a.done - b.done) || (new Date(b.updated_at || 0) - new Date(a.updated_at || 0)));
        },
        blankForm() {
            return { id: null, kind: 'note', title: '', body: '', checklist: [], remind_at_local: '', share_phones_raw: '', color: null, attached_url: null, attached_title: null };
        },
        attachmentHost(url) {
            try { return new URL(url).hostname.replace(/^www\./, ''); } catch { return url || ''; }
        },
        openCreate() { this.form = this.blankForm(); this.editing = true; this.error = ''; },
        openEdit(n) {
            this.form = {
                id: n.id, kind: n.kind || 'note', title: n.title || '', body: n.body || '',
                checklist: (n.checklist || []).map(i => ({ text: i.text || '', done: !!i.done })),
                remind_at_local: n.remind_at ? this.toLocal(n.remind_at) : '',
                share_phones_raw: (n.share_phones || []).join(', '),
                color: n.color || null,
                attached_url: n.attached_url || null,
                attached_title: n.attached_title || null,
            };
            this.editing = true; this.error = '';
        },
        closeEditor() { this.editing = false; },
        switchToChecklist() {
            this.form.kind = 'checklist';
            if (!this.form.checklist.length) this.addItem();
        },
        addItem(afterIdx) {
            const item = { text: '', done: false };
            if (afterIdx === undefined) this.form.checklist.push(item);
            else this.form.checklist.splice(afterIdx + 1, 0, item);
        },
        toLocal(iso) {
            const d = new Date(iso);
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        },
        formatWhen(iso) {
            try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); }
            catch { return iso; }
        },
        payload() {
            return {
                kind: this.form.kind,
                title: this.form.title || null,
                body: this.form.kind === 'note' ? (this.form.body || null) : null,
                checklist: this.form.kind === 'checklist'
                    ? this.form.checklist.filter(i => (i.text || '').trim() !== '')
                    : null,
                remind_at: this.form.remind_at_local ? new Date(this.form.remind_at_local).toISOString() : null,
                color: this.form.color,
                attached_url: this.form.attached_url || null,
                attached_title: this.form.attached_url ? (this.form.attached_title || null) : null,
                share_phones: this.form.share_phones_raw.split(',').map(s => s.trim()).filter(Boolean),
            };
        },
        async save() {
            this.saving = true; this.error = '';
            try {
                const url = this.form.id ? urls.update.replace('__ID__', this.form.id) : urls.store;
                const res = await fetch(url, {
                    method: this.form.id ? 'PATCH' : 'POST',
                    headers,
                    body: JSON.stringify(this.payload()),
                });
                if (!res.ok) {
                    const j = await res.json().catch(() => ({}));
                    this.error = j.error?.message || j.message || 'Could not save. Please check the fields and try again.';
                    return;
                }
                this.editing = false;
                await this.reload();
            } finally {
                this.saving = false;
            }
        },
        async patch(n, body) {
            const res = await fetch(urls.update.replace('__ID__', n.id), { method: 'PATCH', headers, body: JSON.stringify(body) });
            if (res.ok) await this.reload();
        },
        toggleDone(n) { return this.patch(n, { done: !n.done }); },
        toggleItem(n, idx) {
            const items = (n.checklist || []).map((i, j) => j === idx ? { ...i, done: !i.done } : i);
            return this.patch(n, { checklist: items });
        },
        async destroy(n) {
            if (!confirm('Delete this note?')) return;
            const res = await fetch(urls.destroy.replace('__ID__', n.id), { method: 'DELETE', headers });
            if (res.ok) await this.reload();
        },
    };
}
</script>
@endsection
