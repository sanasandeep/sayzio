@extends('user.layouts.app')
@section('title', 'Companion')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Companion',
        'title'    => 'Chat with Companion',
        'subtitle' => 'Ask anything — Companion remembers the recent turns of your conversation.',
        'balance'  => $balance,
    ])

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3 max-h-[60vh] overflow-y-auto">
        @if(empty($history))
            <p class="text-sm text-white/40 text-center py-8">No messages yet. Say hello below.</p>
        @else
            @foreach($history as $turn)
                <div class="flex {{ $turn['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm
                        {{ $turn['role'] === 'user' ? 'bg-violet-600 text-white' : 'bg-white/10 text-white/90' }}">
                        <pre class="whitespace-pre-wrap font-sans">{{ $turn['content'] }}</pre>
                        @if(($turn['role'] ?? null) === 'assistant' && !empty($turn['meta']['credits_spent']))
                            <p class="text-[10px] text-white/40 mt-1">{{ number_format($turn['meta']['credits_spent']) }} ✦</p>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <form method="POST" action="{{ route('user.ai.companion.send') }}"
          class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3 flex gap-2">
        @csrf
        <input type="text" name="message" required maxlength="2000" autofocus
               placeholder="Type a message…"
               class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
        <button class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">
            Send
        </button>
    </form>
    @error('message')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror

    @if(!empty($history))
        <form method="POST" action="{{ route('user.ai.companion.reset') }}" class="mt-3 text-right">
            @csrf
            <button class="text-xs text-white/40 hover:text-red-300">Clear conversation</button>
        </form>
    @endif
</div>
@endsection
