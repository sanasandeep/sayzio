@extends('admin.layouts.app')
@section('title', 'Conversation #'.$conversation->id)
@section('page-title', 'Conversation #'.$conversation->id)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="text-sm text-white/60"><a href="{{ route('admin.site-assistant.conversations') }}" class="hover:text-white">← All conversations</a></div>

    <div class="glass rounded-2xl border border-white/10 p-6 grid md:grid-cols-3 gap-3 text-sm text-white/80">
        <div><span class="text-white/40 text-xs">Visitor:</span> {{ $conversation->visitor_email ?: $conversation->visitor_name ?: '—' }}</div>
        <div><span class="text-white/40 text-xs">Surface:</span> {{ $conversation->surface }}</div>
        <div><span class="text-white/40 text-xs">Last route:</span> <code class="text-xs">{{ $conversation->last_route ?: '—' }}</code></div>
        <div><span class="text-white/40 text-xs">Turns:</span> {{ $conversation->turns_count }}</div>
        <div><span class="text-white/40 text-xs">Credits:</span> {{ $conversation->credits_spent }}</div>
        <div><span class="text-white/40 text-xs">Started:</span> {{ optional($conversation->created_at)->toDayDateTimeString() }}</div>
        @if($conversation->handed_off)<div class="md:col-span-3 text-amber-300 text-xs">Handed off → ContactMessage #{{ $conversation->contact_message_id }}</div>@endif
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        @forelse($conversation->messages as $m)
            <div class="flex {{ $m->role==='user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-xl p-3 {{ $m->role==='user' ? 'bg-purple-600/30 text-white' : 'bg-white/5 text-white/90' }}">
                    <div class="text-[10px] uppercase tracking-wide text-white/40 mb-1">{{ $m->role }} · {{ optional($m->created_at)->diffForHumans() }}</div>
                    <div class="whitespace-pre-wrap text-sm">{{ $m->content }}</div>
                    @if($m->blocks)<details class="mt-2 text-xs text-white/50"><summary>Blocks</summary><pre class="overflow-auto">{{ json_encode($m->blocks, JSON_PRETTY_PRINT) }}</pre></details>@endif
                    @if($m->citations)<details class="mt-2 text-xs text-white/50"><summary>Citations ({{ count($m->citations) }})</summary><pre class="overflow-auto">{{ json_encode($m->citations, JSON_PRETTY_PRINT) }}</pre></details>@endif
                </div>
            </div>
        @empty
            <div class="text-center text-white/40 text-sm">No messages yet.</div>
        @endforelse
    </div>

    <div class="flex gap-2">
        @if($conversation->is_disabled)
            <form method="POST" action="{{ route('admin.site-assistant.conversations.enable', $conversation) }}">@csrf<button class="px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-300 text-sm">Re-enable</button></form>
        @else
            <form method="POST" action="{{ route('admin.site-assistant.conversations.disable', $conversation) }}">@csrf<button class="px-4 py-2 rounded-xl bg-red-500/20 text-red-300 text-sm">Disable conversation</button></form>
        @endif
    </div>
</div>
@endsection
