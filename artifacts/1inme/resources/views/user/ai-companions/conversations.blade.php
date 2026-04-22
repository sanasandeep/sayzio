@extends('user.layouts.app')
@section('title', 'Conversations · ' . $companion->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-4">
    <div class="flex items-end justify-between gap-3">
        <div>
            <a href="{{ route('user.ai-companions.edit', $companion) }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> Back to Companion</a>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $companion->name }}</h1>
            <p class="text-xs text-white/50 mt-1">Conversations are anonymous unless the visitor shared name / email.</p>
        </div>
    </div>

    {{-- ── Analytics: top questions + answered rate + avg rating ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Answered (this month)</p>
            <p class="text-2xl font-bold text-white mt-1">{{ $answeredRate !== null ? $answeredRate . '%' : '—' }}</p>
            <p class="text-[11px] text-white/40 mt-1">Assistant turns ÷ visitor turns</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Avg rating</p>
            <p class="text-2xl font-bold text-white mt-1">{{ $avgRating > 0 ? number_format($avgRating, 2) : '—' }}</p>
            <p class="text-[11px] text-white/40 mt-1">Per-turn thumbs (1–5)</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Conversations</p>
            <p class="text-2xl font-bold text-white mt-1">{{ $conversations->total() }}</p>
            <p class="text-[11px] text-white/40 mt-1">All-time visitor sessions</p>
        </div>
    </div>

    @if(count($topQuestions))
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs uppercase tracking-wider text-white/40 mb-3">Top questions (last 30 days)</p>
            <ol class="space-y-2 text-sm text-white/90">
                @foreach($topQuestions as $q)
                    <li class="flex items-start gap-3">
                        <span class="inline-flex shrink-0 items-center justify-center w-6 h-6 rounded-full bg-violet-500/15 text-violet-300 text-[11px] font-semibold">{{ $q->n }}</span>
                        <span class="truncate">{{ $q->q }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] divide-y divide-white/5">
        @forelse($conversations as $conv)
            <a href="{{ route('user.ai-companions.conversation', [$companion, $conv]) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-white/[0.04]">
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-white truncate">
                        {{ $conv->visitor_name ?: 'Anonymous visitor' }}
                        @if($conv->visitor_email)<span class="text-white/40 text-xs">· {{ $conv->visitor_email }}</span>@endif
                    </p>
                    <p class="text-[11px] text-white/40">
                        {{ $conv->turns_count }} turn{{ $conv->turns_count === 1 ? '' : 's' }} ·
                        {{ $conv->credits_spent }} credits ·
                        last active {{ $conv->last_message_at?->diffForHumans() ?? '—' }}
                        @if($conv->source_origin) · {{ parse_url($conv->source_origin, PHP_URL_HOST) ?: $conv->source_origin }}@endif
                    </p>
                </div>
                <i class="fas fa-chevron-right text-white/30"></i>
            </a>
        @empty
            <div class="p-10 text-center text-sm text-white/40">No conversations yet.</div>
        @endforelse
    </div>

    {{ $conversations->links() }}
</div>
@endsection
