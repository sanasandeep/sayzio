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
    // Engine is on (master switch + voice feature toggle) but the user's
    // plan blocks voice. We can't run a turn, but instead of hiding the
    // widget silently we show a mic that opens the self-serve gate page.
    $voicePlanGated = false;
    try {
        $voiceAvailable = \App\Services\AI\AiEngineSettings::voiceAllowedFor($voiceUser);
        $voicePlanGated = !$voiceAvailable
            && \App\Services\AI\AiEngineSettings::isEnabled()
            && \App\Services\AI\AiEngineSettings::voiceEnabled();
    } catch (\Throwable $e) {}
    // When false, the standalone floating mic/panel is suppressed (the host
    // layout embeds the voice agent elsewhere — e.g. inside the Zio chat
    // panel on the user dashboard). The reusable dictation helper and the
    // window.__voice config below still render so other surfaces keep voice.
    $voiceFloating = $voiceFloating ?? true;

    // Approximate worst-case coins for a single voice turn (STT + reasoning +
    // TTS), mirroring the real charge path + per-plan multiplier. Shown as a
    // heads-up in the panel so the user knows roughly what a turn costs.
    $voiceTurnCoins = 0;
    try {
        if ($voiceAvailable) {
            $voiceTurnCoins = (int) app(\App\Services\AI\AiCostEstimator::class)
                ->estimate($voiceUser, 'voice', '')['coins'];
        }
    } catch (\Throwable $e) {}
@endphp
@if($voiceAvailable)
<script>
// Dictation endpoint config for reusable voiceDictation() controls
// (header search, companion composer). Set independently of the floating
// widget so dictation keeps working on surfaces that suppress it (e.g. the
// user dashboard, where the full voice agent now lives inside the Zio panel).
window.__voice = { dictateUrl: @js(route('user.ai.voice.transcribe')), csrf: @js(csrf_token()) };
</script>
@endif
@if($voiceFloating && $voicePlanGated)
{{-- Plan-gated: a floating mic that routes to the upgrade gate page
     instead of recording, so the feature isn't a silent dead end. --}}
<div class="fixed bottom-5 right-5 z-[1000]" style="font-family: inherit">
    <a href="{{ route('user.ai.voice.show') }}"
       title="Voice Assistant — upgrade to unlock"
       class="relative w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 text-white shadow-xl flex items-center justify-center transition focus:outline-none focus:ring-2 focus:ring-blue-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/>
        </svg>
        <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-slate-900 shadow">
            <i class="fas fa-lock text-[9px]"></i>
        </span>
    </a>
</div>
@endif
@if($voiceFloating && $voiceAvailable)
{{-- Shared voice-runtime core (turn payload + surface bridge + capabilities),
     also used by the Zio panel mic (common.partials.site-assistant) — defined
     idempotently so a page hosting both surfaces registers only one runtime. --}}
@include('common.partials.voice-runtime')
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
                <button @click="tab='chat'" :class="tab==='chat' ? 'text-blue-300' : 'text-white/50 hover:text-white/80'">Voice</button>
                <button @click="tab='caps'; loadCaps()" :class="tab==='caps' ? 'text-blue-300' : 'text-white/50 hover:text-white/80'">What I can do</button>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="handsFree = !handsFree"
                    :title="handsFree ? 'Hands-free on — I keep listening after each reply' : 'Hands-free off'"
                    :class="handsFree ? 'text-emerald-300' : 'text-white/40 hover:text-white/80'"
                    class="text-[11px] flex items-center gap-1"
                >
                    <i class="fas fa-infinity"></i>
                    <span>Hands-free</span>
                </button>
                <button @click="panelOpen=false" class="text-white/40 hover:text-white text-sm">&times;</button>
            </div>
        </div>

        {{-- Chat / transcript --}}
        <div x-show="tab==='chat'" class="p-4 space-y-3">
            <template x-if="!messages.length && !status">
                <p class="text-white/50 text-xs leading-relaxed">
                    Tap the mic and ask anything — “open my dashboard”, “how many clicks today?”, “delete link 42”. Destructive actions always ask before running.
                </p>
            </template>
            @if($voiceTurnCoins > 0)
            <p class="text-white/40 text-[11px] flex items-center gap-1">
                <i class="fas fa-coins"></i> &approx; {{ $voiceTurnCoins }} {{ $voiceTurnCoins === 1 ? 'coin' : 'coins' }} per voice turn
                · Balance <span x-text="balance"></span>
            </p>
            @endif

            <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                <template x-for="(m, idx) in messages" :key="idx">
                    <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                        <span
                            :class="m.role === 'user'
                                ? 'bg-blue-600/30 text-blue-100'
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
                            <p class="text-blue-300 text-[11px] uppercase tracking-wider mb-1" x-text="group.replace('_',' ')"></p>
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
        :class="recording ? 'bg-red-500 hover:bg-red-600 animate-pulse' : 'bg-blue-600 hover:bg-blue-700'"
        class="w-14 h-14 rounded-full text-white shadow-xl flex items-center justify-center transition focus:outline-none focus:ring-2 focus:ring-blue-400"
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
        handsFree: false,
        pendingNav: null,

        init() {
            // When the spoken reply finishes, either follow a pending
            // navigation or, in hands-free mode, start listening again.
            const player = this.$refs.player;
            if (player) {
                player.addEventListener('ended', () => this.afterReply());
            }
        },

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
            try {
                // Turn payload shape + POST + response normalization all live in
                // the shared VoiceRuntime so this surface can never drift from
                // the Zio-panel mic. The surface bridge (client_action dispatch
                // + navigate_to) is shared too; navigate_to is returned here and
                // deferred until the spoken reply finishes (see afterReply).
                const r = await window.VoiceRuntime.sendTurn({
                    url: this.cfg.turnUrl,
                    csrf: this.cfg.csrf,
                    blob,
                    messages: this.messages,
                    confirmedTools: confirmedTools || {},
                });
                if (!r.ok) {
                    this.status = r.error || `Request failed (${r.status}).`;
                    return;
                }
                if (r.transcript) this.messages.push({ role: 'user', content: r.transcript });
                if (r.reply)      this.messages.push({ role: 'assistant', content: r.reply });
                this.pendingConfirmations = r.pending;
                this.lastCredits = r.credits;
                this.balance    = (r.balance != null ? r.balance : this.balance);
                this.status     = '';
                this.pendingNav = window.VoiceRuntime.applyToolResults(r.toolResults);
                if (r.audioBase64) {
                    this.$refs.player.src = 'data:audio/mpeg;base64,' + r.audioBase64;
                    this.$refs.player.play().catch(() => this.afterReply());
                } else {
                    // No spoken reply — run the post-reply step immediately.
                    this.afterReply();
                }
            } catch (e) {
                this.status = 'Network error — please retry.';
            }
        },

        // Called when the reply audio ends (or when there was none).
        afterReply() {
            if (this.pendingNav) {
                const url = this.pendingNav;
                this.pendingNav = null;
                window.location.assign(url);
                return;
            }
            // Hands-free: keep the conversation going unless we're waiting
            // on a destructive confirmation or already recording.
            if (this.handsFree && !this.recording && !this.pendingConfirmations.length) {
                this.startRecording();
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
            this.caps = await window.VoiceRuntime.loadCaps(this.cfg.capUrl);
        },
    };
};
</script>
@endif

{{-- Reusable voice-dictation control for any input/textarea. Records one
     clip, posts it to the transcribe-only endpoint (charges voice_stt,
     plan-gated like a turn), and hands the text back via opts.onText.

     IMPORTANT: this is defined OUTSIDE the @if($voiceAvailable) gate so that
     surfaces which mount `x-data="voiceDictation(...)"` on an always-rendered
     container (e.g. the header search box, the companion composer) never
     reference an undefined function — that would throw an Alpine init error
     and break the host component's other behaviours for plan-gated users.
     The control degrades gracefully: when window.__voice (set only by the
     full widget when voice is available) is absent, vdToggle() reports
     "Voice not available." instead of recording.

       <span x-data="voiceDictation({ onText(t){ this.$refs.box.value = t } })">
         <button @click="vdToggle()" :class="vdRecording && 'text-red-400'">mic</button>
       </span> --}}
<script>
window.voiceDictation = window.voiceDictation || function (opts) {
    opts = opts || {};
    return {
        vdRec: null,
        vdChunks: [],
        vdRecording: false,
        vdBusy: false,
        vdStatus: '',

        async vdToggle() {
            if (this.vdRecording) { try { this.vdRec.stop(); } catch (e) {} return; }
            if (!window.__voice || !window.__voice.dictateUrl) { this.vdStatus = 'Voice not available.'; return; }
            if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') { this.vdStatus = 'No mic support.'; return; }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
                this.vdRec = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
                this.vdChunks = [];
                this.vdRec.ondataavailable = (e) => { if (e.data.size) this.vdChunks.push(e.data); };
                this.vdRec.onstop = async () => {
                    stream.getTracks().forEach(t => t.stop());
                    this.vdRecording = false;
                    this.vdBusy = true;
                    this.vdStatus = 'Transcribing…';
                    const blob = new Blob(this.vdChunks, { type: this.vdRec.mimeType || 'audio/webm' });
                    const fd = new FormData();
                    fd.append('audio', blob, 'dictate.webm');
                    try {
                        const res = await fetch(window.__voice.dictateUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': window.__voice.csrf, 'Accept': 'application/json' },
                            body: fd,
                            credentials: 'same-origin',
                        });
                        const j = await res.json().catch(() => ({}));
                        if (res.ok && j.text) {
                            this.vdStatus = '';
                            if (opts.onText) opts.onText.call(this, j.text);
                        } else {
                            this.vdStatus = j.error || 'Could not transcribe.';
                        }
                    } catch (e) {
                        this.vdStatus = 'Network error.';
                    }
                    this.vdBusy = false;
                };
                this.vdRec.start();
                this.vdRecording = true;
                this.vdStatus = 'Listening…';
            } catch (e) {
                this.vdStatus = 'Microphone permission denied.';
            }
        },
    };
};
</script>
@endauth
