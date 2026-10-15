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
<link rel="stylesheet" href="{{ asset('css/marketing/format-previews-cd6e5b9f2286.css') }}">

<script>
(function () {
    'use strict';

    // The grids worth expanding. Everything else on the page is left alone.
    var SELECTORS = [
        '#audience .audience-card',
        // '#share .share-card' was here. The Share section was removed from the
        // classic homepage on 2026-09-11 -- it restated what a link is, one
        // section after a grid of 18 things a link can be. The selector would
        // have matched nothing and survived as a description of a section that
        // no longer exists, which is the kind of thing that makes a file hard
        // to trust. It still renders in deferred-sections-business, which does
        // not load this injector.
        '#domains .glass',
        // The two proof cards beside the drag-and-drop demo in #features.
        // Marked with an explicit data attribute rather than matched by
        // `.glass` or column position: the big editor card next to them is
        // also a .glass card, and it must NOT get a control, its content is
        // a live drag demo, not text that benefits from more room.
        // Anywhere a card opts in by hand. Scoped by the attribute rather than
        // by section, so a card that moves keeps its control and a card that
        // merely looks similar does not gain one.
        '[data-xc-card]'
        // The link-type stage used to be listed here. It no longer exists:
        // the cards open a fetched modal directly, so there is no panel to
        // expand and nothing in #create for the generic injector to attach to.
    ];

    // The link-type cards carry data-lt-open on the CARD ITSELF, so the whole
    // card is the target rather than a 28px square in its corner. The card is
    // already a <button>, so Enter and Space arrive as clicks and no separate
    // key handling is needed.
    //
    // Nothing here may call card.click(): the element being wired IS the card,
    // and clicking it from its own handler recurses until the stack blows.
    function wireLinkTypeCards() {
        document.querySelectorAll('[data-lt-open]').forEach(function (el) {
            if (el.dataset.xcWired) { return; }
            el.dataset.xcWired = '1';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var slug = el.getAttribute('data-lt-slug');
                if (slug) { openFetched(slug, el.getAttribute('aria-label') || ''); }
            });
        });
    }

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

    /**
     * Open the modal on content fetched from the server. Eighteen live demo
     * iframes have no business loading with the page, so each one is asked
     * for only when someone actually expands that card.
     */
    function openFetched(slug, label) {
        var shell = document.createElement('div');
        shell.className = 'xc-loading';
        shell.textContent = 'Loading';
        var host = openShell(label || 'Link type', shell);

        window.fetch('/home/link-type/' + encodeURIComponent(slug), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.text();
            })
            .then(function (html) {
                if (host && host.isConnected) { host.innerHTML = html; }
            })
            .catch(function () {
                if (host && host.isConnected) {
                    host.innerHTML = '<div class="xc-loading">That did not load. ' +
                        '<a href="/demo-type-' + slug + '">Open the demo page instead</a>.</div>';
                }
            });
    }

    /** The chrome: scrim, panel, close button. Returns the body to fill. */
    function openShell(label, initial) {
        close();
        lastFocused = document.activeElement;

        scrim = document.createElement('div');
        scrim.className = 'xc-scrim';
        scrim.setAttribute('role', 'dialog');
        scrim.setAttribute('aria-modal', 'true');
        scrim.setAttribute('aria-label', label);

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
        if (initial) { body.appendChild(initial); }

        modal.appendChild(closeBtn);
        modal.appendChild(body);
        scrim.appendChild(modal);
        scrim.addEventListener('click', function (e) { if (e.target === scrim) { close(); } });

        document.body.appendChild(scrim);
        document.body.classList.add('xc-locked');
        window.requestAnimationFrame(function () {
            if (scrim) { scrim.classList.add('is-open'); }
        });
        closeBtn.focus();
        return body;
    }

    function open(card) {
        close();
        lastFocused = document.activeElement;

        // A card that ships its own panel gets that; everything else falls
        // back to a clone of itself. The clone is why the Share cards used to
        // open into a copy of what you had just clicked, same words, same
        // three lines, nothing gained. Where a <template class="xc-detail">
        // exists it holds the fuller story instead.
        var tpl = card.querySelector(':scope > template.xc-detail');
        if (tpl) {
            var host = openShell(titleOf(card), null);
            host.appendChild(tpl.content.cloneNode(true));
            host.classList.add('xc-body--detail');
            return;
        }

        var clone = card.cloneNode(true);
        // The clone must not duplicate ids, re-run Alpine trees, or carry the
        // control that opened it.
        clone.removeAttribute('id');
        clone.querySelectorAll('[id]').forEach(function (n) { n.removeAttribute('id'); });
        clone.querySelectorAll('[x-data]').forEach(function (n) { n.removeAttribute('x-data'); });
        clone.querySelectorAll('.xc-btn').forEach(function (n) { n.remove(); });
        clone.classList.remove('xc-host');

        // `.card-lit` means "I am a card that stays blue while the page turns
        // white", and the only thing it does in light mode is force every
        // descendant to white text so the copy survives that blue ground.
        //
        // The flattening below takes the blue ground away. Keeping the class
        // then leaves white text on the modal's white panel, which is exactly
        // what happened: the Performance Coach modal rendered blank in light
        // mode, with only the CTAs visible because they are the one thing that
        // rule deliberately exempts.
        //
        // `text-white` goes for the same reason -- it is the dark-mode half of
        // the same assumption.
        clone.classList.remove('card-lit');
        clone.classList.remove('text-white');
        // The decorative bloom circles are drawn in white-on-blue and turn
        // into grey smudges once the blue is gone.
        clone.querySelectorAll('.bg-white\\/10, .bg-white\\/5').forEach(function (n) { n.remove(); });

        // The icon chip's gradient is painted by `html .card-row .card-ico`,
        // off two custom properties set on the ROW. The modal is not in that
        // row, so the clone's chip matched nothing: a bare 48px square with a
        // dark glyph where the card had a coloured tile with a white one.
        //
        // Copying the computed values across is the fix that does not need to
        // know which gradient this card uses, or that the pair lives on an
        // ancestor at all -- whatever the card is actually painted with today
        // is what the modal shows.
        (function carryIconPaint() {
            var srcIcos = card.querySelectorAll('.card-ico');
            var dstIcos = clone.querySelectorAll('.card-ico');
            for (var i = 0; i < srcIcos.length && i < dstIcos.length; i++) {
                var sc = getComputedStyle(srcIcos[i]);
                dstIcos[i].style.backgroundImage = sc.backgroundImage;
                dstIcos[i].style.backgroundColor = sc.backgroundColor;
                dstIcos[i].style.boxShadow = sc.boxShadow;
                var sGlyph = srcIcos[i].querySelector('i, svg');
                var dGlyph = dstIcos[i].querySelector('i, svg');
                if (sGlyph && dGlyph) { dGlyph.style.color = getComputedStyle(sGlyph).color; }
            }
        })();

        // Card chrome belongs to the card; inside the modal it would be a box
        // drawn inside a box.
        //
        // These have to be !important, which is unusual for an inline style
        // and worth explaining. `public/partials/surfaces` is the site-wide
        // surface layer, and it asserts card chrome on every `.glass` with
        //
        //     :is(.glass, .glass-2, …) { border: 1px solid var(--fs-rule)
        //       !important; background-color: … !important; box-shadow: …
        //       !important; }
        //
        // An important author declaration beats a plain inline one, so the
        // four lines below were losing and the clone kept its hairline. The
        // modal rendered as a rounded box hugging the text with nothing
        // between the border and the content -- the card's own 24px padding
        // having been (correctly) zeroed, since the modal supplies 44px of
        // its own. That is the "card spacing isnt live" Sana is looking at:
        // not a missing gap so much as a box that should never have been
        // drawn around the gap.
        //
        // Only an inline important declaration outranks an author important
        // one, so that is what this is. Everything not contested by that
        // layer stays a plain assignment.
        ['background', 'border', 'box-shadow', 'padding'].forEach(function (prop) {
            clone.style.setProperty(prop, prop === 'padding' || prop === 'border' ? '0' : 'none', 'important');
        });
        // The radius goes with the border: a corner rounded around nothing
        // clips the content instead of framing it.
        clone.style.setProperty('border-radius', '0', 'important');
        clone.style.width = '100%';
        clone.style.maxWidth = 'none';
        clone.style.minHeight = '0';

        scrim = document.createElement('div');
        scrim.className = 'xc-scrim';
        scrim.setAttribute('role', 'dialog');
        scrim.setAttribute('aria-modal', 'true');
        scrim.setAttribute('aria-label', titleOf(card));

        var modal = document.createElement('div');
        // Narrower panel: a clone's content is capped at 660px, so a 1080px
        // one is mostly empty.
        modal.className = 'xc-modal xc-modal--clone';

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
        wireLinkTypeCards();
    }

    // The grids arrive with the deferred home sections, fetched AFTER load, // so the observer is the only thing that ever wires them. It must watch
    // documentElement, not body: this partial is included from <head>, where
    // document.body is still null, and observe(null) throws
    // "parameter 1 is not of type 'Node'". That exception ended the script
    // before the observer existed, so for every visitor the expand control was
    // inert and nothing opened. Nothing above this line depends on <body>, so
    // watching the root element is both correct here and cheap: subtree:true
    // covers everything either way.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
    window.addEventListener('load', scan);
    if (window.MutationObserver && document.documentElement) {
        new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
