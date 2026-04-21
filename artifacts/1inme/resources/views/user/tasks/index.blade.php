@extends('user.layouts.app')
@section('title', 'Tasks')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8" x-data="{ showNew: false, scope: 'team' }">
    <div class="page-hero mb-6 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="hero-title">Tasks</h1>
            <p class="hero-subtitle">Personal to-do lists and shared kanban boards for your workspace.</p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showNew = true; scope = 'personal'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold border"
                    style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-user mr-1"></i> New Personal Board
            </button>
            <button @click="showNew = true; scope = 'team'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg,#7c3aed,#a78bfa);">
                <i class="fas fa-users mr-1"></i> New Team Board
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- New board modal --}}
    <div x-show="showNew" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.5);"
         @keydown.escape.window="showNew = false">
        <div @click.outside="showNew = false"
             class="rounded-2xl w-full max-w-md p-6"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <form action="{{ route('user.tasks.boards.store') }}" method="POST">
                @csrf
                <h2 class="text-lg font-bold mb-4" style="color: var(--text-primary);">
                    <span x-text="scope === 'personal' ? 'New Personal Board' : 'New Team Board'"></span>
                </h2>
                <input type="hidden" name="scope" :value="scope">
                <label class="block text-xs font-semibold mb-1" style="color: var(--text-muted);">Board Name</label>
                <input name="name" required maxlength="120" autofocus
                       class="w-full px-3 py-2 rounded-lg border"
                       style="background: var(--bg-glass-input); border-color: var(--border-strong); color: var(--text-primary);"
                       placeholder="e.g. Marketing Sprint, Launch Plan, My Week">
                <label class="block text-xs font-semibold mt-3 mb-1" style="color: var(--text-muted);">Accent Colour</label>
                <input name="color" type="color" value="#8b5cf6" class="w-16 h-8 rounded">
                <p class="text-xs mt-3" style="color: var(--text-faint);"
                   x-show="scope === 'personal'">Only you will see this board.</p>
                <p class="text-xs mt-3" style="color: var(--text-faint);"
                   x-show="scope === 'team'">Visible to every workspace member with task access.</p>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="showNew = false"
                            class="px-3 py-2 rounded-lg text-sm" style="color: var(--text-muted);">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background: linear-gradient(135deg,#7c3aed,#a78bfa);">Create Board</button>
                </div>
            </form>
        </div>
    </div>

    <section class="mb-8">
        <h2 class="text-sm font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-user mr-1"></i> Personal Boards
        </h2>
        @include('user.tasks.partials.board-grid', ['boards' => $personal, 'emptyMsg' => 'No personal boards yet.'])
    </section>

    <section>
        <h2 class="text-sm font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-users mr-1"></i> Team Boards
        </h2>
        @include('user.tasks.partials.board-grid', ['boards' => $team, 'emptyMsg' => 'No team boards yet — create one to share work with your workspace.'])
    </section>
</div>
@endsection
