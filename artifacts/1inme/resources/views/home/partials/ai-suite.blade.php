{{-- ============================ AI SUITE ============================ --}}
@php
    $__aiProducts = [
        [
            'eyebrow' => 'AI Chatbot',
            'title'   => 'A 24/7 chatbot trained on your Link in Bio.',
            'desc'    => 'Greets every visitor in your voice, answers from your real content, captures leads and books calls — never asleep.',
            'icon'    => 'fa-comments',
            'color'   => '#3d6bff',
            'route'   => 'site.ai-chatbot',
        ],
        [
            'eyebrow' => 'AI Agent',
            'title'   => 'A teammate that runs multi-step tasks.',
            'desc'    => 'Qualifies leads, drafts outreach, updates your contacts and follows up — across your inbox, calendar and CRM.',
            'icon'    => 'fa-robot',
            'color'   => '#1bd4d9',
            'route'   => 'site.ai-agent',
        ],
        [
            'eyebrow' => 'AI Widget',
            'title'   => 'Embed an AI assistant on any website.',
            'desc'    => 'One snippet on WordPress, Shopify, Webflow or your custom site — answers questions and routes hot leads to your inbox.',
            'icon'    => 'fa-window-restore',
            'color'   => '#e94e8c',
            'route'   => 'site.ai-widget',
        ],
        [
            'eyebrow' => 'AI Voice Assistant',
            'title'   => 'Picks up calls in your voice.',
            'desc'    => 'AI receptionist that answers your number, qualifies callers, books real meetings and warm-transfers when it matters.',
            'icon'    => 'fa-headset',
            'color'   => '#ff8a3c',
            'route'   => 'site.ai-voice-assistant',
        ],
    ];
@endphp
@php
    $__aiKey = function ($eyebrow) {
        return [
            'AI Chatbot'         => 'chatbot',
            'AI Agent'           => 'agent',
            'AI Widget'          => 'widget',
            'AI Voice Assistant' => 'voice',
        ][$eyebrow] ?? 'chatbot';
    };
@endphp
<style>
    /* ===== AI Suite v2 — animated illustrations ===== */
    .ai-suite-v2 { isolation: isolate; }
    .ai-suite-v2 .ai-bg-grid {
        position: absolute; inset: 0; pointer-events: none; opacity: .35;
        background-image:
            linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 80%);
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 80%);
    }
    .ai-suite-v2 .ai-bg-blob {
        position: absolute; width: 460px; height: 460px;
        border-radius: 9999px; filter: blur(80px);
        opacity: .35; pointer-events: none;
        animation: aiBlobFloat 18s ease-in-out infinite;
    }
    .ai-suite-v2 .ai-bg-blob-a { top: -120px; left: -100px; background: #3d6bff; }
    .ai-suite-v2 .ai-bg-blob-b { bottom: -180px; right: -120px; background: #5c83ff; animation-delay: -6s; }
    .ai-suite-v2 .ai-bg-blob-c { top: 30%; right: 25%; width: 320px; height: 320px; background: #6e61ff; animation-delay: -12s; opacity: .22; }
    @keyframes aiBlobFloat {
        0%,100% { transform: translate3d(0,0,0) scale(1); }
        50%     { transform: translate3d(40px,-30px,0) scale(1.08); }
    }
    html.light-mode .ai-suite-v2 .ai-bg-blob { opacity: .18; }
    html.light-mode .ai-suite-v2 .ai-bg-grid { opacity: .35;
        background-image:
            linear-gradient(rgba(15,23,42,.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15,23,42,.06) 1px, transparent 1px);
    }

    /* Headline shimmer sweep */
    .ai-shimmer {
        color: #90acff;
    }
    @keyframes aiShimmer { 0%{ background-position: 0% 50%; } 100%{ background-position: 200% 50%; } }

    /* Card */
    .ai-card {
        position: relative; display: block; overflow: hidden;
        border-radius: 1.5rem;
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        padding: 1.25rem 1.25rem 1.4rem;
        transition: transform .4s cubic-bezier(.2,.7,.2,1), border-color .35s ease, box-shadow .4s ease;
    }
    .ai-card:hover, .ai-card:focus-visible {
        transform: translateY(-6px);
        border-color: color-mix(in srgb, var(--ai-accent, #3d6bff) 55%, transparent);
        box-shadow: 0 30px 70px -28px color-mix(in srgb, var(--ai-accent, #3d6bff) 70%, transparent);
    }
    html.light-mode .ai-card {
        background: #ffffff;
        border-color: rgba(15,23,42,.08);
        box-shadow: 0 4px 14px -8px rgba(15,23,42,.10);
    }
    .ai-card-glow {
        position: absolute; inset: -1px; border-radius: inherit; pointer-events: none;
        background: color-mix(in srgb, var(--ai-accent, #3d6bff) 22%, transparent);
        opacity: .35; transition: opacity .4s ease;
    }
    .ai-card:hover .ai-card-glow { opacity: 1; }
    .ai-card-corner {
        position: absolute; top: -50px; right: -50px; width: 160px; height: 160px;
        border-radius: 9999px; opacity: .22;
        background: var(--ai-accent, #3d6bff);
    }

    /* Illustration frame */
    .ai-illus {
        position: relative; height: 132px; border-radius: 1rem; overflow: hidden;
        margin-bottom: 1rem;
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
    }
    html.light-mode .ai-illus {
        background:
            radial-gradient(120% 100% at 0% 0%, color-mix(in srgb, var(--ai-accent, #3d6bff) 14%, transparent), transparent 60%),
            linear-gradient(180deg, #f8fafc, #ffffff);
        border-color: rgba(15,23,42,.08);
    }

    /* ---- Chatbot illustration ---- */
    .ai-chat-bubble {
        position: absolute; padding: .42rem .65rem; border-radius: .9rem;
        font-size: .68rem; font-weight: 600; line-height: 1.1;
        max-width: 70%;
        animation: aiChatPop .5s ease both;
    }
    .ai-chat-b1 { top: 14px; left: 12px; background: rgba(255,255,255,.10); color: #e5e7eb; border-bottom-left-radius: .3rem; animation-delay: .1s; }
    .ai-chat-b2 { top: 50px; right: 12px; background: linear-gradient(135deg, var(--ai-accent), #6366f1); color: #fff; border-bottom-right-radius: .3rem; animation-delay: .8s; }
    .ai-chat-b3 { bottom: 16px; left: 12px; background: rgba(255,255,255,.10); color: #e5e7eb; border-bottom-left-radius: .3rem; padding: .55rem .75rem; animation-delay: 1.6s; }
    html.light-mode .ai-chat-b1, html.light-mode .ai-chat-b3 { background: rgba(15,23,42,.06); color: #1e293b; }
    @keyframes aiChatPop { 0%{ opacity:0; transform: translateY(8px) scale(.9);} 100%{ opacity:1; transform: translateY(0) scale(1);} }
    .ai-typing { display: inline-flex; gap: 3px; vertical-align: middle; }
    .ai-typing i {
        width: 5px; height: 5px; border-radius: 9999px; background: currentColor;
        animation: aiTyping 1.2s ease-in-out infinite;
    }
    .ai-typing i:nth-child(2) { animation-delay: .15s; }
    .ai-typing i:nth-child(3) { animation-delay: .3s; }
    @keyframes aiTyping { 0%,100% { opacity: .3; transform: translateY(0);} 50% { opacity: 1; transform: translateY(-3px);} }
    .ai-card:hover .ai-chat-bubble { animation-duration: .35s; }

    /* ---- Agent illustration ---- */
    .ai-tasklist {
        position: absolute; inset: 14px; display: flex; flex-direction: column; gap: 7px;
        padding: 10px; border-radius: .75rem;
        background: rgba(0,0,0,.25);
        border: 1px solid rgba(255,255,255,.06);
    }
    html.light-mode .ai-tasklist { background: #fff; border-color: rgba(15,23,42,.06); box-shadow: inset 0 0 0 1px rgba(15,23,42,.02); }
    .ai-task {
        display: flex; align-items: center; gap: 8px;
        font-size: .68rem; font-weight: 600; color: #e5e7eb;
    }
    html.light-mode .ai-task { color: #334155; }
    .ai-task-check {
        flex: 0 0 auto; width: 14px; height: 14px; border-radius: 4px;
        border: 1.5px solid color-mix(in srgb, var(--ai-accent) 60%, #94a3b8);
        position: relative; overflow: hidden;
    }
    .ai-task-check::after {
        content: ""; position: absolute; left: 2px; top: 4px; width: 7px; height: 4px;
        border-left: 2px solid #fff; border-bottom: 2px solid #fff;
        transform: rotate(-45deg) scale(0); transform-origin: left top;
        transition: transform .25s ease;
    }
    .ai-task.done .ai-task-check { background: var(--ai-accent); border-color: var(--ai-accent); }
    .ai-task.done .ai-task-check::after { transform: rotate(-45deg) scale(1); }
    .ai-task-bar { flex: 1; height: 6px; border-radius: 9999px; background: rgba(255,255,255,.10); overflow: hidden; }
    html.light-mode .ai-task-bar { background: rgba(15,23,42,.08); }
    .ai-task-bar i {
        display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--ai-accent), #6366f1);
        animation: aiTaskFill 4s ease-in-out infinite;
    }
    .ai-task:nth-child(1) { animation: aiTaskRow 4s ease-in-out infinite; }
    .ai-task:nth-child(2) { animation: aiTaskRow 4s ease-in-out infinite 1.2s; }
    .ai-task:nth-child(3) { animation: aiTaskRow 4s ease-in-out infinite 2.4s; }
    @keyframes aiTaskRow {
        0%, 8%   { opacity: .55; }
        10%, 35% { opacity: 1; }
        40%      { opacity: 1; }
    }
    .ai-task:nth-child(1) .ai-task-bar i { animation-delay: 0s; }
    .ai-task:nth-child(2) .ai-task-bar i { animation-delay: 1.2s; }
    .ai-task:nth-child(3) .ai-task-bar i { animation-delay: 2.4s; }
    @keyframes aiTaskFill { 0%{ width: 0; } 60%, 100%{ width: 100%; } }
    .ai-card:hover .ai-task .ai-task-check { background: var(--ai-accent); border-color: var(--ai-accent); }
    .ai-card:hover .ai-task .ai-task-check::after { transform: rotate(-45deg) scale(1); }

    /* ---- Widget illustration (browser frame) ---- */
    .ai-browser {
        position: absolute; inset: 14px; border-radius: .75rem; overflow: hidden;
        background: rgba(0,0,0,.30);
        border: 1px solid rgba(255,255,255,.08);
        display: flex; flex-direction: column;
    }
    html.light-mode .ai-browser { background: #fff; border-color: rgba(15,23,42,.08); }
    .ai-browser-bar {
        display: flex; align-items: center; gap: 4px;
        padding: 5px 7px; background: rgba(255,255,255,.04);
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    html.light-mode .ai-browser-bar { background: #f1f5f9; border-bottom-color: rgba(15,23,42,.06); }
    .ai-browser-bar span { width: 6px; height: 6px; border-radius: 9999px; background: rgba(255,255,255,.20); }
    html.light-mode .ai-browser-bar span { background: rgba(15,23,42,.18); }
    .ai-browser-body { position: relative; flex: 1; padding: 8px; display: flex; flex-direction: column; gap: 5px; }
    .ai-browser-line { height: 5px; border-radius: 9999px; background: rgba(255,255,255,.08); }
    html.light-mode .ai-browser-line { background: rgba(15,23,42,.08); }
    .ai-browser-line.l1 { width: 70%; }
    .ai-browser-line.l2 { width: 90%; }
    .ai-browser-line.l3 { width: 55%; }
    .ai-widget-pop {
        position: absolute; right: 8px; bottom: 8px;
        width: 64px; padding: 6px 8px; border-radius: .55rem;
        background: linear-gradient(135deg, var(--ai-accent), #6366f1);
        color: #fff; font-size: .58rem; font-weight: 700;
        box-shadow: 0 8px 22px -8px var(--ai-accent);
        display: flex; align-items: center; gap: 5px;
        transform-origin: bottom right;
        animation: aiWidgetPop 4s ease-in-out infinite;
    }
    .ai-widget-pop::before {
        content: ""; width: 7px; height: 7px; border-radius: 9999px; background: #fff;
        box-shadow: 0 0 0 0 rgba(255,255,255,.6);
        animation: aiWidgetDot 1.6s ease-out infinite;
    }
    @keyframes aiWidgetPop {
        0%, 20%, 100% { transform: scale(0) rotate(-12deg); opacity: 0; }
        30%, 75%      { transform: scale(1) rotate(0); opacity: 1; }
        85%           { transform: scale(.9) rotate(-4deg); opacity: .6; }
    }
    @keyframes aiWidgetDot {
        0% { box-shadow: 0 0 0 0 rgba(255,255,255,.55); }
        80%, 100% { box-shadow: 0 0 0 8px rgba(255,255,255,0); }
    }
    .ai-card:hover .ai-widget-pop { animation-duration: 2.4s; }

    /* ---- Voice illustration ---- */
    .ai-voice {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 14px;
    }
    .ai-phone {
        width: 46px; height: 70px; border-radius: 10px;
        background: linear-gradient(160deg, #1f2937, #0f172a);
        border: 1px solid rgba(255,255,255,.10);
        box-shadow: 0 14px 34px -16px rgba(0,0,0,.55), inset 0 0 0 1px rgba(255,255,255,.04);
        position: relative; overflow: hidden;
        animation: aiPhoneRing 2.4s ease-in-out infinite;
    }
    .ai-phone::before {
        content: ""; position: absolute; left: 50%; top: 6px; transform: translateX(-50%);
        width: 14px; height: 3px; border-radius: 9999px; background: rgba(255,255,255,.18);
    }
    .ai-phone::after {
        content: "\f095"; /* fa phone */
        font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome";
        font-weight: 900; font-size: 18px;
        position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
        color: var(--ai-accent);
        text-shadow: 0 0 14px color-mix(in srgb, var(--ai-accent) 70%, transparent);
    }
    @keyframes aiPhoneRing {
        0%, 100% { transform: rotate(-3deg); }
        50%      { transform: rotate(3deg); }
    }
    .ai-wave { display: flex; align-items: center; gap: 4px; height: 56px; }
    .ai-wave i {
        display: block; width: 4px; border-radius: 9999px;
        background: linear-gradient(180deg, var(--ai-accent), #6366f1);
        animation: aiWaveBar 1.2s ease-in-out infinite;
    }
    .ai-wave i:nth-child(1){ height: 18px; animation-delay: 0s; }
    .ai-wave i:nth-child(2){ height: 30px; animation-delay: .12s; }
    .ai-wave i:nth-child(3){ height: 44px; animation-delay: .24s; }
    .ai-wave i:nth-child(4){ height: 26px; animation-delay: .36s; }
    .ai-wave i:nth-child(5){ height: 38px; animation-delay: .48s; }
    .ai-wave i:nth-child(6){ height: 22px; animation-delay: .60s; }
    .ai-wave i:nth-child(7){ height: 14px; animation-delay: .72s; }
    @keyframes aiWaveBar {
        0%,100% { transform: scaleY(.35); }
        50%     { transform: scaleY(1); }
    }
    .ai-card:hover .ai-wave i { animation-duration: .7s; }
    .ai-card:hover .ai-phone { animation-duration: 1.2s; }

    /* Reveal stagger — uses existing `.reveal` mechanism */
    .ai-suite-v2 .ai-card { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1); }
    .ai-suite-v2 .ai-card.in-view, .ai-suite-v2 .reveal.in-view .ai-card { opacity: 1; transform: translateY(0); }
    .ai-suite-v2 .ai-card.d1 { transition-delay: .05s; }
    .ai-suite-v2 .ai-card.d2 { transition-delay: .15s; }
    .ai-suite-v2 .ai-card.d3 { transition-delay: .25s; }
    .ai-suite-v2 .ai-card.d4 { transition-delay: .35s; }

    @media (prefers-reduced-motion: reduce) {
        .ai-suite-v2 .ai-bg-blob,
        .ai-shimmer,
        .ai-chat-bubble,
        .ai-typing i,
        .ai-task,
        .ai-task-bar i,
        .ai-widget-pop,
        .ai-widget-pop::before,
        .ai-phone,
        .ai-wave i { animation: none !important; }
        .ai-shimmer { color: #90acff; }
        .ai-chat-bubble { opacity: 1; transform: none; }
        .ai-widget-pop { transform: none; opacity: 1; }
        .ai-suite-v2 .ai-card { opacity: 1; transform: none; }
    }
</style>
<section id="ai-suite" class="ai-suite-v2 py-24 lg:py-32 relative overflow-hidden" aria-labelledby="ai-suite-h">
    <div class="ai-bg-grid" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-a" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-b" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-c" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#3d6bff">AI suite</div>
            <h2 id="ai-suite-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Built-in AI that <span class="ai-shimmer">works the room</span> for you.
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A chatbot for your Link in Bio, an agent that runs playbooks, an embeddable widget for any site, and a voice assistant that picks up your calls — all under one login.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($__aiProducts as $i => $a)
                @php $key = $__aiKey($a['eyebrow']); @endphp
                <a href="{{ route($a['route']) }}"
                   class="ai-card reveal rd-{{ ($i % 4) + 1 }} d{{ $i + 1 }} group"
                   style="--ai-accent: {{ $a['color'] }};"
                   aria-label="{{ $a['eyebrow'] }} — learn more">
                    <span class="ai-card-glow" aria-hidden="true"></span>
                    <span class="ai-card-corner" aria-hidden="true"></span>

                    <div class="ai-illus" aria-hidden="true">
                        @if($key === 'chatbot')
                            <div class="ai-chat-bubble ai-chat-b1">Hey! 👋</div>
                            <div class="ai-chat-bubble ai-chat-b2">Got it — booking a slot for you.</div>
                            <div class="ai-chat-bubble ai-chat-b3">
                                <span class="ai-typing" style="color: {{ $a['color'] }};">
                                    <i></i><i></i><i></i>
                                </span>
                            </div>
                        @elseif($key === 'agent')
                            <div class="ai-tasklist">
                                <div class="ai-task done">
                                    <span class="ai-task-check"></span>
                                    <span>Qualify lead</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                                <div class="ai-task done">
                                    <span class="ai-task-check"></span>
                                    <span>Draft email</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                                <div class="ai-task">
                                    <span class="ai-task-check"></span>
                                    <span>Schedule follow-up</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                            </div>
                        @elseif($key === 'widget')
                            <div class="ai-browser">
                                <div class="ai-browser-bar">
                                    <span></span><span></span><span></span>
                                </div>
                                <div class="ai-browser-body">
                                    <div class="ai-browser-line l1"></div>
                                    <div class="ai-browser-line l2"></div>
                                    <div class="ai-browser-line l3"></div>
                                    <div class="ai-browser-line l2"></div>
                                    <div class="ai-widget-pop">
                                        <span>Ask AI</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="ai-voice">
                                <div class="ai-phone"></div>
                                <div class="ai-wave">
                                    <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-1" style="color: {{ $a['color'] }};">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-lg font-bold mb-2 leading-snug">{{ $a['title'] }}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-4">{{ $a['desc'] }}</p>
                    <span class="relative inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $a['color'] }};">
                        Learn more <i class="fas fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

