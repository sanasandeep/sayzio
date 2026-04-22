@extends('admin.layouts.app')
@section('title', 'AI Companion Moderation')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-5">
    <div class="flex items-end justify-between gap-3">
        <div>
            <a href="{{ route('admin.ai-companions.index') }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> AI Companions</a>
            <h1 class="text-2xl font-bold text-white mt-1">Moderation queue</h1>
            <p class="text-xs text-white/50 mt-1">Messages flagged for abuse review. Use this to identify Companions whose visitors are sending toxic prompts or whose persona is producing unsafe replies.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.ai-companions.moderation', ['tab' => 'flagged']) }}" class="px-3 py-1.5 rounded-lg text-xs {{ $tab === 'flagged' ? 'bg-violet-500/20 text-violet-200' : 'bg-white/5 text-white/60 hover:text-white' }}">Flagged ({{ $counts['flagged'] }})</a>
            <a href="{{ route('admin.ai-companions.moderation', ['tab' => 'recent']) }}" class="px-3 py-1.5 rounded-lg text-xs {{ $tab === 'recent' ? 'bg-violet-500/20 text-violet-200' : 'bg-white/5 text-white/60 hover:text-white' }}">All recent (7d: {{ $counts['recent'] }})</a>
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] divide-y divide-white/5">
        @forelse($messages as $m)
            <div class="p-4 flex items-start gap-3">
                <div class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold {{ $m->role === 'assistant' ? 'bg-violet-500/15 text-violet-300' : 'bg-white/5 text-white/60' }}">
                    {{ $m->role === 'assistant' ? 'AI' : 'V' }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 text-[11px] text-white/40">
                        <span class="font-mono">{{ $m->conversation->companion->public_id ?? '—' }}</span>
                        <span>·</span>
                        <span>{{ $m->conversation->companion->name ?? 'unknown' }}</span>
                        <span>·</span>
                        <span>{{ $m->created_at->diffForHumans() }}</span>
                        @if($m->is_flagged)
                            <span class="ml-auto px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-300 font-semibold uppercase tracking-wider">Flagged</span>
                        @endif
                    </div>
                    <p class="mt-1.5 text-sm text-white/90 whitespace-pre-wrap break-words">{{ \Illuminate\Support\Str::limit($m->content, 600) }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        @if($m->is_flagged)
                            <form method="POST" action="{{ route('admin.ai-companions.messages.unflag', $m) }}">
                                @csrf
                                <button class="px-2.5 py-1 rounded-lg text-[11px] bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25">Clear flag</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.ai-companions.messages.flag', $m) }}">
                                @csrf
                                <button class="px-2.5 py-1 rounded-lg text-[11px] bg-rose-500/15 text-rose-300 hover:bg-rose-500/25">Flag</button>
                            </form>
                        @endif
                        @if($m->conversation->companion)
                            <form method="POST" action="{{ route('admin.ai-companions.disable', $m->conversation->companion) }}">
                                @csrf
                                <input type="hidden" name="reason" value="Flagged content (admin review)">
                                <button class="px-2.5 py-1 rounded-lg text-[11px] bg-white/5 text-white/70 hover:bg-white/10">Disable Companion</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm text-white/40">No messages match this filter.</div>
        @endforelse
    </div>

    <div>{{ $messages->links() }}</div>
</div>
@endsection
