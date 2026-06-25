@extends('user.layouts.app')

@section('title', 'Activity log')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">{{ $workspace->name }} — Activity</h1>
            <p class="text-sm opacity-70 mt-1">
                Audit who did what in this workspace. Filter the list and export the result to CSV.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.team.index') }}"
               class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
               style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-users mr-1"></i> Back to Team
            </a>
            <a href="{{ route('user.workspaces.activity.export', request()->query()) }}"
               class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('user.workspaces.activity.index') }}"
          class="mb-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-2 p-4 rounded-lg border"
          style="border-color: var(--border-strong); background: var(--bg-card);">
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">Member</label>
            <select name="member" class="w-full px-2 py-2 border rounded text-sm"
                    style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                <option value="">All members</option>
                @foreach($members as $m)
                    <option value="{{ $m['id'] }}" @selected((string) $filters['member'] === (string) $m['id'])>{{ $m['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">Action</label>
            <select name="action" class="w-full px-2 py-2 border rounded text-sm"
                    style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                <option value="">All actions</option>
                @foreach($actionList as $a)
                    <option value="{{ $a }}" @selected($filters['action'] === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">Object type</label>
            <select name="object_type" class="w-full px-2 py-2 border rounded text-sm"
                    style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                <option value="">All types</option>
                @foreach($objectTypes as $ot)
                    <option value="{{ $ot }}" @selected($filters['object_type'] === $ot)>{{ $ot }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">From</label>
            <input type="date" name="from" value="{{ $filters['from'] }}"
                   class="w-full px-2 py-2 border rounded text-sm"
                   style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">To</label>
            <input type="date" name="to" value="{{ $filters['to'] }}"
                   class="w-full px-2 py-2 border rounded text-sm"
                   style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1 opacity-70">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="object, IP…"
                   class="w-full px-2 py-2 border rounded text-sm"
                   style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
        </div>
        <div class="md:col-span-6 flex items-center gap-2 pt-1">
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded text-sm font-semibold">Apply filters</button>
            <a href="{{ route('user.workspaces.activity.index') }}" class="px-3 py-2 text-sm rounded border"
               style="border-color: var(--border-strong);">Clear</a>
            <span class="text-xs opacity-60 ml-auto">{{ $events->total() }} event(s) match</span>
        </div>
    </form>

    <div class="rounded-lg border overflow-x-auto" style="border-color: var(--border-strong); background: var(--bg-card);">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left opacity-70">
                    <th class="px-4 py-2">When</th>
                    <th class="px-4 py-2">Who</th>
                    <th class="px-4 py-2">Action</th>
                    <th class="px-4 py-2">Target</th>
                    <th class="px-4 py-2">IP / device</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $e)
                    @php $actor = $e->actor_user_id ? ($actors[$e->actor_user_id] ?? null) : null; @endphp
                    <tr class="border-t align-top" style="border-color: var(--border-strong);">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ optional($e->created_at)->format('M j, Y') }}</div>
                            <div class="text-xs opacity-60">{{ optional($e->created_at)->format('g:i:s A') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($actor)
                                <div class="font-medium">{{ $actor->name ?: $actor->email }}</div>
                                <div class="text-xs opacity-60">{{ $actor->email }}</div>
                            @else
                                <span class="opacity-60">system</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">{{ $e->action }}</span>
                            @if($e->object_type)
                                <div class="text-xs opacity-60 mt-1">{{ $e->object_type }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($e->object_url)
                                <a href="{{ $e->object_url }}" class="text-primary-600 hover:underline">{{ $e->object_label ?: ('#' . $e->object_id) }}</a>
                            @elseif($e->object_label)
                                {{ $e->object_label }}
                            @elseif($e->object_id)
                                <span class="opacity-60">#{{ $e->object_id }}</span>
                            @else
                                <span class="opacity-40">—</span>
                            @endif
                            @if(!empty($e->payload))
                                <details class="mt-1">
                                    <summary class="text-xs opacity-60 cursor-pointer">payload</summary>
                                    <pre class="mt-1 text-[10px] whitespace-pre-wrap break-all opacity-80">{{ json_encode($e->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs opacity-70">
                            <div>{{ $e->ip ?: '—' }}</div>
                            @if($e->user_agent)
                                <div class="opacity-60 truncate max-w-[240px]" title="{{ $e->user_agent }}">{{ $e->user_agent }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center opacity-60">No activity matches these filters yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $events->links() }}
    </div>
</div>
@endsection
