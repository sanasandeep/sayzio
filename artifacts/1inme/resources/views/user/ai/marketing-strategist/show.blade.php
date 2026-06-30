@extends('user.layouts.app')
@section('title', $strategy->title)

@php $plan = (array) ($strategy->strategy ?? []); @endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Marketing Strategist',
        'title'    => $strategy->title,
        'subtitle' => $strategy->goalSummary(200),
        'balance'  => $balance,
    ])

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('user.ai.marketing-strategist.index') }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-arrow-left mr-1"></i> All strategies
        </a>
        <a href="{{ route('user.ai.marketing-strategist.export', $strategy->id) }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-file-alt mr-1"></i> Export Markdown
        </a>
        <a href="{{ route('user.ai.marketing-strategist.export', $strategy->id) }}?format=pdf"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-file-pdf mr-1"></i> Export PDF
        </a>
        <form method="POST" action="{{ route('user.ai.marketing-strategist.destroy', $strategy->id) }}"
              onsubmit="return confirm('Delete this strategy? This cannot be undone.');" class="ml-auto">
            @csrf @method('DELETE')
            <button class="px-3 py-1.5 rounded-lg bg-red-500/10 text-red-300 hover:bg-red-500/20 text-xs">
                <i class="fas fa-trash mr-1"></i> Delete
            </button>
        </form>
    </div>

    {{-- Summary --}}
    @if(!empty($plan['summary']))
        <div class="rounded-2xl border border-blue-500/20 bg-blue-500/[0.06] p-5 mb-6">
            <p class="text-sm text-blue-100/90 leading-relaxed">{{ $plan['summary'] }}</p>
        </div>
    @endif

    {{-- Suggestions --}}
    @if($suggestions->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-bolt text-amber-300 mr-1"></i> One-click actions</h2>
            <ul class="space-y-2" id="ms-suggestions">
                @foreach($suggestions as $sug)
                    <li class="rounded-xl border border-white/10 bg-white/[0.03] p-4" data-suggestion="{{ $sug->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-200">{{ $sug->typeLabel() }}</span>
                                    <span class="text-sm text-white font-medium truncate">{{ $sug->title }}</span>
                                </div>
                                @if($sug->description)
                                    <p class="text-xs text-white/50 mt-1">{{ $sug->description }}</p>
                                @endif
                                <p class="text-xs mt-2 ms-feedback" data-feedback></p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2" data-actions>
                                @if($sug->status === \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_APPLIED)
                                    <span class="text-xs text-emerald-300"><i class="fas fa-check mr-1"></i>Applied</span>
                                @elseif($sug->status === \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_DISMISSED)
                                    <span class="text-xs text-white/40">Dismissed</span>
                                @else
                                    <button type="button" data-apply
                                            data-confirm="Apply &quot;{{ $sug->title }}&quot;? This makes a real change to your account ({{ strtolower($sug->typeLabel()) }})."
                                            class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                                        Apply
                                    </button>
                                    <button type="button" data-dismiss
                                            class="px-3 py-1.5 rounded-lg bg-white/5 text-white/60 text-xs hover:bg-white/10">
                                        Dismiss
                                    </button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Plays --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        @php
            $renderPlays = function ($plays, $paid) {
                return $plays;
            };
        @endphp
        <section>
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-seedling text-emerald-300 mr-1"></i> Organic plan</h2>
            @forelse((array) ($plan['organic'] ?? []) as $play)
                @include('user.ai.marketing-strategist._play', ['play' => $play, 'paid' => false])
            @empty
                <p class="text-sm text-white/40">No organic plays generated.</p>
            @endforelse
        </section>
        <section>
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-rocket text-sky-300 mr-1"></i> Paid plan</h2>
            @forelse((array) ($plan['paid'] ?? []) as $play)
                @include('user.ai.marketing-strategist._play', ['play' => $play, 'paid' => true])
            @empty
                <p class="text-sm text-white/40">No paid plays generated.</p>
            @endforelse
        </section>
    </div>

    {{-- KPIs --}}
    @if(!empty($plan['kpis']))
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-chart-line text-blue-300 mr-1"></i> KPIs to watch</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach((array) $plan['kpis'] as $kpi)
                    <li class="text-xs px-3 py-1.5 rounded-full bg-white/5 text-white/70">{{ $kpi }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Chat refine --}}
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h2 class="text-white font-semibold mb-1"><i class="fas fa-comments text-blue-300 mr-1"></i> Refine with the strategist</h2>
        <p class="text-xs text-white/50 mb-4">Ask follow-up questions or request changes. Replies are metered from your coin wallet.</p>

        <div id="ms-chat" class="space-y-3 mb-4 max-h-[50vh] overflow-y-auto">
            @foreach($messages as $m)
                <div class="flex {{ $m->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap
                                {{ $m->role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/[0.06] text-white/90' }}">
                        {{ $m->content }}
                    </div>
                </div>
            @endforeach
        </div>

        <form id="ms-chat-form" class="flex items-end gap-2">
            <textarea id="ms-chat-input" rows="1" maxlength="4000" required
                      placeholder="e.g. Make the paid plan cheaper, or focus organic on TikTok."
                      class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30 resize-none focus:ring-blue-500 focus:border-blue-500"></textarea>
            <button type="submit" id="ms-chat-send"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-60">
                Send
            </button>
        </form>
    </section>
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ── Suggestions: apply / dismiss ──────────────────────────────
    const applyUrl = function (id) {
        return @json(url('/user/ai/marketing-strategist/suggestions')) + '/' + id + '/apply';
    };
    const dismissUrl = function (id) {
        return @json(url('/user/ai/marketing-strategist/suggestions')) + '/' + id + '/dismiss';
    };

    document.getElementById('ms-suggestions')?.addEventListener('click', async function (e) {
        const applyBtn = e.target.closest('[data-apply]');
        const dismissBtn = e.target.closest('[data-dismiss]');
        if (!applyBtn && !dismissBtn) return;

        const li = e.target.closest('[data-suggestion]');
        const id = li?.getAttribute('data-suggestion');
        const actions = li?.querySelector('[data-actions]');
        const feedback = li?.querySelector('[data-feedback]');
        if (!id) return;

        // Applying performs a real, state-changing action — confirm first.
        if (applyBtn) {
            const msg = applyBtn.getAttribute('data-confirm')
                || 'Apply this suggestion? This makes a real change to your account.';
            if (!window.confirm(msg)) return;
        }

        const btn = applyBtn || dismissBtn;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = applyBtn ? 'Applying…' : 'Dismissing…';

        try {
            const res = await fetch(applyBtn ? applyUrl(id) : dismissUrl(id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: applyBtn ? JSON.stringify({ confirm: true }) : undefined,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data?.error?.message || 'Action failed.');

            if (applyBtn) {
                let html = '<span class="text-emerald-300"><i class="fas fa-check mr-1"></i>Applied</span>';
                if (data.url) html += ' <a href="' + data.url + '" class="text-blue-300 underline ml-2">Open</a>';
                actions.innerHTML = html;
                if (feedback) feedback.innerHTML = '<span class="text-emerald-300/80">' + (data.message || '') + '</span>';
            } else {
                actions.innerHTML = '<span class="text-xs text-white/40">Dismissed</span>';
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = original;
            if (feedback) feedback.innerHTML = '<span class="text-red-300">' + (err.message || 'Failed') + '</span>';
        }
    });

    // ── Chat refine (SSE stream) ──────────────────────────────────
    const chat  = document.getElementById('ms-chat');
    const form  = document.getElementById('ms-chat-form');
    const input = document.getElementById('ms-chat-input');
    const send  = document.getElementById('ms-chat-send');
    const streamUrl = @json(route('user.ai.marketing-strategist.chat', $strategy->id));

    const bubble = function (role, text) {
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        const inner = document.createElement('div');
        inner.className = 'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap ' +
            (role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/[0.06] text-white/90');
        inner.textContent = text;
        wrap.appendChild(inner);
        chat.appendChild(wrap);
        chat.scrollTop = chat.scrollHeight;
        return inner;
    };

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const msg = (input.value || '').trim();
        if (!msg) return;

        bubble('user', msg);
        input.value = '';
        send.disabled = true;
        const out = bubble('assistant', '…');
        let acc = '';

        try {
            const res = await fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(msg),
            });

            if (!res.ok || !res.body) {
                let m = 'The strategist could not reply right now.';
                try { const j = await res.json(); m = j?.error?.message || m; } catch (_) {}
                out.textContent = m;
                send.disabled = false;
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buf = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buf += decoder.decode(value, { stream: true });
                const frames = buf.split('\n\n');
                buf = frames.pop() || '';
                for (const frame of frames) {
                    const evMatch = frame.match(/^event: (.+)$/m);
                    const dataMatch = frame.match(/^data: (.+)$/m);
                    if (!dataMatch) continue;
                    const ev = evMatch ? evMatch[1] : 'message';
                    let payload = {};
                    try { payload = JSON.parse(dataMatch[1]); } catch (_) {}

                    if (ev === 'token') {
                        if (acc === '') out.textContent = '';
                        acc += payload.delta || '';
                        out.textContent = acc;
                        chat.scrollTop = chat.scrollHeight;
                    } else if (ev === 'error') {
                        out.textContent = payload.message || 'Something went wrong.';
                    } else if (ev === 'done') {
                        if (payload.message?.content) out.textContent = payload.message.content;
                    }
                }
            }
        } catch (err) {
            out.textContent = 'Connection lost. Please try again.';
        } finally {
            send.disabled = false;
        }
    });
})();
</script>
@endsection
