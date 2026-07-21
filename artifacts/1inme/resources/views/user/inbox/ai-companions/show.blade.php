@extends('user.layouts.app')
@section('title', $companion->name . ' · Inbox AI')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8" x-data="cmpInboxChat()">
    <a href="{{ route('user.inbox.ai-companions.index') }}" class="text-xs text-white/40 hover:text-white/70 mb-3 inline-block"><i class="fas fa-arrow-left"></i> AI Companions</a>

    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-300 bg-blue-500/15"><i class="fas fa-robot"></i></div>
        <div>
            <h1 class="text-lg font-bold text-white">{{ $companion->name }}</h1>
            <p class="text-[11px] text-white/40">Bot · responses billed to your coins after free quota</p>
        </div>
    </div>

    <div id="cmp-thread" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-3 min-h-[420px] max-h-[540px] overflow-y-auto">
        @forelse($messages as $m)
            <div class="flex gap-2 {{ $m->role === 'user' ? 'justify-end' : '' }}">
                <div class="max-w-[80%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words {{ $m->role === 'user' ? 'bg-blue-500/20 text-white' : 'bg-white/5 text-white/90' }}">
                    {{ $m->content }}
                </div>
            </div>
        @empty
            <p class="text-center text-sm text-white/40 py-12">Say hello, your AI Companion will reply using its persona.</p>
        @endforelse
    </div>

    <form @submit.prevent="send" class="mt-3 flex gap-2">
        @csrf
        <input type="text" x-model="text" :disabled="sending" placeholder="Message {{ $companion->name }}…" class="flex-1 rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-blue-500/50">
        <button type="submit" :disabled="sending || !text.trim()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold disabled:opacity-40">
            <span x-show="!sending"><i class="fas fa-paper-plane"></i></span>
            <span x-show="sending"><i class="fas fa-spinner fa-spin"></i></span>
        </button>
    </form>
</div>

<script>
function cmpInboxChat() {
    return {
        text: '',
        sending: false,
        send() {
            if (!this.text.trim() || this.sending) return;
            const v = this.text.trim();
            this.text = '';
            this.sending = true;
            this.append('user', v);
            fetch(@json(route('user.inbox.ai-companions.send', $companion)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ message: v })
            }).then(r => r.json()).then(d => {
                if (d.ok) { this.append('assistant', d.answer || ''); }
                else { this.append('error', d.error || 'Sorry, something went wrong.'); }
            }).catch(() => this.append('error', 'Network error.'))
              .finally(() => { this.sending = false; });
        },
        append(role, body) {
            const wrap = document.getElementById('cmp-thread');
            const row = document.createElement('div');
            row.className = 'flex gap-2 ' + (role === 'user' ? 'justify-end' : '');
            const bubble = document.createElement('div');
            const cls = role === 'user' ? 'bg-blue-500/20 text-white' :
                        role === 'error' ? 'bg-rose-500/20 text-rose-200' :
                        'bg-white/5 text-white/90';
            bubble.className = 'max-w-[80%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words ' + cls;
            bubble.textContent = body;
            row.appendChild(bubble);
            wrap.appendChild(row);
            wrap.scrollTop = wrap.scrollHeight;
        },
    };
}
</script>
@endsection
