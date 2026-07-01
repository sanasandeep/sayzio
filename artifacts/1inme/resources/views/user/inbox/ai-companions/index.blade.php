@extends('user.layouts.app')
@section('title', 'AI Companions · Inbox')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-4">
    <div class="flex items-end justify-between gap-3">
        <div>
            <a href="{{ route('user.inbox.dms.index') }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> Back to Inbox</a>
            <h1 class="text-2xl font-bold text-white mt-1">AI Companions</h1>
            <p class="text-xs text-white/50 mt-1">Chat with your AI Companions as if they were inbox contacts. Useful for testing prompts and knowledge before going live.</p>
        </div>
        <a href="{{ route('user.ai-companions.index') }}" class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white"><i class="fas fa-cog mr-1"></i>Manage</a>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] divide-y divide-white/5">
        @forelse($companions as $cmp)
            <a href="{{ route('user.inbox.ai-companions.show', $cmp) }}" class="flex items-center gap-3 p-4 hover:bg-white/[0.04]">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-300 bg-blue-500/15"><i class="fas fa-robot"></i></div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-white">{{ $cmp->name }}</p>
                    <p class="text-[11px] text-white/40">{{ $cmp->placement }} · {{ $cmp->is_disabled ? 'disabled' : 'active' }} · last used {{ $cmp->last_used_at?->diffForHumans() ?? 'never' }}</p>
                </div>
                <i class="fas fa-chevron-right text-white/30 text-xs"></i>
            </a>
        @empty
            <div class="p-8 text-center text-sm text-white/40">
                You haven't created any AI Companions yet.
                <a href="{{ route('user.ai-companions.create') }}" class="text-blue-400 hover:text-blue-300 font-semibold ml-1">Create one →</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
