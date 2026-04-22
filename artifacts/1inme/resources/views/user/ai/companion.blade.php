@extends('user.layouts.app')
@section('title', 'Companion')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Companion',
        'title'    => 'Chat with Companion',
        'subtitle' => 'Ask anything — past conversations are saved so you can pick up where you left off.',
        'balance'  => $balance,
    ])

    <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-4">
        {{-- Sidebar: thread list --}}
        <aside class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 space-y-2 md:max-h-[70vh] md:overflow-y-auto">
            <form method="POST" action="{{ route('user.ai.companion.store') }}">
                @csrf
                <button class="w-full px-3 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">
                    + New conversation
                </button>
            </form>

            <form method="GET" action="{{ route('user.ai.companion.show') }}" class="flex gap-1">
                <input type="search" name="q" value="{{ $search }}" maxlength="120"
                       placeholder="Search conversations…"
                       class="flex-1 min-w-0 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-xs placeholder-white/30">
                @if($search !== '')
                    <a href="{{ route('user.ai.companion.show') }}"
                       class="px-2 py-2 rounded-xl bg-white/5 text-white/60 text-xs hover:bg-white/10"
                       title="Clear search">×</a>
                @endif
            </form>

            @if($threads->isEmpty())
                <p class="text-xs text-white/40 text-center py-4">
                    {{ $search !== '' ? 'No conversations match that search.' : 'No saved conversations yet.' }}
                </p>
            @else
                @if($search !== '')
                    <p class="text-[10px] uppercase tracking-wider text-white/40 px-1 pt-1">
                        {{ $threads->total() }} match{{ $threads->total() === 1 ? '' : 'es' }}
                    </p>
                @endif
                <ul class="space-y-1">
                    @foreach($threads as $t)
                        @php $isActive = $active && $active->id === $t->id; @endphp
                        <li>
                            <a href="{{ route('user.ai.companion.thread', $t->id) }}{{ $search !== '' ? '?q=' . urlencode($search) : '' }}"
                               class="block px-3 py-2 rounded-xl text-sm
                                      {{ $isActive ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/[0.06]' }}">
                                <span class="block truncate">{!! $titles[$t->id] ?? e($t->title) !!}</span>
                                @if(!empty($snippets[$t->id]))
                                    <span class="block text-[11px] text-white/50 mt-0.5 line-clamp-2">{!! $snippets[$t->id] !!}</span>
                                @endif
                                @if($t->last_message_at)
                                    <span class="block text-[10px] text-white/30 mt-0.5">{{ $t->last_message_at->diffForHumans() }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if($threads->hasPages())
                    <div class="pt-2">{{ $threads->links() }}</div>
                @endif
            @endif
        </aside>

        {{-- Main: active thread --}}
        <section>
            @if($active)
                <div class="flex items-center gap-2 mb-3">
                    <form method="POST" action="{{ route('user.ai.companion.rename', $active->id) }}"
                          class="flex-1 flex gap-2">
                        @csrf
                        <input type="text" name="title" value="{{ $active->title }}" maxlength="120"
                               class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        <button class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20">
                            Rename
                        </button>
                    </form>
                    <a href="{{ route('user.ai.companion.export', $active->id) }}"
                       class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20"
                       title="Download this conversation as a markdown file">
                        Export
                    </a>
                    <a href="{{ route('user.ai.companion.export', ['thread' => $active->id, 'format' => 'txt']) }}"
                       class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20"
                       title="Download this conversation as a plain-text file">
                        .txt
                    </a>
                    <form method="POST" action="{{ route('user.ai.companion.destroy', $active->id) }}"
                          onsubmit="return confirm('Delete this conversation? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button class="px-3 py-2 rounded-xl bg-red-500/10 text-red-300 text-xs hover:bg-red-500/20">
                            Delete
                        </button>
                    </form>
                </div>

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

                <form method="POST" action="{{ route('user.ai.companion.send', $active->id) }}"
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
            @else
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
                    <p class="text-white/60 text-sm">Start a new conversation to chat with Companion.</p>
                    <form method="POST" action="{{ route('user.ai.companion.store') }}" class="mt-4">
                        @csrf
                        <button class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">
                            + New conversation
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
