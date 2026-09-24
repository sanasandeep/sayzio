{{--
    The five menu layouts, as CSS over one set of markup.

    Both the restaurant menu and the store menu draw their items with the
    same three elements -- a photo, an info column, and (in order mode) an
    add-to-cart row -- so a layout is a rule about how those three sit, not
    a different template. That keeps the item loop single-source: a sixth
    layout is a block in here, never a fork of the markup.

    The item list carries `.items.lay-<key>`; every rule below is scoped to
    one of those, so a page that picks nothing renders exactly as it always
    did.

    Parameters: $mp (MenuPresentation::resolve), for the heading font on
    a sub-section. Emitted inside the page's own <style>.
--}}

/* ---- Shared -------------------------------------------------------- */
.items { display: block; }
.item .photo { background: rgba(0,0,0,.05); object-fit: cover; }

/* ---- 1. List (the original) ---------------------------------------- */
/* Photo left, text right, one per row. Nothing here changes what this
   page has always looked like -- it is written out so the other four have
   something to override rather than inheriting a default by accident. */
.items.lay-list .item { display: flex; gap: 14px; padding: 14px 0; border-top: 1px solid rgba(0,0,0,.07); }
.items.lay-list .item .photo { width: 74px; height: 74px; border-radius: 14px; flex: 0 0 auto; }
@media (prefers-color-scheme: dark) { .items.lay-list .item { border-color: rgba(255,255,255,.08); } }

/* ---- 2. Two columns ------------------------------------------------- */
/* Cards, two across on a laptop, one on a phone. Each card is its own
   surface, so the border-top separator of the list layout goes away. */
.items.lay-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 12px; }
.items.lay-cards .item {
    display: flex; gap: 12px; padding: 12px;
    border-radius: 14px; border: 1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
}
.items.lay-cards .item .photo { width: 64px; height: 64px; border-radius: 11px; flex: 0 0 auto; }
@media (prefers-color-scheme: dark) {
    .items.lay-cards .item { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
}
@media (max-width: 560px) { .items.lay-cards { grid-template-columns: 1fr; } }

/* ---- 3. Photo grid --------------------------------------------------- */
/* Three across, photo on top and full-bleed. Drops to two on a phone
   rather than one: at this size two still read, and one would make a
   twelve-item category a very long page. */
.items.lay-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 12px; }
.items.lay-grid .item {
    display: block; padding: 0; overflow: hidden;
    border-radius: 14px; border: 1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
}
.items.lay-grid .item .photo { width: 100%; height: 130px; border-radius: 0; display: block; }
.items.lay-grid .item .info { padding: 10px 12px 12px; }
.items.lay-grid .item .addrow { padding: 0 12px 12px; }
@media (prefers-color-scheme: dark) {
    .items.lay-grid .item { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
}
@media (max-width: 720px) { .items.lay-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 380px) { .items.lay-grid { grid-template-columns: 1fr; } }

/* ---- 4. Compact ------------------------------------------------------ */
/* A printed menu: name and price on one line, leader dots between, photos
   hidden entirely. For a list too long to give every entry a picture --
   drinks, sides, a wine list. The dots are a border on a flexing
   pseudo-element, so they stretch to whatever gap is left. */
.items.lay-compact .item { display: block; padding: 9px 0; border: 0; }
.items.lay-compact .item .photo { display: none; }
/* The row wraps, so name + dots + price take the first line and the
   description drops to its own beneath them. Without flex-wrap the four
   children fight for one line: the name breaks in half and the description
   ends up sitting after the price. */
.items.lay-compact .item .info { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 6px; }
.items.lay-compact .item .name { order: 1; flex: 0 1 auto; }
.items.lay-compact .item .info::after {
    content: ''; order: 2; flex: 1 1 24px;
    border-bottom: 1px dotted currentColor; opacity: .3;
    transform: translateY(-3px); min-width: 16px;
}
.items.lay-compact .item .price { order: 3; margin-top: 0; white-space: nowrap; }
.items.lay-compact .item .desc { order: 4; flex: 1 0 100%; margin-top: 2px; }
.items.lay-compact .item .addrow { order: 5; flex: 1 0 100%; margin-top: 6px; }

/* ---- 5. Showcase ----------------------------------------------------- */
/* One item per row, photo full-width above the text. Few items, shown
   large -- a specials board, a tasting menu. */
.items.lay-showcase .item {
    display: block; padding: 0; margin-top: 16px; overflow: hidden;
    border-radius: 18px; border: 1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
}
.items.lay-showcase .item .photo { width: 100%; height: 220px; border-radius: 0; display: block; }
.items.lay-showcase .item .info { padding: 14px 16px 4px; }
.items.lay-showcase .item .name { font-size: 18px; }
.items.lay-showcase .item .addrow { padding: 0 16px 16px; }
@media (prefers-color-scheme: dark) {
    .items.lay-showcase .item { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
}
@media (max-width: 560px) { .items.lay-showcase .item .photo { height: 170px; } }

/* A wider page for the layouts that put things side by side; the reading
   layouts keep the narrow column they were designed for. */
.page.wide { max-width: 1020px; }

/* ---- Sub-sections --------------------------------------------------- */
/* Sana, 2026-09-23: "cats and sub cats". A sub-section is a heading one
   step quieter than the section above it, indented just enough to read as
   belonging to it and not so far that a phone loses the item photos. The
   rule on its left is what carries the nesting at narrow widths, where
   14px of indent alone is ambiguous. */
.subcat { margin-top: 16px; padding-left: 12px; border-left: 2px solid var(--rule, rgba(0,0,0,.07)); }
.subcat > h3 { font-size: 14.5px; font-weight: 700; margin: 0 0 3px; letter-spacing: .01em;
               font-family: {!! $mp['heading_css'] !!}; color: var(--ink-head, inherit); opacity: .86; }
.subcat > .cdesc { font-size: 12.5px; opacity: .55; margin: 0 0 8px; }

/* ---- Dividers -------------------------------------------------------- */
/* The hairline between items got a colour in the last change and still had
   exactly one shape. Scoped to `lay-list`, and deliberately: the other four
   layouts do not have a divider to restyle. Cards, Photo grid and Showcase
   draw each item as a bordered card, where a "divider style" would be
   editing the card's edge; Compact already sets `border: 0` and separates
   its rows with leader dots. Offering the control everywhere and having it
   do nothing in four places out of five is the bug this week has been
   about, so the editor only offers it for List. */
.items.lay-list.div-line .item { border-top-style: solid; }
.items.lay-list.div-dotted .item { border-top-style: dotted; border-top-width: 2px; }
.items.lay-list.div-none .item { border-top-style: none; }
