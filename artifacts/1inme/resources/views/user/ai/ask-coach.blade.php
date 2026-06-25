@extends('user.layouts.app')
@section('title', 'Ask Coach')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Ask Coach',
        'title'    => 'Ask Coach about your Sayzio',
        'subtitle' => 'Read-only self-support — Coach pulls from your live links, audience, payments and account to answer.',
        'balance'  => $balance,
    ])

    <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-4">
        {{-- Sidebar: chats --}}
        <aside class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 space-y-2 md:max-h-[75vh] md:overflow-y-auto">
            <form method="POST" action="{{ route('user.ai.ask-coach.store') }}">
                @csrf
                <button class="w-full px-3 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    + New chat
                </button>
            </form>

            <form method="GET" action="{{ route('user.ai.ask-coach.show') }}" class="flex gap-1">
                <input type="search" name="q" value="{{ $search }}" maxlength="120"
                       placeholder="Search chats…"
                       class="flex-1 min-w-0 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-xs placeholder-white/30">
                @if($search !== '')
                    <a href="{{ route('user.ai.ask-coach.show') }}"
                       class="px-2 py-2 rounded-xl bg-white/5 text-white/60 text-xs hover:bg-white/10">×</a>
                @endif
            </form>

            @if($threads->isEmpty())
                <p class="text-xs text-white/40 text-center py-4">
                    {{ $search !== '' ? 'No chats match.' : 'No saved chats yet.' }}
                </p>
            @else
                <ul class="space-y-1">
                    @foreach($threads as $t)
                        @php $isActive = $active && $active->id === $t->id; @endphp
                        <li>
                            <a href="{{ route('user.ai.ask-coach.thread', $t->id) }}{{ $search !== '' ? '?q=' . urlencode($search) : '' }}"
                               class="block px-3 py-2 rounded-xl text-sm
                                      {{ $isActive ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/[0.06]' }}">
                                <span class="block truncate">{{ $titles[$t->id] ?? $t->title }}</span>
                                @if(!empty($snippets[$t->id]))
                                    <span class="block text-[11px] text-white/50 mt-0.5 line-clamp-2">{{ $snippets[$t->id] }}</span>
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

            {{-- Tools panel — be transparent about what Coach can see --}}
            <div class="mt-4 pt-3 border-t border-white/5">
                <p class="text-[10px] uppercase tracking-wider text-white/40 mb-1">Coach can read</p>
                <ul class="space-y-1">
                    @foreach($tools as $key => $t)
                        <li class="text-[11px] text-white/50">
                            <span class="text-white/70 font-semibold">{{ $t['label'] }}.</span>
                            {{ $t['description'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Main: active thread --}}
        <section>
            @if($active)
                <div class="flex items-center gap-2 mb-3">
                    <form method="POST" action="{{ route('user.ai.ask-coach.rename', $active->id) }}"
                          class="flex-1 flex gap-2">
                        @csrf
                        <input type="text" name="title" value="{{ $active->title }}" maxlength="160"
                               class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        <button class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20">Rename</button>
                    </form>
                    <a href="{{ route('user.ai.ask-coach.export', $active->id) }}"
                       class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20">Export</a>
                    <a href="{{ route('user.ai.ask-coach.export', ['thread' => $active->id, 'format' => 'txt']) }}"
                       class="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs hover:bg-white/20">.txt</a>
                    <form method="POST" action="{{ route('user.ai.ask-coach.destroy', $active->id) }}"
                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this chat?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf
                        @method('DELETE')
                        <button class="px-3 py-2 rounded-xl bg-red-500/10 text-red-300 text-xs hover:bg-red-500/20">Delete</button>
                    </form>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4 max-h-[60vh] overflow-y-auto">
                    @if(empty($history))
                        <p class="text-sm text-white/40 text-center py-8">
                            Ask Coach anything about your account. Try: <em>"Which Link in Bio got the most clicks?"</em> or <em>"How many sales last month?"</em>
                        </p>
                    @else
                        @foreach($history as $turn)
                            <div class="flex {{ $turn['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm space-y-2
                                    {{ $turn['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/90' }}">
                                    <pre class="whitespace-pre-wrap font-sans">{{ $turn['content'] }}</pre>

                                    @if($turn['role'] === 'assistant')
                                        @php $meta = $turn['meta'] ?? []; @endphp

                                        {{-- Inline insight cards (chart / table / kv) --}}
                                        @foreach(($meta['insights'] ?? []) as $ins)
                                            @php $d = $ins['data'] ?? []; @endphp
                                            <div class="rounded-xl bg-black/20 border border-white/5 p-3 text-[11px] text-white/80">
                                                <p class="text-[10px] uppercase tracking-wider text-white/40 mb-2">
                                                    {{ $ins['tool'] ?? 'insight' }}
                                                </p>
                                                @if(($d['kind'] ?? '') === 'kv')
                                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                                                        @foreach(($d['pairs'] ?? []) as $p)
                                                            <dt class="text-white/50">{{ $p['key'] ?? '' }}</dt>
                                                            <dd class="text-white text-right">{{ $p['value'] ?? '' }}</dd>
                                                        @endforeach
                                                    </dl>
                                                @elseif(($d['kind'] ?? '') === 'table')
                                                    <table class="w-full text-left">
                                                        <thead class="text-[10px] text-white/40">
                                                            <tr>@foreach(($d['columns'] ?? []) as $c)<th class="py-1 pr-2 font-normal">{{ $c }}</th>@endforeach</tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach(($d['rows'] ?? []) as $r)
                                                                <tr class="border-t border-white/5">
                                                                    @foreach($r as $cell)
                                                                        <td class="py-1 pr-2 align-top">{{ is_bool($cell) ? ($cell ? 'live' : 'paused') : $cell }}</td>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @elseif(in_array(($d['kind'] ?? ''), ['bar','line']))
                                                    @php
                                                        $series = $d['series'] ?? [];
                                                        $maxV = max(array_map(fn($s) => (int)($s['value'] ?? 0), $series ?: [['value'=>1]]));
                                                        $maxV = $maxV > 0 ? $maxV : 1;
                                                    @endphp
                                                    <div class="space-y-1">
                                                        @foreach($series as $s)
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-24 truncate text-white/50">{{ $s['label'] ?? '' }}</span>
                                                                <span class="flex-1 h-2 bg-white/5 rounded overflow-hidden">
                                                                    <span class="block h-full bg-blue-500/70" style="width: {{ max(2, round(((int)$s['value'] / $maxV) * 100)) }}%"></span>
                                                                </span>
                                                                <span class="w-10 text-right text-white">{{ $s['value'] ?? 0 }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach

                                        {{-- Action deep-links --}}
                                        @if(!empty($meta['actions']))
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($meta['actions'] as $a)
                                                    <a href="{{ $a['url'] ?? '#' }}"
                                                       title="{{ $a['reason'] ?? '' }}"
                                                       class="text-[11px] px-2 py-1 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30">
                                                        → {{ $a['label'] ?? 'Open' }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Citations: when a Mind source backs the citation, link
                                             to its detail page so the asker can verify the answer. --}}
                                        @if(!empty($meta['citations']))
                                            <p class="text-[10px] text-white/40">
                                                Sources:
                                                @foreach($meta['citations'] as $c)
                                                    @php
                                                        $cMid = (int) ($c['mind_id'] ?? 0);
                                                        $cSid = (int) ($c['id'] ?? 0);
                                                        $cHref = ($cMid && $cSid)
                                                            ? route('user.minds.sources.show', ['mind' => $cMid, 'source' => $cSid])
                                                            : null;
                                                        $cLabel = (string) ($c['title'] ?? $c['label'] ?? $c['source'] ?? '');
                                                    @endphp
                                                    @if($cHref)
                                                        <a href="{{ $cHref }}"
                                                           class="inline-block px-1.5 py-0.5 mr-1 rounded bg-white/5 text-white/80 underline decoration-white/20 hover:decoration-white/60 hover:text-white"
                                                           title="View source">{{ $cLabel }}</a>
                                                    @else
                                                        <span class="inline-block px-1.5 py-0.5 mr-1 rounded bg-white/5 text-white/60">{{ $cLabel }}</span>
                                                    @endif
                                                @endforeach
                                            </p>
                                        @endif

                                        {{-- Feedback + credits --}}
                                        <div class="flex items-center justify-between text-[10px] text-white/40 pt-1">
                                            <form method="POST" action="{{ route('user.ai.ask-coach.feedback', $turn['id']) }}" class="flex items-center gap-1">
                                                @csrf
                                                @php $fb = $turn['feedback'] ?? null; @endphp
                                                <button name="feedback" value="up"
                                                        class="px-1.5 py-0.5 rounded {{ $fb === 'up' ? 'bg-emerald-500/30 text-emerald-200' : 'hover:bg-white/10' }}">👍</button>
                                                <button name="feedback" value="down"
                                                        class="px-1.5 py-0.5 rounded {{ $fb === 'down' ? 'bg-red-500/30 text-red-200' : 'hover:bg-white/10' }}">👎</button>
                                                @if($fb)
                                                    <button name="feedback" value="clear" class="px-1 text-white/30 hover:text-white/60">clear</button>
                                                @endif
                                            </form>
                                            @if(!empty($meta['credits_spent']))
                                                <span>{{ number_format($meta['credits_spent']) }} ✦</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <form method="POST" action="{{ route('user.ai.ask-coach.send', $active->id) }}"
                      data-coach-form
                      data-stream-url="{{ route('user.ai.ask-coach.send', $active->id) }}"
                      data-thread-url="{{ route('user.ai.ask-coach.thread', $active->id) }}"
                      class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3 flex gap-2">
                    @csrf
                    <input type="text" name="message" required maxlength="2000" autofocus
                           data-coach-input
                           placeholder="Ask Coach about your Sayzio data…"
                           class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    <button data-coach-send class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                        Send
                    </button>
                </form>
                @error('message')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror

                <script>
                (function () {
                    const form = document.querySelector('[data-coach-form]');
                    if (!form || !window.fetch || !window.TextDecoder) return;

                    const input  = form.querySelector('[data-coach-input]');
                    const button = form.querySelector('[data-coach-send]');
                    const csrf   = form.querySelector('input[name="_token"]').value;
                    const stream = form.dataset.streamUrl;
                    const reload = form.dataset.threadUrl;
                    const list   = form.previousElementSibling; // chat scroll panel

                    function bubble(role, text) {
                        const wrap = document.createElement('div');
                        wrap.className = 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
                        const inner = document.createElement('div');
                        inner.className = 'max-w-[85%] rounded-2xl px-4 py-3 text-sm space-y-2 ' +
                            (role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/90');
                        const pre = document.createElement('pre');
                        pre.className = 'whitespace-pre-wrap font-sans';
                        pre.textContent = text;
                        inner.appendChild(pre);
                        wrap.appendChild(inner);
                        list.appendChild(wrap);
                        list.scrollTop = list.scrollHeight;
                        return pre;
                    }

                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();
                        const message = (input.value || '').trim();
                        if (!message || button.disabled) return;
                        button.disabled = true;
                        input.disabled = true;
                        const original = button.textContent;
                        button.textContent = '…';

                        // Remove the empty-state placeholder if present.
                        const empty = list.querySelector('p.text-white\\/40');
                        if (empty) empty.remove();

                        bubble('user', message);
                        const target = bubble('assistant', '');
                        target.textContent = '…';
                        let started = false;
                        input.value = '';

                        try {
                            const res = await fetch(stream, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'text/event-stream',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ message }),
                                credentials: 'same-origin',
                            });

                            if (!res.ok || !res.body) {
                                throw new Error('Stream failed (' + res.status + ')');
                            }

                            const reader  = res.body.getReader();
                            const decoder = new TextDecoder('utf-8');
                            let buffer = '';
                            let event = 'message';
                            let payload = '';
                            let errored = false;

                            const handleFrame = function () {
                                if (!payload) { event = 'message'; return; }
                                let data;
                                try { data = JSON.parse(payload); } catch (_) { event = 'message'; payload = ''; return; }
                                if (event === 'token' && typeof data.delta === 'string') {
                                    if (!started) { target.textContent = ''; started = true; }
                                    target.textContent += data.delta;
                                    list.scrollTop = list.scrollHeight;
                                } else if (event === 'error') {
                                    errored = true;
                                    target.textContent = data.message || 'Coach could not reply.';
                                } else if (event === 'done') {
                                    if (data && data.message && typeof data.message.content === 'string') {
                                        target.textContent = data.message.content;
                                    }
                                }
                                event = 'message';
                                payload = '';
                            };

                            while (true) {
                                const { value, done } = await reader.read();
                                if (done) break;
                                buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, '\n');
                                let idx;
                                while ((idx = buffer.indexOf('\n\n')) !== -1) {
                                    const frame = buffer.slice(0, idx);
                                    buffer = buffer.slice(idx + 2);
                                    event = 'message'; payload = '';
                                    frame.split('\n').forEach(function (line) {
                                        if (line.startsWith('event:')) event = line.slice(6).trim();
                                        else if (line.startsWith('data:')) payload += line.slice(5).trim();
                                    });
                                    handleFrame();
                                }
                            }
                            // Reload to pick up insight cards / actions / citations / feedback.
                            if (!errored) window.location.assign(reload);
                        } catch (err) {
                            target.textContent = 'Coach could not reply right now. Please try again.';
                        } finally {
                            button.disabled = false;
                            input.disabled = false;
                            button.textContent = original;
                            input.focus();
                        }
                    });
                })();
                </script>
            @else
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
                    <p class="text-white/60 text-sm">Start a chat to ask Coach about your Sayzio.</p>
                    <form method="POST" action="{{ route('user.ai.ask-coach.store') }}" class="mt-4">
                        @csrf
                        <button class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                            + New chat
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
