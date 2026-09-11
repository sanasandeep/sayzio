{{-- ══════════════════════════════════════════════════════════════════
     THE MARKETING DESIGN SYSTEM
     ══════════════════════════════════════════════════════════════════

     One place that decides what a Sayzio marketing surface looks like, so
     the homepage, /pricing and every page after them stop being three
     designs that happen to share a logo.

     Sana set the rules. They are listed here as rules, not as taste, so a
     later edit can be checked against them:

       1. NO COLOURED TEXT. Every word takes --sy-ink / --sy-ink-2 /
          --sy-ink-3. Colour lives in ribbons, borders and filled buttons.
          A blue heading and an amber number are how a page starts looking
          like a receipt.
       2. BORDERED CARDS. A hairline and a soft shadow. Not a backdrop
          blur, an inset highlight stack and a glowing ring, which is three
          effects doing one job and reads as plastic.
       3. SECTIONAL DIVIDERS. Sections are separated by a rule, optionally
          carrying a label, rather than by guessing at margins.
       4. ONE SET OF CTAs. Filled, ghost, held. Three shapes, one geometry.
       5. GRIDS. Rows are grids with one gap value, so things line up
          because of the layout rather than in spite of it.
       6. GRADIENT RIBBONS. The brand gradient appears as a ribbon -- a
          3px edge, a thin marker -- never as a wash behind content.
       7. FEWER COLOURED ICONS. Icons are ink, small, and quiet. They mark
          a row; they are not the point of it.

     Included with @once, so any number of partials may ask for it.
     ══════════════════════════════════════════════════════════════════ --}}
@once
<style>
    /* ---------------------------------------------------------------
       TOKENS
       Light is the base; dark overrides only the values. Nothing below
       this block names a raw colour except the brand gradient itself.
       --------------------------------------------------------------- */
    .sy {
        --sy-ground:  #F5F6FA;
        --sy-card:    #FFFFFF;
        --sy-ink:     #0B1033;
        --sy-ink-2:   #4E5680;
        /* #757CA6 measured 4.05:1 on white -- under AA for the 11-12px
           text this token is mostly used on (eyebrows, group headings,
           cell subtitles, the price unit). #6A7192 is 4.78. */
        --sy-ink-3:   #6A7192;
        --sy-rule:    #E4E7F0;
        --sy-rule-2:  #CFD5E8;
        --sy-shadow:  0 24px 60px -34px rgba(11,16,48,.30);
        --sy-grad:    linear-gradient(90deg, #3d6bff 0%, #6e61ff 52%, #22d3ee 100%);
        --sy-grad-warm: linear-gradient(90deg, #d97706 0%, #f59e0b 52%, #fcd34d 100%);
    }
    html:not(.light-mode) .sy {
        --sy-ground:  #0E1017;
        --sy-card:    #15181F;
        --sy-ink:     #FFFFFF;
        --sy-ink-2:   #A9B0D0;
        --sy-ink-3:   #7A81A6;
        --sy-rule:    rgba(255,255,255,.09);
        --sy-rule-2:  rgba(255,255,255,.16);
        --sy-shadow:  0 24px 60px -34px rgba(0,0,0,.75);
    }

    /* ---------------------------------------------------------------
       RULE 5 — GRIDS
       One gap value, one set of tracks. `--sy-min` is the point a column
       stops shrinking and the grid wraps instead.
       --------------------------------------------------------------- */
    .sy-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(auto-fit, minmax(var(--sy-min, 260px), 1fr));
        align-items: stretch;
    }
    @media (min-width: 880px) { .sy-grid { gap: 24px; } }
    .sy-grid--2 { grid-template-columns: 1fr; }
    @media (min-width: 880px) { .sy-grid--2 { grid-template-columns: 1fr 1fr; } }

    /* ---------------------------------------------------------------
       RULE 3 — SECTIONAL DIVIDERS
       A hairline across the section. With a label it becomes a marker:
       rule, small caps, rule. The gradient tick on the left of the label
       is the only colour a divider carries (rule 6).
       --------------------------------------------------------------- */
    .sy-divider { border: 0; height: 1px; background: var(--sy-rule); margin: 56px 0; }
    .sy-divider-label {
        display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
        gap: 18px; margin: 64px 0 36px;
    }
    .sy-divider-label::before, .sy-divider-label::after {
        content: ""; height: 1px; background: var(--sy-rule);
    }
    .sy-divider-label > span {
        display: inline-flex; align-items: center; gap: 10px;
        font-size: 11px; font-weight: 800; letter-spacing: .2em;
        text-transform: uppercase; color: var(--sy-ink-3); white-space: nowrap;
    }
    .sy-divider-label > span::before {
        content: ""; width: 18px; height: 3px; border-radius: 999px;
        background: var(--sy-grad);
    }

    /* ---------------------------------------------------------------
       RULE 6 — GRADIENT RIBBONS
       A 3px edge on the thing being singled out. It reads as a marker
       because it is thin; the moment it becomes a panel it is a wash.
       --------------------------------------------------------------- */
    .sy-ribbon { position: relative; }
    .sy-ribbon::before {
        content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px;
        border-radius: 999px 999px 0 0;
        background: var(--sy-grad);
    }
    .sy-ribbon--warm::before { background: var(--sy-grad-warm); }

    /* ---------------------------------------------------------------
       RULE 2 — BORDERED CARDS
       --------------------------------------------------------------- */
    .sy-card {
        position: relative; display: flex; flex-direction: column;
        border-radius: 22px; padding: 32px 30px 28px;
        background: var(--sy-card);
        border: 1px solid var(--sy-rule);
        box-shadow: var(--sy-shadow);
        overflow: hidden;
        transition: transform .35s cubic-bezier(.16,1,.3,1), border-color .25s ease;
    }
    .sy-card:hover { transform: translateY(-4px); border-color: var(--sy-rule-2); }

    /* The one card being sold is dark in BOTH themes. That is the whole
       ranking device: not a saturated rectangle, which is loud rather than
       ranked, and not a tint, which is invisible. */
    .sy-card--lead {
        --sy-card:   #0B1030;
        --sy-rule:   rgba(255,255,255,.12);
        --sy-rule-2: rgba(255,255,255,.24);
        --sy-ink:    #FFFFFF;
        --sy-ink-2:  rgba(255,255,255,.72);
        --sy-ink-3:  rgba(255,255,255,.52);
        background:
            radial-gradient(120% 90% at 12% -8%, rgba(92,131,255,.30), transparent 58%),
            linear-gradient(158deg, #14225A 0%, #0B1030 54%, #080C24 100%);
        box-shadow: 0 30px 70px -34px rgba(11,16,48,.62);
    }

    /* ---------------------------------------------------------------
       CARD CONTENTS — eyebrow, figure, note, blurb
       The name is an eyebrow and the figure is the headline. A card whose
       name is set as large as its price has two headlines and no hierarchy.
       --------------------------------------------------------------- */
    /* Name and flag share one row rather than the flag floating over the
       name. Absolutely positioned, the flag sat on top of the eyebrow the
       moment a card got narrow or a plan name got long -- "PROFESSIONAL"
       and "MOST POPULAR" were 10px apart at 300px. The row is reserved on
       every card so a card with no flag keeps the same top. */
    .sy-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; min-height: 26px; margin-bottom: 16px;
    }
    .sy-eyebrow {
        font-size: 11px; font-weight: 800; letter-spacing: .2em;
        text-transform: uppercase; color: var(--sy-ink-3); min-width: 0;
    }
    .sy-figure {
        display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
        font-size: clamp(2.1rem, 3.6vw, 2.9rem); line-height: 1;
        font-weight: 700; letter-spacing: -.03em; color: var(--sy-ink);
        font-variant-numeric: tabular-nums;
    }
    /* Rule 1: the unit is quieter ink, not a colour. */
    .sy-figure .unit {
        font-size: .9rem; font-weight: 500; letter-spacing: 0; color: var(--sy-ink-3);
    }
    /* "2,150,000" at the base size pushed its unit onto a line of its own.
       The step-down is by character count, which is what actually decides
       whether it fits. */
    .sy-figure--long { font-size: clamp(1.7rem, 2.7vw, 2.2rem); }
    .sy-note { margin-top: 10px; font-size: 12px; line-height: 1.55; color: var(--sy-ink-3); }
    .sy-note .was { text-decoration: line-through; opacity: .7; margin-right: 6px; }
    .sy-blurb {
        margin-top: 14px; font-size: 14px; line-height: 1.6; color: var(--sy-ink-2);
    }

    /* A flag, not a sticker. Bordered and quiet; the gradient ribbon on the
       card is what says "this one". */
    .sy-flag {
        flex: none;
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 10px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; white-space: nowrap;
        padding: 5px 11px; border-radius: 999px;
        color: var(--sy-ink-2);
        background: var(--sy-ground);
        border: 1px solid var(--sy-rule-2);
    }
    .sy-card--lead .sy-flag { background: rgba(255,255,255,.10); color: #FFFFFF; }

    /* ---------------------------------------------------------------
       CELLS — hairline rows
       RULE 7 lives here: one icon size, one icon colour, inherited.
       --------------------------------------------------------------- */
    .sy-cells { margin-top: 24px; border-top: 1px solid var(--sy-rule); }
    .sy-cell {
        display: flex; align-items: baseline; gap: 12px;
        padding: 12px 2px; border-bottom: 1px solid var(--sy-rule);
        font-size: 13.5px; line-height: 1.45; color: var(--sy-ink-2);
    }
    /* RULE 7 in one class, for icons that live outside a cell: a glyph that
       marks a line, in ink, at the size of the text around it. The page had
       emerald ticks, amber bolts, cyan arrows and blue stars, each a
       different hue for no reason a reader could name. */
    .sy-ico { color: var(--sy-ink-3); }
    /* Rule 1 takes colour away from links too, so they need the other
       affordance or they stop looking clickable: an underline, offset so it
       does not crowd the descenders. */
    .sy-link {
        color: var(--sy-ink); font-weight: 650;
        text-decoration: underline; text-underline-offset: 3px;
        text-decoration-color: var(--sy-rule-2);
        transition: text-decoration-color .18s ease;
    }
    .sy-link:hover { text-decoration-color: var(--sy-ink); }
    .sy-cell i {
        font-size: 11px; color: var(--sy-ink-3); flex: none;
        width: 14px; text-align: center; position: relative; top: -1px;
    }
    .sy-cell b { color: var(--sy-ink); font-weight: 650; font-variant-numeric: tabular-nums; }
    .sy-cell-body { min-width: 0; display: block; }
    .sy-cell-sub { display: block; margin-top: 2px; font-size: 12px; color: var(--sy-ink-3); }
    .sy-cell-note {
        margin-left: auto; padding-left: 12px; font-size: 12px;
        color: var(--sy-ink-2); white-space: nowrap; text-align: right;
        font-variant-numeric: tabular-nums;
    }
    /* A group heading inside a cell list: same eyebrow, no rule of its own. */
    .sy-group {
        padding: 18px 2px 7px; font-size: 10.5px; font-weight: 800;
        letter-spacing: .16em; text-transform: uppercase; color: var(--sy-ink-3);
    }
    .sy-more {
        display: inline-block; margin-top: 4px; padding: 0;
        background: none; border: 0; cursor: pointer;
        font-size: 12px; font-weight: 650; color: var(--sy-ink-2);
        border-bottom: 1px solid var(--sy-rule-2);
    }
    .sy-more:hover { color: var(--sy-ink); }

    /* ---------------------------------------------------------------
       FORMS
       An input a person cannot see the edges of is an input they do not
       know they can type in. The subscribe field was `bg-white/5` with a
       15%-white border, which on a light page is a faint grey smudge --
       in the live screenshots it reads as placeholder text floating on
       the card with no box around it at all.
       --------------------------------------------------------------- */
    .sy-field { display: flex; flex-direction: column; gap: 10px; }
    .sy-input {
        width: 100%;
        padding: 12px 16px;
        border-radius: 999px;
        font-size: 14px; line-height: 1.4;
        color: var(--sy-ink);
        background: var(--sy-ground);
        border: 1px solid var(--sy-rule-2);
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .sy-input::placeholder { color: var(--sy-ink-3); opacity: 1; }
    .sy-input:focus {
        outline: none;
        border-color: var(--sy-ink);
        box-shadow: 0 0 0 3px rgba(61,107,255,.16);
    }
    .sy-input:disabled { opacity: .6; cursor: not-allowed; }

    /* A result line, not a coloured box. Success and failure differ by the
       word and the glyph; the emerald panel was a third card style. */
    .sy-notice {
        display: flex; align-items: baseline; gap: 9px;
        padding: 11px 2px; margin-bottom: 4px;
        border-top: 1px solid var(--sy-rule);
        border-bottom: 1px solid var(--sy-rule);
        font-size: 13px; line-height: 1.45; color: var(--sy-ink-2);
    }
    .sy-notice b { color: var(--sy-ink); font-weight: 650; }
    .sy-notice i { font-size: 11px; color: var(--sy-ink-3); flex: none; position: relative; top: -1px; }
    .sy-notice--bad { color: var(--sy-ink-2); }
    .sy-notice--bad i { color: #b45309; }
    html:not(.light-mode) .sy-notice--bad i { color: #FCD9A0; }

    /* One card, centred, when a section only has one. A lone card stretched
       across the full width is a banner, and it stops reading as a card. */
    .sy-solo { max-width: 27rem; margin-inline: auto; }

    /* ---------------------------------------------------------------
       RULE 4 — CTAs
       Three, and only three. Same pill, same padding, same weight.
       filled : the action being sold
       ghost  : the alternative
       held   : a state, not an action (you are already on this plan)
       --------------------------------------------------------------- */
    /* ---------------------------------------------------------------
       SEGMENTED TOGGLE
       Part of rule 4: the control that switches a view is not a third CTA
       style. Ink knob, ink label. A saturated blue knob was the loudest
       thing above the cards and it is not the thing being sold.
       --------------------------------------------------------------- */
    .sy-seg {
        position: relative; display: inline-flex; align-items: center;
        padding: 4px; border-radius: 999px;
        background: var(--sy-card); border: 1px solid var(--sy-rule);
        box-shadow: var(--sy-shadow);
    }
    .sy-seg-knob {
        position: absolute; top: 4px; bottom: 4px; left: 4px;
        width: calc(50% - 4px); border-radius: 999px;
        background: var(--sy-ink);
        transition: transform .3s cubic-bezier(.16,1,.3,1);
    }
    .sy-seg button {
        position: relative; z-index: 1; appearance: none; border: 0; cursor: pointer;
        background: transparent; min-width: 7rem;
        padding: 8px 18px; border-radius: 999px;
        font-size: 13px; font-weight: 700; letter-spacing: .01em;
        color: var(--sy-ink-2); white-space: nowrap;
        transition: color .18s ease;
    }
    .sy-seg button:hover { color: var(--sy-ink); }
    .sy-seg button.is-on { color: var(--sy-card); }
    @media (prefers-reduced-motion: reduce) { .sy-seg-knob { transition: none; } }

    .sy-foot { margin-top: 24px; }
    /* When the CTA is the last thing in the card, it sits on the card's
       floor -- so every button in a row lines up however much copy sits
       above it. When the CTA leads (plans put it above the feature list)
       this does not apply. */
    .sy-foot:last-child { margin-top: auto; padding-top: 24px; }
    .sy-foot--lead { margin-top: 22px; }
    .sy-cta {
        display: flex; align-items: center; justify-content: center; gap: 9px;
        width: 100%; padding: 13px 20px; border-radius: 999px;
        font-size: 14px; font-weight: 700; letter-spacing: .01em;
        text-decoration: none; cursor: pointer; appearance: none;
        border: 1px solid var(--sy-ink);
        background: var(--sy-ink); color: var(--sy-card);
        transition: transform .2s ease, box-shadow .25s ease, background-color .2s ease;
    }
    .sy-cta:hover { transform: translateY(-1px); box-shadow: 0 14px 30px -14px rgba(11,16,48,.5); }
    .sy-cta i { font-size: 11px; }
    .sy-cta--ghost {
        background: transparent; color: var(--sy-ink); border-color: var(--sy-rule-2);
    }
    .sy-cta--ghost:hover { border-color: var(--sy-ink); background: transparent; }
    .sy-cta--held {
        background: transparent; color: var(--sy-ink-3);
        border-color: var(--sy-rule); cursor: default;
    }
    .sy-cta--held:hover { transform: none; box-shadow: none; }
    /* On the dark lead card the filled button is white -- the brightest
       thing on the page, which is correct: it is the one action being sold. */
    .sy-card--lead .sy-cta { background: #FFFFFF; border-color: #FFFFFF; color: #0B1030; }
    .sy-foot-note { margin-top: 11px; text-align: center; font-size: 11.5px; color: var(--sy-ink-3); }

    @media (max-width: 560px) {
        .sy-card { padding: 26px 22px 24px; border-radius: 18px; }
        .sy-divider-label { margin: 44px 0 28px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sy-card, .sy-cta { transition: none; }
        .sy-card:hover, .sy-cta:hover { transform: none; }
    }
</style>
@endonce
