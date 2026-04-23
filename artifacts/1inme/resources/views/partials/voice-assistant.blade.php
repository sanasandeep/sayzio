{{--
    Floating Voice Assistant — included by both user and admin layouts.
    Renders nothing for guests or when the feature is disabled for the
    user's plan. The whole UI is a single Alpine.js component:

      • Mic button (bottom-right) toggles recording.
      • While recording, audio is captured with MediaRecorder.
      • On stop, the blob is POSTed to /user/ai/voice/turn together with
        the running message history.
      • The reply audio is played, the transcript is appended, and any
        pending destructive tool calls are rendered as confirm/cancel
        chips. Tapping confirm replays the same turn with
        `confirmed_tools[name] = true`.
      • A second tab in the panel shows the live capabilities catalogue
        (groups + limitations) fetched from /user/ai/voice/capabilities.

    The widget gracefully degrades: missing MediaRecorder, missing mic
    permission, 402 (out of credits), and 403 (plan not allowed) all
    surface friendly messages instead of crashing.
--}}
@auth
@php
    $voiceUser = auth()->user();
    $voiceAvailable = false;
    try {
        $voiceAvailable = \App\Services\AI\AiEngineSettings::voiceAllowedFor($voiceUser);
    } catch (\Throwable $e) {}
@endphp
@if($voiceAvailable)
<div
    x-data="voiceAssistant({
        turnUrl: @js(route('user.ai.voice.turn')),
        capUrl:  @js(route('user.ai.voice.capabilities')),
        csrf:    @js(csrf_token()),
    })"
    x-cloak
    class="fixed bottom-5 right-5 z-[1000]"
    style="font-family: inherit"
>
    {{-- Floating panel --}}
    <div
        x-show="panelOpen"
        x-transition.opacity
        @click.outside="panelOpen = false"
        class="absolute bottom-16 right-0 w-[22rem] max-w-[92vw] bg-[#0d1118] border border-white/10 rounded-2xl shadow-2xl text-white overflow-hidden"
    >
        {{-- Tabs --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
            <div class="flex items-center gap-3 text-xs font-medium">
                <button @click="tab='chat'" :class="tab==='chat' ? 'text-violet-300' : 'text-white/50 hover:text-white/80'">Voice</button>
                <button @click="tab='caps'; loadCaps()" :class="tab==='caps' ? 'text-violet-300' : 'text-white/50 hover:text-white/80'">What I can do</button>
            </div>
            <button @click="panelOpen=false" class="text-white/40 hover:text-white text-sm">&times;</button>
        </div>

        {{-- Chat / transcript --}}
        <div x-show="tab==='chat'" class="p-4 space-y-3">
            <template x-if="!messages.length && !status">
                <p class="text-white/50 text-xs leading-relaxed">
                    Tap the mic and ask anything — “open my dashboard”, “how many clicks today?”, “delete link 42”. Destructive actions always ask before running.
                </p>
            </template>

            <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                <template x-for="(m, idx) in messages" :key="idx">
                    <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                        <span
                            :class="m.role === 'user'
                                ? 'bg-violet-600/30 text-violet-100'
                                : 'bg-white/5 text-white/85'"
                            class="inline-block rounded-2xl px-3 py-1.5 text-xs max-w-[85%] whitespace-pre-wrap"
                            x-text="m.content"
                        ></span>
                    </div>
                </template>
            </div>

            <template x-if="pendingConfirmations.length">
                <div class="rounded-xl border border-amber-400/40 bg-amber-400/10 p-3 space-y-2">
                    <p class="text-amber-200 text-xs font-medium">Confirm before I run:</p>
                    <template x-for="(c, i) in pendingConfirmations" :key="i">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <code class="text-amber-100 truncate" x-text="c.tool"></code>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button @click="confirmTool(c.tool, true)"  class="px-2 py-1 rounded bg-emerald-500/80 hover:bg-emerald-500 text-white text-[11px]">Yes</button>
                                <button @click="confirmTool(c.tool, false)" class="px-2 py-1 rounded bg-white/10 hover:bg-white/20 text-white/80 text-[11px]">Cancel</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="status">
                <p class="text-white/40 text-[11px]" x-text="status"></p>
            </template>
            <template x-if="lastCredits">
                <p class="text-white/40 text-[11px]">
                    Last turn: STT <span x-text="lastCredits.stt"></span> ·
                    LLM <span x-text="lastCredits.llm"></span> ·
                    TTS <span x-text="lastCredits.tts"></span>
                    (= <span x-text="lastCredits.total"></span> credits) ·
                    Balance <span x-text="balance"></span>
                </p>
            </template>
        </div>

        {{-- Capabilities --}}
        <div x-show="tab==='caps'" class="p-4 space-y-3 max-h-[24rem] overflow-y-auto">
            <template x-if="!caps">
                <p class="text-white/40 text-xs">Loading…</p>
            </template>
            <template x-if="caps">
                <div class="space-y-3">
                    <template x-for="(items, group) in caps.tools" :key="group">
                        <div>
                            <p class="text-violet-300 text-[11px] uppercase tracking-wider mb-1" x-text="group.replace('_',' ')"></p>
                            <ul class="space-y-1">
                                <template x-for="t in items" :key="t.name">
                                    <li class="text-xs text-white/80 leading-snug">
                                        <span class="font-mono text-white/60" x-text="t.name"></span>
                                        <template x-if="t.destructive"><span class="ml-1 text-amber-300 text-[10px]">⚠ confirms</span></template>
                                        <span class="block text-white/50" x-text="t.description"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <div>
                        <p class="text-rose-300 text-[11px] uppercase tracking-wider mb-1">What I can't do</p>
                        <ul class="space-y-1 list-disc pl-4">
                            <template x-for="lim in caps.limitations" :key="lim">
                                <li class="text-xs text-white/60 leading-snug" x-text="lim"></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Mic button --}}
    <button
        type="button"
        @click="onMicClick()"
        @contextmenu.prevent="panelOpen = !panelOpen"
        :title="recording ? 'Stop and send' : 'Tap to talk · right-click for panel'"
        :class="recording ? 'bg-red-500 hover:bg-red-600 animate-pulse' : 'bg-violet-600 hover:bg-violet-700'"
        class="w-14 h-14 rounded-full text-white shadow-xl flex items-center justify-center transition focus:outline-none focus:ring-2 focus:ring-violet-400"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/>
        </svg>
    </button>

    <audio x-ref="player" class="hidden"></audio>
</div>

<script>
window.voiceAssistant = function (cfg) {
    return {
        cfg,
        panelOpen: false,
        tab: 'chat',
        recording: false,
        rec: null,
        chunks: [],
        messages: [],
        pendingConfirmations: [],
        lastCredits: null,
        balance: null,
        status: '',
        caps: null,
        lastAudio: null,

        async onMicClick() {
            if (this.recording) { return this.stopRecording(); }
            this.panelOpen = true;
            await this.startRecording();
        },

        async startRecording() {
            if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
                this.status = 'Your browser does not support voice recording.';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
                this.rec = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
                this.chunks = [];
                this.rec.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
                this.rec.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(this.chunks, { type: this.rec.mimeType || 'audio/webm' });
                    this.lastAudio = blob;
                    this.sendTurn(blob);
                };
                this.rec.start();
                this.recording = true;
                this.status = 'Listening…';
            } catch (e) {
                this.status = 'Microphone permission denied.';
            }
        },

        stopRecording() {
            if (this.rec && this.recording) {
                this.recording = false;
                this.status = 'Thinking…';
                try { this.rec.stop(); } catch (e) {}
            }
        },

        async sendTurn(blob, confirmedTools) {
            const fd = new FormData();
            const ext = (blob.type.includes('webm')) ? 'webm' : 'ogg';
            fd.append('audio', blob, `voice.${ext}`);
            fd.append('context', JSON.stringify({
                messages: this.messages,
                confirmed_tools: confirmedTools || {},
            }));
            try {
                const res = await fetch(this.cfg.turnUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.cfg.csrf, 'Accept': 'application/json' },
                    body: fd,
                    credentials: 'same-origin',
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.status = json.error || `Request failed (${res.status}).`;
                    return;
                }
                if (json.transcript) this.messages.push({ role: 'user', content: json.transcript });
                if (json.reply)      this.messages.push({ role: 'assistant', content: json.reply });
                this.pendingConfirmations = json.pending_confirmations || [];
                this.lastCredits = json.credits || null;
                this.balance    = (json.balance ?? this.balance);
                this.status     = '';
                if (json.audio_base64) {
                    this.$refs.player.src = 'data:audio/mpeg;base64,' + json.audio_base64;
                    this.$refs.player.play().catch(() => {});
                }
            } catch (e) {
                this.status = 'Network error — please retry.';
            }
        },

        confirmTool(name, accepted) {
            if (!accepted) {
                this.pendingConfirmations = this.pendingConfirmations.filter(c => c.tool !== name);
                this.messages.push({ role: 'assistant', content: `Cancelled ${name}.` });
                return;
            }
            if (!this.lastAudio) return;
            const map = {}; map[name] = true;
            this.pendingConfirmations = this.pendingConfirmations.filter(c => c.tool !== name);
            this.status = 'Running…';
            this.sendTurn(this.lastAudio, map);
        },

        async loadCaps() {
            if (this.caps) return;
            try {
                const res = await fetch(this.cfg.capUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                this.caps = await res.json();
            } catch (e) {
                this.caps = { tools: {}, limitations: ['Could not load capabilities.'] };
            }
        },
    };
};
</script>
@endif
@endauth
