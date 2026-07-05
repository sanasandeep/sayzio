@php
    /** @var \App\Modules\User\Models\DeliveryProject $project */
    $tasks = $project->tasks;
    $overall = $project->progressPercent();

    // Task #3584 — schedule events. Client-facing pages (share link + portal)
    // inherently have project-level access, so the calendar always renders
    // here regardless of privacy tier.
    $calendarEvents = $project->relationLoaded('calendar') && $project->calendar
        ? $project->calendar->events->sortBy('start_at')
        : collect();

    // Build the timeline window from the earliest start/due to the latest.
    $dates = collect();
    foreach ($tasks as $t) {
        if ($t->start_date) $dates->push($t->start_date);
        if ($t->due_date)   $dates->push($t->due_date);
    }
    $hasTimeline = $dates->isNotEmpty();
    if ($hasTimeline) {
        $min = $dates->min();
        $max = $dates->max();
        $minTs = \Illuminate\Support\Carbon::parse($min)->startOfDay();
        $maxTs = \Illuminate\Support\Carbon::parse($max)->endOfDay();
        $spanDays = max(1, $minTs->diffInDays($maxTs));
    }

    $statusColors = [
        'todo'        => '#94a3b8',
        'in_progress' => '#3d6bff',
        'done'        => '#22c55e',
    ];
@endphp

<div class="dp-readonly">
    {{-- Overall progress --}}
    <div class="dp-card">
        <div class="dp-progress-head">
            <span class="dp-progress-label">Overall progress</span>
            <span class="dp-progress-value">{{ $overall }}%</span>
        </div>
        <div class="dp-bar"><div class="dp-bar-fill" style="width: {{ $overall }}%"></div></div>
        <div class="dp-progress-sub">{{ $tasks->where('status', 'done')->count() }} of {{ $tasks->count() }} tasks done</div>
    </div>

    {{-- Task list --}}
    <div class="dp-card">
        <h3 class="dp-card-title">Tasks</h3>
        @if($tasks->isEmpty())
            <p class="dp-empty">No tasks yet.</p>
        @else
            <table class="dp-table">
                <thead>
                    <tr>
                        <th>Task</th><th>Owner</th><th>Status</th><th>Dates</th><th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                        <tr>
                            <td class="dp-td-title">{{ $task->title }}</td>
                            <td>{{ optional($task->assignee)->name ?? '—' }}</td>
                            <td>
                                <span class="dp-chip" style="background: {{ $statusColors[$task->status] ?? '#94a3b8' }}1a; color: {{ $statusColors[$task->status] ?? '#64748b' }}">
                                    {{ $task->statusLabel() }}
                                </span>
                            </td>
                            <td class="dp-td-dates">
                                {{ $task->start_date ? $task->start_date->format('M j') : '—' }}
                                @if($task->due_date) &rarr; {{ $task->due_date->format('M j') }} @endif
                            </td>
                            <td>
                                <div class="dp-mini-bar"><div class="dp-mini-fill" style="width: {{ (int) $task->progress }}%"></div></div>
                                <span class="dp-mini-value">{{ (int) $task->progress }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Schedule / calendar --}}
    @if($calendarEvents->isNotEmpty())
        <div class="dp-card">
            <h3 class="dp-card-title">Schedule</h3>
            <div class="dp-schedule">
                @foreach($calendarEvents as $event)
                    <div class="dp-schedule-row">
                        <div class="dp-schedule-date">
                            {{ $event->start_at->format('M j') }}
                            @if(!$event->end_at->isSameDay($event->start_at)) &rarr; {{ $event->end_at->format('M j') }} @endif
                        </div>
                        <div class="dp-schedule-title">{{ $event->title }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gantt / timeline --}}
    @if($hasTimeline)
        <div class="dp-card">
            <h3 class="dp-card-title">Timeline</h3>
            <div class="dp-gantt">
                @foreach($tasks as $task)
                    @php
                        $s = $task->start_date ?: $task->due_date;
                        $e = $task->due_date ?: $task->start_date;
                        if (!$s && !$e) continue;
                        $sTs = \Illuminate\Support\Carbon::parse($s)->startOfDay();
                        $eTs = \Illuminate\Support\Carbon::parse($e)->endOfDay();
                        $left = $spanDays > 0 ? ($minTs->diffInDays($sTs) / $spanDays) * 100 : 0;
                        $width = $spanDays > 0 ? max(2, ($sTs->diffInDays($eTs) / $spanDays) * 100) : 100;
                        $left = max(0, min(100, $left));
                        $width = min(100 - $left, $width);
                    @endphp
                    <div class="dp-gantt-row">
                        <div class="dp-gantt-name" title="{{ $task->title }}">{{ \Illuminate\Support\Str::limit($task->title, 24) }}</div>
                        <div class="dp-gantt-track">
                            <div class="dp-gantt-bar" style="left: {{ $left }}%; width: {{ $width }}%; background: {{ $statusColors[$task->status] ?? '#3d6bff' }}">
                                <span class="dp-gantt-fill" style="width: {{ (int) $task->progress }}%"></span>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="dp-gantt-axis">
                    <span>{{ $minTs->format('M j, Y') }}</span>
                    <span>{{ $maxTs->format('M j, Y') }}</span>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .dp-readonly { display: flex; flex-direction: column; gap: 16px; }
    .dp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
    .dp-card-title { font-size: 14px; font-weight: 700; margin: 0 0 12px; color: #0f172a; }
    .dp-progress-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
    .dp-progress-label { font-size: 13px; font-weight: 600; color: #475569; }
    .dp-progress-value { font-size: 22px; font-weight: 800; color: #3d6bff; }
    .dp-progress-sub { font-size: 12px; color: #94a3b8; margin-top: 8px; }
    .dp-bar { height: 12px; background: #eef2ff; border-radius: 999px; overflow: hidden; }
    .dp-bar-fill { height: 100%; background: linear-gradient(90deg,#3d6bff,#90acff); border-radius: 999px; transition: width .3s; }
    .dp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .dp-table th { text-align: left; color: #94a3b8; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
    .dp-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .dp-td-title { font-weight: 600; color: #0f172a; }
    .dp-td-dates { white-space: nowrap; color: #64748b; }
    .dp-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .dp-mini-bar { display: inline-block; width: 70px; height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; vertical-align: middle; }
    .dp-mini-fill { display: block; height: 100%; background: #3d6bff; }
    .dp-mini-value { font-size: 11px; color: #64748b; margin-left: 6px; }
    .dp-empty { color: #94a3b8; font-size: 13px; }
    .dp-schedule { display: flex; flex-direction: column; gap: 6px; }
    .dp-schedule-row { display: flex; align-items: baseline; gap: 12px; font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
    .dp-schedule-row:last-child { border-bottom: none; }
    .dp-schedule-date { flex-shrink: 0; width: 130px; color: #64748b; font-weight: 600; }
    .dp-schedule-title { color: #0f172a; }
    .dp-gantt { display: flex; flex-direction: column; gap: 8px; }
    .dp-gantt-row { display: flex; align-items: center; gap: 12px; }
    .dp-gantt-name { width: 140px; flex-shrink: 0; font-size: 12px; color: #475569; }
    .dp-gantt-track { position: relative; flex: 1; height: 20px; background: #f8fafc; border-radius: 6px; }
    .dp-gantt-bar { position: absolute; top: 3px; height: 14px; border-radius: 6px; opacity: .85; overflow: hidden; }
    .dp-gantt-fill { display: block; height: 100%; background: rgba(255,255,255,.45); }
    .dp-gantt-axis { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; margin-top: 8px; padding-left: 152px; }
</style>
