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
    .rt-toolbar button { padding: 2px 8px; border-radius: 6px; font-size: 12px; color: var(--text-muted); border: 1px solid var(--border-soft); background: var(--bg-glass-input); }
    .rt-toolbar button:hover { color: var(--text-primary); }
    .rt-editor { min-height: 96px; padding: 8px 10px; border: 1px solid var(--border-soft); border-radius: 8px; background: var(--bg-glass-input); color: var(--text-primary); font-size: 14px; }
    .rt-editor:focus { outline: 2px solid rgba(124,58,237,0.4); }
    .progress-track { height: 6px; background: var(--bg-glass-input); border-radius: 999px; overflow: hidden; }
    .progress-fill  { height: 6px; background: linear-gradient(90deg,#7c3aed,#a78bfa); }
    .col-drag-handle { cursor: grab; opacity: 0.5; padding: 0 4px; }
    .col-drag-handle:hover { opacity: 1; }
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
            <form action="{{ route('user.tasks.boards.archive', $board) }}" method="POST"
                  onsubmit="return confirm('Archive this board? You can restore it later from the boards list.')">
                @csrf
                <button class="px-3 py-2 rounded-lg text-sm font-semibold border"
                        style="border-color: var(--border-strong); color: var(--text-primary);"
                        title="Archive board (recoverable)">
                    <i class="fas fa-box-archive"></i>
                </button>
            </form>
            <form action="{{ route('user.tasks.boards.destroy', $board) }}" method="POST"
                  onsubmit="return confirm('Permanently delete this board and all its cards?')">
                @csrf @method('DELETE')
                <button class="px-3 py-2 rounded-lg text-sm font-semibold"
                        style="color: #ef4444;"
                        title="Delete board permanently">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>

    {{-- Filter bar: assignee / label / due range / search. All client-side. --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 p-3 rounded-xl"
         style="background: var(--bg-card); border: 1px solid var(--border-soft);">
        <input type="text" x-model.debounce.150ms="filters.search" placeholder="Search title / description…"
               class="px-3 py-1.5 rounded-lg border text-sm flex-1 min-w-[200px]"
               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
        <select x-model="filters.assignee"
                class="px-2 py-1.5 rounded-lg border text-sm"
                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
            <option value="">All assignees</option>
            @foreach($members as $m)
                <option value="{{ $m->id }}">{{ $m->name }}</option>
            @endforeach
        </select>
        <select x-model="filters.label"
                class="px-2 py-1.5 rounded-lg border text-sm"
                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
            <option value="">All labels</option>
            @foreach($board->labels as $lab)
                <option value="{{ $lab->id }}">{{ $lab->name }}</option>
            @endforeach
        </select>
        <select x-model="filters.due"
                class="px-2 py-1.5 rounded-lg border text-sm"
                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
            <option value="">Any due date</option>
            <option value="overdue">Overdue</option>
            <option value="today">Due today</option>
            <option value="week">Due this week</option>
            <option value="none">No due date</option>
        </select>
        <button @click="filters = { search:'', assignee:'', label:'', due:'' }"
                class="text-xs font-semibold px-2 py-1.5 rounded-lg border"
                style="border-color: var(--border-soft); color: var(--text-muted);">
            Clear
        </button>
    </div>

    <div class="kanban-scroll flex gap-4" data-sortable-cols>
        @foreach($board->columns as $col)
            <div class="kanban-col" data-column-id="{{ $col->id }}">
                <div class="kanban-col-header" style="border-top: 3px solid {{ $col->color ?: '#8b5cf6' }}; border-radius: 14px 14px 0 0;">
                    <span class="col-drag-handle" data-col-handle title="Drag to reorder column"><i class="fas fa-grip-vertical"></i></span>
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
                             data-card-id="{{ $card->id }}"
                             data-card-title="{{ strtolower($card->title.' '.$card->description) }}"
                             data-card-assignees="{{ $card->assignees->pluck('id')->implode(',') }}"
                             data-card-labels="{{ $card->labels->pluck('id')->implode(',') }}"
                             data-card-due="{{ $card->due_date ? $card->due_date->toDateString() : '' }}"
                             id="card-{{ $card->id }}"
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

                <div class="mb-3">
                    <label class="block text-[10px] font-bold uppercase mb-1" style="color: var(--text-faint);">
                        Progress (<span x-text="card.progress || 0"></span>%)
                    </label>
                    <input type="range" min="0" max="100" step="5" x-model.number="card.progress"
                           @change="saveCard({ progress: parseInt(card.progress) })"
                           class="w-full">
                    <div class="progress-track mt-1"><div class="progress-fill" :style="`width:${card.progress||0}%`"></div></div>
                </div>

                <label class="block text-[10px] font-bold uppercase mb-1" style="color: var(--text-faint);">Description</label>
                <div class="rt-toolbar flex gap-1 mb-1">
                    <button type="button" @click="rtCmd('bold')"><b>B</b></button>
                    <button type="button" @click="rtCmd('italic')"><i>I</i></button>
                    <button type="button" @click="rtCmd('underline')"><u>U</u></button>
                    <button type="button" @click="rtCmd('insertUnorderedList')">• List</button>
                    <button type="button" @click="rtCmd('insertOrderedList')">1. List</button>
                </div>
                <div class="rt-editor" contenteditable="true" x-ref="descEditor"
                     @blur="saveCard({ description_html: $event.target.innerHTML })"
                     x-html="card.description_html || ''"
                     data-placeholder="Add more detail…"></div>

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
                    {{-- The wrapper is the SortableJS handle. Each child is
                         tagged with data-id so we can read the new ordering
                         straight off the DOM after a drag completes. --}}
                    <div x-ref="subtaskList" data-subtask-list>
                        <template x-for="s in card.subtasks" :key="s.id">
                            <div class="flex items-center gap-2 mb-1 px-1 py-0.5 rounded" :data-id="s.id"
                                 style="background: var(--bg-glass-input);">
                                <i class="fas fa-grip-vertical text-[10px] opacity-50 cursor-grab subtask-handle"></i>
                                <input type="checkbox" :checked="s.completed" @change="toggleSubtask(s)">
                                <span :class="s.completed ? 'line-through opacity-60' : ''" x-text="s.title" class="text-sm flex-1" style="color: var(--text-primary);"></span>
                                <button @click="destroySubtask(s)" class="text-xs" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                    </div>
                    <form @submit.prevent="addSubtask($event)">
                        <input name="title" placeholder="+ Add subtask" maxlength="240"
                               class="w-full mt-1 px-2 py-1 text-sm rounded border"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    </form>
                </div>

                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Attachments</h3>
                    <template x-for="a in (card.attachments || [])" :key="a.id">
                        <div class="flex items-center justify-between gap-2 mb-1 p-2 rounded text-sm" style="background: var(--bg-glass-input);">
                            <a :href="a.url" target="_blank" class="flex items-center gap-2 flex-1 truncate" style="color: var(--text-primary);">
                                <i class="fas fa-paperclip"></i>
                                <span class="truncate" x-text="a.original_name"></span>
                                <span class="text-xs" style="color: var(--text-faint);" x-text="a.human_size"></span>
                            </a>
                            <button @click="destroyAttachment(a.id)" class="text-xs" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                    <label class="inline-flex items-center gap-2 px-3 py-1.5 mt-1 rounded-lg text-xs font-semibold border cursor-pointer"
                           style="border-color: var(--border-strong); color: var(--text-primary);">
                        <i class="fas fa-upload"></i> Upload file (max 10MB)
                        <input type="file" class="hidden" @change="uploadAttachment($event)">
                    </label>
                </div>

                <div class="mt-5">
                    {{-- Tabbed footer: Comments + Activity log. Activity rows
                         come from card.activities (server already eager-loads
                         them in showCard()). --}}
                    <div class="flex items-center gap-1 mb-2 border-b" style="border-color: var(--border-soft);">
                        <button type="button" @click="cardTab = 'comments'"
                                class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                :style="cardTab === 'comments'
                                    ? 'color: var(--text-primary); border-bottom: 2px solid #7c3aed;'
                                    : 'color: var(--text-faint);'">
                            <i class="fas fa-comment mr-1"></i> Comments
                            <span class="ml-1 opacity-70" x-text="(card.comments || []).length"></span>
                        </button>
                        <button type="button" @click="cardTab = 'activity'"
                                class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                :style="cardTab === 'activity'
                                    ? 'color: var(--text-primary); border-bottom: 2px solid #7c3aed;'
                                    : 'color: var(--text-faint);'">
                            <i class="fas fa-clock-rotate-left mr-1"></i> Activity
                            <span class="ml-1 opacity-70" x-text="(card.activities || []).length"></span>
                        </button>
                    </div>

                    {{-- Comments pane --}}
                    <div x-show="cardTab === 'comments'">
                        <template x-for="c in card.comments" :key="c.id">
                            <div class="mb-2 p-2 rounded" style="background: var(--bg-glass-input);">
                                <div class="text-xs font-semibold" style="color: var(--text-primary);" x-text="c.user?.name || 'Someone'"></div>
                                <div class="text-sm whitespace-pre-line" style="color: var(--text-primary);" x-text="c.body"></div>
                            </div>
                        </template>

                        {{-- Composer with @-mention autocomplete against workspace members --}}
                        <form @submit.prevent="addComment($event)" class="relative">
                            <textarea name="body" rows="2" placeholder="Write a comment… use @name to mention" maxlength="5000"
                                      x-ref="commentBody"
                                      @input="onCommentInput($event)"
                                      @keydown.escape.prevent="mentionOpen = false"
                                      @keydown.arrow-down.prevent="mentionMove(1)"
                                      @keydown.arrow-up.prevent="mentionMove(-1)"
                                      @keydown.enter="mentionOpen && (mentionPick(mentionMatches[mentionIndex]), $event.preventDefault())"
                                      class="w-full px-2 py-1 text-sm rounded border"
                                      style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></textarea>

                            <div x-show="mentionOpen && mentionMatches.length > 0"
                                 x-cloak
                                 class="absolute left-0 right-0 mt-1 rounded-lg border shadow-lg z-50 max-h-56 overflow-y-auto"
                                 style="background: var(--bg-card); border-color: var(--border-strong);">
                                <template x-for="(m, i) in mentionMatches" :key="m.id">
                                    <button type="button" @click.prevent="mentionPick(m)"
                                            @mouseenter="mentionIndex = i"
                                            class="w-full text-left px-3 py-1.5 text-sm flex items-center gap-2"
                                            :style="i === mentionIndex
                                                ? 'background: rgba(124,58,237,0.12); color: var(--text-primary);'
                                                : 'color: var(--text-primary);'">
                                        <span class="w-5 h-5 rounded-full bg-violet-500/30 text-[10px] flex items-center justify-center font-bold uppercase"
                                              x-text="(m.name || '?').charAt(0)"></span>
                                        <span x-text="m.name"></span>
                                        <span class="ml-auto text-[10px] opacity-60">@<span x-text="m.mention_token"></span></span>
                                    </button>
                                </template>
                            </div>

                            <button class="mt-1 px-3 py-1 rounded text-xs font-semibold text-white" style="background: #7c3aed;">Post</button>
                        </form>
                    </div>

                    {{-- Activity pane --}}
                    <div x-show="cardTab === 'activity'" x-cloak>
                        <template x-if="!card.activities || card.activities.length === 0">
                            <p class="text-xs italic" style="color: var(--text-faint);">No activity yet.</p>
                        </template>
                        <ol class="space-y-2">
                            {{-- The activities() relation already orders by
                                 created_at DESC server-side, so we render in
                                 the array order (newest first). --}}
                            <template x-for="a in (card.activities || [])" :key="a.id">
                                <li class="flex items-start gap-2 text-xs" style="color: var(--text-muted);">
                                    <i class="fas fa-circle text-[6px] mt-1.5" style="color:#7c3aed;"></i>
                                    <div class="flex-1">
                                        <span class="font-semibold" style="color: var(--text-primary);" x-text="a.user?.name || 'Someone'"></span>
                                        <span x-text="' ' + activityLabel(a)"></span>
                                        <div class="opacity-60 text-[11px] mt-0.5" x-text="formatActivityTime(a.created_at)"></div>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
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
        // Workspace members reachable from this board, with the same mention
        // token format the server's notifyMentions() expects (lowercased, no
        // whitespace) so the autocomplete inserts text the parser will hit.
        boardMembers: @json($members->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'mention_token' => mb_strtolower(preg_replace('/\s+/', '', (string) $m->name)),
        ])),
        filters: { search: '', assignee: '', label: '', due: '' },
        card: null,
        cardTab: 'comments',
        // Mention autocomplete state.
        mentionOpen: false,
        mentionQuery: '',
        mentionStart: 0,
        mentionMatches: [],
        mentionIndex: 0,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        init() {
            const self = this;
            // Re-apply filters whenever any filter value changes.
            this.$watch('filters', () => this.applyFilters(), { deep: true });
            this.applyFilters();

            // Column drag-and-drop reorder (drag handle on each header).
            const colsEl = document.querySelector('[data-sortable-cols]');
            if (colsEl) {
                Sortable.create(colsEl, {
                    handle: '[data-col-handle]',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: () => {
                        const order = Array.from(colsEl.querySelectorAll('.kanban-col'))
                            .map(c => parseInt(c.dataset.columnId));
                        self.fetchJson(`/user/tasks/boards/${self.boardId}/columns/reorder`, {
                            method: 'POST', body: { order }
                        });
                    },
                });
            }

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
            // Defer until Alpine paints the new subtask DOM nodes.
            this.$nextTick(() => this.initSubtaskSortable());
        },

        // Bind Sortable to the subtask list each time the drawer renders.
        // We replace the previous instance to avoid leaks across re-opens.
        initSubtaskSortable() {
            const el = this.$refs.subtaskList;
            if (!el || typeof Sortable === 'undefined') return;
            if (el._sortable) { try { el._sortable.destroy(); } catch (_) {} }
            const self = this;
            el._sortable = Sortable.create(el, {
                animation: 150,
                handle: '.subtask-handle',
                ghostClass: 'sortable-ghost',
                onEnd: () => self.persistSubtaskOrder(),
            });
        },

        async persistSubtaskOrder() {
            if (!this.card) return;
            const el = this.$refs.subtaskList;
            if (!el) return;
            const order = Array.from(el.querySelectorAll('[data-id]'))
                .map(n => parseInt(n.dataset.id, 10))
                .filter(Boolean);
            if (!order.length) return;
            // Re-sync local state so any subsequent toggle/delete uses the
            // post-drag ordering even before the server round-trip lands.
            const byId = Object.fromEntries(this.card.subtasks.map(s => [s.id, s]));
            this.card.subtasks = order.map(id => byId[id]).filter(Boolean);
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/subtasks/reorder`,
                { method: 'POST', body: { order } });
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
            this.mentionOpen = false;
            // A new comment writes an activity row server-side; refresh the
            // card so the Activity tab stays in sync without a full reload.
            this.refreshActivities();
        },

        // ---- @-mention autocomplete -------------------------------------
        // Detect "@token" being typed at the caret. Filter workspace members
        // by name / mention_token (case-insensitive prefix or substring).
        onCommentInput(e) {
            const ta = e.target;
            const pos = ta.selectionStart;
            const upto = ta.value.slice(0, pos);
            const m = upto.match(/(^|\s)@([A-Za-z0-9._\-]{0,32})$/);
            if (!m) { this.mentionOpen = false; return; }
            const q = m[2].toLowerCase();
            this.mentionStart = pos - m[2].length - 1; // position of '@'
            this.mentionQuery = q;
            this.mentionMatches = (this.boardMembers || [])
                .filter(u => {
                    if (!q) return true;
                    return u.mention_token.startsWith(q)
                        || (u.name || '').toLowerCase().includes(q);
                })
                .slice(0, 6);
            this.mentionIndex = 0;
            this.mentionOpen = this.mentionMatches.length > 0;
        },
        mentionMove(delta) {
            if (!this.mentionOpen || !this.mentionMatches.length) return;
            const n = this.mentionMatches.length;
            this.mentionIndex = (this.mentionIndex + delta + n) % n;
        },
        mentionPick(member) {
            if (!member) return;
            const ta = this.$refs.commentBody;
            const before = ta.value.slice(0, this.mentionStart);
            const after  = ta.value.slice(ta.selectionStart);
            const insert = '@' + member.mention_token + ' ';
            ta.value = before + insert + after;
            const caret = (before + insert).length;
            ta.setSelectionRange(caret, caret);
            ta.focus();
            this.mentionOpen = false;
        },

        // ---- Activity tab helpers ---------------------------------------
        async refreshActivities() {
            if (!this.card) return;
            try {
                const data = await this.fetchJson(`/user/tasks/cards/${this.card.id}`);
                this.card.activities = data.card.activities || [];
            } catch (_) { /* non-fatal */ }
        },
        activityLabel(a) {
            // Backend stores rows as { type, data }; normalise so older
            // payloads or future renames still display something sensible.
            const t = a.type || a.action || 'updated';
            const d = a.data || a.meta || {};
            switch (t) {
                case 'created':            return 'created this card';
                case 'moved':              return 'moved the card';
                case 'assigned':           return 'assigned a teammate';
                case 'unassigned':         return 'unassigned a teammate';
                case 'attached':           return `attached "${d.name || 'a file'}"`;
                case 'attachment_removed': return `removed attachment "${d.name || ''}"`;
                case 'commented':          return 'left a comment';
                case 'completed':          return 'marked it complete';
                case 'reopened':           return 'reopened the card';
                case 'title':
                case 'description':
                case 'description_html':
                case 'due_date':
                case 'priority':
                case 'progress':
                case 'updated':            return `updated ${String(t).replace(/_/g, ' ')}`;
                default:                   return String(t).replace(/_/g, ' ');
            }
        },
        formatActivityTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleString(); } catch (_) { return iso; }
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

        rtCmd(cmd) {
            document.execCommand(cmd, false, null);
            const ed = this.$refs.descEditor;
            if (ed) this.saveCard({ description_html: ed.innerHTML });
        },

        async uploadAttachment(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) {
                alert('File too large (10MB max).');
                e.target.value = '';
                return;
            }
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', this.csrf);
            const r = await fetch(`/user/tasks/cards/${this.card.id}/attachments`, {
                method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
            });
            const j = await r.json();
            if (j.ok) {
                this.card.attachments = this.card.attachments || [];
                this.card.attachments.unshift(j.attachment);
            }
            e.target.value = '';
        },
        async destroyAttachment(id) {
            await this.fetchJson(`/user/tasks/attachments/${id}`, { method: 'DELETE' });
            this.card.attachments = (this.card.attachments || []).filter(a => a.id !== id);
        },

        applyFilters() {
            const f = this.filters;
            const today = new Date(); today.setHours(0,0,0,0);
            const weekEnd = new Date(today); weekEnd.setDate(weekEnd.getDate() + 7);
            document.querySelectorAll('.kanban-card').forEach(card => {
                let show = true;
                if (f.search) {
                    const t = (card.dataset.cardTitle || '').toLowerCase();
                    if (!t.includes(f.search.toLowerCase())) show = false;
                }
                if (show && f.assignee) {
                    const ids = (card.dataset.cardAssignees || '').split(',').filter(Boolean);
                    if (!ids.includes(String(f.assignee))) show = false;
                }
                if (show && f.label) {
                    const ids = (card.dataset.cardLabels || '').split(',').filter(Boolean);
                    if (!ids.includes(String(f.label))) show = false;
                }
                if (show && f.due) {
                    const due = card.dataset.cardDue;
                    if (f.due === 'none') { if (due) show = false; }
                    else if (!due) show = false;
                    else {
                        const d = new Date(due); d.setHours(0,0,0,0);
                        if (f.due === 'overdue' && d >= today) show = false;
                        if (f.due === 'today' && d.getTime() !== today.getTime()) show = false;
                        if (f.due === 'week' && (d < today || d > weekEnd)) show = false;
                    }
                }
                card.style.display = show ? '' : 'none';
            });
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
