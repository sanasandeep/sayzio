@extends('user.layouts.app')
@section('title', $board->name)
@push('styles')
<style>
    .kanban-scroll { overflow-x: auto; padding-bottom: 12px; }
    .kanban-col {
        min-width: 300px; max-width: 300px;
        background: var(--bg-card); border: 1px solid var(--border-soft);
        border-radius: 14px; display: flex; flex-direction: column;
    }
    .kanban-col-header {
        padding: 10px 14px; border-bottom: 1px solid var(--border-soft);
        display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13px;
        color: var(--text-primary);
    }
    .kanban-col-cards { padding: 10px; min-height: 60px; flex: 1; }
    .kanban-card {
        background: var(--bg-glass-input); border: 1px solid var(--border-soft);
        border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer;
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .kanban-card:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,.12); border-color: rgba(124,58,237,.4); }
    .kanban-card.completed { opacity: .55; text-decoration: line-through; }
    .sortable-ghost { opacity: 0.4; }
    .sortable-chosen { box-shadow: 0 8px 24px rgba(0,0,0,.25); }
    .drawer { position: fixed; right: 0; top: 0; bottom: 0; width: 100%; max-width: 560px;
              background: var(--bg-card); border-left: 1px solid var(--border-strong);
              box-shadow: -8px 0 32px rgba(0,0,0,.25); z-index: 60;
              transform: translateX(100%); transition: transform .25s ease; overflow-y: auto; }
    .drawer.open { transform: translateX(0); }
    .priority-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    .label-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; color: white; }
</style>
@endpush
@section('content')
<div x-data="kanbanBoard({{ $board->id }})" x-init="init()" class="px-6 py-6">
    <div class="page-hero mb-5 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.tasks.index') }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="hero-title">{{ $board->name }}</h1>
                @if($board->scope === 'personal')
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full mr-2"
                          style="background: rgba(139,92,246,0.12); color:#7c3aed;">PRIVATE</span>
                @endif
                <span class="text-xs" style="color: var(--text-faint);">
                    {{ $board->columns->count() }} columns · {{ $board->columns->sum(fn($c)=>$c->cards->count()) }} cards
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showAddColumn = true"
                    class="px-3 py-2 rounded-lg text-sm font-semibold border"
                    style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-plus mr-1"></i> Add Column
            </button>
            <form action="{{ route('user.tasks.boards.destroy', $board) }}" method="POST"
                  onsubmit="return confirm('Delete this board and all its cards?')">
                @csrf @method('DELETE')
                <button class="px-3 py-2 rounded-lg text-sm font-semibold"
                        style="color: #ef4444;">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="kanban-scroll flex gap-4">
        @foreach($board->columns as $col)
            <div class="kanban-col" data-column-id="{{ $col->id }}">
                <div class="kanban-col-header" style="border-top: 3px solid {{ $col->color ?: '#8b5cf6' }}; border-radius: 14px 14px 0 0;">
                    <span class="flex-1">{{ $col->name }}</span>
                    @if($col->is_done)<i class="fas fa-check-circle text-emerald-500" title="Done column"></i>@endif
                    <span class="text-xs font-normal" style="color: var(--text-faint);">{{ $col->cards->count() }}@if($col->wip_limit)/{{ $col->wip_limit }}@endif</span>
                    <button @click="deleteColumn({{ $col->id }})" class="text-xs" style="color: var(--text-faint);" title="Delete column">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="kanban-col-cards" data-sortable-cards data-column-id="{{ $col->id }}">
                    @foreach($col->cards as $card)
                        <div class="kanban-card {{ $card->completed_at ? 'completed' : '' }}"
                             data-card-id="{{ $card->id }}" id="card-{{ $card->id }}"
                             @click="openCard({{ $card->id }})">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $card->title }}</div>
                            <div class="flex items-center flex-wrap gap-1 mt-2">
                                @foreach($card->labels as $label)
                                    <span class="label-pill" style="background: {{ $label->color }};">{{ $label->name }}</span>
                                @endforeach
                                @if($card->priority !== 'normal')
                                    @php $p = $priorities[$card->priority]; @endphp
                                    <span class="priority-pill" style="background: {{ $p['color'] }}22; color: {{ $p['color'] }};">{{ $p['label'] }}</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-2 text-xs" style="color: var(--text-faint);">
                                <div class="flex items-center gap-2">
                                    @if($card->due_date)
                                        <span @class([
                                            'text-red-500' => $card->due_date->isPast() && !$card->completed_at,
                                        ])><i class="fas fa-calendar"></i> {{ $card->due_date->format('M j') }}</span>
                                    @endif
                                    @if($card->subtasks->count())
                                        <span><i class="fas fa-tasks"></i> {{ $card->subtasks->where('completed', true)->count() }}/{{ $card->subtasks->count() }}</span>
                                    @endif
                                </div>
                                <div class="flex -space-x-1">
                                    @foreach($card->assignees->take(3) as $a)
                                        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white text-[9px] font-bold flex items-center justify-center border" style="border-color: var(--bg-card);" title="{{ $a->name }}">
                                            {{ strtoupper(substr($a->name, 0, 1)) }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <form @submit.prevent="addCard($el, {{ $col->id }})" class="p-2 border-t" style="border-color: var(--border-soft);">
                    <input name="title" placeholder="+ Add a card" maxlength="200"
                           class="w-full px-2 py-1.5 text-sm rounded border-0"
                           style="background: transparent; color: var(--text-primary);">
                </form>
            </div>
        @endforeach
    </div>

    {{-- Add column modal --}}
    <div x-show="showAddColumn" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,.5);" @keydown.escape.window="showAddColumn = false">
        <div @click.outside="showAddColumn = false" class="rounded-2xl w-full max-w-md p-6" style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <form action="{{ route('user.tasks.columns.store', $board) }}" method="POST">
                @csrf
                <h2 class="text-lg font-bold mb-4" style="color: var(--text-primary);">Add Column</h2>
                <label class="block text-xs font-semibold mb-1" style="color: var(--text-muted);">Name</label>
                <input name="name" required maxlength="80" autofocus
                       class="w-full px-3 py-2 rounded-lg border"
                       style="background: var(--bg-glass-input); border-color: var(--border-strong); color: var(--text-primary);">
                <label class="flex items-center gap-2 mt-3 text-sm" style="color: var(--text-primary);">
                    <input type="checkbox" name="is_done" value="1"> Cards dropped here are marked complete
                </label>
                <label class="block text-xs font-semibold mt-3 mb-1" style="color: var(--text-muted);">Colour</label>
                <input name="color" type="color" value="#8b5cf6" class="w-16 h-8 rounded">
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="showAddColumn = false" class="px-3 py-2 rounded-lg text-sm" style="color: var(--text-muted);">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#7c3aed,#a78bfa);">Add</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Card detail drawer --}}
    <div class="drawer" :class="{ open: drawerOpen }" @click.outside="drawerOpen = false">
        <template x-if="card">
            <div class="p-6">
                <div class="flex items-start justify-between mb-3">
                    <input x-model="card.title" @blur="saveCard({ title: card.title })"
                           class="text-xl font-bold w-full bg-transparent border-0 outline-none"
                           style="color: var(--text-primary);">
                    <button @click="drawerOpen = false" class="text-lg" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1" style="color: var(--text-faint);">Due Date</label>
                        <input type="date" x-model="card.due_date" @change="saveCard({ due_date: card.due_date })"
                               class="w-full px-2 py-1 rounded border text-sm"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase mb-1" style="color: var(--text-faint);">Priority</label>
                        <select x-model="card.priority" @change="saveCard({ priority: card.priority })"
                                class="w-full px-2 py-1 rounded border text-sm"
                                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                            @foreach($priorities as $key => $p)
                                <option value="{{ $key }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="block text-[10px] font-bold uppercase mb-1" style="color: var(--text-faint);">Description</label>
                <textarea x-model="card.description" @blur="saveCard({ description: card.description })" rows="4"
                          class="w-full px-3 py-2 rounded-lg border text-sm"
                          style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"
                          placeholder="Add more detail…"></textarea>

                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Assignees</h3>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="a in card.assignees" :key="a.id">
                            <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs" style="background: rgba(124,58,237,.12); color: var(--text-primary);">
                                <span x-text="a.name"></span>
                                <button @click="unassign(a.id)" class="text-xs"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                    </div>
                    <select @change="if($event.target.value) { assign(parseInt($event.target.value)); $event.target.value=''; }"
                            class="px-2 py-1 rounded border text-sm"
                            style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">+ Add assignee</option>
                        @foreach($members as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Labels</h3>
                    <div class="flex flex-wrap gap-1 mb-2">
                        <template x-for="l in card.labels" :key="l.id">
                            <span class="label-pill flex items-center gap-1" :style="`background:${l.color};`">
                                <span x-text="l.name"></span>
                                <button @click="detachLabel(l.id)" class="text-[10px]"><i class="fas fa-times"></i></button>
                            </span>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <select @change="if($event.target.value) { attachLabel(parseInt($event.target.value)); $event.target.value=''; }"
                                class="px-2 py-1 rounded border text-sm flex-1"
                                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                            <option value="">+ Apply existing label</option>
                            <template x-for="l in boardLabels" :key="l.id">
                                <option :value="l.id" x-text="l.name"></option>
                            </template>
                        </select>
                        <button @click="newLabelOpen = !newLabelOpen" type="button"
                                class="px-2 py-1 rounded text-xs font-semibold border"
                                style="border-color: var(--border-strong); color: var(--text-primary);">
                            New
                        </button>
                    </div>
                    <form x-show="newLabelOpen" x-cloak @submit.prevent="createLabel($event)" class="mt-2 flex gap-2">
                        <input name="name" placeholder="Label name" maxlength="60" required
                               class="flex-1 px-2 py-1 text-sm rounded border"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <input name="color" type="color" value="#8b5cf6" class="w-12 h-8 rounded">
                        <button class="px-2 py-1 rounded text-xs font-semibold text-white" style="background:#7c3aed;">Add</button>
                    </form>
                </div>

                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Subtasks</h3>
                    <template x-for="s in card.subtasks" :key="s.id">
                        <div class="flex items-center gap-2 mb-1">
                            <input type="checkbox" :checked="s.completed" @change="toggleSubtask(s)">
                            <span :class="s.completed ? 'line-through opacity-60' : ''" x-text="s.title" class="text-sm flex-1" style="color: var(--text-primary);"></span>
                            <button @click="destroySubtask(s)" class="text-xs" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                    <form @submit.prevent="addSubtask($event)">
                        <input name="title" placeholder="+ Add subtask" maxlength="240"
                               class="w-full mt-1 px-2 py-1 text-sm rounded border"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    </form>
                </div>

                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Comments</h3>
                    <template x-for="c in card.comments" :key="c.id">
                        <div class="mb-2 p-2 rounded" style="background: var(--bg-glass-input);">
                            <div class="text-xs font-semibold" style="color: var(--text-primary);" x-text="c.user?.name || 'Someone'"></div>
                            <div class="text-sm whitespace-pre-line" style="color: var(--text-primary);" x-text="c.body"></div>
                        </div>
                    </template>
                    <form @submit.prevent="addComment($event)">
                        <textarea name="body" rows="2" placeholder="Write a comment…" maxlength="5000"
                                  class="w-full px-2 py-1 text-sm rounded border"
                                  style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></textarea>
                        <button class="mt-1 px-3 py-1 rounded text-xs font-semibold text-white" style="background: #7c3aed;">Post</button>
                    </form>
                </div>

                <div class="mt-6 pt-4 border-t flex justify-between" style="border-color: var(--border-soft);">
                    <button @click="destroyCard()" class="text-xs font-semibold" style="color:#ef4444;">
                        <i class="fas fa-trash mr-1"></i> Delete card
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function kanbanBoard(boardId) {
    return {
        boardId,
        drawerOpen: false,
        showAddColumn: false,
        newLabelOpen: false,
        boardLabels: @json($board->labels->map(fn($l) => ['id'=>$l->id,'name'=>$l->name,'color'=>$l->color])),
        card: null,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        init() {
            const self = this;
            document.querySelectorAll('[data-sortable-cards]').forEach(el => {
                Sortable.create(el, {
                    group: 'cards',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: (evt) => self.onCardDrop(evt),
                });
            });
            // Open ?card=X or #card-X if present
            const hash = location.hash.match(/card-(\d+)/);
            if (hash) self.openCard(parseInt(hash[1]));
        },

        async fetchJson(url, opts = {}) {
            const headers = { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(opts.headers || {}) };
            if (opts.body && !(opts.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(opts.body);
            }
            const r = await fetch(url, { ...opts, headers, credentials: 'same-origin' });
            return r.ok ? r.json() : Promise.reject(await r.text());
        },

        async onCardDrop(evt) {
            const cardId = evt.item.dataset.cardId;
            const columnId = evt.to.dataset.columnId;
            const position = evt.newIndex;
            await this.fetchJson(`/user/tasks/cards/${cardId}/move`, {
                method: 'POST',
                body: { column_id: parseInt(columnId), position },
            });
        },

        async openCard(id) {
            const data = await this.fetchJson(`/user/tasks/cards/${id}`);
            this.card = data.card;
            this.card.due_date = this.card.due_date ? this.card.due_date.substring(0, 10) : null;
            this.drawerOpen = true;
        },

        async saveCard(payload) {
            if (!this.card) return;
            await this.fetchJson(`/user/tasks/cards/${this.card.id}`, { method: 'PATCH', body: payload });
        },

        async assign(userId) {
            if (!this.card) return;
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/assign`, { method: 'POST', body: { user_id: userId } });
            await this.openCard(this.card.id);
        },

        async unassign(userId) {
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/assignees/${userId}`, { method: 'DELETE' });
            await this.openCard(this.card.id);
        },

        async addSubtask(e) {
            const input = e.target.querySelector('input');
            const title = input.value.trim();
            if (!title) return;
            const r = await this.fetchJson(`/user/tasks/cards/${this.card.id}/subtasks`, { method: 'POST', body: { title } });
            this.card.subtasks.push(r.subtask);
            input.value = '';
        },

        async toggleSubtask(s) {
            const r = await this.fetchJson(`/user/tasks/subtasks/${s.id}/toggle`, { method: 'POST' });
            s.completed = r.completed;
        },

        async destroySubtask(s) {
            await this.fetchJson(`/user/tasks/subtasks/${s.id}`, { method: 'DELETE' });
            this.card.subtasks = this.card.subtasks.filter(x => x.id !== s.id);
        },

        async addComment(e) {
            const ta = e.target.querySelector('textarea');
            const body = ta.value.trim();
            if (!body) return;
            const r = await this.fetchJson(`/user/tasks/cards/${this.card.id}/comments`, { method: 'POST', body: { body } });
            this.card.comments.push(r.comment);
            ta.value = '';
        },

        async attachLabel(labelId) {
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/labels`, { method: 'POST', body: { label_id: labelId } });
            await this.openCard(this.card.id);
        },
        async detachLabel(labelId) {
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/labels/${labelId}`, { method: 'DELETE' });
            await this.openCard(this.card.id);
        },
        async createLabel(e) {
            const fd = new FormData(e.target);
            const r = await this.fetchJson(`/user/tasks/boards/${this.boardId}/labels`, {
                method: 'POST',
                body: { name: fd.get('name'), color: fd.get('color') },
            });
            this.boardLabels.push(r.label);
            await this.attachLabel(r.label.id);
            e.target.reset();
            this.newLabelOpen = false;
        },

        async destroyCard() {
            if (!confirm('Delete this card?')) return;
            await this.fetchJson(`/user/tasks/cards/${this.card.id}`, { method: 'DELETE' });
            location.reload();
        },

        async addCard(form, columnId) {
            const input = form.querySelector('input');
            const title = input.value.trim();
            if (!title) return;
            await this.fetchJson(`/user/tasks/boards/${this.boardId}/cards`, {
                method: 'POST', body: { column_id: columnId, title },
            });
            location.reload();
        },

        async deleteColumn(id) {
            if (!confirm('Delete this column? Cards inside will be moved to the next column.')) return;
            const fd = new FormData(); fd.append('_token', this.csrf); fd.append('_method', 'DELETE');
            await fetch(`/user/tasks/columns/${id}`, { method: 'POST', body: fd, credentials: 'same-origin' });
            location.reload();
        },
    };
}
</script>
@endsection
