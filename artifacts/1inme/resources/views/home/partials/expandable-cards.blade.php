{{--
    Expandable cards.

    The pattern from stripe.com: a card carries a small expand control in its
    corner, and opening it lifts the same card into a modal with room to
    breathe. Applied here to the three card grids on the home page that are
    dense enough to reward it: who it is for, sharing, and domains.

    The modal shows the card's OWN content, enlarged. It does not introduce
    headlines, bullet lists or claims that are not already on the page, which
    is the only honest way to add this without someone writing new copy for
    thirty cards first. When that copy exists, a card can opt into richer
    modal content by carrying a [data-expand-more] element, which is shown in
    the modal and hidden in the card.

    Nothing here edits the card partials. The control is injected, so a card
    that is restyled or replaced keeps working, and removing this include
    removes the feature completely.
--}}
<style>
    /* ---------- the corner control ---------- */
    .xc-host { position: relative; }
    .xc-btn {
        position: absolute; top: 12px; right: 12px; z-index: 3;
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.06);
        color: #fff; cursor: pointer; padding: 0;
        opacity: 0; transform: translateY(-2px);
        transition: opacity .18s ease, transform .18s ease, background .18s ease, border-color .18s ease;
    }
    html.light-mode .xc-btn { border-color: #E6E8F2; background: #fff; color: #0B1033; }
    .xc-host:hover .xc-btn, .xc-btn:focus-visible { opacity: 1; transform: none; }
    .xc-btn:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.22); }
    html.light-mode .xc-btn:hover { background: #F2F4FB; border-color: #D2D6E6; }
    .xc-btn svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
    /* Touch and keyboard users never hover, so the control is always there. */
    @media (hover: none) { .xc-btn { opacity: 1; transform: none; } }

    /* ---------- the modal ---------- */
    .xc-scrim {
        /* Above everything. The page stacks its own sections, the sticky
           header and the assistant widget well past any tidy number, and at
           z-index 200 the modal opened underneath the section behind it. */
        position: fixed; inset: 0; z-index: 2147483000;
        display: flex; align-items: flex-start; justify-content: center;
        padding: clamp(16px, 5vh, 64px) 16px;
        overflow-y: auto; overscroll-behavior: contain;
        background: rgba(6,6,14,.62);
        opacity: 0; transition: opacity .22s ease;
    }
    html.light-mode .xc-scrim { background: rgba(11,16,51,.38); }
    .xc-scrim.is-open { opacity: 1; }
    .xc-modal {
        position: relative; width: min(1080px, 100%);
        border-radius: 18px; padding: clamp(22px, 3.4vw, 44px);
        background: #14132A; border: 1px solid rgba(255,255,255,.10);
        box-shadow: 0 40px 90px -40px rgba(0,0,0,.8);
        transform: translateY(10px) scale(.985);
        transition: transform .24s cubic-bezier(.2,.7,.3,1);
    }
    html.light-mode .xc-modal { background: #fff; border-color: #E6E8F2; box-shadow: 0 40px 90px -46px rgba(11,16,51,.4); }
    .xc-scrim.is-open .xc-modal { transform: none; }
    .xc-close {
        position: absolute; top: 14px; right: 14px; z-index: 2;
        width: 36px; height: 36px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.06);
        color: #fff; cursor: pointer; padding: 0; font-size: 17px; line-height: 1;
    }
    html.light-mode .xc-close { border-color: #E6E8F2; background: #F7F8FC; color: #0B1033; }
    .xc-close:hover { background: rgba(255,255,255,.12); }
    html.light-mode .xc-close:hover { background: #EDEFF7; }

    /* The clone sits at a comfortable reading size rather than card size. */
    .xc-body { padding-right: 44px; }
    .xc-body :is(h3, h4) { font-size: clamp(22px, 2.4vw, 30px); letter-spacing: -.03em; line-height: 1.15; }
    .xc-body p { font-size: 15.5px; line-height: 1.6; max-width: 68ch; }
    .xc-body [data-expand-more] { display: block; }
    .xc-host [data-expand-more] { display: none; }

    /* The link-type stage is mostly picture, so in the modal it gets the
       room: a taller preview and a bigger drawing. The carousel dots belong
       to the rail outside, not in here. */
    .xc-body .lt-mock-zone { height: 420px; flex: 0 0 46%; }
    .xc-body .lt-mock > * { transform: scale(1.5); }
    .xc-body .lt-dots { display: none; }

    body.xc-locked { overflow: hidden; }

    @media (prefers-reduced-motion: reduce) {
        .xc-scrim, .xc-modal, .xc-btn { transition: none; }
        .xc-modal { transform: none; }
    }
</style>

<script>
(function () {
    'use strict';

    // The grids worth expanding. Everything else on the page is left alone.
    var SELECTORS = [
        '#audience .audience-card',
        '#share .glass',
        '#domains .glass',
        // The link-type stage: expanding it opens whichever type is showing
        // at a size the drawing actually deserves.
        '#create .lt-stage'
    ];

    var EXPAND_ICON =
        '<svg viewBox="0 0 24 24" aria-hidden="true">' +
        '<path d="M14 4h6v6M20 4l-7.5 7.5M10 20H4v-6M4 20l7.5-7.5"/></svg>';

    var scrim = null;
    var lastFocused = null;

    function titleOf(card) {
        var h = card.querySelector('h2, h3, h4, [class*="title"]');
        return h ? h.textContent.trim().replace(/\s+/g, ' ').slice(0, 80) : 'this card';
    }

    function close() {
        if (!scrim) { return; }
        scrim.classList.remove('is-open');
        document.body.classList.remove('xc-locked');
        var dying = scrim;
        scrim = null;
        window.setTimeout(function () { dying.remove(); }, 240);
        if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
    }

    function open(card) {
        close();
        lastFocused = document.activeElement;

        var clone = card.cloneNode(true);
        // The clone must not duplicate ids, re-run Alpine trees, or carry the
        // control that opened it.
        clone.removeAttribute('id');
        clone.querySelectorAll('[id]').forEach(function (n) { n.removeAttribute('id'); });
        clone.querySelectorAll('[x-data]').forEach(function (n) { n.removeAttribute('x-data'); });
        clone.querySelectorAll('.xc-btn').forEach(function (n) { n.remove(); });
        clone.classList.remove('xc-host');
        // Card chrome belongs to the card; inside the modal it would be a box
        // drawn inside a box.
        clone.style.background = 'none';
        clone.style.border = '0';
        clone.style.boxShadow = 'none';
        clone.style.padding = '0';
        clone.style.width = '100%';
        clone.style.maxWidth = 'none';
        clone.style.minHeight = '0';

        scrim = document.createElement('div');
        scrim.className = 'xc-scrim';
        scrim.setAttribute('role', 'dialog');
        scrim.setAttribute('aria-modal', 'true');
        scrim.setAttribute('aria-label', titleOf(card));

        var modal = document.createElement('div');
        modal.className = 'xc-modal';

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'xc-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', close);

        var body = document.createElement('div');
        body.className = 'xc-body';
        body.appendChild(clone);

        modal.appendChild(closeBtn);
        modal.appendChild(body);
        scrim.appendChild(modal);

        scrim.addEventListener('click', function (e) { if (e.target === scrim) { close(); } });

        document.body.appendChild(scrim);
        document.body.classList.add('xc-locked');
        // Next frame, so the opening transition has a state to move from.
        window.requestAnimationFrame(function () {
            if (scrim) { scrim.classList.add('is-open'); }
        });
        closeBtn.focus();
    }

    document.addEventListener('keydown', function (e) {
        if (!scrim) { return; }
        if (e.key === 'Escape') { close(); return; }
        if (e.key !== 'Tab') { return; }
        // Keep tabbing inside the dialog while it is open.
        var focusables = scrim.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusables.length) { return; }
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    function attach(card) {
        if (card.dataset.xcReady) { return; }
        card.dataset.xcReady = '1';
        card.classList.add('xc-host');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'xc-btn';
        btn.innerHTML = EXPAND_ICON;
        btn.setAttribute('aria-label', 'Expand ' + titleOf(card));
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            open(card);
        });
        card.appendChild(btn);
    }

    function scan() {
        SELECTORS.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(attach);
        });
    }

    // The grids arrive with the deferred home sections, so scan on load and
    // again whenever the page injects more of itself.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
    window.addEventListener('load', scan);
    if (window.MutationObserver) {
        new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
    }
})();
</script>
