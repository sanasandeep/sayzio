@extends('user.layouts.app')
@section('title', 'Conversation · ' . $companion->name)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-4">
    <div>
        <a href="{{ route('user.ai-companions.conversations', $companion) }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> Back</a>
        <h1 class="text-xl font-bold text-white mt-1">
            {{ $conversation->visitor_name ?: 'Anonymous visitor' }}
            @if($conversation->visitor_email)<span class="text-white/40 text-sm">· {{ $conversation->visitor_email }}</span>@endif
        </h1>
        <p class="text-[11px] text-white/40">
            {{ $conversation->turns_count }} turns · {{ $conversation->credits_spent }} credits ·
            opened {{ $conversation->created_at?->diffForHumans() }}
            @if($conversation->source_origin) from {{ $conversation->source_origin }}@endif
        </p>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-3">
        @forelse($messages as $m)
            <div class="flex {{ $m->role === 'user' ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm {{ $m->role === 'user' ? 'bg-white/10 text-white' : 'bg-violet-600/30 text-white' }}">
                    <div class="text-[10px] uppercase tracking-wider opacity-50 mb-1">{{ $m->role === 'user' ? 'Visitor' : 'AI' }} · {{ $m->created_at->diffForHumans() }}</div>
                    <div class="whitespace-pre-wrap">{{ $m->content }}</div>
                    @if(!empty($m->citations))
                        <div class="text-[10px] opacity-60 mt-2">Sources: {{ collect($m->citations)->pluck('title')->filter()->join(', ') }}</div>
                    @endif
                    @if($m->credits_spent > 0)
                        <div class="text-[10px] opacity-50 mt-1">{{ $m->credits_spent }} credits</div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-white/40 text-center py-6">No messages.</p>
        @endforelse
    </div>
</div>
@endsection
