<details open class="task-column-people" data-column-people="{{ $col->id }}">
    <summary><span title="Assignees and people involved in tasks currently in this column"><i class="fas fa-users" aria-hidden="true"></i> People</span><span>{{ count($columnPeople[$col->id] ?? []) }}</span></summary>
    <div class="task-column-people-list">
        @forelse($columnPeople[$col->id] ?? [] as $person)
            <details class="task-column-person">
                <summary><span class="task-person-initial" aria-hidden="true"><span>{{ mb_strtoupper(mb_substr($person['name'], 0, 1)) }}</span>@if($person['avatar'] ?? null)<img src="{{ $person['avatar'] }}" alt="" loading="lazy" onerror="this.hidden=true">@endif</span><span class="task-person-copy"><strong>{{ $person['name'] }}</strong><small>{{ implode(' · ', array_keys($person['roles'])) }} · {{ count($person['tasks']) }} {{ count($person['tasks']) === 1 ? 'task' : 'tasks' }}</small></span></summary>
                <div class="task-person-tasks">
                    @foreach($person['tasks'] as $task)
                        <button type="button" @click="openCard({{ $task['id'] }})"><strong>{{ $task['title'] }}</strong><small>{{ implode(' · ', array_keys($task['roles'])) }}</small></button>
                    @endforeach
                </div>
            </details>
        @empty
            <p class="task-column-people-hint">No assigned people or task activity yet.</p>
        @endforelse
    </div>
</details>
