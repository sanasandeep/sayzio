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
        /* The panel does not animate in. It used to rise and scale from
           translateY(10px)/.985, which is a fine flourish on a panel holding
           one line of text and an irritation on one holding several hundred
           words: the reader is here to read, and the first thing the motion
           does is move the text they are already reading. The scrim still
           fades, so the change of context still registers. */
    }
    html.light-mode .xc-modal { background: #fff; border-color: #E6E8F2; box-shadow: 0 40px 90px -46px rgba(11,16,51,.4); }
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

    /* ---------- the clone, un-carded ----------
       A card is a box that has to fill a fixed height in a grid, and it uses
       flex to do it: `flex-1` on its inner column, `mt-auto` to push the
       controls to the bottom edge. The modal is not that box. Cloning the
       card carried those rules into a panel three times as wide and free to
       be any height, so the flex pushed the two halves apart and left a band
       of dead space through the middle of every modal, with the controls
       stretched across the full width at the bottom of it.

       Neutralising them here is the whole fix: inside the modal the content
       simply stacks at its natural height, in reading order. */
    .xc-body { padding-right: 44px; }
    .xc-body .mt-auto { margin-top: 0 !important; }
    .xc-body .flex-1  { flex: 0 1 auto !important; }
    .xc-body .h-full  { height: auto !important; }
    /* Same reason: a card that sets its own min-height for grid alignment
       has nothing to align with in here. */
    .xc-body [class*="min-h-"] { min-height: 0 !important; }

    /* Decorative card backdrops do not come along. They are sized and masked
       for a 380px card, and stretched across a 1080px panel they read as a
       smear across the text rather than as the card's own texture. The
       Themes card's ribbon is the one that showed. */
    .xc-body .th-ribbon { display: none !important; }

    /* The card's OWN content keeps a card's measure; only the long-form block
       uses the full panel. Left to stretch, a row built as `label ... value`
       puts 900px of nothing between "CNAME" and "cname.1in.me", a swatch row
       throws its active-theme pill against the far edge, and a 4px preview bar
       becomes a stray full-width rule. None of that is a layout the card was
       ever designed to produce -- it is the same layout at four times the
       width. */
    .xc-body .flex-col > *:not([data-expand-more]) { max-width: 660px; }
    .xc-body :is(h3, h4) { font-size: clamp(22px, 2.4vw, 30px); letter-spacing: -.03em; line-height: 1.15; }
    .xc-body p { font-size: 15.5px; line-height: 1.6; max-width: 68ch; }
    .xc-body [data-expand-more] { display: block; }
    .xc-host [data-expand-more] { display: none; }

    /* ---------- richer modal copy (xm- = expand more) ----------
       Type and rhythm for the long-form block a card opts into. It only ever
       renders inside .xc-body, so everything here is scoped to that: the card
       itself keeps its own compact styling untouched. */
    .xc-body [data-expand-more] {
        margin-top: 26px; padding-top: 24px;
        border-top: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .xc-body [data-expand-more] { border-top-color: #E6E8F2; }

    .xm-lead {
        margin: 0 0 22px; max-width: 70ch;
        font-size: 16px !important; line-height: 1.65; opacity: .85;
    }
    /* Two columns on a wide panel — "what you get" beside "why it matters" —
       so a long block reads as two short ones instead of one deep scroll. */
    .xm-cols { display: grid; gap: 26px 40px; }
    @media (min-width: 860px) { .xm-cols { grid-template-columns: 1fr 1fr; } }

    .xm-h {
        margin: 0 0 13px; font-size: 11px !important; font-weight: 700;
        letter-spacing: .13em; text-transform: uppercase; opacity: .55;
    }
    .xm-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .xm-list li { display: flex; gap: 10px; font-size: 14.5px; line-height: 1.55; }
    .xm-list i {
        flex: none; margin-top: 4px; font-size: 10px;
        color: var(--xm-accent, #3d6bff);
    }
    .xm-list strong { font-weight: 700; }

    /* A closing note — plan availability, a caveat — set apart from the
       claims above it so it does not read as one of them.

       max-width is reset because .xc-body sets a 68ch reading measure on
       every <p>, which is right for a paragraph and wrong for a full-width
       tinted strip: it left the note boxed under the left column only,
       looking like it belonged to that column's last bullet. */
    .xm-note {
        margin: 24px 0 0; padding: 12px 15px; border-radius: 10px;
        max-width: none !important;
        font-size: 13px !important; line-height: 1.55;
        background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
    }
    html.light-mode .xm-note { background: #F7F8FC; border-color: #E6E8F2; }
    .xm-note i { margin-right: 7px; opacity: .8; }

    /* The link-type stage is mostly picture, so in the modal it gets the
       room: a taller preview and a bigger drawing. The carousel dots belong
       to the rail outside, not in here. */
    .xc-body .lt-mock-zone { height: 420px; flex: 0 0 46%; }
    .xc-body .lt-mock > * { transform: scale(1.5); }
    .xc-body .lt-dots { display: none; }
    .xc-body .lt-pane-usage { display: block; }

    /* ---------- the Share panel ----------
       Two columns: what you get on the left, the thing itself on the right,
       sitting on the same gradient its card wears so the panel reads as that
       card opened rather than as a different component. */
    .xc-body--detail { padding-right: 0; }
    .xcd { display: grid; gap: clamp(22px, 3vw, 40px); align-items: stretch; }
    @media (min-width: 900px) { .xcd { grid-template-columns: 1fr 1fr; } }
    .xcd-main { padding-right: 40px; }
    /* Same gradient chip as on the card it opened from, one size up, so the
       panel reads as that card enlarged rather than a different screen. */
    .xcd-ico {
        display: grid; place-items: center; width: 48px; height: 48px;
        border-radius: 12px; font-size: 18px; color: #fff;
        background: linear-gradient(135deg, var(--g1, #3d6bff), var(--g2, #7c5cff));
        box-shadow: 0 10px 22px -12px color-mix(in srgb, var(--g1, #3d6bff) 85%, transparent);
    }
    .xcd-title {
        margin: 18px 0 0; font-size: clamp(24px, 2.6vw, 33px); font-weight: 800;
        letter-spacing: -.03em; line-height: 1.12;
    }
    .xcd-lead { margin: 12px 0 0; font-size: 16px; line-height: 1.6; opacity: .78; max-width: 46ch; }
    .xcd-points { list-style: none; margin: 24px 0 0; padding: 0; display: grid; gap: 13px; }
    .xcd-points li { display: flex; gap: 11px; font-size: 14.5px; line-height: 1.55; }
    .xcd-points i {
        flex: none; margin-top: 3px; width: 18px; height: 18px; border-radius: 50%;
        display: grid; place-items: center; font-size: 9px; color: #fff;
        background: color-mix(in srgb, var(--g1, #3d6bff) 78%, #000);
    }
    .xcd-points strong { font-weight: 700; }
    .xcd-stats {
        display: flex; flex-wrap: wrap; gap: 26px; margin: 26px 0 0; padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .xcd-stats { border-top-color: #E6E8F2; }
    .xcd-stats dt { font-size: 21px; font-weight: 800; letter-spacing: -.02em; }
    .xcd-stats dd {
        margin: 2px 0 0; font-size: 11px; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; opacity: .5;
    }
    .xcd-cta {
        margin-top: 26px; display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 20px; border: 0; border-radius: 10px; cursor: pointer;
        font-size: 14.5px; font-weight: 700; color: #fff;
        background: linear-gradient(120deg, var(--g1, #3d6bff), var(--g2, #7c5cff));
    }
    .xcd-cta i { font-size: 11px; }
    .xcd-visual {
        position: relative; overflow: hidden; border-radius: 14px;
        border: 1px solid rgba(255,255,255,.10);
        display: grid; place-items: center; padding: 34px 28px; min-height: 300px;
    }
    html.light-mode .xcd-visual { border-color: #E6E8F2; }
    /* The panel is tall — it matches the copy column — so the demo sits in a
       lot of space. A faint dot field and a stronger corner bloom than the
       card uses give that space something to be. */
    .xcd-visual::before {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(currentColor 1px, transparent 1px);
        background-size: 18px 18px;
        opacity: .07;
    }
    .xcd-visual .share-wash {
        background:
            radial-gradient(78% 52% at 0% 0%,     color-mix(in srgb, var(--g1) 34%, transparent), transparent 74%),
            radial-gradient(70% 46% at 100% 100%, color-mix(in srgb, var(--g2) 26%, transparent), transparent 76%);
    }
    html.light-mode .xcd-visual .share-wash {
        background:
            radial-gradient(74% 48% at 0% 0%,     color-mix(in srgb, var(--g1) 19%, transparent), transparent 76%),
            radial-gradient(66% 42% at 100% 100%, color-mix(in srgb, var(--g2) 15%, transparent), transparent 78%);
    }
    /* The demo is drawn at card size; in here it gets the room to be read. */
    .xcd-demo { position: relative; width: 100%; max-width: 340px; }
    @media (min-width: 900px) { .xcd-demo { transform: scale(1.12); } }

    /* ---------- the link-type modal ----------
       Two columns: what it is on the left, the thing itself on the right. */
    .ltm { display: grid; gap: clamp(20px, 3vw, 36px); align-items: start; }
    @media (min-width: 860px) { .ltm { grid-template-columns: .82fr 1.18fr; } }
    .ltm-ico {
        width: 46px; height: 46px; border-radius: 14px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size: 17px;
    }
    .ltm-name {
        margin: 16px 0 0; font-size: clamp(24px, 2.6vw, 32px); font-weight: 800;
        letter-spacing: -.03em; line-height: 1.12; display: flex; align-items: center;
        gap: 10px; flex-wrap: wrap;
    }
    .ltm-new {
        font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase;
        padding: 3px 8px; border-radius: 9999px; border: 1px solid;
    }
    .ltm-desc { margin: 12px 0 0; font-size: 15.5px; line-height: 1.6; opacity: .8; }
    .ltm-usage {
        margin: 20px 0 0; padding-top: 18px; font-size: 15px; line-height: 1.6;
        border-top: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .ltm-usage { border-top-color: #E6E8F2; }
    .ltm-usage span {
        display: block; font-size: 11px; font-weight: 700; letter-spacing: .12em;
        text-transform: uppercase; opacity: .55; margin-bottom: 6px;
    }
    .ltm-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 24px; }
    .ltm-cta {
        display: inline-flex; align-items: center; gap: 8px; border: 0; cursor: pointer;
        padding: 11px 18px; border-radius: 11px; color: #fff; font-size: 14.5px; font-weight: 700;
    }
    .ltm-cta i { font-size: 11px; }
    .ltm-link { font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; }
    .ltm-link i { font-size: 11px; opacity: .7; }

    .ltm-frame {
        border-radius: 14px; overflow: hidden;
        border: 1px solid rgba(255,255,255,.12); background: #0B0B18;
    }
    html.light-mode .ltm-frame { border-color: #E6E8F2; background: #F7F8FC; }
    .ltm-bar {
        display: flex; align-items: center; gap: 6px;
        padding: 9px 12px; border-bottom: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .ltm-bar { border-bottom-color: #E6E8F2; }
    .ltm-bar i { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.18); }
    html.light-mode .ltm-bar i { background: #D2D6E6; }
    .ltm-bar span { margin-left: 8px; font-size: 11.5px; opacity: .55; }
    .ltm-frame iframe {
        display: block; width: 100%; height: min(62vh, 560px); border: 0; background: #fff;
    }
    .ltm-frame-empty { display: grid; place-items: center; height: 260px; font-size: 14px; opacity: .6; }
    .ltm-cap { margin: 10px 0 0; font-size: 12px; opacity: .55; }

    /* ══ The animated scene ══
       A composition of interface parts for one link type, built from a few
       primitives so nineteen scenes look like one family. --a is the type's
       accent; --d is each element's delay. Everything starts in its OFF state
       and animates to ON, so the scene reads correctly even if the animation
       never runs (reduced motion, a paused tab, an old engine): the
       reduced-motion block below simply lands every element on its end state. */
    .lts {
        --a: #3d6bff;
        display: flex; flex-direction: column; gap: 9px;
        padding: 22px; min-height: 280px; height: min(52vh, 420px);
        justify-content: center;
        border-radius: 14px; overflow: hidden;
        border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.02);
    }
    html.light-mode .lts { border-color: #E6E8F2; background: #F8F9FD; }

    /* ── primitives ── */
    .lts-card { padding: 13px 14px; border-radius: 11px; background: rgba(255,255,255,.05);
                border: 1px solid rgba(255,255,255,.08); display: flex; flex-direction: column; gap: 8px }
    html.light-mode .lts-card { background: #fff; border-color: #E6E8F2 }
    .lts-card.lts-accent { border-color: color-mix(in srgb, var(--a) 55%, transparent);
                           background: color-mix(in srgb, var(--a) 12%, transparent) }
    .lts-line { height: 8px; border-radius: 4px; background: rgba(255,255,255,.16) }
    html.light-mode .lts-line { background: #DFE3F0 }
    .lts-line.lts-faint { opacity: .55 }
    .lts-w50 { width: 50% } .lts-w60 { width: 60% } .lts-w70 { width: 70% }
    .lts-w72 { width: 72% } .lts-w75 { width: 75% } .lts-w80 { width: 80% }
    .lts-w85 { width: 85% } .lts-w90 { width: 90% }
    .lts-mid { margin-left: auto; margin-right: auto }
    .lts-lbl { font-size: 10.5px; font-weight: 700; letter-spacing: .1em;
               text-transform: uppercase; opacity: .5 }
    .lts-lbl.lts-right { text-align: right }
    .lts-btn { padding: 11px 14px; border-radius: 10px; text-align: center;
               font-size: 13px; font-weight: 700; background: rgba(255,255,255,.07);
               border: 1px solid rgba(255,255,255,.1) }
    html.light-mode .lts-btn { background: #fff; border-color: #E6E8F2 }
    .lts-btn.lts-solid { background: var(--a); border-color: transparent; color: #fff }
    .lts-num { font-size: 19px; font-weight: 800; color: var(--a) }
    .lts-dim { opacity: .5 }
    .lts-avatar { width: 52px; height: 52px; border-radius: 50%; margin: 0 auto;
                  object-fit: cover; background: var(--a); flex-shrink: 0 }
    .lts-avatar.lts-sm { width: 32px; height: 32px; margin: 0 }
    .lts-avatar.lts-xs { width: 22px; height: 22px; margin: 0 }
    .lts-row { display: flex; align-items: center; justify-content: space-between; gap: 9px;
               padding: 9px 0; font-size: 12.5px;
               border-bottom: 1px solid rgba(255,255,255,.07) }
    html.light-mode .lts-row { border-bottom-color: #ECEEF6 }
    .lts-row.lts-thin { border: 0; padding: 5px 0; justify-content: flex-start; gap: 9px }
    .lts-row i { color: var(--a); font-size: 11px; width: 14px }
    .lts-row .lts-line { flex: 1 }
    .lts-price { color: var(--a); font-weight: 800 }
    .lts-chip { padding: 6px 12px; border-radius: 9999px; font-size: 11.5px; font-weight: 600;
                border: 1px solid color-mix(in srgb, var(--a) 45%, transparent);
                color: var(--a) }
    .lts-chips { display: flex; gap: 7px; flex-wrap: wrap }

    /* ── scene parts ── */
    .lts-arrow { text-align: center; font-size: 14px; color: var(--a); opacity: .85; line-height: 1 }
    .lts-url { font-size: 15px; font-weight: 700 }
    .lts-stats { display: flex; gap: 22px }
    .lts-stats div { display: flex; flex-direction: column }
    .lts-stats span { font-size: 10.5px; opacity: .55 }
    .lts-socials { display: flex; gap: 9px; justify-content: center; margin: 2px 0 6px }
    .lts-soc { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
               font-size: 13px; color: var(--a);
               background: color-mix(in srgb, var(--a) 15%, transparent) }
    .lts-bub { max-width: 82%; padding: 9px 13px; border-radius: 13px; font-size: 12.5px; line-height: 1.45 }
    .lts-bub.lts-in { background: rgba(255,255,255,.07); border-bottom-left-radius: 4px }
    html.light-mode .lts-bub.lts-in { background: #EEF1F9 }
    .lts-bub.lts-out { background: var(--a); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px }
    .lts-typing { display: inline-flex; gap: 4px; padding: 10px 13px; border-radius: 13px;
                  background: rgba(255,255,255,.07); width: fit-content }
    html.light-mode .lts-typing { background: #EEF1F9 }
    .lts-typing i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; opacity: .5;
                    animation: lts-blink 1.1s infinite }
    .lts-typing i:nth-child(2) { animation-delay: .15s } .lts-typing i:nth-child(3) { animation-delay: .3s }
    .lts-deck { position: relative; height: 128px }
    .lts-slide { position: absolute; inset: 0; border-radius: 11px; overflow: hidden;
                 display: flex; flex-direction: column; justify-content: flex-end;
                 background: color-mix(in srgb, var(--a) 22%, #0B0B18);
                 border: 1px solid color-mix(in srgb, var(--a) 50%, transparent);
                 opacity: 0; animation: lts-slide 6s infinite }
    .lts-slide-1 { animation-delay: 2s } .lts-slide-2 { animation-delay: 4s }
    .lts-slide-cap { position: absolute; left: 0; right: 0; bottom: 0; padding: 14px;
                     display: flex; flex-direction: column; gap: 7px;
                     background: linear-gradient(transparent, rgba(6,6,14,.85)) }
    .lts-dots { display: flex; gap: 5px; justify-content: center }
    .lts-dot { width: 5px; height: 5px; border-radius: 9999px; background: rgba(255,255,255,.2);
               animation: lts-dot 6s infinite }
    html.light-mode .lts-dot { background: #D6DAE9 }
    .lts-dot-1 { animation-delay: 2s } .lts-dot-2 { animation-delay: 4s }
    .lts-head { display: flex; align-items: center; justify-content: space-between;
                font-size: 14px; font-weight: 700 }
    .lts-cart { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 9999px;
                background: var(--a); color: #fff; font-size: 11px; font-weight: 800;
                display: grid; place-items: center }
    .lts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px }
    .lts-grid.lts-grid-3 { grid-template-columns: repeat(3, 1fr) }
    .lts-tile { padding: 9px 9px 11px; border-radius: 10px; font-size: 11.5px; text-align: center;
                background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08) }
    html.light-mode .lts-tile { background: #fff; border-color: #E6E8F2 }
    .lts-thumb { display: block; width: 100%; height: 46px; border-radius: 7px; margin-bottom: 6px;
                 object-fit: cover; background: color-mix(in srgb, var(--a) 25%, transparent) }
    .lts-tile span { display: block; margin-top: 2px }
    .lts-file { display: flex; align-items: center; gap: 9px; font-size: 12.5px }
    .lts-file i { color: var(--a) }
    .lts-track { height: 6px; border-radius: 3px; background: rgba(255,255,255,.1); overflow: hidden }
    html.light-mode .lts-track { background: #E6E8F2 }
    .lts-fill { display: block; height: 100%; width: 0; background: var(--a);
                animation: lts-fill 1.6s .4s ease-out forwards }
    .lts-datecard { width: 74px; margin: 0 auto; padding: 10px 0; border-radius: 12px; text-align: center;
                    background: color-mix(in srgb, var(--a) 15%, transparent);
                    border: 1px solid color-mix(in srgb, var(--a) 40%, transparent) }
    .lts-datecard b { display: block; font-size: 26px; font-weight: 800; line-height: 1; color: var(--a) }
    .lts-datecard span { font-size: 10px; letter-spacing: .12em; opacity: .6 }
    .lts-month { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px }
    .lts-day { height: 15px; border-radius: 4px; background: rgba(255,255,255,.08) }
    html.light-mode .lts-day { background: #E9ECF6 }
    .lts-day-on { background: var(--a) }
    .lts-sect { display: flex; flex-direction: column; gap: 6px }
    .lts-review { display: flex; flex-direction: column; gap: 6px; padding-bottom: 8px }
    .lts-stars { display: flex; gap: 3px; font-size: 13px; color: var(--a) }
    .lts-star-off { opacity: .25 }
    .lts-score { display: flex; align-items: baseline; gap: 7px }
    .lts-score span { font-size: 11px; opacity: .55 }
    .lts-swatches { display: flex; gap: 7px }
    .lts-sw { flex: 1; height: 42px; border-radius: 9px }
    .lts-type { font-size: 30px; font-weight: 800; line-height: 1; color: var(--a) }
    .lts-locked { position: relative; border-radius: 11px; overflow: hidden;
                  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08) }
    html.light-mode .lts-locked { background: #fff; border-color: #E6E8F2 }
    .lts-blur { filter: blur(7px) }
    /* A photograph that fills its frame: used for slide art and the locked cover. */
    .lts-cover { display: block; width: 100%; height: 100%; object-fit: cover }
    .lts-locked .lts-cover { height: 132px }
    /* A dish thumbnail on a menu row. */
    .lts-pic { width: 38px; height: 38px; border-radius: 7px; object-fit: cover; flex-shrink: 0 }
    /* A photograph across the top of a scene, with the name laid over it.
       The scrim is what keeps white text legible on a bright photo. */
    .lts-banner { position: relative; border-radius: 11px; overflow: hidden }
    .lts-banner .lts-cover { height: 96px }
    .lts-banner::after { content: ''; position: absolute; inset: auto 0 0; height: 60%;
                         background: linear-gradient(transparent, rgba(0,0,0,.72)) }
    .lts-banner-name { position: absolute; left: 11px; bottom: 9px; z-index: 1;
                       font-size: 14px; font-weight: 700; color: #fff }
    .lts-banner .lts-cart { position: absolute; right: 10px; top: 10px; z-index: 1 }
    .lts-grow { flex: 1; min-width: 0 }
    .lts-resume-head { display: flex; align-items: center; gap: 10px }
    .lts-review-head { display: flex; align-items: center; gap: 8px }
    .lts-lock { position: absolute; inset: 0; margin: auto; width: 36px; height: 36px; border-radius: 50%;
                display: grid; place-items: center; font-size: 13px; color: #fff; background: var(--a) }
    .lts-qr { display: grid; grid-template-columns: repeat(9, 1fr); gap: 2px;
              width: 150px; margin: 0 auto; padding: 11px; border-radius: 10px; background: #fff }
    .lts-qp { aspect-ratio: 1; border-radius: 2px; background: transparent }
    .lts-qp-on { background: #0B0B18; animation: lts-qp .5s var(--d, 0s) both }
    .lts-field { display: flex; flex-direction: column; gap: 5px }
    .lts-input { height: 30px; border-radius: 8px; display: flex; align-items: center; padding: 0 10px;
                 background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1) }
    html.light-mode .lts-input { background: #fff; border-color: #E6E8F2 }
    .lts-caret { width: 1.5px; height: 13px; background: var(--a); animation: lts-blink 1s infinite }
    .lts-sent { display: flex; align-items: center; gap: 7px; justify-content: center;
                font-size: 12px; font-weight: 700; color: var(--a) }

    /* ── motion ── */
    .lts-rise { animation: lts-rise .5s var(--d, 0s) cubic-bezier(.2,.7,.3,1) both }
    .lts-pop  { animation: lts-pop  .45s var(--d, 0s) cubic-bezier(.2,.9,.3,1.2) both }
    @keyframes lts-rise { from { opacity: 0; transform: translateY(10px) } to { opacity: 1; transform: none } }
    @keyframes lts-pop  { from { opacity: 0; transform: scale(.9) } to { opacity: 1; transform: none } }
    @keyframes lts-blink { 0%, 100% { opacity: .25 } 50% { opacity: 1 } }
    @keyframes lts-fill { to { width: 78% } }
    @keyframes lts-qp   { from { opacity: 0; transform: scale(.4) } to { opacity: 1; transform: none } }
    @keyframes lts-slide { 0%, 3% { opacity: 0; transform: translateX(14px) }
                           8%, 30% { opacity: 1; transform: none }
                           36%, 100% { opacity: 0; transform: translateX(-14px) } }
    @keyframes lts-dot   { 0%, 30% { background: var(--a); width: 14px } 36%, 100% { width: 5px } }

    /* Reduced motion: no movement anywhere, but every element still lands on
       its END state, so the scene is complete rather than half-drawn. */
    @media (prefers-reduced-motion: reduce) {
        .lts-rise, .lts-pop, .lts-qp-on { animation: none; opacity: 1; transform: none }
        .lts-typing i, .lts-caret { animation: none; opacity: .6 }
        .lts-fill { animation: none; width: 78% }
        .lts-slide { animation: none; opacity: 0 }
        .lts-slide-0 { opacity: 1 }
        .lts-dot { animation: none } .lts-dot-0 { background: var(--a); width: 14px }
    }

    .xc-loading { display: grid; place-items: center; min-height: 220px; font-size: 14px; opacity: .6; }

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
        // Named rather than '#share .glass': the Share cards carry their own
        // class now, and a selector that depends on a styling hook breaks the
        // moment that hook moves.
        '#share .share-card',
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
