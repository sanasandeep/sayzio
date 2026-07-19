@extends('user.layouts.app')
@section('title', $project->title)
@section('content')
@php
    $initialTasks = $project->tasks->map(fn ($t) => [
        'id' => $t->id,
        'title' => $t->title,
        'status' => $t->status,
        'progress' => (int) $t->progress,
        'assignee_user_id' => $t->assignee_user_id ? (int) $t->assignee_user_id : null,
        'assignee_name' => optional($t->assignee)->name,
        'start_date' => optional($t->start_date)->toDateString(),
        'due_date' => optional($t->due_date)->toDateString(),
        'position' => (int) $t->position,
    ])->values();
@endphp
<div class="max-w-6xl mx-auto px-4 py-8"
     x-data="deliveryProject({
        projectId: {{ $project->id }},
        tasks: {{ Illuminate\Support\Js::from($initialTasks) }},
        members: {{ Illuminate\Support\Js::from($members) }},
        statuses: {{ Illuminate\Support\Js::from($statuses) }},
        urls: {
            storeTask: '{{ route('user.delivery-projects.tasks.store', $project) }}',
            updateTask: '{{ url('user/delivery-projects/tasks') }}',
            reorderTasks: '{{ route('user.delivery-projects.tasks.reorder', $project) }}',
        },
        csrf: '{{ csrf_token() }}'
     })">

    <div class="page-hero mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <a href="{{ route('user.delivery-projects.index') }}" class="text-xs mb-1 inline-block" style="color: var(--text-tertiary);"><i class="fas fa-arrow-left mr-1"></i> All projects</a>
            <h1 class="hero-title">{{ $project->title }}</h1>
            <p class="hero-subtitle">
                {{ $project->statusLabel() }}
                @if($project->client_name) · Client: {{ $project->client_name }} @endif
                @if($project->sourceLabel()) · From {{ $project->sourceLabel() }} @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('user.delivery-projects.update', $project) }}">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $project->status === 'completed' ? 'active' : 'completed' }}">
                <button class="px-3 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-{{ $project->status === 'completed' ? 'rotate-left' : 'check' }} mr-1"></i>
                    {{ $project->status === 'completed' ? 'Reopen' : 'Mark completed' }}
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm" style="background: rgba(34,197,94,.12); color:#16a34a;">{{ session('success') }}</div>
    @endif

    {{-- Overall progress --}}
    <div class="glass-card rounded-2xl p-5 mb-4" style="border:1px solid var(--border);">
        <div class="flex justify-between items-baseline mb-2">
            <span class="text-sm font-semibold" style="color: var(--text-secondary);">Overall progress</span>
            <span class="text-2xl font-extrabold" style="color:#3d6bff;" x-text="overall + '%'"></span>
        </div>
        <div class="h-3 rounded-full overflow-hidden" style="background: var(--surface-2);">
            <div class="h-full rounded-full" :style="`width: ${overall}%; background: linear-gradient(90deg,#3d6bff,#90acff);`"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Task list --}}
        <div class="lg:col-span-2 glass-card rounded-2xl p-5" style="border:1px solid var(--border);">
            <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Tasks</h3>

            <div class="space-y-2 mb-4">
                <template x-for="task in tasks" :key="task.id">
                    <div class="rounded-lg p-3" style="background: var(--surface-2); border:1px solid var(--border);">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="flex flex-col -my-1 mr-0.5">
                                <button type="button" @click="moveTask(task, -1)" :disabled="isFirst(task)"
                                        class="text-[10px] leading-none py-0.5 opacity-40 hover:opacity-100 disabled:opacity-10 disabled:cursor-not-allowed"
                                        style="color: var(--text-secondary);" title="Move up"><i class="fas fa-chevron-up"></i></button>
                                <button type="button" @click="moveTask(task, 1)" :disabled="isLast(task)"
                                        class="text-[10px] leading-none py-0.5 opacity-40 hover:opacity-100 disabled:opacity-10 disabled:cursor-not-allowed"
                                        style="color: var(--text-secondary);" title="Move down"><i class="fas fa-chevron-down"></i></button>
                            </div>
                            <input x-model="task.title" @change="saveTask(task, {title: task.title})"
                                   class="flex-1 bg-transparent text-sm font-medium outline-none" style="color: var(--text-primary);">
                            <button @click="removeTask(task)" class="text-xs opacity-50 hover:opacity-100" style="color:#ef4444;"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <select x-model="task.status" @change="saveTask(task, {status: task.status})"
                                    class="rounded px-2 py-1" style="background: var(--surface); border:1px solid var(--border); color: var(--text-secondary);">
                                <template x-for="(label, key) in statuses" :key="key">
                                    <option :value="key" x-text="label"></option>
                                </template>
                            </select>
                            <select x-model="task.assignee_user_id" @change="saveTask(task, {assignee_user_id: task.assignee_user_id})"
                                    class="rounded px-2 py-1" style="background: var(--surface); border:1px solid var(--border); color: var(--text-secondary);">
                                <option :value="null">Unassigned</option>
                                <template x-for="m in members" :key="m.user_id">
                                    <option :value="m.user_id" x-text="m.name"></option>
                                </template>
                            </select>
                            <input type="date" x-model="task.start_date" @change="saveTask(task, {start_date: task.start_date})"
                                   class="rounded px-2 py-1" style="background: var(--surface); border:1px solid var(--border); color: var(--text-secondary);">
                            <input type="date" x-model="task.due_date" @change="saveTask(task, {due_date: task.due_date})"
                                   class="rounded px-2 py-1" style="background: var(--surface); border:1px solid var(--border); color: var(--text-secondary);">
                            <div class="flex items-center gap-1">
                                <input type="range" min="0" max="100" step="5" x-model.number="task.progress"
                                       @change="saveTask(task, {progress: task.progress})" style="width:80px;">
                                <span x-text="task.progress + '%'" style="color: var(--text-tertiary);"></span>
                            </div>
                        </div>
                    </div>
                </template>
                <p x-show="tasks.length === 0" class="text-sm" style="color: var(--text-tertiary);">No tasks yet, add your first below.</p>
            </div>

            <form @submit.prevent="addTask()" class="flex gap-2">
                <input x-model="newTitle" placeholder="Add a task…" required
                       class="flex-1 rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
                <button class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">Add</button>
            </form>
        </div>

        {{-- Sharing + warranty --}}
        <div class="space-y-4">
            <div class="glass-card rounded-2xl p-5" style="border:1px solid var(--border);">
                <h3 class="font-semibold mb-2" style="color: var(--text-primary);">Share with buyer</h3>
                <p class="text-xs mb-2" style="color: var(--text-tertiary);">Anyone with this read-only link can view progress.</p>
                <div class="flex gap-2">
                    <input readonly value="{{ $shareUrl }}" id="dp-share-url"
                           class="flex-1 rounded-lg px-3 py-2 text-xs" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-secondary);">
                    <button onclick="navigator.clipboard.writeText(document.getElementById('dp-share-url').value)"
                            class="px-3 py-2 rounded-lg text-xs font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);"><i class="fas fa-copy"></i></button>
                </div>
                <form method="POST" action="{{ route('user.delivery-projects.share-token', $project) }}" class="mt-2">
                    @csrf
                    <button class="text-xs" style="color: var(--text-tertiary);"><i class="fas fa-rotate mr-1"></i>Regenerate link</button>
                </form>
            </div>

            <div class="glass-card rounded-2xl p-5" style="border:1px solid var(--border);">
                <h3 class="font-semibold mb-2" style="color: var(--text-primary);">Calendar privacy</h3>
                <p class="text-xs mb-3" style="color: var(--text-tertiary);">Task start/due dates are automatically kept as calendar events. Choose who can see this project's schedule.</p>
                <form method="POST" action="{{ route('user.delivery-projects.calendar-privacy', $project) }}" class="space-y-2" x-data="{ privacy: '{{ $project->calendarPrivacy() }}' }">
                    @csrf @method('PUT')
                    @foreach($calendarPrivacies as $value => $label)
                        <label class="flex items-start gap-2 rounded-lg p-2 cursor-pointer" style="background: var(--surface-2); border:1px solid var(--border);">
                            <input type="radio" name="privacy" value="{{ $value }}" x-model="privacy" @change="$el.closest('form').submit()" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold" style="color: var(--text-primary);">{{ $label }}</span>
                                <span class="block text-xs" style="color: var(--text-tertiary);">{{ $calendarPrivacyCopy[$value] }}</span>
                            </span>
                        </label>
                    @endforeach
                    @if($project->calendarPrivacy() === \App\Modules\User\Models\DeliveryProject::CALENDAR_PRIVACY_PUBLIC && $project->calendar)
                        <div class="flex gap-2 pt-1">
                            <input readonly value="{{ route('public.calendars.ics', $project->calendar->id) }}" id="dp-calendar-feed-url"
                                   class="flex-1 rounded-lg px-3 py-2 text-xs" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-secondary);">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('dp-calendar-feed-url').value)"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);"><i class="fas fa-copy"></i></button>
                        </div>
                    @endif
                </form>
            </div>

            <div class="glass-card rounded-2xl p-5" style="border:1px solid var(--border);">
                <h3 class="font-semibold mb-2" style="color: var(--text-primary);">Warranty</h3>
                <form method="POST" action="{{ route('user.delivery-projects.update', $project) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs mb-1" style="color: var(--text-tertiary);">Expires on</label>
                        <input type="date" name="warranty_expires_at" value="{{ optional($project->warranty_expires_at)->toDateString() }}"
                               class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="block text-xs mb-1" style="color: var(--text-tertiary);">Remind days before</label>
                        <input type="number" name="warranty_reminder_days" min="0" max="365" value="{{ $project->warranty_reminder_days }}"
                               class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
                    </div>
                    <button class="text-sm font-semibold px-3 py-2 rounded-lg border w-full" style="border-color: var(--border-strong); color: var(--text-primary);">Save warranty</button>
                    @if($project->warranty_expires_at)
                        <p class="text-xs {{ $project->warrantyExpired() ? 'text-red-500' : '' }}" style="color: {{ $project->warrantyExpired() ? '#ef4444' : 'var(--text-tertiary)' }};">
                            <i class="fas fa-shield-halved mr-1"></i>{{ $project->warrantyExpired() ? 'Expired' : 'Active until' }} {{ $project->warranty_expires_at->format('M j, Y') }}
                        </p>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- Client conversation --}}
    @php
        $needsReply = $project->unansweredClientCount();
        $lastTeamId = (int) $project->comments->where('author_role', 'team')->max('id');
    @endphp
    <div class="glass-card rounded-2xl p-5 mt-4" style="border:1px solid var(--border);">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
            <h3 class="font-semibold" style="color: var(--text-primary);">Conversation with client</h3>
            @if($needsReply > 0)
                <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold inline-flex items-center gap-1"
                      style="background: rgba(239,68,68,.12); color:#ef4444;">
                    <i class="fas fa-reply"></i> {{ $needsReply }} awaiting reply
                </span>
            @endif
        </div>
        <p class="text-xs mb-3" style="color: var(--text-tertiary);">Questions from your buyer show up here. Replies are emailed to them.</p>

        <div class="space-y-3 mb-4">
            @forelse($project->comments as $comment)
                @php $awaiting = !$comment->isTeam() && (int) $comment->id > $lastTeamId; @endphp
                <div class="rounded-lg p-3" style="background: var(--surface-2); border:1px solid {{ $awaiting ? '#ef4444' : 'var(--border)' }};">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold" style="color: {{ $comment->isTeam() ? '#3d6bff' : 'var(--text-primary)' }};">
                            <i class="fas fa-{{ $comment->isTeam() ? 'headset' : 'user' }} mr-1"></i>{{ $comment->displayName() }}
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium" style="background: var(--surface); color: var(--text-tertiary);">{{ $comment->isTeam() ? 'Team' : 'Client' }}</span>
                            @if($awaiting)
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold" style="background: rgba(239,68,68,.12); color:#ef4444;">Needs reply</span>
                            @endif
                        </span>
                        <span class="text-[11px]" style="color: var(--text-tertiary);">{{ optional($comment->created_at)->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm whitespace-pre-line" style="color: var(--text-secondary);">{{ $comment->body }}</p>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-tertiary);">No messages yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('user.delivery-projects.comments.store', $project) }}" class="flex gap-2">
            @csrf
            <input name="body" required maxlength="2000" placeholder="Reply to the client…"
                   class="flex-1 rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            <button class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">Send</button>
        </form>
    </div>

    {{-- Gantt timeline --}}
    <div class="glass-card rounded-2xl p-5 mt-4" style="border:1px solid var(--border);">
        <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Timeline</h3>
        <div x-show="timelineTasks.length === 0" class="text-sm" style="color: var(--text-tertiary);">Add start/due dates to tasks to see the timeline.</div>
        <div x-show="timelineTasks.length > 0" class="dp-gantt">
            <template x-for="t in timelineTasks" :key="t.id">
                <div class="dp-gantt-row">
                    <div class="dp-gantt-name" x-text="t.title"></div>
                    <div class="dp-gantt-track">
                        <div class="dp-gantt-bar" :style="`left:${t._left}%; width:${t._width}%; background:${statusColor(t.status)}`">
                            <span class="dp-gantt-fill" :style="`width:${t.progress}%`"></span>
                        </div>
                    </div>
                </div>
            </template>
            <div class="dp-gantt-axis">
                <span x-text="axisMin"></span><span x-text="axisMax"></span>
            </div>
        </div>
    </div>
</div>

<style>
    .dp-gantt { display:flex; flex-direction:column; gap:8px; }
    .dp-gantt-row { display:flex; align-items:center; gap:12px; }
    .dp-gantt-name { width:140px; flex-shrink:0; font-size:12px; color: var(--text-secondary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dp-gantt-track { position:relative; flex:1; height:20px; background: var(--surface-2); border-radius:6px; }
    .dp-gantt-bar { position:absolute; top:3px; height:14px; border-radius:6px; opacity:.9; overflow:hidden; }
    .dp-gantt-fill { display:block; height:100%; background: rgba(255,255,255,.45); }
    .dp-gantt-axis { display:flex; justify-content:space-between; font-size:11px; color: var(--text-tertiary); margin-top:8px; padding-left:152px; }
</style>

<script>
function deliveryProject(cfg) {
    return {
        projectId: cfg.projectId,
        tasks: cfg.tasks,
        members: cfg.members,
        statuses: cfg.statuses,
        urls: cfg.urls,
        csrf: cfg.csrf,
        newTitle: '',
        axisMin: '', axisMax: '',

        get overall() {
            if (!this.tasks.length) return 0;
            return Math.round(this.tasks.reduce((s, t) => s + (t.progress || 0), 0) / this.tasks.length);
        },

        statusColor(s) {
            return ({ todo: '#94a3b8', in_progress: '#3d6bff', done: '#22c55e' })[s] || '#3d6bff';
        },

        isFirst(task) { return this.tasks.length > 0 && this.tasks[0].id === task.id; },
        isLast(task) { return this.tasks.length > 0 && this.tasks[this.tasks.length - 1].id === task.id; },

        async moveTask(task, dir) {
            const i = this.tasks.findIndex(t => t.id === task.id);
            const j = i + dir;
            if (i < 0 || j < 0 || j >= this.tasks.length) return;
            const arr = this.tasks.slice();
            [arr[i], arr[j]] = [arr[j], arr[i]];
            this.tasks = arr;
            await fetch(this.urls.reorderTasks, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ order: this.tasks.map(t => t.id) }),
            });
        },

        get timelineTasks() {
            const dated = this.tasks.filter(t => t.start_date || t.due_date);
            if (!dated.length) { this.axisMin = ''; this.axisMax = ''; return []; }
            const toTs = d => new Date(d + 'T00:00:00').getTime();
            let min = Infinity, max = -Infinity;
            dated.forEach(t => {
                const s = toTs(t.start_date || t.due_date);
                const e = toTs(t.due_date || t.start_date);
                if (s < min) min = s;
                if (e > max) max = e;
            });
            const span = Math.max(1, (max - min));
            this.axisMin = new Date(min).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            this.axisMax = new Date(max).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            return dated.map(t => {
                const s = toTs(t.start_date || t.due_date);
                const e = toTs(t.due_date || t.start_date);
                let left = ((s - min) / span) * 100;
                let width = Math.max(3, ((e - s) / span) * 100);
                left = Math.max(0, Math.min(100, left));
                width = Math.min(100 - left, width);
                return { ...t, _left: left, _width: width };
            });
        },

        async addTask() {
            if (!this.newTitle.trim()) return;
            const res = await fetch(this.urls.storeTask, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ title: this.newTitle.trim() }),
            });
            const data = await res.json();
            if (data.ok) { this.tasks.push(data.task); this.newTitle = ''; }
        },

        async saveTask(task, patch) {
            await fetch(`${this.urls.updateTask}/${task.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(patch),
            }).then(r => r.json()).then(d => {
                if (d.ok && d.task) {
                    task.status = d.task.status;
                    task.progress = d.task.progress;
                    task.assignee_name = d.task.assignee_name;
                }
            });
        },

        async removeTask(task) {
            if (!confirm('Delete this task?')) return;
            await fetch(`${this.urls.updateTask}/${task.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            this.tasks = this.tasks.filter(t => t.id !== task.id);
        },
    };
}
</script>
@endsection
