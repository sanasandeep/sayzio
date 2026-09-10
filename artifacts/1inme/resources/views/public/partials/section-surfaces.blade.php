{{-- How a band separates from the one above it, and how a surface that
     stays dark keeps its ink when the page turns white.

     This was written for the homepage and lived in that file's <style>. Every
     marketing page has the same two problems -- 42 pages, 200-odd bands, none
     of them declaring a separator, and dark product mocks losing their text in
     light mode on most of them -- so it is one partial, included by
     home.blade.php and by public/layouts/site.blade.php, rather than a rule
     set that only one page in the site obeys.

     Deliberately written to be order-independent, because it is now included
     at a different point on two different pages:

       - .sec-ground paints through :where(), which weighs nothing, so any
         band's own background rule beats it whatever the source order;
       - the light-mode ink rule outranks the two sheets it has to beat
         (marketing-anim.css and public/partials/surfaces.blade.php) on
         specificity rather than on position;
       - .card-lit-cta and .surface-lit-keep escape that rule through a
         :not() rather than by repainting after it.

     Nothing here depends on being read last. --}}
@once
<style>
/* ─── Section dividers ───
   stripe.com separates its bands with a single hairline across the
   content width, and that one line does a lot: it tells you a section
   has ended before you have read a word of the next one. This page ran
   its sections together, so a long scroll read as one continuous
   surface with headings scattered through it.

   Drawn as a ::before on the section rather than as a <hr> between
   them, so the markup stays as it is and a reordered section keeps its
   own rule. Inset to the content width (the same max-w-7xl plus
   gutters the sections use) so it lines up with the text above it,
   not with the viewport.

   Which sections get one is now marked in the markup, with a
   `sec-rule` class on the <section> tag, rather than by an id list
   kept here. The list was the reason dividers kept turning up
   missing: half the page's bands live in their own partials
   (create-showcase, resume, dialer-contacts, forms, notifications)
   and were simply never added to it, one band -- "Grow" -- had no id
   to add, and `#compare-legacy` had been renamed. None of that is
   visible from this file, so the gaps were only ever found by
   looking at the page.

   A class puts the decision next to the section it applies to, and
   HomepageSectionDividerTest fails when a new full-bleed band on the
   page ground has neither the class nor an entry in its opt-out
   list, so the next section added cannot quietly go without one.

   Still deliberately NOT every section -- but which ones are exempt is
   no longer a claim written down somewhere. It used to be: a list of
   ids in HomepageSectionDividerTest, each with a prose reason of the
   form "sits on its own tinted ground". Nothing checked the reason,
   and the light-mode sheet (public/partials/surfaces.blade.php) had
   quietly made half of them false -- it flattens every section wash on
   the page, #buzz's included, so bands excused from carrying a rule
   because they "announce themselves by changing colour" were sitting
   on the same white as everything else. Seven consecutive bands, from
   the top of the AI zone to the social-proof band, had no separator of
   any kind: just under 8,000px of unbroken sheet.

   So the exemption is now a class that PAINTS rather than a sentence
   that asserts. A band is one of two things:

     .sec-ground -- brings a ground of its own, in BOTH themes. The
                    change of colour is the separator.
     .sec-rule   -- sits on the page's ground and carries the hairline.

   A band cannot claim a ground it does not have, because claiming it
   is what draws it.

   The separator belongs to the BOUNDARY, not to the band, so where a
   grounded band is followed by one on the page ground, the colour
   change has already marked that edge and the hairline underneath it
   is one separator too many. `.sec-ground + .sec-rule` drops it.

   A grounded ZONE is not an exception to this. #ai-zone is ~6,600px
   across six bands; its ground separates the zone from the page, and
   the bands inside it still share a ground with each other, so they
   still carry rules. "Reads as one band" was never true of a quarter
   of the page. */
.sec-rule::before {
    content: "";
    position: absolute; top: 0; left: 50%; transform: translateX(-50%);
    width: min(1280px, 100% - 2rem); height: 1px;
    background: var(--sec-rule, rgba(255,255,255,.08));
    pointer-events: none;
}
html.light-mode .sec-rule::before { --sec-rule: rgba(15,23,42,.09); }
@media (min-width: 640px)  { .sec-rule::before { width: min(1280px, 100% - 3rem); } }
@media (min-width: 1024px) { .sec-rule::before { width: min(1280px, 100% - 4rem); } }

/* ─── .sec-first: the band with nothing above it ───
   Every band declares how it separates from the one above. The first band on
   a page has no band above it, so it declares that instead.

   This was inferred for a while rather than declared: "the first <section> in
   a view that extends a layout". That reads well and is wrong, because the
   homepage's hero is not in home.blade.php -- it is in a partial the page
   includes, so the inference exempted nothing and wanted a hairline drawn
   directly under the site header.

   It draws nothing on purpose. Its whole job is to be present, so that a band
   without a rule is a band someone forgot rather than a band someone decided
   about. The guard checks it is not used more than once on a page. */
.sec-first { /* declarative: nothing above this band to divide from */ }

/* ─── .sec-ground: the page's second surface ───
   One tint, used sparingly, so the page has a rhythm instead of
   reading as a single 26,000px sheet with headings scattered down it.
   Six bands take it: the trust band under the hero, How it works,
   Grow, the AI zone, Buzz and pricing -- roughly every 4,000-7,000px,
   which is what gives the page chapters.

   Both values are #pricing's, which had already picked a pair by hand
   and is the only band on the page that has read as its own surface in
   both themes for a while. They are a token now so the next band that
   wants a ground cannot invent a fourth shade of nearly-white.

   `position: relative` is stated rather than assumed: several of these
   bands (the trust band, the Zio hub band) do not carry Tailwind's
   `relative`, and without it the ::before that some of them also need
   would anchor to the wrong ancestor.

   The light-mode value is wrapped in :where() so the whole selector
   weighs nothing. Three bands want a ground of their own rather than
   the shared tint -- the Zio hub is dark in both themes, the pricing
   band has its own pair -- and at zero specificity a single class on
   the band beats this in both directions without depending on which
   <style> the browser happened to see last. Two of those bands live in
   fragments injected after this file, so source order was doing the
   work by accident. */
.sec-ground { position: relative; }
:where(.sec-ground) { background-color: var(--sec-ground, #0E1017); }
:where(html.light-mode) :where(.sec-ground) { --sec-ground: #F5F6FA; }
/* A hairline at a ground boundary was going to be suppressed -- the
   colour change already marks that join -- and `.sec-ground +
   .sec-rule::before { display: none }` is how. It does not survive
   contact with the page: half the bands live in partials that emit a
   <style> block immediately before their <section>, so the two
   sections are not adjacent siblings and `+` never matches. It fired
   at three boundaries out of six.

   Inconsistent is worse than either option, and the ruled version is
   the better of the two anyway: photographed at four boundaries, the
   hairline lands exactly on the colour step and crisps it, where the
   unruled ones read as a soft smear. It is also what #pricing had
   already chosen for itself with `border-block`.

   So every band keeps its rule, grounded neighbours included, and the
   suppression is gone rather than left in place firing at random. */

/* ─── .card-lit: a card that stays blue in BOTH modes ───
   The page has more than one of these -- the Premium plan and the
   Performance Coach -- and each was a flat #3d6bff rectangle. They
   carry their weight properly now: a gradient with a light source, an
   inset top highlight so the top edge catches, and a wide coloured
   glow beneath so the card sits above the page rather than being
   painted on it. The glow warms on hover instead of the whole card
   lifting into a heavier shadow.

   One class rather than one per card, because the light-mode text
   problem below is a property of the SURFACE, not of any one card:
   anything that keeps a saturated fill while the page turns white
   needs its text protected the same way. A second copy of this would
   be a second place to forget. */
.card-lit {
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.30),
        0 2px 6px -2px rgba(28,48,140,.45),
        0 28px 64px -28px rgba(61,107,255,.75);
    transition: box-shadow .3s ease, transform .3s ease;
}
.card-lit:hover {
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.38),
        0 2px 6px -2px rgba(28,48,140,.5),
        0 36px 80px -30px rgba(61,107,255,.95);
}
html.light-mode .card-lit {
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.34),
        0 2px 6px -2px rgba(28,48,140,.28),
        0 30px 70px -30px rgba(61,107,255,.55);
}

/* ─── .card-lit text, in light mode ───
   A pre-existing bug, and not confined to one card. Measured before
   any of today's changes: the Premium heading computed to
   rgb(15,23,42) and its feature tiles to rgb(31,41,55); the
   Performance Coach heading and eyebrow computed to the same
   near-black. All of it on saturated blue.

   The page's light mode darkens headings and body text for a white
   ground, which is right everywhere except on the cards that do not
   turn white with it. Widening the Premium card only made it easier
   to see; the Coach card had it just as badly and nobody had noticed.

   Setting the colour on the card and letting it inherit fixes each
   subtree in one place; elements with their own ground -- a white
   CTA, a tinted chip -- opt back out below.

   !important is what it takes on the heading specifically: this rule
   and `html.light-mode h3:not(.grad-text)` are both (0,2,2), so the
   tie goes to source order, and that rule is ~2400 lines further down
   this file. Raising specificity here would only invite the next
   person to raise theirs.

   ─── and it is not only the cards ───
   The comment above says the problem is a property of the SURFACE
   rather than of any one card, and then the fix was scoped to
   `.card-lit`, which also paints a blue gradient. So every other dark
   surface on the page -- product mocks, phone frames, screens -- kept
   the bug, because carrying `card-lit` would have repainted them blue.

   Audited in Chromium: composite the real ground behind every element
   in light mode, keep the ones that are still dark, and check the text
   inside. Six mocks came back with near-black text on a near-black
   ground: the notifications panel (11 nodes -- the reported one), the
   marketing strategist card (14), the share stage (11), the dialer
   phone (8), the biolink phone in Features (7) and the AI Suite screen
   (3). Two more dark surfaces, the Zio hub band and the AI hero stage,
   were fine -- somebody had fixed those two by hand, which is why this
   read as one broken panel rather than a pattern.

   `surface-lit` is the ink half on its own, for any surface that keeps
   a dark ground while the page turns white. `.card-lit` is now that
   plus the blue gradient. */
html.light-mode :is(.card-lit, .surface-lit),
html.light-mode :is(.card-lit, .surface-lit) :is(h1,h2,h3,h4,p,span,div,b,strong,li,a,td,th,label,small,time):not(.surface-lit-keep, .surface-lit-keep *, .card-lit-cta, .card-lit-cta *) {
    color: #fff !important;
}
/* Restore the deliberately-tinted text: these already set their own
   colour against their own background and must keep it. All
   !important, because the rule above is -- so the CTA went
   white-on-white the moment that landed. An !important above forces
   !important on everything that has to escape it.

   `.card-lit-cta` is also named in the :not() above, and has to be:
   adding `.surface-lit-keep *` to that :not() raised the ink rule by
   one class, past this one, and the pricing card's CTA went
   white-on-white again -- the exact failure this rule was written for,
   reintroduced by the fix for a different card. Excluding it up there
   is what actually keeps it out; this rule only says what colour it
   takes instead. */
html.light-mode .card-lit .card-lit-cta,
html.light-mode .card-lit .card-lit-cta :is(span,i,div) { color: #3d6bff !important; }
/* Muted whites stay muted rather than snapping to full strength, and
   the grey utilities the light-mode sheet rewrites to near-black
   (marketing-anim.css: .text-gray-300/400/500 and the slate mirror)
   come back as muted white instead -- inside these surfaces they were
   always the secondary line, not the primary one. */
html.light-mode :is(.card-lit, .surface-lit) :is(.text-white\/80, .text-white\/70, .text-white\/60) { color: rgba(255,255,255,.8) !important; }
html.light-mode :is(.card-lit, .surface-lit) .text-white\/45 { color: rgba(255,255,255,.45) !important; }
html.light-mode .surface-lit :is(.text-gray-300, .text-gray-400, .text-slate-300, .text-slate-400) { color: rgba(255,255,255,.66) !important; }
html.light-mode .surface-lit :is(.text-gray-500, .text-slate-500) { color: rgba(255,255,255,.5) !important; }
/* A mock's own accent chips, badges and tinted labels set a colour
   against their own fill and have to keep it -- the same escape
   `.card-lit-cta` gets, available to any surface. It is a `:not()` on
   the rule above rather than a rule that puts the colour back: once an
   !important has landed, "inherit !important" only inherits the white
   that landed on the parent. The subtree has to be excluded before the
   fact, not repainted after it. */
</style>
@endonce
