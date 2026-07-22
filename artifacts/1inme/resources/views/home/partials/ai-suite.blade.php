{{-- ============================ AI SUITE ============================ --}}
@php
    $__aiProducts = [
        [
            'key'      => 'chatbot',
            'eyebrow'  => 'AI Chatbot',
            'title'    => 'A 24/7 chatbot trained on your Link in Bio.',
            'short'    => 'Greets every visitor in your voice, answers from your real content and captures leads while you sleep.',
            'image'    => 'img/ai/chatbot.svg',
            'color'    => '#3d6bff',
            'route'    => 'site.ai-chatbot',
            'features' => [
                'Trained on your real content',
                'Captures leads around the clock',
                'Books calls automatically',
                'Hands off to a human',
            ],
        ],
        [
            'key'      => 'agent',
            'eyebrow'  => 'AI Agent',
            'title'    => 'A teammate that runs multi-step tasks.',
            'short'    => 'Qualifies leads, drafts outreach, updates contacts and follows up across your inbox, calendar and CRM.',
            'image'    => 'img/ai/agent.svg',
            'color'    => '#1bd4d9',
            'route'    => 'site.ai-agent',
            'features' => [
                'Runs editable playbooks',
                'Connects your tools & CRM',
                'Asks before key actions',
                'Full audit trail',
            ],
        ],
        [
            'key'      => 'widget',
            'eyebrow'  => 'AI Widget',
            'title'    => 'Embed an AI assistant on any website.',
            'short'    => 'One snippet on WordPress, Shopify, Webflow or your own site. Answers questions and routes hot leads to you.',
            'image'    => 'img/ai/widget.svg',
            'color'    => '#e94e8c',
            'route'    => 'site.ai-widget',
            'features' => [
                'One snippet, any site',
                'Matches your brand',
                'Replies in 30+ languages',
                'Privacy-first analytics',
            ],
        ],
        [
            'key'      => 'voice',
            'eyebrow'  => 'AI Voice Assistant',
            'title'    => 'Picks up your calls in your own voice.',
            'short'    => 'An AI receptionist that answers your number, qualifies callers, books real meetings and transfers when it matters.',
            'image'    => 'img/ai/voice.svg',
            'color'    => '#ff8a3c',
            'route'    => 'site.ai-voice-assistant',
            'features' => [
                'Answers in your own voice',
                'Qualifies & routes callers',
                'Books real meetings',
                'Transcript & recap of every call',
            ],
        ],
    ];
@endphp
<style>
    /* ===== AI Suite v4 — interactive live showcase ===== */
    .aisx { isolation: isolate; }

    /* Ambient background (kept light — the AI zone wrapper carries the base wash) */
    .aisx .aisx-grid-bg {
        position: absolute; inset: -44px; pointer-events: none; opacity: .3;
        background-image:
            linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(ellipse 72% 62% at 50% 42%, #000 30%, transparent 82%);
        -webkit-mask-image: radial-gradient(ellipse 72% 62% at 50% 42%, #000 30%, transparent 82%);
        animation: aisxGridPan 38s linear infinite;
        will-change: background-position;
    }
    @keyframes aisxGridPan { from { background-position: 0 0, 0 0; } to { background-position: 46px 46px, 46px 46px; } }
    html.light-mode .aisx .aisx-grid-bg {
        background-image:
            linear-gradient(rgba(15,23,42,.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15,23,42,.05) 1px, transparent 1px);
    }
    .aisx .aisx-blob {
        position: absolute; width: 440px; height: 440px; border-radius: 9999px;
        filter: blur(90px); opacity: .3; pointer-events: none; will-change: transform;
    }
    .aisx .aisx-blob-a { top: -140px; left: -110px; background: #3d6bff; animation: aisxBlobA 22s ease-in-out infinite; }
    .aisx .aisx-blob-b { bottom: -170px; right: -120px; background: #5c83ff; opacity: .22; animation: aisxBlobB 27s ease-in-out infinite; animation-delay: -8s; }
    @keyframes aisxBlobA { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(60px,40px,0) scale(1.12); } }
    @keyframes aisxBlobB { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(-48px,-40px,0) scale(1.08); } }
    html.light-mode .aisx .aisx-blob { opacity: .14; }

    /* Headline shimmer */
    .aisx .aisx-shimmer {
        position: relative;
        background: linear-gradient(100deg, #90acff, #3d6bff, #5c83ff, #90acff);
        background-size: 220% 100%;
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
        animation: aisxFlow 7s ease-in-out infinite;
    }
    @keyframes aisxFlow { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

    /* ===== Layout ===== */
    .aisx .aisx-layout { display: grid; grid-template-columns: 1fr; gap: 1.75rem; align-items: center; }
    .aisx .aisx-demo-col { order: 1; }
    .aisx .aisx-cards-col { order: 2; }
    @media (min-width: 1024px) {
        .aisx .aisx-layout { grid-template-columns: 5fr 7fr; gap: 2.75rem; }
        .aisx .aisx-cards-col { order: 1; }
        .aisx .aisx-demo-col { order: 2; }
    }

    /* ===== Selectable product cards ===== */
    .aisx .aisx-cards { display: flex; flex-direction: column; gap: .8rem; }
    .aisx .aisx-card {
        position: relative; width: 100%; text-align: left; cursor: pointer;
        display: flex; gap: .9rem; align-items: flex-start;
        padding: 1rem 1.1rem; border-radius: 1.1rem;
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        transition: border-color .3s ease, background .3s ease, transform .3s ease, box-shadow .35s ease;
        -webkit-tap-highlight-color: transparent;
    }
    html.light-mode .aisx .aisx-card { background: #fff; border-color: rgba(15,23,42,.08); box-shadow: 0 4px 14px -10px rgba(15,23,42,.12); }
    .aisx .aisx-card:hover { transform: translateX(3px); border-color: color-mix(in srgb, var(--c, #3d6bff) 45%, transparent); }
    .aisx .aisx-card:focus-visible { outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--c, #3d6bff) 45%, transparent); }
    .aisx .aisx-card[aria-selected="true"] {
        border-color: color-mix(in srgb, var(--c, #3d6bff) 62%, transparent);
        background: color-mix(in srgb, var(--c, #3d6bff) 10%, rgba(255,255,255,.02));
        box-shadow: 0 24px 60px -30px color-mix(in srgb, var(--c, #3d6bff) 80%, transparent);
    }
    html.light-mode .aisx .aisx-card[aria-selected="true"] {
        background: color-mix(in srgb, var(--c, #3d6bff) 8%, #fff);
    }
    /* Active accent rail */
    .aisx .aisx-card::before {
        content: ''; position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px;
        border-radius: 9999px; background: var(--c, #3d6bff);
        opacity: 0; transform: scaleY(.4); transform-origin: center;
        transition: opacity .3s ease, transform .35s cubic-bezier(.2,.8,.25,1.2);
    }
    .aisx .aisx-card[aria-selected="true"]::before { opacity: 1; transform: scaleY(1); }

    .aisx .aisx-card-ico {
        flex: 0 0 auto; width: 46px; height: 46px; border-radius: 14px; display: block;
        box-shadow: 0 14px 30px -14px color-mix(in srgb, var(--c, #3d6bff) 90%, transparent);
    }
    .aisx .aisx-card-body { min-width: 0; }
    .aisx .aisx-card-eyebrow { font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--c, #3d6bff); }
    .aisx .aisx-card-title { font-size: .98rem; font-weight: 700; line-height: 1.3; margin: 2px 0 4px; color: #f1f5f9; }
    html.light-mode .aisx .aisx-card-title { color: #0f172a; }
    .aisx .aisx-card-desc { font-size: .82rem; line-height: 1.5; color: #9ca3af; }
    html.light-mode .aisx .aisx-card-desc { color: #64748b; }

    /* Feature ticks — collapsed by default, expand on the active card. On small
       screens they always show so mobile isn't a bland list of titles. */
    .aisx .aisx-card-feats {
        list-style: none; margin: .55rem 0 0; padding: 0;
        display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px;
        max-height: 0; overflow: hidden; opacity: 0;
        transition: max-height .4s ease, opacity .35s ease, margin .3s ease;
    }
    .aisx .aisx-card[aria-selected="true"] .aisx-card-feats { max-height: 140px; opacity: 1; }
    .aisx .aisx-card-feats li { display: flex; align-items: flex-start; gap: 6px; font-size: .74rem; font-weight: 600; color: #cbd5e1; line-height: 1.3; }
    html.light-mode .aisx .aisx-card-feats li { color: #475569; }
    .aisx .aisx-card-feats i { color: var(--c, #3d6bff); font-size: 8px; margin-top: 4px; flex: 0 0 auto; }
    .aisx .aisx-card-link {
        display: inline-flex; align-items: center; gap: .4rem; margin-top: .6rem;
        font-size: .78rem; font-weight: 700; color: var(--c, #3d6bff);
        max-height: 0; overflow: hidden; opacity: 0; transition: max-height .4s ease, opacity .35s ease, gap .25s ease;
    }
    .aisx .aisx-card[aria-selected="true"] .aisx-card-link { max-height: 30px; opacity: 1; }
    .aisx .aisx-card-link:hover { gap: .65rem; }

    @media (max-width: 1023px) {
        /* On mobile every card shows its features + link so the section carries
           real content even before the demo animates. */
        .aisx .aisx-card-feats { max-height: 140px; opacity: 1; }
        .aisx .aisx-card-link { max-height: 30px; opacity: 1; }
    }

    /* ===== Live demo screen ===== */
    .aisx .aisx-demo-wrap { position: relative; display: flex; justify-content: center; }
    .aisx .aisx-screen {
        --c: #3d6bff;
        position: relative; width: 100%; max-width: 560px;
        border-radius: 22px; overflow: hidden;
        border: 1px solid rgba(255,255,255,.1);
        background: linear-gradient(180deg, rgba(13,14,26,.97), rgba(9,10,20,.99));
        box-shadow: 0 44px 100px -44px color-mix(in srgb, var(--c) 60%, transparent), 0 16px 40px -16px rgba(0,0,0,.6);
        animation: aisxBob 8s ease-in-out infinite; will-change: transform;
        transition: box-shadow .5s ease;
    }
    @keyframes aisxBob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
    html.light-mode .aisx .aisx-screen {
        background: linear-gradient(180deg, #0f1326, #0c1020);
        box-shadow: 0 44px 90px -46px rgba(15,23,42,.5), 0 0 0 1px color-mix(in srgb, var(--c) 22%, transparent);
    }
    .aisx .aisx-bar {
        display: flex; align-items: center; gap: 8px; padding: 12px 16px;
        border-bottom: 1px solid rgba(255,255,255,.07); background: rgba(255,255,255,.02);
    }
    .aisx .aisx-dot { width: 11px; height: 11px; border-radius: 9999px; display: inline-block; }
    .aisx .aisx-dot:nth-child(1) { background: #ff5f57; }
    .aisx .aisx-dot:nth-child(2) { background: #febc2e; }
    .aisx .aisx-dot:nth-child(3) { background: #28c840; }
    .aisx .aisx-bar-title {
        margin-left: 8px; font-size: 12px; font-weight: 600; letter-spacing: .02em;
        color: #9ca3af; display: inline-flex; align-items: center; gap: 7px;
    }
    .aisx .aisx-bar-title b { color: color-mix(in srgb, var(--c) 70%, #fff); font-weight: 700; }
    .aisx .aisx-live { width: 7px; height: 7px; border-radius: 9999px; background: #28c840; box-shadow: 0 0 0 0 rgba(40,200,64,.6); animation: aisxPulse 2.2s ease-out infinite; }
    @keyframes aisxPulse { 0% { box-shadow: 0 0 0 0 rgba(40,200,64,.55); } 70% { box-shadow: 0 0 0 7px rgba(40,200,64,0); } 100% { box-shadow: 0 0 0 0 rgba(40,200,64,0); } }

    .aisx .aisx-stage {
        position: relative; height: 400px; padding: 18px 18px 22px;
        font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; color: #e5e7eb;
    }
    .aisx .aisx-scene { position: absolute; inset: 18px 18px 22px; display: none; flex-direction: column; }
    .aisx .aisx-scene.is-active { display: flex; }

    /* Row reveal — resting/no-JS/reduced-motion = visible; JS play animates in. */
    .aisx .aisx-row { opacity: 1; }
    @media (prefers-reduced-motion: no-preference) {
        .aisx .aisx-scene.is-active.is-playing .aisx-row {
            opacity: 0; animation: aisxRow .55s cubic-bezier(.2,.8,.25,1.2) forwards; animation-delay: var(--d, 0s);
            will-change: transform, opacity;
        }
    }
    @keyframes aisxRow { from { opacity: 0; transform: translateY(11px) scale(.98); } to { opacity: 1; transform: none; } }

    /* Chat bubbles (chatbot + widget scenes) */
    .aisx .aisx-chat { display: flex; flex-direction: column; gap: .55rem; }
    .aisx .aisx-msg { max-width: 84%; padding: .6rem .8rem; border-radius: 1rem; font-size: .86rem; line-height: 1.4; }
    .aisx .aisx-msg-in { align-self: flex-start; background: rgba(255,255,255,.06); border-top-left-radius: .3rem; color: #e9edf5; }
    .aisx .aisx-msg-out { align-self: flex-end; color: #fff; border-top-right-radius: .3rem; background: linear-gradient(135deg, color-mix(in srgb, var(--c) 92%, #000), color-mix(in srgb, var(--c) 65%, #000)); }
    html.light-mode .aisx .aisx-msg-in { background: rgba(255,255,255,.08); }
    .aisx .aisx-msg small { display: block; margin-top: 3px; font-size: .66rem; opacity: .7; }
    .aisx .aisx-typing { display: inline-flex; align-items: center; gap: 4px; }
    .aisx .aisx-typing i { width: 6px; height: 6px; border-radius: 50%; background: color-mix(in srgb, var(--c) 70%, #fff); opacity: .6; animation: aisxDot 1.2s ease-in-out infinite; }
    .aisx .aisx-typing i:nth-child(2) { animation-delay: .18s; }
    .aisx .aisx-typing i:nth-child(3) { animation-delay: .36s; }
    @keyframes aisxDot { 0%,60%,100% { transform: translateY(0); opacity: .4; } 30% { transform: translateY(-4px); opacity: 1; } }

    /* Chip / capture pill */
    .aisx .aisx-chip {
        margin-top: auto; align-self: flex-start; display: inline-flex; align-items: center; gap: 7px;
        font-size: .74rem; font-weight: 700; padding: 7px 12px; border-radius: 9999px;
        color: #b8ffcf; background: rgba(34,197,94,.14); border: 1px solid rgba(34,197,94,.32);
    }
    .aisx .aisx-chip i { color: #22c55e; }

    /* Agent playbook */
    .aisx .aisx-play { display: flex; flex-direction: column; gap: .55rem; }
    .aisx .aisx-play-head { font-size: .8rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; margin-bottom: .2rem; }
    .aisx .aisx-play-head .aisx-run { font-size: .66rem; font-weight: 700; color: color-mix(in srgb, var(--c) 75%, #fff); background: color-mix(in srgb, var(--c) 18%, transparent); padding: 2px 8px; border-radius: 9999px; }
    .aisx .aisx-step { display: flex; align-items: flex-start; gap: .7rem; padding: .6rem .7rem; border-radius: .8rem; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06); font-size: .84rem; color: #e5e7eb; }
    html.light-mode .aisx .aisx-step { background: rgba(255,255,255,.05); }
    .aisx .aisx-step-ck { flex: 0 0 auto; width: 20px; height: 20px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: #fff; background: #22c55e; margin-top: 1px; }
    .aisx .aisx-step.is-hold .aisx-step-ck { background: color-mix(in srgb, var(--c) 80%, #000); }
    .aisx .aisx-step-sub { display: block; font-size: .7rem; color: #9ca3af; margin-top: 2px; }
    .aisx .aisx-hold-pill { font-size: .64rem; font-weight: 700; color: #ffe2c7; background: rgba(255,138,60,.18); border: 1px solid rgba(255,138,60,.3); padding: 2px 7px; border-radius: 9999px; margin-left: auto; align-self: center; }

    /* Widget scene — mini website + floating bubble */
    .aisx .aisx-site { position: relative; flex: 1; border-radius: 14px; overflow: hidden; border: 1px solid rgba(255,255,255,.07); background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.015)); }
    .aisx .aisx-site-nav { display: flex; align-items: center; gap: 6px; padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.06); }
    .aisx .aisx-site-logo { width: 20px; height: 20px; border-radius: 6px; background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 55%, #000)); }
    .aisx .aisx-site-links { margin-left: auto; display: flex; gap: 10px; }
    .aisx .aisx-site-links span { width: 30px; height: 6px; border-radius: 9999px; background: rgba(255,255,255,.12); }
    .aisx .aisx-site-body { padding: 16px 14px; }
    .aisx .aisx-site-body .l { height: 8px; border-radius: 9999px; background: rgba(255,255,255,.1); margin-bottom: 8px; }
    .aisx .aisx-site-body .l1 { width: 62%; height: 12px; background: rgba(255,255,255,.16); }
    .aisx .aisx-site-body .l2 { width: 90%; }
    .aisx .aisx-site-body .l3 { width: 80%; }
    .aisx .aisx-widget-pop {
        position: absolute; right: 12px; bottom: 12px; width: 200px;
        border-radius: 14px; overflow: hidden; border: 1px solid color-mix(in srgb, var(--c) 40%, transparent);
        background: rgba(13,14,26,.98); box-shadow: 0 24px 50px -20px rgba(0,0,0,.7);
    }
    .aisx .aisx-widget-pop-bar { display: flex; align-items: center; gap: 6px; padding: 8px 10px; font-size: .68rem; font-weight: 700; color: #fff; background: linear-gradient(135deg, color-mix(in srgb, var(--c) 90%, #000), color-mix(in srgb, var(--c) 60%, #000)); }
    .aisx .aisx-widget-pop-body { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
    .aisx .aisx-widget-pop .aisx-msg { max-width: 100%; font-size: .74rem; padding: .45rem .6rem; }
    .aisx .aisx-widget-bubble {
        position: absolute; right: 12px; bottom: 12px; width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem;
        background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 55%, #000));
        box-shadow: 0 14px 30px -12px color-mix(in srgb, var(--c) 90%, transparent);
        animation: aisxBubble 3s ease-in-out infinite;
    }
    @keyframes aisxBubble { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    .aisx .aisx-lang-tag { position: absolute; left: 12px; bottom: 14px; font-size: .64rem; font-weight: 700; color: #cbd5e1; background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.1); padding: 3px 8px; border-radius: 9999px; }

    /* Voice scene — call UI */
    .aisx .aisx-call { flex: 1; display: flex; flex-direction: column; align-items: center; }
    .aisx .aisx-call-head { display: flex; flex-direction: column; align-items: center; gap: 4px; margin-bottom: 12px; }
    .aisx .aisx-call-avatar {
        position: relative; width: 62px; height: 62px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.3rem; background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 55%, #000));
    }
    .aisx .aisx-call-avatar::after { content: ''; position: absolute; inset: -6px; border-radius: 50%; border: 2px solid color-mix(in srgb, var(--c) 55%, transparent); animation: aisxRing 2.2s ease-out infinite; }
    @keyframes aisxRing { 0% { transform: scale(1); opacity: .8; } 100% { transform: scale(1.35); opacity: 0; } }
    .aisx .aisx-call-name { font-size: .92rem; font-weight: 700; color: #fff; }
    .aisx .aisx-call-sub { font-size: .72rem; color: #9ca3af; }
    .aisx .aisx-wave { display: flex; align-items: center; justify-content: center; gap: 3px; height: 26px; margin-bottom: 14px; }
    .aisx .aisx-wave i { width: 3px; border-radius: 9999px; background: color-mix(in srgb, var(--c) 75%, #fff); opacity: .85; animation: aisxWave 1.1s ease-in-out infinite; }
    .aisx .aisx-wave i:nth-child(odd) { height: 9px; } .aisx .aisx-wave i:nth-child(even) { height: 18px; } .aisx .aisx-wave i:nth-child(3n) { height: 13px; }
    .aisx .aisx-wave i:nth-child(1){animation-delay:-.9s} .aisx .aisx-wave i:nth-child(2){animation-delay:-.7s} .aisx .aisx-wave i:nth-child(3){animation-delay:-.5s} .aisx .aisx-wave i:nth-child(4){animation-delay:-.3s} .aisx .aisx-wave i:nth-child(5){animation-delay:-.1s} .aisx .aisx-wave i:nth-child(6){animation-delay:-.5s} .aisx .aisx-wave i:nth-child(7){animation-delay:-.8s}
    @keyframes aisxWave { 0%,100% { transform: scaleY(.5); } 50% { transform: scaleY(1.3); } }
    .aisx .aisx-transcript { width: 100%; display: flex; flex-direction: column; gap: .5rem; }
    .aisx .aisx-line { font-size: .82rem; line-height: 1.4; color: #cbd5e1; }
    .aisx .aisx-line b { color: color-mix(in srgb, var(--c) 70%, #fff); }
    .aisx .aisx-line.caller b { color: #cbd5e1; }

    /* Floating "one login" badge */
    .aisx .aisx-badge {
        position: absolute; bottom: -16px; left: -12px; z-index: 3;
        display: flex; align-items: center; gap: 10px;
        background: #11121e; border: 1px solid rgba(255,255,255,.1); border-radius: 15px; padding: 11px 13px;
        box-shadow: 0 22px 50px -22px rgba(0,0,0,.7); animation: aisxBadge 6s ease-in-out infinite;
    }
    html.light-mode .aisx .aisx-badge { background: #fff; border-color: rgba(15,23,42,.1); }
    @keyframes aisxBadge { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
    .aisx .aisx-badge-ico { width: 34px; height: 34px; border-radius: 11px; flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, #3d6bff, #1bd4d9); }
    .aisx .aisx-badge-t { font-size: .8rem; font-weight: 700; color: #fff; line-height: 1.15; }
    html.light-mode .aisx .aisx-badge-t { color: #0f172a; }
    .aisx .aisx-badge-s { font-size: .68rem; color: #9ca3af; }

    @media (max-width: 1023px) {
        .aisx .aisx-stage { height: 380px; }
        .aisx .aisx-badge { left: 50%; transform: translateX(-50%); }
        @keyframes aisxBadge { 0%,100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(-7px); } }
    }

    /* ===== Reduced motion ===== */
    @media (prefers-reduced-motion: reduce) {
        .aisx .aisx-grid-bg, .aisx .aisx-blob, .aisx .aisx-shimmer, .aisx .aisx-screen,
        .aisx .aisx-live, .aisx .aisx-typing i, .aisx .aisx-widget-bubble,
        .aisx .aisx-call-avatar::after, .aisx .aisx-wave i, .aisx .aisx-badge { animation: none !important; }
        .aisx .aisx-shimmer { background: none !important; -webkit-text-fill-color: #90acff !important; color: #90acff !important; }
        .aisx .aisx-screen { transform: none !important; }
    }
</style>
<section id="ai-suite" class="aisx py-24 lg:py-32 relative overflow-hidden" aria-labelledby="ai-suite-h">
    <div class="aisx-grid-bg" aria-hidden="true"></div>
    <div class="aisx-blob aisx-blob-a" aria-hidden="true"></div>
    <div class="aisx-blob aisx-blob-b" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#3d6bff">
                <i class="fas fa-microchip text-sm"></i> AI Suite
            </div>
            <h2 id="ai-suite-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Built-in AI that <span class="aisx-shimmer">works the room</span> for you.
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A chatbot for your Link in Bio, an agent that runs playbooks, an embeddable widget for any
                site and a voice assistant that picks up your calls. Four AI coworkers, one login. Tap any
                one to watch it work.
            </p>
        </div>

        <div class="aisx-layout">
            {{-- Selectable product cards --}}
            <div class="aisx-cards-col reveal rd-2">
                <div class="aisx-cards" role="tablist" aria-label="AI Suite products">
                    @foreach($__aiProducts as $i => $a)
                        {{-- div (not <button>): the card holds a real "Learn more"
                             <a>, and interactive content inside a button is invalid
                             HTML. Enter/Space activation is handled in the JS below. --}}
                        <div class="aisx-card"
                                style="--c: {{ $a['color'] }};"
                                role="tab"
                                tabindex="0"
                                data-aisx-tab="{{ $i }}"
                                aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-controls="aisx-scene-{{ $a['key'] }}">
                            <img class="aisx-card-ico" src="{{ asset($a['image']) }}" alt="" width="46" height="46" loading="lazy" decoding="async">
                            <span class="aisx-card-body">
                                <span class="aisx-card-eyebrow">{{ $a['eyebrow'] }}</span>
                                <span class="aisx-card-title block">{{ $a['title'] }}</span>
                                <span class="aisx-card-desc block">{{ $a['short'] }}</span>
                                <ul class="aisx-card-feats">
                                    @foreach($a['features'] as $feat)
                                        <li><i class="fas fa-circle"></i><span>{{ $feat }}</span></li>
                                    @endforeach
                                </ul>
                                <a href="{{ route($a['route']) }}" class="aisx-card-link">
                                    Learn more <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Live demo screen --}}
            <div class="aisx-demo-col reveal rd-3">
                <div class="aisx-demo-wrap">
                    <div class="aisx-screen" data-aisx-screen style="--c: {{ $__aiProducts[0]['color'] }};">
                        <div class="aisx-bar">
                            <span class="aisx-dot" aria-hidden="true"></span>
                            <span class="aisx-dot" aria-hidden="true"></span>
                            <span class="aisx-dot" aria-hidden="true"></span>
                            <span class="aisx-bar-title">
                                <span class="aisx-live" aria-hidden="true"></span>
                                Sayzio · <b data-aisx-title>AI Chatbot</b>
                            </span>
                        </div>
                        <div class="aisx-stage" aria-live="polite">

                            {{-- Scene: Chatbot --}}
                            <div class="aisx-scene is-active" id="aisx-scene-chatbot" data-aisx-scene="0" role="tabpanel"
                                 aria-label="AI Chatbot answering a visitor on a Link in Bio page">
                                <div class="aisx-chat">
                                    <div class="aisx-msg aisx-msg-out aisx-row" style="--d:0s">Do you take commissions right now?</div>
                                    <div class="aisx-msg aisx-msg-in aisx-row" style="--d:.5s"><span class="aisx-typing"><i></i><i></i><i></i></span></div>
                                    <div class="aisx-msg aisx-msg-in aisx-row" style="--d:1.1s">Yes! I've got 2 slots open in July. Want me to grab your email so we can lock one in?</div>
                                    <div class="aisx-msg aisx-msg-out aisx-row" style="--d:1.7s">maya@studio.co</div>
                                </div>
                                <div class="aisx-chip aisx-row" style="--d:2.3s"><i class="fas fa-user-check"></i> Lead captured &amp; booking link sent</div>
                            </div>

                            {{-- Scene: Agent --}}
                            <div class="aisx-scene" id="aisx-scene-agent" data-aisx-scene="1" role="tabpanel"
                                 aria-label="AI Agent running a lead follow-up playbook">
                                <div class="aisx-play">
                                    <div class="aisx-play-head aisx-row" style="--d:0s">
                                        <i class="fas fa-diagram-project"></i> Playbook: New lead follow-up
                                        <span class="aisx-run">running</span>
                                    </div>
                                    <div class="aisx-step aisx-row" style="--d:.4s">
                                        <span class="aisx-step-ck"><i class="fas fa-check"></i></span>
                                        <span>Qualified lead from contact form<span class="aisx-step-sub">Budget &amp; timeline matched</span></span>
                                    </div>
                                    <div class="aisx-step aisx-row" style="--d:.9s">
                                        <span class="aisx-step-ck"><i class="fas fa-check"></i></span>
                                        <span>Enriched contact &amp; added to CRM<span class="aisx-step-sub">Company, role, socials</span></span>
                                    </div>
                                    <div class="aisx-step is-hold aisx-row" style="--d:1.4s">
                                        <span class="aisx-step-ck"><i class="fas fa-pen"></i></span>
                                        <span>Drafted intro email<span class="aisx-step-sub">Ready for your review</span></span>
                                        <span class="aisx-hold-pill">Needs your OK</span>
                                    </div>
                                    <div class="aisx-step aisx-row" style="--d:1.9s">
                                        <span class="aisx-step-ck"><i class="fas fa-check"></i></span>
                                        <span>Scheduled follow-up in 3 days<span class="aisx-step-sub">Added to your calendar</span></span>
                                    </div>
                                </div>
                                <div class="aisx-chip aisx-row" style="--d:2.5s"><i class="fas fa-shield-halved"></i> Full audit trail saved</div>
                            </div>

                            {{-- Scene: Widget --}}
                            <div class="aisx-scene" id="aisx-scene-widget" data-aisx-scene="2" role="tabpanel"
                                 aria-label="AI Widget embedded on an external website">
                                <div class="aisx-site aisx-row" style="--d:0s">
                                    <div class="aisx-site-nav">
                                        <span class="aisx-site-logo"></span>
                                        <span class="aisx-site-links"><span></span><span></span><span></span></span>
                                    </div>
                                    <div class="aisx-site-body">
                                        <div class="l l1"></div>
                                        <div class="l l2"></div>
                                        <div class="l l3"></div>
                                    </div>
                                    <span class="aisx-lang-tag aisx-row" style="--d:1.7s"><i class="fas fa-language"></i> Replies in 30+ languages</span>
                                    <div class="aisx-widget-pop aisx-row" style="--d:.6s">
                                        <div class="aisx-widget-pop-bar"><i class="fas fa-robot text-[11px]"></i> Ask us anything</div>
                                        <div class="aisx-widget-pop-body">
                                            <div class="aisx-msg aisx-msg-out aisx-row" style="--d:.9s">¿Hacen envíos internacionales?</div>
                                            <div class="aisx-msg aisx-msg-in aisx-row" style="--d:1.4s">¡Sí! Enviamos a todo el mundo 🌍 ¿A qué país?</div>
                                        </div>
                                    </div>
                                    <div class="aisx-widget-bubble" aria-hidden="true"><i class="fas fa-comment-dots"></i></div>
                                </div>
                            </div>

                            {{-- Scene: Voice --}}
                            <div class="aisx-scene" id="aisx-scene-voice" data-aisx-scene="3" role="tabpanel"
                                 aria-label="AI Voice Assistant handling a phone call">
                                <div class="aisx-call">
                                    <div class="aisx-call-head aisx-row" style="--d:0s">
                                        <span class="aisx-call-avatar"><i class="fas fa-phone-volume"></i></span>
                                        <span class="aisx-call-name">Zio · Front desk</span>
                                        <span class="aisx-call-sub">On call · +1 (415) 555-0132</span>
                                    </div>
                                    <div class="aisx-wave aisx-row" style="--d:.4s" aria-hidden="true">
                                        <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                                    </div>
                                    <div class="aisx-transcript">
                                        <div class="aisx-line caller aisx-row" style="--d:.8s"><b>Caller:</b> Hi, are you open this Saturday?</div>
                                        <div class="aisx-line aisx-row" style="--d:1.4s"><b>Zio:</b> We are — 9 to 5. Want me to book you in?</div>
                                        <div class="aisx-line caller aisx-row" style="--d:2s"><b>Caller:</b> Yes please, around 11.</div>
                                    </div>
                                    <div class="aisx-chip aisx-row" style="--d:2.6s"><i class="fas fa-calendar-check"></i> Meeting booked · Sat 11:00</div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="aisx-badge" aria-hidden="true">
                        <span class="aisx-badge-ico"><i class="fas fa-layer-group text-sm"></i></span>
                        <span>
                            <span class="aisx-badge-t block">One login, one AI team</span>
                            <span class="aisx-badge-s block">Grounded in your real data</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    if (window.__aisxInit) return;
    window.__aisxInit = true;

    function init() {
        var section = document.getElementById('ai-suite');
        if (!section) return;
        var cards = Array.prototype.slice.call(section.querySelectorAll('[data-aisx-tab]'));
        var scenes = Array.prototype.slice.call(section.querySelectorAll('[data-aisx-scene]'));
        var screen = section.querySelector('[data-aisx-screen]');
        var titleEl = section.querySelector('[data-aisx-title]');
        if (!cards.length || !scenes.length || !screen) return;

        var titles = cards.map(function (c) {
            var e = c.querySelector('.aisx-card-eyebrow');
            return e ? e.textContent.trim() : '';
        });
        var colors = cards.map(function (c) { return c.style.getPropertyValue('--c') || '#3d6bff'; });

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var idx = 0;
        var timer = null;
        var visible = false;
        var CYCLE = 5400;

        function paint(i, animate) {
            idx = i;
            cards.forEach(function (c, k) {
                c.setAttribute('aria-selected', k === i ? 'true' : 'false');
            });
            scenes.forEach(function (s, k) {
                s.classList.toggle('is-active', k === i);
                s.classList.remove('is-playing');
            });
            if (titleEl && titles[i]) titleEl.textContent = titles[i];
            if (colors[i]) screen.style.setProperty('--c', colors[i]);

            if (animate && !reduce) {
                var scene = scenes[i];
                // Force reflow so the row-reveal keyframes restart cleanly.
                void scene.offsetWidth;
                scene.classList.add('is-playing');
            }
        }

        function next() { paint((idx + 1) % scenes.length, true); }

        function startTimer() {
            if (reduce || timer) return;
            timer = setInterval(function () { if (visible) next(); }, CYCLE);
        }
        function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

        cards.forEach(function (card, i) {
            card.addEventListener('click', function () {
                paint(i, true);
                // Restart the cadence so a manual pick gets its full dwell time.
                stopTimer(); startTimer();
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    // Cards are divs with role="tab" (a real <a> lives inside,
                    // so they can't be <button>s) — emulate button activation.
                    if (e.target.closest && e.target.closest('a')) return;
                    e.preventDefault(); paint(i, true); stopTimer(); startTimer();
                } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                    e.preventDefault(); var n = (i + 1) % cards.length; cards[n].focus(); paint(n, true); stopTimer(); startTimer();
                } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                    e.preventDefault(); var p = (i - 1 + cards.length) % cards.length; cards[p].focus(); paint(p, true); stopTimer(); startTimer();
                }
            });
        });

        // Kick the first scene's reveal + drive cycling only while in view.
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    visible = e.isIntersecting;
                    if (visible) {
                        if (!reduce) paint(idx, true);
                        startTimer();
                    } else {
                        stopTimer();
                    }
                });
            }, { threshold: 0.3 });
            io.observe(section);
        } else {
            visible = true;
            paint(0, true);
            startTimer();
        }
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
</script>
