{{-- Styles for the pricing band. Its own partial rather than a <style> inside
     pricing.blade.php, because that file is also included by the compact
     homepage designs and a duplicated stylesheet is a second place to forget.

     The band was rebuilt against a Stripe reference. What was taken from it is
     specific, and none of it is "look like Stripe":

       - the colour lives in the GROUND, not in the cards: one soft diagonal
         wash bleeding off both page edges, so the section reads as lit rather
         than as two coloured rectangles;
       - the two plans are a LIGHT/DARK pair. They used to be white against
         saturated #3d6bff, which shouts rather than ranks. Deep navy is what
         reads as the premium one;
       - inside each card, a copy block and then value cells stacked and
         separated by hairlines, instead of six rounded chips with their own
         borders and fills;
       - faint dotted verticals on the ground;
       - a floating pill of secondary links under the cards;
       - far more air than the band had.

     Wrapped in @once: only one fragment is rendered per request today,
     but the partial is included by all seven of them and a stylesheet
     emitted twice is a diff nobody reads. Safe here in a way it was not
     in the audience mocks last batch -- this one is not inside a
     <template>, where the whole block never reaches the document. --}}
@once
<style>
    /* The tokens and the band chrome are separate concerns. /pricing wants
       the card language -- the light/dark pair, the hairline cells, the ink
       pill -- on its own ground, with its own rail, and without this band's
       wash and dotted verticals. `.pr-scope` is those tokens and nothing
       else, so there is one definition of what a Sayzio pricing card looks
       like rather than two that drift. */
    .pr-band, .pr-scope {
        --pr-ground:   #F5F6FA;
        --pr-ink:      #0B1033;
        --pr-ink-2:    #4E5680;
        --pr-ink-3:    #757CA6;
        --pr-rule:     #E4E7F0;
        --pr-rule-2:   #CFD5E8;
        --pr-card:     #FFFFFF;
        --pr-dot:      rgba(15,23,42,.10);
        --pr-shadow:   0 24px 60px -34px rgba(11,16,48,.30);
    }
    .pr-band {
        position: relative;
        isolation: isolate;
        background-color: var(--pr-ground);
    }
    html:not(.light-mode) :is(.pr-band, .pr-scope) {
        --pr-ground:   #0E1017;
        --pr-ink:      #FFFFFF;
        --pr-ink-2:    #A9B0D0;
        --pr-ink-3:    #7A81A6;
        --pr-rule:     rgba(255,255,255,.09);
        --pr-rule-2:   rgba(255,255,255,.16);
        --pr-card:     #15181F;
        --pr-dot:      rgba(255,255,255,.07);
        --pr-shadow:   0 24px 60px -34px rgba(0,0,0,.75);
    }

    /* The wash. Two soft corners rather than one linear sweep: a straight
       diagonal gradient across a 1500px band has a visible axis running
       through the middle of it, and the corners are where the colour is
       wanted anyway. Full-bleed by construction -- it is the band's own
       background, and the band is full width. */
    .pr-band::before {
        content: "";
        position: absolute; inset: 0; z-index: -2; pointer-events: none;
        background:
            radial-gradient(105% 78% at 4% -10%, rgba(61,107,255,.13), transparent 58%),
            radial-gradient(95% 72% at 98% 108%, rgba(27,212,217,.11), transparent 56%),
            radial-gradient(70% 60% at 82% -6%, rgba(124,92,255,.07), transparent 60%);
    }
    html:not(.light-mode) .pr-band::before {
        background:
            radial-gradient(105% 78% at 4% -10%, rgba(61,107,255,.20), transparent 58%),
            radial-gradient(95% 72% at 98% 108%, rgba(27,212,217,.13), transparent 56%),
            radial-gradient(70% 60% at 82% -6%, rgba(124,92,255,.12), transparent 60%);
    }

    /* Dotted verticals: one dot every 9px down a column, one column every
       92px. A linear-gradient tiled at 92px x 10px was the obvious way to do
       it and gives SOLID rules -- the 1px slice fills the whole 10px of the
       tile, so there is no gap to see. A radial-gradient with a dot smaller
       than its tile is what actually dots. */
    .pr-band::after {
        content: "";
        position: absolute; inset: 0; z-index: -1; pointer-events: none;
        background-image: radial-gradient(circle at 0.75px 0.75px, var(--pr-dot) 0.75px, transparent 1px);
        background-size: 92px 9px;
        -webkit-mask-image: linear-gradient(180deg, transparent, #000 14%, #000 86%, transparent);
                mask-image: linear-gradient(180deg, transparent, #000 14%, #000 86%, transparent);
    }

    /* 1180 rather than the page's 1280. Stripe's pricing pair is narrower
       than its body copy, not wider: two cards with room around them read as
       a choice, and the same pair run edge to edge reads as a wall. */
    .pr-wide { max-width: 1180px; }

    .pr-head { text-align: center; max-width: 640px; margin-inline: auto; }
    .pr-eyebrow {
        display: inline-block; font-size: 11px; font-weight: 700;
        letter-spacing: .22em; text-transform: uppercase; color: var(--pr-ink-3);
        margin-bottom: 18px;
    }
    .pr-title {
        font-size: clamp(2rem, 4.2vw, 3.1rem); line-height: 1.06;
        letter-spacing: -.02em; font-weight: 700; color: var(--pr-ink);
        text-wrap: balance; margin-bottom: 14px;
    }
    .pr-sub { font-size: 1rem; line-height: 1.6; color: var(--pr-ink-2); text-wrap: balance; }

    /* ---- billing toggle ---- */
    .pr-toggle {
        display: inline-flex; align-items: center; gap: 2px; padding: 4px;
        border-radius: 999px; background: var(--pr-card);
        border: 1px solid var(--pr-rule); box-shadow: var(--pr-shadow);
    }
    .pr-toggle button {
        appearance: none; border: 0; cursor: pointer; background: transparent;
        padding: 8px 18px; border-radius: 999px; font-size: 12px; font-weight: 700;
        letter-spacing: .04em; color: var(--pr-ink-2); white-space: nowrap;
        display: inline-flex; align-items: center; gap: 8px;
        transition: color .18s ease, background-color .18s ease;
    }
    .pr-toggle button:hover { color: var(--pr-ink); }
    .pr-toggle button[aria-selected="true"] { background: var(--pr-ink); color: var(--pr-ground); }
    html:not(.light-mode) .pr-toggle button[aria-selected="true"] { background: #FFFFFF; color: #0B1033; }
    .pr-save {
        font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        padding: 2px 7px; border-radius: 999px;
        color: #0F766E; background: rgba(16,185,129,.16);
    }
    html:not(.light-mode) .pr-save { color: #6EE7B7; background: rgba(16,185,129,.16); }
    .pr-toggle button[aria-selected="true"] .pr-save { color: #065F46; background: rgba(110,231,183,.9); }

    /* ---- the pair ---- */
    .pr-pair { display: grid; gap: 20px; align-items: start; }
    @media (min-width: 880px) { .pr-pair { grid-template-columns: 1fr 1fr; gap: 24px; } }

    .pr-card {
        position: relative; display: flex; flex-direction: column;
        border-radius: 22px; padding: 34px 32px 30px;
        background: var(--pr-card); border: 1px solid var(--pr-rule);
        box-shadow: var(--pr-shadow);
        transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s;
    }
    .pr-card:hover { transform: translateY(-4px); }

    /* The premium card is dark in BOTH themes -- that is the pair. It carries
       `card-lit` for its ink, which is the class the rest of the page uses for
       a surface that does not turn white when the page does. What it no longer
       carries is #3d6bff: a saturated blue rectangle is loud, and loud is not
       the same as ranked. */
    .pr-card--dark {
        --pr-card: #0B1030;
        --pr-rule: rgba(255,255,255,.12);
        --pr-rule-2: rgba(255,255,255,.22);
        --pr-ink: #FFFFFF;
        --pr-ink-2: rgba(255,255,255,.72);
        --pr-ink-3: rgba(255,255,255,.5);
        background:
            radial-gradient(120% 90% at 12% -8%, rgba(92,131,255,.34), transparent 58%),
            radial-gradient(90% 70% at 96% 104%, rgba(27,212,217,.14), transparent 60%),
            linear-gradient(158deg, #14225A 0%, #0B1030 52%, #080C24 100%);
        box-shadow: 0 30px 70px -34px rgba(11,16,48,.62);
    }

    .pr-flag {
        position: absolute; top: 22px; right: 24px;
        font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
        padding: 5px 11px; border-radius: 999px;
        color: #9FB4FF; background: rgba(61,107,255,.18);
        border: 1px solid rgba(159,180,255,.28);
    }

    .pr-name {
        font-size: 11px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase;
        color: var(--pr-ink-3); margin-bottom: 18px;
    }
    .pr-price {
        display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
        font-size: clamp(2.4rem, 5vw, 3.2rem); line-height: 1;
        font-weight: 700; letter-spacing: -.03em; color: var(--pr-ink);
        font-variant-numeric: tabular-nums;
    }
    .pr-price .per { font-size: .95rem; font-weight: 500; letter-spacing: 0; color: var(--pr-ink-3); }
    .pr-price-note { margin-top: 10px; font-size: 12px; line-height: 1.5; color: var(--pr-ink-3); }
    .pr-price-note .was { text-decoration: line-through; opacity: .75; margin-right: 6px; }
    .pr-blurb { margin-top: 14px; font-size: 14px; line-height: 1.6; color: var(--pr-ink-2); }

    /* ---- value cells ---- */
    /* Hairlines between rows instead of a border, a fill and a radius around
       each one. Six bordered chips in a card is six boxes competing with the
       box they are in; a rule between rows says the same thing and disappears
       when you are not looking at it. */
    .pr-cells { margin-top: 26px; border-top: 1px solid var(--pr-rule); }
    .pr-cell {
        display: flex; align-items: baseline; gap: 12px;
        padding: 13px 2px; border-bottom: 1px solid var(--pr-rule);
        font-size: 13.5px; line-height: 1.45; color: var(--pr-ink-2);
    }
    .pr-cell i { font-size: 11px; color: var(--pr-ink-3); flex: none; width: 14px; text-align: center; position: relative; top: -1px; }
    .pr-card--dark .pr-cell i { color: #9FB4FF; }
    .pr-cell b { color: var(--pr-ink); font-weight: 650; font-variant-numeric: tabular-nums; }
    .pr-cell .pr-cell-note {
        margin-left: auto; font-size: 11px; letter-spacing: .04em; text-transform: uppercase;
        color: var(--pr-ink-3); white-space: nowrap;
    }

    .pr-foot { margin-top: 26px; padding-top: 4px; }
    .pr-cta {
        display: flex; align-items: center; justify-content: center; gap: 9px;
        width: 100%; padding: 14px 20px; border-radius: 999px;
        font-size: 14px; font-weight: 700; letter-spacing: .01em;
        border: 1px solid var(--pr-ink); color: var(--pr-ground); background: var(--pr-ink);
        text-decoration: none; cursor: pointer; appearance: none;
        transition: transform .2s ease, box-shadow .25s ease, opacity .2s ease;
    }
    .pr-cta:hover { transform: translateY(-1px); box-shadow: 0 14px 30px -14px rgba(11,16,48,.5); }
    /* In dark mode both cards are dark, so a filled white button on each of
       them gives the section two equally loud actions and no hierarchy. The
       free plan's takes an outline there. In light mode the pair already
       separates them -- dark-filled against white-on-navy -- and both stay
       filled. */
    html:not(.light-mode) .pr-card:not(.pr-card--dark) .pr-cta {
        background: transparent; color: #FFFFFF; border-color: rgba(255,255,255,.34);
    }
    html:not(.light-mode) .pr-card:not(.pr-card--dark) .pr-cta:hover {
        border-color: rgba(255,255,255,.7); background: rgba(255,255,255,.06);
    }
    /* On the dark card the filled button is white, which is the brightest
       thing in the section -- which is correct, it is the one action being
       sold. `card-lit-cta` keeps light mode from painting its label white on
       its own white ground. */
    .pr-card--dark .pr-cta { background: #FFFFFF; border-color: #FFFFFF; color: #0B1030; }
    .pr-note {
        margin-top: 12px; text-align: center; font-size: 11.5px; color: var(--pr-ink-3);
    }

    /* ---- the anchor pill ---- */
    /* Three secondary errands that used to be a button plus two grey links in
       two separate rows. One floating pill, the way a pricing page keeps its
       side routes together without any of them competing with the plan CTAs. */
    .pr-anchors {
        margin: 42px auto 0; width: max-content; max-width: 100%;
        display: flex; flex-wrap: wrap; justify-content: center; align-items: stretch;
        gap: 2px; padding: 5px; border-radius: 999px;
        background: var(--pr-card); border: 1px solid var(--pr-rule);
        box-shadow: var(--pr-shadow);
    }
    .pr-anchors a {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 18px; border-radius: 999px; text-decoration: none;
        font-size: 13px; font-weight: 600; color: var(--pr-ink-2); white-space: nowrap;
        transition: background-color .18s ease, color .18s ease;
    }
    .pr-anchors a:hover { color: var(--pr-ink); background: rgba(127,140,190,.14); }
    .pr-anchors a i { font-size: 11px; color: var(--pr-ink-3); }
    .pr-anchors a:hover i { color: #3d6bff; }

    .pr-trust {
        margin-top: 22px; display: flex; flex-wrap: wrap; justify-content: center;
        gap: 8px 26px; font-size: 12.5px; color: var(--pr-ink-3);
    }
    .pr-trust span { display: inline-flex; align-items: center; gap: 7px; }
    .pr-trust i { font-size: 11px; color: var(--pr-ink-3); }

    @media (max-width: 560px) {
        .pr-card { padding: 28px 22px 24px; border-radius: 18px; }
        .pr-anchors { border-radius: 20px; }
        .pr-anchors a { flex: 1 1 auto; justify-content: center; }
    }
    @media (prefers-reduced-motion: reduce) {
        .pr-card, .pr-cta { transition: none; }
        .pr-card:hover { transform: none; }
    }
</style>
@endonce
