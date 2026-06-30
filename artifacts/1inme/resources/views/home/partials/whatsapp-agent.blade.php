{{-- ============================ WHATSAPP AGENT ============================ --}}
@php
    $waUrl = \Illuminate\Support\Facades\Route::has('site.whatsapp-agent') ? route('site.whatsapp-agent') : url('/whatsapp-agent');
@endphp
<section id="whatsapp-agent" class="wa-promo py-24 lg:py-32 relative overflow-hidden">
    <div class="wa-glow wa-glow-a" aria-hidden="true"></div>
    <div class="wa-glow wa-glow-b" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

            {{-- Copy --}}
            <div>
                <div class="reveal inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.2em] mb-4 wa-eyebrow">
                    <i class="fab fa-whatsapp text-base"></i> WhatsApp Agent
                </div>
                <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                    Build links by <span class="wa-grad">chatting on WhatsApp.</span>
                </h2>
                <p class="reveal rd-2 text-lg text-gray-400 leading-relaxed mb-8 max-w-xl">
                    Message Ask Zio like a teammate. It creates and edits short links,
                    QR codes, contact cards, calendar events and file links — right inside the chat.
                    Send a voice note and it transcribes it; send a photo and it understands it.
                </p>
                <ul class="reveal rd-3 space-y-3 mb-9 max-w-xl">
                    @foreach ([
                        'Create &amp; edit links, QR codes, vCards, events and file links',
                        'Voice notes transcribed automatically (Whisper)',
                        'Drop in a photo and the agent reads it',
                        'No app to open — it all happens in WhatsApp',
                    ] as $__wf)
                        <li class="flex items-start gap-3">
                            <span class="wa-check shrink-0"><i class="fas fa-check text-[11px]"></i></span>
                            <span class="text-gray-300">{!! $__wf !!}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="reveal rd-4 flex flex-wrap items-center gap-4">
                    @guest
                        <a href="{{ route('register.page') }}" class="wa-btn wa-btn-primary">
                            <i class="fab fa-whatsapp"></i> Get started free
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="wa-btn wa-btn-primary">
                            <i class="fab fa-whatsapp"></i> Go to your dashboard
                        </a>
                    @endguest
                    <a href="{{ $waUrl }}" class="wa-btn wa-btn-ghost">
                        See how it works <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
                <p class="reveal rd-5 text-xs text-gray-500 mt-5">
                    Available on paid plans. Each turn is metered and paid from your coin wallet,
                    with an automatic refund if a turn fails. Requires a verified phone number.
                </p>
            </div>

            {{-- Chat mockup --}}
            <div class="reveal rd-2 wa-phone-wrap">
                <div class="wa-phone" role="img" aria-label="Example WhatsApp conversation with Ask Zio">
                    <div class="wa-phone-bar">
                        <span class="wa-avatar"><i class="fab fa-whatsapp"></i></span>
                        <span class="wa-phone-name">Ask Zio<small>online</small></span>
                    </div>
                    <div class="wa-thread">
                        <div class="wa-msg wa-msg-out">Make a short link for my new pricing page sayzio.com/pricing</div>
                        <div class="wa-msg wa-msg-in">Done — your short link is <b>sayzio.app/pricing</b> 🎉 Want a QR code for it too?</div>
                        <div class="wa-msg wa-msg-out wa-voice">
                            <i class="fas fa-microphone"></i>
                            <span class="wa-wave"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                            <small>0:06</small>
                        </div>
                        <div class="wa-msg wa-msg-in">Got it 👍 I made a QR code and added it to a contact card with your number. Here you go.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<style>
    /* ===== WhatsApp Agent promo band ===== */
    .wa-promo { isolation: isolate; --wa: #25d366; --wa-deep: #1da851; }
    .wa-promo .wa-glow {
        position: absolute; border-radius: 9999px; filter: blur(90px);
        opacity: .22; pointer-events: none; will-change: transform;
    }
    .wa-promo .wa-glow-a { width: 460px; height: 460px; top: -140px; left: -120px; background: #25d366; animation: waGlowA 22s ease-in-out infinite; }
    .wa-promo .wa-glow-b { width: 380px; height: 380px; bottom: -160px; right: -100px; background: #1da851; opacity: .18; animation: waGlowB 27s ease-in-out infinite; animation-delay: -8s; }
    @keyframes waGlowA { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(50px,40px,0) scale(1.12); } }
    @keyframes waGlowB { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(-44px,-36px,0) scale(1.08); } }
    html.light-mode .wa-promo .wa-glow { opacity: .12; }

    .wa-promo .wa-eyebrow { color: var(--wa); }
    .wa-promo .wa-grad {
        background: linear-gradient(100deg, #25d366, #1da851, #34e07a, #25d366);
        background-size: 220% 100%;
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
        animation: waFlow 7s ease-in-out infinite;
    }
    @keyframes waFlow { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

    .wa-promo .wa-check {
        width: 24px; height: 24px; border-radius: 9999px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; background: linear-gradient(135deg, #25d366, #1da851);
        box-shadow: 0 6px 16px -6px color-mix(in srgb, #25d366 70%, transparent);
    }

    .wa-promo .wa-btn {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .8rem 1.4rem; border-radius: 9999px; font-weight: 700; font-size: .95rem;
        transition: transform .25s ease, box-shadow .3s ease, background .3s ease;
    }
    .wa-promo .wa-btn-primary {
        color: #04270f; background: linear-gradient(135deg, #25d366, #1ebd5b);
        box-shadow: 0 18px 40px -16px color-mix(in srgb, #25d366 75%, transparent);
    }
    .wa-promo .wa-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 24px 50px -16px color-mix(in srgb, #25d366 85%, transparent); }
    .wa-promo .wa-btn-ghost {
        color: var(--wa); border: 1px solid color-mix(in srgb, #25d366 45%, transparent);
        background: color-mix(in srgb, #25d366 10%, transparent);
    }
    .wa-promo .wa-btn-ghost:hover { transform: translateY(-2px); background: color-mix(in srgb, #25d366 18%, transparent); }

    /* Phone / chat mockup */
    .wa-promo .wa-phone-wrap { display: flex; justify-content: center; }
    .wa-promo .wa-phone {
        width: 100%; max-width: 360px; border-radius: 1.75rem; overflow: hidden;
        border: 1px solid rgba(255,255,255,.10);
        background: #0b141a;
        box-shadow: 0 40px 90px -40px rgba(0,0,0,.7), 0 0 0 1px color-mix(in srgb, #25d366 18%, transparent);
        animation: waBob 7s ease-in-out infinite;
        will-change: transform;
    }
    @keyframes waBob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    html.light-mode .wa-promo .wa-phone { box-shadow: 0 40px 80px -42px rgba(15,23,42,.45), 0 0 0 1px color-mix(in srgb, #25d366 22%, transparent); }
    .wa-promo .wa-phone-bar {
        display: flex; align-items: center; gap: .7rem;
        padding: .9rem 1.1rem; background: linear-gradient(135deg, #1f2c33, #111b21);
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .wa-promo .wa-avatar {
        width: 38px; height: 38px; border-radius: 9999px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.15rem; background: linear-gradient(135deg, #25d366, #1da851);
    }
    .wa-promo .wa-phone-name { display: flex; flex-direction: column; color: #e9edef; font-weight: 700; font-size: .95rem; line-height: 1.1; }
    .wa-promo .wa-phone-name small { color: #8fa3ad; font-weight: 500; font-size: .72rem; margin-top: 2px; }
    .wa-promo .wa-thread {
        padding: 1.1rem; display: flex; flex-direction: column; gap: .6rem;
        background:
            radial-gradient(circle at 18% 12%, rgba(37,211,102,.06), transparent 40%),
            #0b141a;
        min-height: 340px;
    }
    .wa-promo .wa-msg {
        max-width: 82%; padding: .6rem .8rem; border-radius: 1rem; font-size: .9rem;
        line-height: 1.35; color: #e9edef; position: relative;
    }
    .wa-promo .wa-msg-in { align-self: flex-start; background: #202c33; border-top-left-radius: .3rem; }
    .wa-promo .wa-msg-out { align-self: flex-end; background: #005c4b; border-top-right-radius: .3rem; }
    .wa-promo .wa-msg b { color: #6ff7b0; }
    .wa-promo .wa-voice { display: inline-flex; align-items: center; gap: .55rem; }
    .wa-promo .wa-voice > i { color: #8fe9b3; }
    .wa-promo .wa-voice small { color: #cfe9d8; font-size: .72rem; }
    .wa-promo .wa-wave { display: inline-flex; align-items: center; gap: 3px; height: 18px; }
    .wa-promo .wa-wave i {
        width: 3px; border-radius: 9999px; background: #8fe9b3; opacity: .85;
        animation: waWave 1.1s ease-in-out infinite;
    }
    .wa-promo .wa-wave i:nth-child(odd) { height: 8px; }
    .wa-promo .wa-wave i:nth-child(even) { height: 15px; }
    .wa-promo .wa-wave i:nth-child(3n) { height: 11px; }
    .wa-promo .wa-wave i:nth-child(1) { animation-delay: -.9s; }
    .wa-promo .wa-wave i:nth-child(2) { animation-delay: -.7s; }
    .wa-promo .wa-wave i:nth-child(3) { animation-delay: -.5s; }
    .wa-promo .wa-wave i:nth-child(4) { animation-delay: -.3s; }
    .wa-promo .wa-wave i:nth-child(5) { animation-delay: -.1s; }
    @keyframes waWave { 0%,100% { transform: scaleY(.5); } 50% { transform: scaleY(1.25); } }

    /* ===== Live-conversation sequencing ===== */
    /* When JS drives the thread, unrevealed bubbles are removed from layout so the
       thread grows as each message lands (instead of reserving all the space up front). */
    .wa-promo .wa-thread.wa-seq .wa-msg:not(.wa-show) { display: none; }
    .wa-promo .wa-thread.wa-seq .wa-msg.wa-show {
        animation: waPop .44s cubic-bezier(.2,.8,.25,1.2) forwards;
    }
    @keyframes waPop {
        0%   { opacity: 0; transform: translateY(12px) scale(.96); }
        60%  { opacity: 1; transform: translateY(-2px) scale(1.012); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    /* Loop reset: gently fade out whatever is on screen before replaying. */
    .wa-promo .wa-thread.wa-fading .wa-msg,
    .wa-promo .wa-thread.wa-fading .wa-indicator {
        animation: none !important;
        transition: opacity .5s ease, transform .5s ease !important;
        opacity: 0 !important;
        transform: translateY(-8px) !important;
    }

    /* Transient "typing…" bubble (incoming) */
    .wa-promo .wa-typing { display: inline-flex; align-items: center; padding-top: .65rem; padding-bottom: .65rem; }
    .wa-promo .wa-dots { display: inline-flex; align-items: center; gap: 4px; height: 8px; }
    .wa-promo .wa-dots i {
        width: 7px; height: 7px; border-radius: 9999px; background: #8fa3ad; opacity: .6;
        animation: waDot 1.25s ease-in-out infinite;
    }
    .wa-promo .wa-dots i:nth-child(2) { animation-delay: .18s; }
    .wa-promo .wa-dots i:nth-child(3) { animation-delay: .36s; }
    @keyframes waDot {
        0%, 60%, 100% { transform: translateY(0); opacity: .4; }
        30% { transform: translateY(-4px); opacity: 1; }
    }

    /* Transient "recording…" bubble (outgoing) */
    .wa-promo .wa-recording { display: inline-flex; align-items: center; gap: .55rem; }
    .wa-promo .wa-recording > i { color: #8fe9b3; }
    .wa-promo .wa-recording small { color: #cfe9d8; font-size: .72rem; }
    .wa-promo .wa-rec-dot {
        width: 9px; height: 9px; border-radius: 9999px; background: #ff5d5d; flex-shrink: 0;
        animation: waRec 1s ease-in-out infinite;
    }
    @keyframes waRec { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: .3; transform: scale(.78); } }

    @media (prefers-reduced-motion: reduce) {
        .wa-promo .wa-glow,
        .wa-promo .wa-grad,
        .wa-promo .wa-phone,
        .wa-promo .wa-wave i,
        .wa-promo .wa-dots i,
        .wa-promo .wa-rec-dot { animation: none !important; }
        /* Never hide bubbles when motion is reduced — show the full thread at once. */
        .wa-promo .wa-thread.wa-seq .wa-msg:not(.wa-show) { display: revert !important; }
        .wa-promo .wa-thread.wa-seq .wa-msg { opacity: 1 !important; transform: none !important; animation: none !important; }
        .wa-promo .wa-thread.wa-seq .wa-msg.wa-show { animation: none !important; }
    }
</style>

<script>
(function () {
    var section = document.getElementById('whatsapp-agent');
    if (!section) return;
    var thread = section.querySelector('.wa-thread');
    if (!thread) return;

    var msgs = Array.prototype.slice.call(thread.querySelectorAll('.wa-msg'));
    if (msgs.length < 4) return;

    var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    var runId = 0;
    var running = false;

    function clearIndicators() {
        var nodes = thread.querySelectorAll('.wa-indicator');
        for (var i = 0; i < nodes.length; i++) nodes[i].parentNode.removeChild(nodes[i]);
    }

    function reset() {
        thread.classList.remove('wa-fading');
        for (var i = 0; i < msgs.length; i++) msgs[i].classList.remove('wa-show');
        clearIndicators();
    }

    function sleep(ms, id) {
        return new Promise(function (resolve, reject) {
            setTimeout(function () { id === runId ? resolve() : reject(); }, ms);
        });
    }

    function indicator(kind, beforeEl) {
        var d = document.createElement('div');
        d.setAttribute('aria-hidden', 'true');
        if (kind === 'typing') {
            d.className = 'wa-msg wa-msg-in wa-indicator wa-typing wa-show';
            d.innerHTML = '<span class="wa-dots"><i></i><i></i><i></i></span>';
        } else {
            d.className = 'wa-msg wa-msg-out wa-indicator wa-recording wa-show';
            d.innerHTML = '<i class="fas fa-microphone"></i><span class="wa-rec-dot"></span><small>recording\u2026</small>';
        }
        // Insert the transient bubble immediately before the message it precedes
        // so it sits in the right conversational slot (last visible position).
        thread.insertBefore(d, beforeEl);
        return d;
    }

    function play(id) {
        var ind;
        return sleep(650, id)
            .then(function () { msgs[0].classList.add('wa-show'); return sleep(620, id); })
            .then(function () { ind = indicator('typing', msgs[1]); return sleep(1300, id); })
            .then(function () { ind.parentNode.removeChild(ind); msgs[1].classList.add('wa-show'); return sleep(900, id); })
            .then(function () { ind = indicator('recording', msgs[2]); return sleep(1450, id); })
            .then(function () { ind.parentNode.removeChild(ind); msgs[2].classList.add('wa-show'); return sleep(750, id); })
            .then(function () { ind = indicator('typing', msgs[3]); return sleep(1450, id); })
            .then(function () { ind.parentNode.removeChild(ind); msgs[3].classList.add('wa-show'); return sleep(3400, id); })
            .then(function () { thread.classList.add('wa-fading'); return sleep(580, id); })
            .then(function () { reset(); return sleep(420, id); })
            .then(function () { if (id === runId) play(id); })
            .catch(function () {});
    }

    function start() {
        if (reduceMq.matches || running) return;
        running = true;
        runId++;
        thread.classList.add('wa-seq');
        reset();
        play(runId);
    }

    function stop() {
        running = false;
        runId++;
        reset();
    }

    if (reduceMq.matches) return; // leave the full thread visible, no sequencing

    thread.classList.add('wa-seq'); // hide bubbles until the section is in view

    var io = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
            entries[i].isIntersecting ? start() : stop();
        }
    }, { threshold: 0.25, rootMargin: '0px 0px -10% 0px' });
    io.observe(section);

    var onReduceChange = function (e) {
        if (e.matches) {
            running = false;
            runId++;
            thread.classList.remove('wa-seq');
            reset();
        }
    };
    if (reduceMq.addEventListener) reduceMq.addEventListener('change', onReduceChange);
    else if (reduceMq.addListener) reduceMq.addListener(onReduceChange);
})();
</script>
