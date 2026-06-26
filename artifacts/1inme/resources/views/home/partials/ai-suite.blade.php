{{-- ============================ AI SUITE ============================ --}}
@php
    $__aiProducts = [
        [
            'eyebrow'  => 'AI Chatbot',
            'title'    => 'A 24/7 chatbot trained on your Link in Bio.',
            'desc'     => 'Greets every visitor in your voice, answers from your real content, captures leads and books calls — never asleep.',
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
            'eyebrow'  => 'AI Agent',
            'title'    => 'A teammate that runs multi-step tasks.',
            'desc'     => 'Qualifies leads, drafts outreach, updates your contacts and follows up — across your inbox, calendar and CRM.',
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
            'eyebrow'  => 'AI Widget',
            'title'    => 'Embed an AI assistant on any website.',
            'desc'     => 'One snippet on WordPress, Shopify, Webflow or your custom site — answers questions and routes hot leads to your inbox.',
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
            'eyebrow'  => 'AI Voice Assistant',
            'title'    => 'Picks up calls in your voice.',
            'desc'     => 'AI receptionist that answers your number, qualifies callers, books real meetings and warm-transfers when it matters.',
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
    /* ===== AI Suite v3 — image-icon flip cards ===== */
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
    .ai-shimmer { color: #90acff; }

    /* ---- Flip card shell ---- */
    .ai-flip {
        position: relative;
        perspective: 1500px;
        border-radius: 1.5rem;
        cursor: pointer;
        outline: none;
        -webkit-tap-highlight-color: transparent;
    }
    .ai-flip-inner {
        position: relative;
        width: 100%;
        min-height: 330px;
        transform-style: preserve-3d;
        transition: transform .7s cubic-bezier(.2,.7,.2,1);
    }
    .ai-flip:hover .ai-flip-inner,
    .ai-flip.is-flipped .ai-flip-inner {
        transform: rotateY(180deg);
    }

    /* ---- Faces ---- */
    .ai-face {
        position: absolute; inset: 0;
        display: flex; flex-direction: column;
        border-radius: 1.5rem;
        padding: 1.5rem;
        overflow: hidden;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        transition: border-color .35s ease, box-shadow .4s ease;
    }
    html.light-mode .ai-face {
        background: #ffffff;
        border-color: rgba(15,23,42,.08);
        box-shadow: 0 4px 14px -8px rgba(15,23,42,.10);
    }
    .ai-flip:hover .ai-face,
    .ai-flip:focus-visible .ai-face,
    .ai-flip.is-flipped .ai-face {
        border-color: color-mix(in srgb, var(--ai-accent, #3d6bff) 55%, transparent);
        box-shadow: 0 30px 70px -28px color-mix(in srgb, var(--ai-accent, #3d6bff) 70%, transparent);
    }
    .ai-flip:focus-visible { box-shadow: 0 0 0 3px color-mix(in srgb, var(--ai-accent, #3d6bff) 45%, transparent); }
    .ai-face-back { transform: rotateY(180deg); }

    /* Decorative accent corner on the front */
    .ai-face-corner {
        position: absolute; top: -55px; right: -55px; width: 150px; height: 150px;
        border-radius: 9999px; opacity: .20; pointer-events: none;
        background: var(--ai-accent, #3d6bff);
    }

    /* ---- Front face ---- */
    .ai-icon {
        width: 60px; height: 60px; border-radius: 18px; display: block;
        box-shadow: 0 16px 34px -16px color-mix(in srgb, var(--ai-accent, #3d6bff) 90%, transparent);
        margin-bottom: 1.1rem;
        transition: transform .4s cubic-bezier(.2,.7,.2,1);
    }
    .ai-flip:hover .ai-icon { transform: translateY(-2px) rotate(-3deg); }
    .ai-front-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ai-accent, #3d6bff); margin-bottom: .35rem; }
    .ai-front-title { font-size: 1.075rem; font-weight: 700; line-height: 1.3; margin-bottom: .5rem; }
    .ai-front-desc { font-size: .85rem; line-height: 1.55; color: #9ca3af; }
    html.light-mode .ai-front-desc { color: #64748b; }
    .ai-front-hint {
        margin-top: auto; padding-top: 1rem;
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        color: var(--ai-accent, #3d6bff);
    }
    .ai-front-hint .ai-hint-rot { transition: transform .4s ease; }
    .ai-flip:hover .ai-front-hint .ai-hint-rot { transform: rotate(180deg); }

    /* ---- Back face ---- */
    .ai-back-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ai-accent, #3d6bff); margin-bottom: .9rem; }
    .ai-back-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .65rem; flex: 1; }
    .ai-back-list li { display: flex; align-items: flex-start; gap: .6rem; font-size: .85rem; font-weight: 600; line-height: 1.35; color: #e5e7eb; }
    html.light-mode .ai-back-list li { color: #334155; }
    .ai-back-check {
        flex: 0 0 auto; width: 18px; height: 18px; border-radius: 6px; margin-top: 1px;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--ai-accent, #3d6bff) 18%, transparent);
        color: var(--ai-accent, #3d6bff);
    }
    .ai-back-check i { font-size: 9px; }
    .ai-back-link {
        margin-top: 1.1rem;
        display: inline-flex; align-items: center; gap: .5rem;
        font-size: .82rem; font-weight: 700;
        color: var(--ai-accent, #3d6bff);
        transition: gap .25s ease;
    }
    .ai-back-link:hover { gap: .8rem; }

    /* Reveal entrance reuses the page `.reveal` mechanism */

    @media (prefers-reduced-motion: reduce) {
        .ai-suite-v2 .ai-bg-blob,
        .ai-shimmer { animation: none !important; }
        .ai-shimmer { color: #90acff; }
        /* Flatten the flip: stack both faces so all content + the link stay reachable */
        .ai-flip { perspective: none; cursor: default; }
        .ai-flip-inner { transform: none !important; transition: none; transform-style: flat; min-height: 0; }
        .ai-face { position: relative; inset: auto; backface-visibility: visible; -webkit-backface-visibility: visible; }
        .ai-face-back { transform: none; margin-top: .75rem; }
        .ai-icon, .ai-front-hint .ai-hint-rot { transition: none; }
        .ai-front-hint { display: none; }
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
                <div class="ai-flip reveal rd-{{ ($i % 4) + 1 }}"
                     style="--ai-accent: {{ $a['color'] }};"
                     role="button"
                     tabindex="0"
                     aria-pressed="false"
                     aria-label="{{ $a['eyebrow'] }} — flip card to see what's included">
                    <div class="ai-flip-inner">
                        {{-- Front --}}
                        <div class="ai-face ai-face-front">
                            <span class="ai-face-corner" aria-hidden="true"></span>
                            <img class="ai-icon" src="{{ asset($a['image']) }}" alt="" width="60" height="60" loading="lazy" decoding="async">
                            <div class="ai-front-eyebrow">{{ $a['eyebrow'] }}</div>
                            <h3 class="ai-front-title">{{ $a['title'] }}</h3>
                            <p class="ai-front-desc">{{ $a['desc'] }}</p>
                            <span class="ai-front-hint" aria-hidden="true">
                                What's included <i class="fas fa-arrows-rotate ai-hint-rot text-[10px]"></i>
                            </span>
                        </div>
                        {{-- Back --}}
                        <div class="ai-face ai-face-back">
                            <div class="ai-back-eyebrow">{{ $a['eyebrow'] }} includes</div>
                            <ul class="ai-back-list">
                                @foreach($a['features'] as $feat)
                                    <li>
                                        <span class="ai-back-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                                        <span>{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route($a['route']) }}" class="ai-back-link" data-ai-flip-link>
                                Learn more <i class="fas fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<script>
(function () {
    if (window.__aiFlipInit) return;
    window.__aiFlipInit = true;

    function init() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var cards = document.querySelectorAll('#ai-suite .ai-flip');

        cards.forEach(function (card) {
            var link = card.querySelector('[data-ai-flip-link]');

            if (reduce) {
                // Both faces are shown; behave as a static block, link always reachable.
                card.removeAttribute('role');
                card.removeAttribute('tabindex');
                card.removeAttribute('aria-pressed');
                return;
            }

            if (link) link.setAttribute('tabindex', '-1');

            function setFlipped(flipped) {
                card.classList.toggle('is-flipped', flipped);
                card.setAttribute('aria-pressed', flipped ? 'true' : 'false');
                if (link) link.setAttribute('tabindex', flipped ? '0' : '-1');
            }

            card.addEventListener('click', function (e) {
                if (e.target.closest('a')) return; // let the Learn more link navigate
                setFlipped(!card.classList.contains('is-flipped'));
            });

            card.addEventListener('keydown', function (e) {
                if (e.target.closest('a')) return;
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    setFlipped(!card.classList.contains('is-flipped'));
                }
            });

            // Keep the link tab-reachable while the mouse hover keeps the card flipped.
            card.addEventListener('mouseenter', function () { if (link) link.setAttribute('tabindex', '0'); });
            card.addEventListener('mouseleave', function () {
                if (link && !card.classList.contains('is-flipped')) link.setAttribute('tabindex', '-1');
            });
        });
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
</script>
