@php
    use App\Modules\User\Controllers\SlideDeckController;
    use App\Modules\User\Models\LinkSlideDeck;
    use Illuminate\Support\Facades\URL;

    $req            = request();
    $isOwnerPreview = $req && $req->boolean('_preview')
        && $req->hasValidSignatureWhileIgnoring(['_draft', '_t'], false);

    $deck = LinkSlideDeck::withoutGlobalScope('workspace')
        ->where('link_id', $link->id)
        ->first();

    // Public viewers: read the frozen snapshot. Owner preview: build a
    // fresh snapshot from the live editor tables so unsaved-then-saved
    // (but not yet published) changes show up.
    if ($isOwnerPreview && $deck) {
        $deck->load('slides');
        $payload = SlideDeckController::buildSnapshot($deck, $link);
    } else {
        $payload = ($deck && is_array($deck->published_snapshot)) ? $deck->published_snapshot : null;
    }

    $settings = $payload['settings'] ?? [];
    $theme    = $settings['theme'] ?? [];
    $bg       = $theme['background'] ?? '#0f172a';
    $accent   = $theme['accent']     ?? '#8b5cf6';
    $text     = $theme['text']       ?? '#f8fafc';
    $defaultTransition = $settings['transition'] ?? 'slide';
    $autoAdvance       = (int) ($settings['auto_advance'] ?? 0);
    $loop              = (bool) ($settings['loop'] ?? false);
    $slides            = $payload['slides'] ?? [];

    $title = $link->title ?: $link->alias;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
<title>{{ $title }}</title>
@if($link->seo_description)
    <meta name="description" content="{{ $link->seo_description }}">
@endif
@if($link->favicon)
    <link rel="icon" href="{{ $link->favicon }}">
@endif
<style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; height: 100%; overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: {{ $bg }}; color: {{ $text }};
        overscroll-behavior: none; -webkit-tap-highlight-color: transparent; }
    .sl-deck { position: fixed; inset: 0; }
    .sl-stage { position: absolute; inset: 0; overflow: hidden; }
    .sl-slide {
        position: absolute; inset: 0;
        display: flex; flex-direction: column; align-items: stretch; justify-content: center;
        padding: max(env(safe-area-inset-top), 24px) 20px max(env(safe-area-inset-bottom), 24px);
        opacity: 0; pointer-events: none;
        transition: opacity 350ms ease, transform 350ms ease;
        transform: translate3d(0, 6%, 0) scale(0.98);
    }
    .sl-slide.is-active { opacity: 1; pointer-events: auto; transform: translate3d(0,0,0) scale(1); }
    .sl-slide.is-prev   { opacity: 0; transform: translate3d(0,-6%,0) scale(0.98); }
    .sl-slide[data-transition="fade"]   { transform: none; }
    .sl-slide[data-transition="zoom"]   { transform: scale(0.85); }
    .sl-slide[data-transition="zoom"].is-active { transform: scale(1); }
    .sl-slide[data-transition="flip"]   { transform: rotateX(45deg); transform-origin: 50% 100%; }
    .sl-slide[data-transition="flip"].is-active { transform: rotateX(0); }
    .sl-slide[data-transition="none"]   { transition: none; }

    .sl-content {
        max-width: 460px; width: 100%; margin: 0 auto;
        display: flex; flex-direction: column; gap: 14px;
    }
    .sl-title {
        font-size: clamp(22px, 6vw, 36px); font-weight: 800; line-height: 1.15;
        text-align: center; letter-spacing: -0.01em;
    }
    .sl-blocks { display: flex; flex-direction: column; gap: 12px; }
    .sl-blocks > * + * { margin-top: 0; }
    .sl-block-anim { opacity: 0; transform: var(--bx, none);
        transition: opacity var(--bd, 400ms) ease, transform var(--bd, 400ms) ease;
        transition-delay: var(--ba, 0ms); }
    .sl-slide.is-active .sl-block-anim { opacity: 1; transform: none; }
    .sl-blocks .biolink-block, .sl-blocks .block, .sl-blocks > div { color: inherit; }
    .sl-blocks a, .sl-blocks button { color: inherit; }

    .sl-progress {
        position: absolute; top: max(env(safe-area-inset-top), 12px); left: 12px; right: 12px;
        display: flex; gap: 4px; z-index: 5; pointer-events: none;
    }
    .sl-bar { flex: 1; height: 3px; background: rgba(255,255,255,0.18); border-radius: 2px; overflow: hidden; }
    .sl-bar-fill { display: block; width: 0; height: 100%; background: {{ $accent }}; transition: width 200ms linear; }
    .sl-bar.is-done .sl-bar-fill { width: 100%; }
    .sl-bar.is-active .sl-bar-fill { width: var(--p, 100%); }

    /* Nav is edge-only so the middle of the screen stays interactive
       for slide blocks (links, polls, forms, etc.). The container
       ignores pointer events; only the explicit edge buttons receive
       them. */
    .sl-nav { position: absolute; inset: 0; z-index: 4; pointer-events: none; }
    .sl-nav button {
        position: absolute; top: 0; bottom: 0; width: 64px;
        background: transparent; border: 0; cursor: pointer; outline: none;
        color: transparent; font-size: 0; pointer-events: auto;
    }
    #sl-prev  { left: 0; }
    #sl-next  { right: 0; }
    #sl-pause {
        top: 8px; left: 50%; transform: translateX(-50%);
        width: 44px; height: 44px; bottom: auto; border-radius: 999px;
    }
    .sl-counter {
        position: absolute; bottom: max(env(safe-area-inset-bottom), 14px); left: 0; right: 0;
        text-align: center; font-size: 12px; opacity: 0.7; z-index: 5; pointer-events: none;
    }
    .sl-empty {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        text-align: center; padding: 24px; opacity: 0.85; font-size: 15px;
    }

    /* Tame the embedded biolink-block partial. The static block list
       partial assumes a Tailwind page; here we just want the inner text
       and CTAs to be readable and tappable on the dark slide canvas. */
    .sl-blocks img { max-width: 100%; height: auto; }
    .sl-blocks h1, .sl-blocks h2, .sl-blocks h3 { color: inherit; }
    .sl-blocks p { color: inherit; opacity: 0.92; }
    .sl-blocks button, .sl-blocks .btn, .sl-blocks a.btn {
        cursor: pointer;
    }
</style>
</head>
<body>
<div class="sl-deck" id="sl-deck">
    @if(empty($slides))
        <div class="sl-empty">
            This deck is empty.@if($isOwnerPreview)<br><small>Add slides in the editor and republish.</small>@endif
        </div>
    @else
        <div class="sl-progress" id="sl-progress" aria-hidden="true">
            @foreach($slides as $i => $s)
                <div class="sl-bar" data-i="{{ $i }}"><span class="sl-bar-fill"></span></div>
            @endforeach
        </div>

        <div class="sl-stage" id="sl-stage">
            @foreach($slides as $i => $s)
                @php
                    $bgInline = '';
                    $bgConf = $s['background'] ?? [];
                    $bgType = $bgConf['type'] ?? 'color';
                    if ($bgType === 'image' && !empty($bgConf['image_url'])) {
                        $bgInline = "background-image:url('".e($bgConf['image_url'])."'); background-size:cover; background-position:center;";
                    } elseif ($bgType === 'gradient') {
                        $bgInline = "background: linear-gradient(135deg, "
                            . e($bgConf['from_color'] ?? '#1e293b') . ", "
                            . e($bgConf['to_color'] ?? '#0f172a') . ");";
                    } else {
                        $bgInline = "background:" . e($bgConf['color'] ?? $bg) . ";";
                    }
                    $tr = $s['transition'] ?? $defaultTransition;
                @endphp
                <section
                    class="sl-slide"
                    data-i="{{ $i }}"
                    data-transition="{{ $tr }}"
                    style="{{ $bgInline }}"
                >
                    <div class="sl-content">
                        @if(!empty($s['title']))
                            <div class="sl-title">{{ $s['title'] }}</div>
                        @endif
                        <div class="sl-blocks">
                            @foreach(($s['blocks'] ?? []) as $b)
                                @php
                                    $anim   = is_array($b['animation'] ?? null) ? $b['animation'] : null;
                                    $enter  = $anim['enter']       ?? 'fade';
                                    $delay  = (int) ($anim['delay_ms']    ?? 0);
                                    $durMs  = (int) ($anim['duration_ms'] ?? 400);
                                    $align  = $anim['align']       ?? 'center';
                                    $tx = ['fade'=>'none','slide_up'=>'translateY(16px)','slide_down'=>'translateY(-16px)','slide_left'=>'translateX(16px)','slide_right'=>'translateX(-16px)','zoom'=>'scale(0.92)','flip'=>'rotateX(20deg)','none'=>'none'][$enter] ?? 'none';
                                    $alignCss = ['left'=>'flex-start','center'=>'center','right'=>'flex-end','stretch'=>'stretch'][$align] ?? 'center';
                                @endphp
                                <div class="sl-block-anim"
                                     data-enter="{{ $enter }}"
                                     style="--bx:{{ $tx }};--bd:{{ $durMs }}ms;--ba:{{ $delay }}ms;align-self:{{ $alignCss }};width:{{ $align==='stretch' ? '100%' : 'auto' }};max-width:100%;">
                                    {!! $b['html'] ?? '' !!}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="sl-nav" aria-hidden="true">
            <button type="button" id="sl-prev" aria-label="Previous slide">prev</button>
            <button type="button" id="sl-pause" aria-label="Pause"></button>
            <button type="button" id="sl-next" aria-label="Next slide">next</button>
        </div>

        <div class="sl-counter"><span id="sl-counter">1 / {{ count($slides) }}</span></div>
    @endif
</div>

<script>
(function () {
    const ALIAS = @json($link->alias);
    const TOTAL = {{ count($slides) }};
    if (TOTAL === 0) return;

    const AUTO_MS = {{ $autoAdvance }};
    const LOOP    = {{ $loop ? 'true' : 'false' }};
    const VISIT_URL = '/sl/' + encodeURIComponent(ALIAS) + '/view';
    const STORAGE_KEY = 'sl_page_session_' + ALIAS;
    const CSRF = '{{ csrf_token() }}';

    let pageSessionId = null;
    try { pageSessionId = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (!pageSessionId) {
        pageSessionId = 'sl_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
        try { localStorage.setItem(STORAGE_KEY, pageSessionId); } catch (e) {}
    }

    const slides = Array.from(document.querySelectorAll('.sl-slide'));
    const bars   = Array.from(document.querySelectorAll('.sl-progress .sl-bar'));
    const counter = document.getElementById('sl-counter');
    let current = 0;
    let timer = null;
    let paused = false;

    // Cap dwell-time pings at 10 minutes — matches server validator.
    // Anything longer is almost certainly a tab left open and would
    // otherwise skew the per-slide average. The server applies the
    // same cap, but trimming here saves a round trip.
    const DWELL_CAP_MS = 600000;

    function track(i, completed, dwellMs) {
        try {
            const body = { slide_index: i, page_session_id: pageSessionId, completed: !!completed };
            if (Number.isFinite(dwellMs) && dwellMs >= 0) {
                body.dwell_ms = Math.min(DWELL_CAP_MS, Math.round(dwellMs));
            }
            fetch(VISIT_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(body),
                keepalive: true,
                credentials: 'same-origin',
            }).catch(() => {});
        } catch (e) {}
    }
    let completedFired = false;
    // Time the active slide became visible. We fire a follow-up "exit"
    // ping with dwell_ms when the viewer leaves it (next/prev nav, tab
    // hidden, or page unload) so analytics can compute average time
    // spent per slide without changing the impression-count semantics.
    let dwellStart = 0;
    function flushDwell() {
        if (!dwellStart) return;
        const elapsed = Date.now() - dwellStart;
        dwellStart = 0;
        if (elapsed > 0) track(current, false, elapsed);
    }

    function clearTimer() { if (timer) { clearInterval(timer); timer = null; } }

    function show(idx) {
        if (TOTAL === 0) return;
        if (idx < 0) idx = LOOP ? TOTAL - 1 : 0;
        if (idx >= TOTAL) {
            if (LOOP) { idx = 0; } else { idx = TOTAL - 1; }
        }
        slides.forEach((el, i) => {
            el.classList.toggle('is-active', i === idx);
            el.classList.toggle('is-prev', i < idx);
        });
        bars.forEach((b, i) => {
            b.classList.toggle('is-done', i < idx);
            b.classList.toggle('is-active', i === idx);
            const fill = b.querySelector('.sl-bar-fill');
            if (i < idx) fill.style.width = '100%';
            else if (i > idx) fill.style.width = '0%';
            else fill.style.width = AUTO_MS > 0 ? '0%' : '100%';
        });
        counter.textContent = (idx + 1) + ' / ' + TOTAL;
        // Flush dwell for the slide we are leaving before swapping
        // `current`, so the exit ping is attributed to the right slide.
        if (idx !== current) flushDwell();
        current = idx;
        track(idx, false);
        dwellStart = Date.now();
        // Fire a one-shot deck-completion event when the viewer reaches
        // the final slide so analytics can compute completion rate.
        if (!completedFired && idx >= TOTAL - 1) {
            completedFired = true;
            track(idx, true);
        }
        startAuto();
    }

    // Page hide / tab switch: flush dwell for the currently visible
    // slide so the last slide in a session still contributes an avg-time
    // sample. `pagehide` is more reliable than `unload` on mobile Safari.
    // When the tab becomes visible again we restart the dwell timer so
    // subsequent time on the same slide is still attributed (otherwise
    // we'd undercount viewers who tab away and return).
    window.addEventListener('pagehide', flushDwell);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flushDwell();
        } else if (!dwellStart) {
            dwellStart = Date.now();
        }
    });

    function startAuto() {
        clearTimer();
        if (paused || AUTO_MS <= 0) return;
        const fill = bars[current].querySelector('.sl-bar-fill');
        // Re-trigger transition: width 0 → 100% over AUTO_MS.
        fill.style.transition = 'none';
        fill.style.width = '0%';
        // Force reflow.
        void fill.offsetWidth;
        fill.style.transition = 'width ' + AUTO_MS + 'ms linear';
        fill.style.width = '100%';
        timer = setTimeout(() => {
            if (current >= TOTAL - 1 && !LOOP) { return; }
            show(current + 1);
        }, AUTO_MS);
    }

    document.getElementById('sl-prev').addEventListener('click', (e) => { e.preventDefault(); show(current - 1); });
    document.getElementById('sl-next').addEventListener('click', (e) => { e.preventDefault(); show(current + 1); });
    document.getElementById('sl-pause').addEventListener('click', (e) => {
        e.preventDefault();
        paused = !paused;
        if (paused) clearTimer(); else startAuto();
    });

    // Keyboard navigation (desktop preview).
    window.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' || e.key === ' ') { show(current + 1); }
        else if (e.key === 'ArrowLeft') { show(current - 1); }
    });

    // Touch swipe (vertical first, fall back to horizontal).
    let tStartX = 0, tStartY = 0, tStartT = 0;
    document.getElementById('sl-deck').addEventListener('touchstart', (e) => {
        const t = e.changedTouches[0];
        tStartX = t.clientX; tStartY = t.clientY; tStartT = Date.now();
    }, { passive: true });
    document.getElementById('sl-deck').addEventListener('touchend', (e) => {
        const t = e.changedTouches[0];
        const dx = t.clientX - tStartX;
        const dy = t.clientY - tStartY;
        const dt = Date.now() - tStartT;
        if (dt > 800) return;
        if (Math.abs(dy) > 60 && Math.abs(dy) > Math.abs(dx)) {
            show(current + (dy < 0 ? 1 : -1));
        } else if (Math.abs(dx) > 60) {
            show(current + (dx < 0 ? 1 : -1));
        }
    }, { passive: true });

    show(0);
})();
</script>
</body>
</html>
