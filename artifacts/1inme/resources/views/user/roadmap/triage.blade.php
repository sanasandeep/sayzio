@extends('layouts.app')

@section('title', 'Roadmap triage')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-6xl">
    <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <i class="fas fa-route text-indigo-500"></i>
                Roadmap, {{ $link->title ?? $link->alias }}
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Triage public submissions, push them to your kanban, ship and notify upvoters.</p>
        </div>
        <a href="{{ route('user.links.show', $link) }}" class="text-sm text-indigo-600 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Back to link
        </a>
    </div>

    @if(session('success'))
        <div class="mb-3 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    @if($blocks->isEmpty())
        <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-xl text-sm text-yellow-900">
            No Roadmap block found on this Link in Bio yet. Add one from the block editor and submissions will appear here.
        </div>
    @else
        <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
            @php
                $tabs = ['pending' => 'Pending', 'ideas' => 'Ideas', 'planned' => 'Planned', 'in_progress' => 'In Progress', 'shipped' => 'Shipped', 'rejected' => 'Rejected', 'merged' => 'Merged'];
            @endphp
            @foreach($tabs as $key => $label)
                @php $n = (int) ($counts[$key] ?? 0); @endphp
                <a href="{{ route('user.roadmap.triage', ['link' => $link, 'status' => $key, 'block_id' => $blockId]) }}"
                   class="px-3 py-1.5 rounded-full border {{ $status === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'hover:border-indigo-300' }}"
                   @if($status !== $key) style="background: var(--bg-card); color: var(--text-secondary); border-color: var(--border-glass);" @endif>
                    {{ $label }} <span class="ml-1 text-xs opacity-75">{{ $n }}</span>
                </a>
            @endforeach

            @if($blocks->count() > 1)
                <form method="GET" class="ml-auto">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <select name="block_id" onchange="this.form.submit()" class="text-sm rounded-lg border" style="background: var(--bg-glass-input); color: var(--text-primary); border-color: var(--border-glass);">
                        <option value="0">All blocks</option>
                        @foreach($blocks as $b)
                            <option value="{{ $b->id }}" {{ $blockId === $b->id ? 'selected' : '' }}>{{ data_get($b->settings, 'title') ?: ('Block #' . $b->id) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        <div class="space-y-3">
            @forelse($items as $item)
                <div class="border rounded-xl p-4 shadow-sm" style="background: var(--bg-card); border-color: var(--border-glass);">
                    <div class="flex items-start gap-3">
                        <div class="text-center px-2 py-1 rounded-lg min-w-[52px]" style="background: var(--bg-glass);">
                            <div class="text-xl font-bold text-indigo-600 tabular-nums">{{ $item->votes_count }}</div>
                            <div class="text-[10px] uppercase tracking-wider" style="color: var(--text-muted);">votes</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold" style="color: var(--text-primary);">{{ $item->title }}</h3>
                            @if($item->description)
                                <p class="text-sm mt-1 whitespace-pre-line" style="color: var(--text-muted);">{{ \Illuminate\Support\Str::limit($item->description, 400) }}</p>
                            @endif
                            <div class="text-xs mt-2 flex flex-wrap gap-3" style="color: var(--text-muted);">
                                <span><i class="fas fa-clock mr-1"></i>{{ $item->created_at->diffForHumans() }}</span>
                                @if($item->submitter_name)
                                    <span><i class="fas fa-user mr-1"></i>{{ $item->submitter_name }}</span>
                                @endif
                                @if($item->submitter_email)
                                    <span><i class="fas fa-envelope mr-1"></i>{{ $item->submitter_email }}</span>
                                @endif
                                @if($item->task_card_id)
                                    <span class="text-green-700"><i class="fas fa-link mr-1"></i>Linked to kanban card #{{ $item->task_card_id }}</span>
                                @endif
                                @if($item->shipped_at)
                                    <span class="text-emerald-700"><i class="fas fa-rocket mr-1"></i>Shipped {{ $item->shipped_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('user.roadmap.update', ['link' => $link, 'item' => $item]) }}" class="mt-3 flex flex-wrap gap-2 items-center border-t pt-3" style="border-color: var(--border-glass);">
                        @csrf @method('PATCH')
                        <select name="status" class="text-sm rounded-lg border" style="background: var(--bg-glass-input); color: var(--text-primary); border-color: var(--border-glass);">
                            @foreach(\App\Modules\User\Models\RoadmapItem::STATUSES as $k => $label)
                                <option value="{{ $k }}" {{ $item->status === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="text-xs flex items-center gap-1" style="color: var(--text-secondary);">
                            <input type="checkbox" name="sync_to_kanban" value="1" checked class="rounded text-indigo-600"> Sync to kanban
                        </label>
                        <label class="text-xs flex items-center gap-1" style="color: var(--text-secondary);">
                            <input type="checkbox" name="is_blocked" value="1" {{ $item->is_blocked ? 'checked' : '' }} class="rounded text-red-500"> Hide from public
                        </label>
                        <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                    </form>

                    <div class="mt-2 flex flex-wrap gap-2 items-center">
                        <form method="POST" action="{{ route('user.roadmap.merge', ['link' => $link, 'item' => $item]) }}" class="flex items-center gap-1">
                            @csrf
                            <input type="number" name="into_id" placeholder="Merge into ID" class="text-xs rounded-lg border w-32" min="1" style="background: var(--bg-glass-input); color: var(--text-primary); border-color: var(--border-glass);">
                            <button type="submit" class="text-xs px-2 py-1 rounded-lg border btn-ghost" style="border-color: var(--border-glass);">
                                <i class="fas fa-code-branch mr-1"></i> Merge
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.roadmap.destroy', ['link' => $link, 'item' => $item]) }}" onsubmit="return confirm('Delete this idea, its votes and comments?');" class="ml-auto">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs px-2 py-1 rounded-lg text-red-600 hover:bg-red-50">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-6 border border-dashed rounded-xl text-center text-sm" style="background: var(--bg-glass); border-color: var(--border-glass); color: var(--text-muted);">
                    No items in this status.
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</div>
@endsection
