@extends('user.layouts.app')
@section('title', $board->name)
@push('styles')
<style>
    .task-workspace .form-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-strong);border-radius:12px;background:var(--bg-card);font-size:12px;font-weight:600}
    .task-workspace .kanban-col{min-width:300px;max-width:300px}
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
    .kanban-card:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,.12); border-color: rgba(61,107,255,.4); }
    .kanban-card.completed { opacity: .55; text-decoration: line-through; }
    .sortable-ghost { opacity: 0.4; }
    .sortable-chosen { box-shadow: 0 8px 24px rgba(0,0,0,.25); }
    .drawer { position: fixed; right: 0; top: 0; bottom: 0; width: 100%; max-width: min(640px, 100vw);
              background: var(--bg-dropdown); border-left: 1px solid var(--border-strong);
              box-shadow: -8px 0 32px rgba(0,0,0,.25); z-index: 60;
              transform: translateX(100%); transition: transform .25s ease; overflow-y: auto; }
    .drawer.open { transform: translateX(0); }
    .task-workflow-modal .form-chip{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:10px 14px;border:1px solid var(--border-strong);border-radius:12px;background:var(--bg-card);color:var(--text-primary);font-size:12px;font-weight:600;line-height:1.4}
    .task-workflow-modal .task-modal-primary{background:var(--c-primary,#3d6bff);border-color:var(--c-primary,#3d6bff);color:#fff}
    .task-workflow-modal .task-modal-close{display:grid;place-items:center;width:36px;height:36px;border:1px solid var(--border-soft);border-radius:10px;background:var(--bg-card);color:var(--text-muted);flex-shrink:0}
    .task-workflow-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
    .task-workflow-fields label{display:flex;flex-direction:column;gap:6px;color:var(--text-muted);font-size:12px;font-weight:600;min-width:0}
    .task-workflow-fields .theme-input{width:100%;min-width:0;height:40px;padding:8px 10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--bg-card);color:var(--text-primary);font-size:13px;font-weight:400}
    .task-workflow-footer{display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;border-top:1px solid var(--border-soft);padding-top:16px;margin-top:18px}
    .task-workflow-modal button:focus-visible{outline:2px solid var(--c-primary,#3d6bff);outline-offset:3px}

    .priority-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    .label-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; color: white; }
    .rt-toolbar button { padding: 2px 8px; border-radius: 6px; font-size: 12px; color: var(--text-muted); border: 1px solid var(--border-soft); background: var(--bg-glass-input); }
    .rt-toolbar button:hover { color: var(--text-primary); }
    .rt-editor { min-height: 96px; padding: 8px 10px; border: 1px solid var(--border-soft); border-radius: 8px; background: var(--bg-glass-input); color: var(--text-primary); font-size: 14px; }
    .rt-editor:focus { outline: 2px solid rgba(61,107,255,0.4); }
    .progress-track { height: 6px; background: var(--bg-glass-input); border-radius: 999px; overflow: hidden; }
    .progress-fill  { height: 6px; background: linear-gradient(90deg,#3d6bff,#90acff); }
    .col-drag-handle { cursor: grab; opacity: 0.5; padding: 0 4px; }
    .col-drag-handle:hover { opacity: 1; }
.task-editor-tabs{display:flex;overflow-x:auto;gap:16px;position:sticky;top:0;background:var(--bg-card);z-index:5;padding:12px 0;margin-bottom:18px;border-bottom:1px solid var(--border-soft)}.task-editor-tabs button{white-space:nowrap;font-size:12px;padding:8px 2px;border-bottom:2px solid transparent}.task-editor-tabs button[aria-selected=true]{color:var(--c-primary,#3d6bff);border-color:currentColor}.task-related-results{max-height:230px;overflow:auto}.task-related-choice{display:flex;justify-content:space-between;width:100%;padding:10px;text-align:left;border-bottom:1px solid var(--border-soft);font-size:13px}.task-related-choice:hover{background:var(--bg-glass-input)}.drawer .task-progress-range{padding:0;border:0;min-height:28px;height:28px;accent-color:var(--c-primary,#3d6bff)}.drawer .theme-input{max-width:100%}.drawer [data-editor-panel=time] input{min-width:0}.drawer [x-cloak]{display:none!important}
</style>
@endpush
@section('content')
@include('user.cloud-files._attach-picker')
@include('user.planner.styles')
<div x-data="kanbanBoard({{ $board->id }})" @gantt-window="ganttAnchor=$event.detail.from;ganttDays=$event.detail.days"  class="task-workspace px-6 py-6">
    @include('user.partials.page-hero',['title'=>$board->name,'subtitle'=>'Manage your project tasks, checklists and deadlines.','icon'=>'fa-list-check','favicon'=>null,'url'=>null,'back'=>route('user.tasks.index'),'actions'=>[],'chips'=>[['text'=>$board->scope==='personal'?'Private board':'Team board','icon'=>'fa-users'],['text'=>$board->columns->count().' columns','icon'=>'fa-table-columns'],['text'=>$board->columns->sum(fn($c)=>$c->cards->count()).' tasks','icon'=>'fa-check']]])
    <div class="flex justify-end mb-5 gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('user.client-invoices.dashboard') }}"
               class="px-3 py-2 rounded-lg text-sm font-semibold border"
               style="border-color: var(--border-strong); color: var(--text-primary);"
               title="Open client invoices dashboard">
                <i class="fas fa-file-invoice-dollar mr-1"></i> Invoices
            </a>
            <button type="button" @click="showBilledColumnPicker = true"
                    class="px-3 py-2 rounded-lg text-sm font-semibold border"
                    style="border-color: var(--border-strong); color: var(--text-primary);"
                    title="Pick the column where paid cards auto-move">
                <i class="fas fa-check-double mr-1"></i> Billed Column
            </button>
            <button type="button" @click="createInvoiceFromSelected()"
                    x-show="selectedCardIds.length > 0" x-cloak
                    class="px-3 py-2 rounded-lg text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg,#10b981,#059669);">
                <i class="fas fa-file-invoice mr-1"></i>
                Invoice <span x-text="selectedCardIds.length"></span> card<span x-show="selectedCardIds.length !== 1">s</span>
            </button>
            <button @click="showAddColumn = true"
                    class="px-3 py-2 rounded-lg text-sm font-semibold border"
                    style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-plus mr-1"></i> Add Column
            </button>
            <form action="{{ route('user.tasks.boards.archive', $board) }}" method="POST"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Archive this board?', message: 'You can restore it later from the boards list.', confirmText: 'Archive', confirmIcon: 'fa-box-archive', iconClass: 'fa-box-archive'})">
                @csrf
                <button class="px-3 py-2 rounded-lg text-sm font-semibold border"
                        style="border-color: var(--border-strong); color: var(--text-primary);"
                        title="Archive board (recoverable)">
                    <i class="fas fa-box-archive"></i>
                </button>
            </form>
            <form action="{{ route('user.tasks.boards.destroy', $board) }}" method="POST"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this board?', message: 'The board and all of its cards will be permanently deleted.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button class="px-3 py-2 rounded-lg text-sm font-semibold"
                        style="color: #ef4444;"
                        title="Delete board permanently">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>

    @if(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.export'))<div class="flex gap-2 mb-3"><button type="button" class="form-chip" title="Export the full board (up to 500 tasks / 20 columns); interactive search filters are excluded" @click="exportView('png')">Export image</button><button type="button" class="form-chip" title="Export the full board as a paginated PDF; Gantt uses the selected date window" @click="exportView('pdf')">Export PDF</button></div>@endif
    <div class="task-workspace-toolbar"><div class="folder-view-switch"><button type="button" @click="boardView='kanban'" :aria-pressed="boardView==='kanban'">Board</button><button type="button" @click="boardView='gantt'" :aria-pressed="boardView==='gantt'">Gantt timeline</button></div><span class="notes-hint" x-text="syncStatus" role="status"></span><button type="button" class="form-chip" @click="location.reload()">Refresh updates</button><a class="form-chip" href="{{ route('user.ai.mind.show', $projectAssignment?->project_id ? ['project_id'=>$projectAssignment->project_id] : []) }}">Open AI Planner →</a></div>
    <p class="text-sm text-red-500 mb-3" x-show="notice" x-text="notice" role="alert"></p>
    @if(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.edit') && auth()->user()->canInWorkspace(app('current_workspace'),'projects.edit'))<form method="POST" action="{{ route('user.tasks.boards.update',$board) }}" class="notes-card mb-4 flex flex-wrap items-center gap-3">@csrf @method('PUT')<label class="text-sm">Optional project <select name="project_id" class="theme-input"> <option value="">No project</option>@foreach($projects as $projectChoice)<option value="{{ $projectChoice->id }}" @selected($projectAssignment?->project_id===$projectChoice->id)>{{ $projectChoice->name }}</option>@endforeach</select></label><button class="form-chip">Save project</button><span class="notes-hint">A project connection includes visible board tasks in the folder and Planner resource picker. Personal boards stay private.</span></form>@endif
    {{-- Filter bar: assignee / label / due range / search. All client-side. --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 p-3 rounded-xl"
         style="background: var(--bg-card); border: 1px solid var(--border-soft);">
        <input type="text" x-model.debounce.150ms="filters.search" placeholder="Search tasks and related items…"
               class="px-3 py-1.5 rounded-lg border text-sm flex-1 min-w-[200px]"
               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
        @php $assigneeOptions=$members->mapWithKeys(fn($member)=>[(string)$member->id=>$member->name])->all();$labelOptions=$board->labels->mapWithKeys(fn($label)=>[(string)$label->id=>$label->name])->all(); @endphp
        @include('user.planner.multi-filter',['model'=>'filters.assignees','options'=>$assigneeOptions,'filterLabel'=>'Assignees'])
        @include('user.planner.multi-filter',['model'=>'filters.labels','options'=>$labelOptions,'filterLabel'=>'Labels'])
        <select aria-label="Due dates" x-model="filters.due"
                class="px-2 py-1.5 rounded-lg border text-sm"
                style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
            <option value="">Any due date</option>
            <option value="overdue">Overdue</option>
            <option value="today">Due today</option>
            <option value="week">Due this week</option>
            <option value="none">No due date</option>
        </select>
        <button @click="filters = { search:'', assignees:[], labels:[], due:'' }"
                class="text-xs font-semibold px-2 py-1.5 rounded-lg border"
                style="border-color: var(--border-soft); color: var(--text-muted);">
            Clear
        </button>
    </div>

    @include('user.tasks.partials.workflow')
    @include('user.tasks.partials.gantt')
    <div x-show="boardView==='kanban'" class="kanban-scroll flex gap-4" data-sortable-cols>
        @foreach($board->columns as $col)
            <div class="kanban-col relative" data-column-id="{{ $col->id }}"
                 x-data="{ editing:false, name:@js($col->name), color:'{{ $col->color ?: '#5c83ff' }}', saving:false,columnError:'',wip:{{ (int) ($col->wip_limit ?? 0) }}, is_done:{{ $col->is_done ? 'true':'false' }} }">
                <div class="kanban-col-header" :style="'border-top: 3px solid '+color+'; border-radius: 14px 14px 0 0;'">
                    <span class="col-drag-handle" data-col-handle title="Drag to reorder column"><i class="fas fa-grip-vertical"></i></span>
                    <span class="flex-1 cursor-pointer" @click="editing=true" x-text="name"></span>
                    <i class="fas fa-check-circle text-emerald-500" x-show="is_done" title="Done column"></i>
                    <span class="text-xs font-normal" title="Tasks in this column / advisory task limit" style="color: var(--text-faint);">{{ $col->cards->count() }}<span x-show="wip>0">/<span x-text="wip"></span></span></span>
                    @if(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.workflow'))<button type="button" class="text-xs" title="Column connections" @click="openWorkflow({{ $col->id }})"><i class="fas fa-code-branch"></i><span class="sr-only">Column connections</span></button>@endif
                    <button @click="editing=true" class="text-xs" style="color: var(--text-faint);" title="Edit column" aria-label="Edit column"><i class="fas fa-pen"></i></button>
                    <button @click="deleteColumn({{ $col->id }})" class="text-xs" style="color: var(--text-faint);" title="Delete column" aria-label="Delete column"><i class="fas fa-times"></i></button>
                </div>
                {{-- Inline edit popover wired to PUT /columns/{id} --}}
                <div x-show="editing" x-transition class="absolute z-30 p-3 mt-1 rounded-xl shadow-xl space-y-2"
                     @click.outside="editing=false"
                     style="background: var(--bg-card); border: 1px solid var(--border-soft); width: 280px;max-width:100%;">
                    <input type="text" x-model="name" maxlength="60"
                           class="w-full px-2 py-1 text-sm rounded border"
                           style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="color" class="h-7 w-10 rounded cursor-pointer">
                        <label class="text-xs flex-1">Task limit (0 = unlimited)<input type="number" min="0" max="999" x-model.number="wip" placeholder="WIP"
                               class="w-full px-2 py-1 text-sm rounded border"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                    </div>
                    <p class="text-xs opacity-60">The limit is a visual target; it does not block adding tasks.</p><p class="text-xs text-red-500" role="alert" x-text="columnError"></p>
                    <label class="flex items-center gap-2 text-xs" style="color: var(--text-muted);">
                        <input type="checkbox" x-model="is_done"> Complete tasks moved into this column
                    </label>
                    <div class="flex justify-end gap-2">
                        <button @click="editing=false" class="text-xs px-2 py-1" style="color: var(--text-muted);">Cancel</button>
                        <button class="text-xs px-3 py-1 rounded" style="background:#5c83ff;color:#fff;"
                                @click="saving=true;columnError='';saveColumn({{ $col->id }}, { name, color, wip_limit: wip || null, is_done }).then(() => editing=false).catch(e=>columnError=e.message).finally(()=>saving=false)" :disabled="saving">Save</button>
                    </div>
                </div>
                <div class="kanban-col-cards" data-sortable-cards data-column-id="{{ $col->id }}">
                    @foreach($col->cards as $card)
                        <div class="kanban-card {{ $card->completed_at ? 'completed' : '' }}"
                             data-task-filter data-card-id="{{ $card->id }}"
                             data-card-title="{{ strtolower($card->title.' '.$card->description.' '.collect($card->related_items ?? [])->map(fn ($item) => ($item['title'] ?? '').' '.($item['note'] ?? ''))->implode(' ')) }}"
                             data-card-assignees="{{ $card->assignees->pluck('id')->implode(',') }}"
                             data-card-labels="{{ $card->labels->pluck('id')->implode(',') }}"
                             data-card-due="{{ $card->due_date ? $card->due_date->toDateString() : '' }}"
                             data-card-billable="{{ $card->billable ? '1' : '0' }}"
                             data-card-invoiced="{{ $card->client_invoice_id ? '1' : '0' }}"
                             id="card-{{ $card->id }}"
                             @click="openCard({{ $card->id }})">
                            @if($card->billable && !$card->client_invoice_id)
                                <label class="absolute top-1.5 right-1.5 cursor-pointer" @click.stop>
                                    <input type="checkbox" :checked="selectedCardIds.includes({{ $card->id }})"
                                           @change="toggleCardSelect({{ $card->id }}, $event.target.checked)">
                                </label>
                            @endif
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $card->title }}</div><time class="block text-xs opacity-60 mt-2" datetime="{{ $card->updated_at->toIso8601String() }}">Updated {{ $card->updated_at->timezone(auth()->user()->timezone??config('app.timezone'))->format('d M Y, H:i') }}</time>
                            <div class="progress-track mt-2" role="progressbar" aria-label="Task progress" aria-valuenow="{{ $card->progress }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-fill" data-task-progress="{{ $card->id }}" style="width:{{ $card->progress }}%"></div></div>
                            @if(count($card->related_items ?? []))
                                <p class="text-xs mt-1 truncate" style="color:var(--text-faint);">Related: {{ collect($card->related_items)->pluck('title')->take(2)->implode(' · ') }}{{ count($card->related_items) > 2 ? ' +'.(count($card->related_items)-2) : '' }}</p>
                            @endif
                            @if($card->billable)
                                <div class="mt-1 flex items-center gap-1 flex-wrap">
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full"
                                          style="background: rgba(16,185,129,0.12); color: #059669;">
                                        <i class="fas fa-dollar-sign"></i>
                                        {{ $card->rate_type === 'hourly' ? 'Hourly' : 'Flat' }}
                                    </span>
                                    @if($card->client_invoice_id)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full"
                                              style="background: rgba(61,107,255,0.18); color: #3d6bff;">
                                            <i class="fas fa-receipt"></i>
                                            {{ optional($card->clientInvoice)->status === 'paid' ? 'PAID' : 'INVOICED' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
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
                                        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white text-[9px] font-bold flex items-center justify-center border" style="border-color: var(--bg-card);" title="{{ $a->name }}">
                                            <img src="{{ $a->resolveAvatarUrl() }}" alt="{{ $a->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%" onerror="this.hidden=true">
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
                <input name="color" type="color" value="#5c83ff" class="w-16 h-8 rounded">
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="showAddColumn = false" class="px-3 py-2 rounded-lg text-sm" style="color: var(--text-muted);">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">Add</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Card detail drawer --}}
    <div class="drawer" role="dialog" aria-label="Task details" :aria-hidden="!drawerOpen" :class="{ open: drawerOpen }" @click.outside="if(!cloudPickerOpen) drawerOpen = false" @task-cloud-state.window="cloudPickerOpen=$event.detail.open">
        <template x-if="card">
            <div class="p-6">
                <div class="flex items-start justify-between mb-3">
                    <input x-model="card.title" @blur="saveCard({ title: card.title })"
                           class="text-xl font-bold w-full bg-transparent border-0 outline-none"
                           style="color: var(--text-primary);">
                    <button @click="drawerOpen = false" class="text-lg" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                </div>

                <p class="text-xs opacity-60 mb-3">Created <span x-text="new Date(card.created_at).toLocaleString()"></span> · Updated <span x-text="new Date(card.updated_at).toLocaleString()"></span></p>
                @if(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.edit') || $board->owner_user_id===auth()->id())<button type="button" class="form-chip mb-3" @click="setCompleted(!card.completed_at)" x-text="card.completed_at?'Reopen task':'Mark completed'"></button>@endif
                <p class="text-sm mb-3" style="color:var(--danger,#dc2626)" x-show="notice" x-text="notice" role="alert"></p>
                <nav class="task-editor-tabs" aria-label="Task editor sections" role="tablist">
                    <template x-for="tab in ['details','checklist','people','connections','time','activity']" :key="tab"><button type="button" role="tab" :aria-selected="editorTab===tab" @click="setEditorTab(tab)" x-text="({details:'Details',checklist:'Checklist',people:'People & categories',connections:'Connections & files',time:'Time & billing',activity:'Discussion & history'})[tab]"></button></template>
                </nav>
<div x-show="editorTab==='details'" x-cloak data-editor-panel="details">
                <label class="block text-sm mb-3">Optional project<select class="theme-input" x-model="card.project_id" @change="saveCard({project_id:card.project_id||null})"><option value="">Use board project</option>@foreach($projects as $projectChoice)<option value="{{ $projectChoice->id }}">{{ $projectChoice->name }}</option>@endforeach</select></label>
                <label class="block text-sm mb-3">Start date<input type="date" class="theme-input" x-model="card.start_date" @change="saveCard({start_date:card.start_date||null})"></label>
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
                           class="w-full task-progress-range" aria-label="Task progress">

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


</div>
<div x-show="editorTab==='people'" x-cloak data-editor-panel="people">
                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Assignees</h3>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="a in card.assignees" :key="a.id">
                            <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs" style="background: rgba(61,107,255,.12); color: var(--text-primary);">
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
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Categories (labels)</h3><p class="text-xs mb-2" style="color:var(--text-muted)">Group tasks with tags such as Design, Bug or Client work. Use them to filter the board and match workflow routes.</p>
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
                            <option value="">+ Choose a category</option>
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
                        <input name="name" placeholder="Category name, e.g. Design" maxlength="60" required
                               class="flex-1 px-2 py-1 text-sm rounded border"
                               style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <input name="color" type="color" value="#5c83ff" class="w-12 h-8 rounded">
                        <button class="px-2 py-1 rounded text-xs font-semibold text-white" style="background:#3d6bff;">Add</button>
                    </form>
                </div>


</div>
<div x-show="editorTab==='checklist'" x-cloak data-editor-panel="checklist">
                <div class="mt-5">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">Subtasks</h3>
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


</div>
<div x-show="editorTab==='connections'" x-cloak data-editor-panel="connections">
                <section class="mb-5 p-3 rounded-xl border" style="border-color:var(--border-soft); background:var(--bg-glass-input);" aria-label="Related Sayzio items">
                    <h3 class="font-bold text-sm">Related Sayzio items</h3>
                    <p class="text-xs my-2" style="color:var(--text-faint);">What is this task about? Connect a link, Mind, customer, invoice or any Sayzio page. Opening an item uses your normal access permissions.</p>
                    <template x-for="(item, index) in (card.related_items || [])" :key="index">
                        <div class="py-2 border-b" style="border-color:var(--border-soft);">
                            <div class="flex items-center gap-2"><span class="text-xs uppercase" x-text="item.type"></span><a class="font-semibold underline break-all" :href="item.url" target="_blank" rel="noopener noreferrer" x-text="item.title"></a><button type="button" class="ml-auto text-xs" @click="removeRelatedItem(index)" :aria-label="'Remove ' + item.title">Remove</button></div>
                            <p class="text-xs mt-1 break-words" x-text="item.note"></p>
                        </div>
                    </template>
                    <p x-show="relatedError" x-cloak role="alert" class="text-sm mt-2" style="color:var(--danger,#dc2626);" x-text="relatedError"></p>
                    <div class="grid gap-2 mt-3">
                        <label>Item type<select x-model="relatedKind" @change="loadRelated()" class="theme-input"><option value="link">Links &amp; pages</option><option value="mind">AI Minds</option><option value="staff">AI Staff</option><option value="calendar">Events &amp; calendars</option><option value="project">Projects</option><option value="contact">Contacts</option><option value="invoice">Client invoices</option><option value="file">Files</option></select></label>
                        <input class="theme-input" type="search" x-model="relatedQuery" @input.debounce.250ms="loadRelated()" placeholder="Search accessible Sayzio items…" aria-label="Search related items">
                        <button type="button" class="form-chip" @click="loadRelated()">Browse items</button>
                        <p class="notes-hint" x-show="relatedLoading">Loading…</p>
                        <div class="task-related-results"><template x-for="item in relatedResults" :key="item.type+':'+item.id"><button type="button" @click="connectRelated(item)" class="task-related-choice"><span x-text="item.title"></span><small>Connect +</small></button></template><p class="notes-hint" x-show="relatedLoaded && !relatedResults.length && !relatedLoading">No matching accessible items.</p></div>
                    </div>
                    <details class="mt-3"><summary class="text-xs">Connect another Sayzio page by URL</summary><form class="grid gap-2 mt-2" @submit.prevent="addRelatedItem($event)"><input name="type" type="hidden" value="other"><input class="theme-input" name="title" required maxlength="160" placeholder="Page name"><input class="theme-input" name="url" type="url" required placeholder="Full Sayzio page URL"><input class="theme-input" name="note" maxlength="500" placeholder="Optional context"><button class="form-chip">Connect page</button></form></details>
                </section>

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

                    {{-- Cloud-library attachments for this card. Shares the
                         workspace cloud file picker; bytes stay in the
                         provider, we just store a reference. --}}
                    <div class="mt-3" x-init="$watch('open', value => $dispatch('task-cloud-state', {open:value}))" x-data="cloudAttachPicker({
                            mode: 'ajax',
                            targetType: 'task_card',
                            targetId: card.id,
                            onAttached: (atts) => { card.cloud_attachments = (card.cloud_attachments || []).concat(atts); },
                        })">
                        <template x-for="a in (card.cloud_attachments || [])" :key="'cf' + a.id">
                            <div class="flex items-center justify-between gap-2 mb-1 p-2 rounded text-sm" style="background: var(--bg-glass-input);">
                                <a :href="a.link" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 flex-1 truncate" style="color: var(--text-primary);">
                                    <i :class="a.provider_icon" style="color: var(--text-muted);"></i>
                                    <span class="truncate" x-text="a.name"></span>
                                    <span class="text-xs" style="color: var(--text-faint);" x-text="a.provider_label + ' · ' + a.human_size"></span>
                                </a>
                                <button type="button" @click="detachCloudFile(a.id)" class="text-xs" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="show()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold border"
                                style="border-color: var(--border-strong); color: var(--text-primary);">
                            <i class="fas fa-cloud"></i> Attach from Cloud Files
                        </button>
                        @include('user.cloud-files._attach-modal', ['confirmLabel' => 'Attach to card'])
                    </div>
                </div>


</div>
<div x-show="editorTab==='activity'" x-cloak data-editor-panel="activity">
                <div class="mt-5">
                    {{-- Tabbed footer: Comments + Activity log. Activity rows
                         come from card.activities (server already eager-loads
                         them in showCard()). --}}
                    <div class="flex items-center gap-1 mb-2 border-b" style="border-color: var(--border-soft);">
                        <button type="button" @click="cardTab = 'comments'"
                                class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                :style="cardTab === 'comments'
                                    ? 'color: var(--text-primary); border-bottom: 2px solid #3d6bff;'
                                    : 'color: var(--text-faint);'">
                            <i class="fas fa-comment mr-1"></i> Comments
                            <span class="ml-1 opacity-70" x-text="(card.comments || []).length"></span>
                        </button>
                        <button type="button" @click="cardTab = 'activity'"
                                class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                :style="cardTab === 'activity'
                                    ? 'color: var(--text-primary); border-bottom: 2px solid #3d6bff;'
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
                                                ? 'background: rgba(61,107,255,0.12); color: var(--text-primary);'
                                                : 'color: var(--text-primary);'">
                                        <span class="w-5 h-5 rounded-full bg-blue-500/30 text-[10px] flex items-center justify-center font-bold uppercase"
                                              x-text="(m.name || '?').charAt(0)"></span>
                                        <span x-text="m.name"></span>
                                        <span class="ml-auto text-[10px] opacity-60">@<span x-text="m.mention_token"></span></span>
                                    </button>
                                </template>
                            </div>

                            <button class="mt-1 px-3 py-1 rounded text-xs font-semibold text-white" style="background: #3d6bff;">Post</button>
                        </form>
                    </div>

                    {{-- Activity pane --}}
                    <div x-show="cardTab === 'activity'" x-cloak>
                        <template x-if="!card.activities || card.activities.length === 0">
                            <p class="text-xs italic" style="color: var(--text-faint);">No activity yet.</p>
                        </template>
                        <ol class="space-y-2">
                            <template x-for="a in (card.activities || [])" :key="a.id">
                                <li class="flex items-start gap-2 text-xs" style="color: var(--text-muted);">
                                    <i class="fas fa-circle text-[6px] mt-1.5" style="color:#3d6bff;"></i>
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


</div>
<div x-show="editorTab==='time'" x-cloak data-editor-panel="time">
                <section class="notes-card"><h3 class="font-bold">Task time tracking</h3><p class="notes-hint">Track time even when the task is not billable. Attendance mode resumes on clock-in/end-break and stops on break/clock-out for this selected task.</p>@if(auth()->user()->canInWorkspace(app('current_workspace'),'tasks.track'))<label class="text-sm block my-3"><input type="checkbox" x-model="followAttendance"> Follow my attendance</label><div class="flex gap-2"><button class="form-chip" type="button" @click="startTimer()">Start / select task</button><button class="form-chip" type="button" @click="stopTimer()">Stop / unlink</button></div><p class="notes-hint mt-2" x-text="card.running_timer?'Timer running for you':'No timer running for you'"></p><form @submit.prevent="addManualEntry($event)" class="flex flex-wrap gap-2 mt-3"><input class="theme-input" name="minutes" type="number" required min="1" max="1440" placeholder="Minutes"><input class="theme-input" name="note" maxlength="240" placeholder="Work note"><button class="form-chip">Log time manually</button></form>@endif<div class="mt-3"><template x-for="entry in (card.time_entries||[])" :key="entry.id"><p class="text-xs py-1"><span x-text="entry.user?.name"></span> · <span x-text="entry.minutes+'m · '+entry.source"></span></p></template></div></section>
                <details class="mt-4"><summary>Optional billing settings</summary>
                {{-- Billing & time tracking ---------------------------------- --}}
                <div class="mt-5 pt-4 border-t" style="border-color: var(--border-soft);">
                    <h3 class="text-xs font-bold uppercase mb-2" style="color: var(--text-faint);">
                        <i class="fas fa-dollar-sign mr-1"></i> Billing
                    </h3>
                    <label class="flex items-center gap-2 text-sm mb-2" style="color: var(--text-primary);">
                        <input type="checkbox" :checked="!!card.billable"
                               @change="saveBilling({ billable: $event.target.checked })">
                        Billable card
                    </label>

                    <div x-show="card.billable" x-cloak class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-[10px] font-bold uppercase" style="color: var(--text-faint);">Rate type
                                <select :value="card.rate_type || 'hourly'"
                                        @change="saveBilling({ rate_type: $event.target.value })"
                                        class="w-full mt-1 px-2 py-1 text-sm rounded border"
                                        style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                                    <option value="hourly">Hourly</option>
                                    <option value="flat">Flat fee</option>
                                </select>
                            </label>
                            <label class="text-[10px] font-bold uppercase" style="color: var(--text-faint);">
                                Rate (in cents/minor)
                                <input type="number" min="0" :value="card.rate_amount_minor || 0"
                                       @blur="saveBilling({ rate_amount_minor: parseInt($event.target.value || 0) })"
                                       class="w-full mt-1 px-2 py-1 text-sm rounded border"
                                       style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                            </label>
                        </div>

                        <p class="notes-hint">Unbilled time: <span x-text="formatMinutes(card.unbilled_minutes||0)"></span>. Track work in the controls above.</p>
                        <template x-if="card.client_invoice_id">
                            <a :href="`/user/client-invoices/${card.client_invoice_id}`"
                               class="inline-flex items-center gap-1 text-xs font-semibold" style="color: #3d6bff;">
                                <i class="fas fa-receipt"></i> View invoice →
                            </a>
                        </template>
                    </div>
                </div>


</details>
</div>
                <div class="mt-6 pt-4 border-t flex justify-between" style="border-color: var(--border-soft);">
                    <button @click="destroyCard()" class="text-xs font-semibold" style="color:#ef4444;">
                        <i class="fas fa-trash mr-1"></i> Delete card
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- "Billed Column" picker modal -------------------------------------- --}}
    <div x-show="showBilledColumnPicker" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,.5);"
         @keydown.escape.window="showBilledColumnPicker = false">
        <div @click.outside="showBilledColumnPicker = false"
             class="rounded-2xl w-full max-w-md p-6"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <form action="{{ route('user.tasks.boards.billed-column', $board) }}" method="POST">
                @csrf @method('PUT')
                <h2 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Billed Column</h2>
                <p class="text-xs mb-4" style="color: var(--text-muted);">
                    Cards auto-move to this column when their invoice is paid.
                </p>
                <select name="column_id" class="w-full px-3 py-2 rounded-lg border"
                        style="background: var(--bg-glass-input); border-color: var(--border-strong); color: var(--text-primary);">
                    <option value="">None</option>
                    @foreach($board->columns as $col)
                        <option value="{{ $col->id }}" @selected($board->billed_column_id == $col->id)>{{ $col->name }}</option>
                    @endforeach
                </select>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="showBilledColumnPicker = false" class="px-3 py-2 rounded-lg text-sm" style="color: var(--text-muted);">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Hidden form used to POST card_ids[] to the draft endpoint --------- --}}
    <form x-ref="invoiceForm" action="{{ route('user.client-invoices.draft') }}" method="POST" class="hidden">
        @csrf
        <template x-for="id in selectedCardIds" :key="id">
            <input type="hidden" name="card_ids[]" :value="id">
        </template>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function kanbanBoard(boardId) {
    return {
        boardId,
        drawerOpen: false, editorTab: 'details', cloudPickerOpen:false, followAttendance:false, ganttAnchor:taskToday(), ganttDays:35,
        relatedKind:'link',relatedQuery:'',relatedResults:[],relatedLoading:false,relatedLoaded:false,
        boardView:sessionStorage.getItem('task-view-'+boardId)||'kanban', syncRevision:@js($syncRevision),syncStatus:'Checks every 15 seconds',syncTimer:null,notice:'',
        workflowOpen:false,workflowColumn:null,workflowRules:[],
        workflowColumns:@js($board->columns->map(fn($col)=>['id'=>$col->id,'name'=>$col->name,'transitions'=>$col->transitions??[]])->values()),
        openWorkflow(id){this.workflowColumn=id;this.workflowRules=JSON.parse(JSON.stringify(this.workflowColumns.find(c=>c.id===id).transitions));this.workflowOpen=true;},
        async saveWorkflow(){await this.fetchJson(`/user/tasks/columns/${this.workflowColumn}/transitions`,{method:'PUT',body:{transitions:this.workflowRules}});this.workflowOpen=false;location.reload();},
        showAddColumn: false,
        showBilledColumnPicker: false,
        selectedCardIds: [],
        newLabelOpen: false,
        boardLabels: @json($board->labels->map(fn($l) => ['id'=>$l->id,'name'=>$l->name,'color'=>$l->color])),
        @php($_boardMembers = $members->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'mention_token' => mb_strtolower(preg_replace('/\s+/', '', (string) $m->name)),
        ]))
        boardMembers: @json($_boardMembers),
        filters: { search: '', assignees: [], labels: [], due: '' },
        card: null,
        cardTab: 'comments',
        mentionOpen: false,
        mentionQuery: '',
        mentionStart: 0,
        mentionMatches: [],
        mentionIndex: 0,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        init() {
            const self = this;
            try{const stored=JSON.parse(sessionStorage.getItem('task-filters-'+boardId));if(stored)this.filters=stored;}catch{}
            // Re-apply filters whenever any filter value changes.
            this.$watch('filters', () => this.applyFilters(), { deep: true });
            this.applyFilters();
            this.$watch('boardView',value=>sessionStorage.setItem('task-view-'+boardId,value));
            this.syncTimer=setInterval(()=>this.checkUpdates(),15000);

            // Column drag-and-drop reorder (drag handle on each header).
            const colsEl = document.querySelector('[data-sortable-cols]');
            if (colsEl && typeof Sortable!=='undefined') {
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

            if(typeof Sortable!=='undefined') document.querySelectorAll('[data-sortable-cards]').forEach(el => {
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

        destroy(){clearInterval(this.syncTimer);},
        async checkUpdates(){
            if(document.hidden)return;
            try{
                const result=await this.fetchJson(`/user/tasks/boards/${this.boardId}/sync`);
                if(result.revision!==this.syncRevision){
                    const busy=this.drawerOpen||this.showAddColumn||this.showBilledColumnPicker||this.selectedCardIds.length||document.querySelector('.sortable-chosen')||document.querySelector('.kanban-col [x-show="editing"]:not([style*="display: none"])')||['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName);
                    if(busy){this.syncStatus='Updates available · finish editing, then refresh';return;}
                    sessionStorage.setItem('task-filters-'+this.boardId,JSON.stringify(this.filters));location.reload();
                }else this.syncStatus='Up to date · '+new Date(result.checked_at).toLocaleTimeString();
            }catch{this.syncStatus='Could not refresh updates. Try Refresh updates.';}
        },
        async setCompleted(completed){
            await this.saveQueue;
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/complete`,{method:'POST',body:{completed}});
            sessionStorage.setItem('task-filters-'+this.boardId,JSON.stringify(this.filters));location.reload();
        },
        async loadRelated(){const kind=this.relatedKind,query=this.relatedQuery;this.relatedLoading=true;this.relatedLoaded=true;try{const r=await this.fetchJson(`/user/tasks/boards/${this.boardId}/related-choices?kind=${kind}&q=${encodeURIComponent(query)}`);if(kind===this.relatedKind&&query===this.relatedQuery)this.relatedResults=r.items;}catch(e){this.relatedError=e.message;this.relatedResults=[];}finally{this.relatedLoading=false;}},
        async connectRelated(item){if(this.relatedSaving)return;this.relatedSaving=true;try{if((this.card.related_items||[]).some(r=>r.url===item.url))return;const items=[...(this.card.related_items||[]),{type:item.type,title:item.title,url:item.url,note:''}];await this.saveCard({related_items:items});this.card.related_items=items;}finally{this.relatedSaving=false;}},
        async exportView(format){const mode=this.boardView==='gantt'?'gantt':'board';const query=new URLSearchParams({mode,format:format==='png'?'svg':format,from:this.ganttAnchor,days:this.ganttDays});const url=`/user/tasks/boards/${this.boardId}/export?${query}`;if(format==='pdf'){window.open(url,'_blank','noopener');return;}const response=await fetch(url);if(!response.ok){this.notice='Export failed. Check your export permission or board size.';return;}const svg=await response.blob();const image=new Image();const src=URL.createObjectURL(svg);try{await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=reject;image.src=src;});const canvas=document.createElement('canvas');const scale=Math.min(1,8192/image.naturalWidth,16384/image.naturalHeight,Math.sqrt(32000000/(image.naturalWidth*image.naturalHeight)));canvas.width=Math.round(image.naturalWidth*scale);canvas.height=Math.round(image.naturalHeight*scale);canvas.getContext('2d').drawImage(image,0,0,canvas.width,canvas.height);const a=document.createElement('a');a.download='task-'+mode+'.png';a.href=canvas.toDataURL('image/png');a.click();}finally{URL.revokeObjectURL(src);}},
        async fetchJson(url, opts = {}) {
            const headers = { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(opts.headers || {}) };
            if (opts.body && !(opts.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(opts.body);
            }
            const r = await fetch(url, { ...opts, headers, credentials: 'same-origin' });
            if(!r.ok){let data;try{data=await r.json()}catch{}const error=new Error(data?.message||'Request failed. Please try again.');this.notice=error.message;throw error;}
            if(r.redirected||!r.headers.get('content-type')?.includes('application/json')){this.notice='Your session expired. Refresh and sign in again.';throw new Error(this.notice);}
            return r.json();
        },

        async onCardDrop(evt) {
            const cardId = evt.item.dataset.cardId;
            const columnId = evt.to.dataset.columnId;
            const position = evt.newIndex;
            try { await this.fetchJson(`/user/tasks/cards/${cardId}/move`, {
                method: 'POST',
                body: { column_id: parseInt(columnId), position },
            });location.reload();}catch(e){evt.from.insertBefore(evt.item,evt.from.children[evt.oldIndex]||null);}
        },

        setEditorTab(tab){this.editorTab=tab;this.$nextTick(()=>document.querySelector('.drawer').scrollTo({top:0}));if(tab==='connections'&&!this.relatedLoaded)this.loadRelated();},
        async openCard(id) {
            const same=this.card?.id===id;
            if(!same){this.editorTab='details';this.relatedResults=[];this.relatedLoaded=false;}
            const data = await this.fetchJson(`/user/tasks/cards/${id}`);
            this.card = data.card;
            this.card.start_date=this.card.start_date?this.card.start_date.substring(0,10):null;
            this.card.due_date = this.card.due_date ? this.card.due_date.substring(0, 10) : null;
            this.drawerOpen = true;
            this.$nextTick(() => this.initSubtaskSortable());
        },

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
            const byId = Object.fromEntries(this.card.subtasks.map(s => [s.id, s]));
            this.card.subtasks = order.map(id => byId[id]).filter(Boolean);
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/subtasks/reorder`,
                { method: 'POST', body: { order } });
        },

        relatedSaving:false, relatedError:'',
        async addRelatedItem(event) {
            const id=this.card.id;
            const form=event.target;
            const item=Object.fromEntries(new FormData(form));
            const items=[...(this.card.related_items || []),item];
            this.relatedSaving=true; this.relatedError='';
            try { await this.saveCard({related_items:items}); if(this.card?.id===id) this.card.related_items=items; form.reset(); }
            catch(error) { this.relatedError=error.message; }
            finally { this.relatedSaving=false; }
        },
        async removeRelatedItem(index) {
            if(this.relatedSaving)return;
            const id=this.card.id;
            const items=(this.card.related_items || []).filter((_,i)=>i!==index);
            this.relatedSaving=true; this.relatedError='';
            try { await this.saveCard({related_items:items}); if(this.card?.id===id) this.card.related_items=items; }
            catch(error) { this.relatedError=error.message; }
            finally { this.relatedSaving=false; }
        },
        saveQueue:Promise.resolve(),
        saveCard(payload) {
            if(!this.card)return;
            const id=this.card.id;
            this.saveQueue=this.saveQueue.catch(()=>{}).then(async()=>{
                if(this.card?.id!==id)return;
                const result=await this.fetchJson(`/user/tasks/cards/${id}`,{method:'PATCH',body:{...payload,expected_revision:this.card.revision}});
                if(this.card?.id===id) { const moved=result.card.column_id!==this.card.column_id;this.card.revision=result.revision;this.card.updated_at=result.card.updated_at;this.card.progress=Number(result.card.progress)||0;this.card.billable=result.card.billable;this.card.completed_at=result.card.completed_at;this.notice='';document.querySelectorAll(`[data-task-progress="${id}"]`).forEach(el=>{el.style.width=this.card.progress+'%';el.parentElement.setAttribute('aria-valuenow',this.card.progress);});window.dispatchEvent(new CustomEvent('task-progress-update',{detail:{id,progress:this.card.progress,done:!!this.card.completed_at}}));if(moved)location.reload(); }
            });return this.saveQueue;
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
            this.refreshActivities();
        },

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

        async detachCloudFile(id) {
            await this.fetchJson(`/user/cloud-files/attach/${id}`, { method: 'DELETE' });
            this.card.cloud_attachments = (this.card.cloud_attachments || []).filter(a => a.id !== id);
        },

        applyFilters() {
            const f = this.filters;
            const today = new Date(); today.setHours(0,0,0,0);
            const weekEnd = new Date(today); weekEnd.setDate(weekEnd.getDate() + 7);
            document.querySelectorAll('[data-task-filter]').forEach(card => {
                let show = true;
                if (f.search) {
                    const t = (card.dataset.cardTitle || '').toLowerCase();
                    if (!t.includes(f.search.toLowerCase())) show = false;
                }
                if (show && f.assignees.length) {
                    const ids = (card.dataset.cardAssignees || '').split(',').filter(Boolean);
                    if (!f.assignees.some(id=>ids.includes(String(id)))) show = false;
                }
                if (show && f.labels.length) {
                    const ids = (card.dataset.cardLabels || '').split(',').filter(Boolean);
                    if (!f.labels.some(id=>ids.includes(String(id)))) show = false;
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

        // ----- Billing / time tracking helpers -----
        toggleCardSelect(id, on) {
            const idx = this.selectedCardIds.indexOf(id);
            if (on && idx === -1) this.selectedCardIds.push(id);
            if (!on && idx !== -1) this.selectedCardIds.splice(idx, 1);
        },
        createInvoiceFromSelected() {
            if (!this.selectedCardIds.length) return;
            this.$nextTick(() => this.$refs.invoiceForm.submit());
        },
        formatMinutes(m) {
            m = parseInt(m || 0, 10);
            if (m < 60) return m + 'm';
            const h = Math.floor(m / 60), r = m % 60;
            return r ? `${h}h ${r}m` : `${h}h`;
        },
        async saveBilling(payload) { await this.saveCard(payload); },
        async startTimer() {
            const r = await this.fetchJson(`/user/tasks/cards/${this.card.id}/timer/start`, { method: 'POST',body:{follow_attendance:this.followAttendance} });
            if(r.waiting)this.notice='Selected task will track time on your next clock-in or end-break.';
            this.card.running_timer = r.entry ? { id: r.entry.id, started_at: r.entry.started_at } : null;
            await this.openCard(this.card.id);
        },
        async stopTimer() {
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/timer/stop`, { method: 'POST' });
            await this.openCard(this.card.id);
        },
        async addManualEntry(e) {
            const fd = new FormData(e.target);
            const minutes = parseInt(fd.get('minutes'), 10);
            if (!minutes || minutes < 1) return;
            await this.fetchJson(`/user/tasks/cards/${this.card.id}/time-entries`, {
                method: 'POST', body: { minutes, note: fd.get('note') || null },
            });
            e.target.reset();
            await this.openCard(this.card.id);
        },
        async destroyTimeEntry(id) {
            await this.fetchJson(`/user/tasks/time-entries/${id}`, { method: 'DELETE' });
            await this.openCard(this.card.id);
        },

        async destroyCard() {
            if (!await window.themedConfirmAsync({
                title: 'Delete this card?',
                message: 'This permanently removes the card and its history.',
                confirmText: 'Delete',
                confirmIcon: 'fa-trash',
                iconClass: 'fa-trash',
            })) return;
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
            if (!await window.themedConfirmAsync({
                title: 'Delete this column?',
                message: 'Cards inside will be moved to the next column.',
                confirmText: 'Delete',
                confirmIcon: 'fa-trash',
                iconClass: 'fa-trash',
            })) return;
            const fd = new FormData(); fd.append('_token', this.csrf); fd.append('_method', 'DELETE');
            await fetch(`/user/tasks/columns/${id}`, { method: 'POST', body: fd, credentials: 'same-origin' });
            location.reload();
        },

        async saveColumn(id, payload) {
            await this.fetchJson(`/user/tasks/columns/${id}`, { method: 'PUT', body: payload });
            location.reload();
        },
    };
}
</script>
@endsection
