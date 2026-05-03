{{--
  Shared CSS for the public resume page and the editor's live preview.
  Defines style-knob classes (header_style, divider, accent, title_style,
  layout) that the renderer + JS preview attach to the wrapper. Inline
  per-resume tokens (theme colors, typography) are still applied with
  inline `style=""` attributes.

  Class scheme (set on the wrapper):
    .rr.rr-h-{header_style}
       .rr-d-{divider}
       .rr-a-{accent}
       .rr-t-{title_style}
       .rr-layout-{layout}
--}}
<style>
    /* ── Layouts ─────────────────────────────────────────────── */
    .rr.rr-layout-sidebar .pv-sidebar { display: grid; grid-template-columns: 200px 1fr; gap: 22px; }
    .rr.rr-layout-sidebar-right .pv-sidebar { display: grid; grid-template-columns: 1fr 200px; gap: 22px; }
    .rr.rr-layout-sidebar .pv-sidebar > .pv-side-col { border-right: 1px solid rgba(0,0,0,0.08); padding-right: 18px; }
    .rr.rr-layout-sidebar-right .pv-sidebar > .pv-side-col { border-left: 1px solid rgba(0,0,0,0.08); padding-left: 18px; order: 2; }

    .rr.rr-layout-two-col .pv-twocol { columns: 2; column-gap: 28px; }
    .rr.rr-layout-two-col .pv-twocol .pv-section { break-inside: avoid; }

    .rr.rr-layout-portfolio-grid .pv-portfolio-grid { grid-template-columns: 1fr 1fr 1fr; }
    .rr.rr-layout-compact { font-size: 11.5px; line-height: 1.35; }
    .rr.rr-layout-compact .pv-section { margin-top: 10px; }
    .rr.rr-layout-compact .pv-item { margin-bottom: 6px; }

    .rr.rr-layout-timeline .pv-section[data-key="experience"] .pv-item,
    .rr.rr-layout-timeline .pv-section[data-key="projects"] .pv-item {
        position: relative; padding-left: 18px; border-left: 2px solid currentColor; margin-left: 4px;
    }
    .rr.rr-layout-timeline .pv-section[data-key="experience"] .pv-item::before,
    .rr.rr-layout-timeline .pv-section[data-key="projects"] .pv-item::before {
        content:''; position:absolute; left:-6px; top:6px; width:10px; height:10px; border-radius:50%; background: currentColor;
    }

    /* ── Header styles ───────────────────────────────────────── */
    .rr-header { padding-bottom: 10px; margin-bottom: 14px; }
    .rr.rr-h-rule .rr-header       { border-bottom: 2px solid currentColor; }
    .rr.rr-h-underline .rr-header  { border-top: 2px solid currentColor; padding-top: 8px; }
    .rr.rr-h-minimal .rr-header    { border: none; padding-bottom: 4px; }
    .rr.rr-h-centered .rr-header   { text-align: center; border-bottom: 1px solid currentColor; }
    .rr.rr-h-centered .pv-contact  { justify-content: center; }
    .rr.rr-h-banner .rr-header {
        margin: -32px -36px 16px; padding: 22px 36px 18px; color: #fff;
        background: var(--rr-accent, currentColor);
    }
    .rr.rr-h-banner .rr-header .pv-name,
    .rr.rr-h-banner .rr-header .pv-headline,
    .rr.rr-h-banner .rr-header .pv-contact { color: #fff !important; }
    .rr.rr-h-block .rr-header {
        background: rgba(0,0,0,0.04); border-radius: 10px; padding: 14px 16px;
    }
    .rr.rr-h-split .rr-header {
        display: grid; grid-template-columns: 80px 1fr; gap: 14px; align-items: center;
        border-bottom: 1px solid currentColor;
    }
    .rr.rr-h-split .rr-header::before {
        content: attr(data-monogram); font-weight: 800; font-size: 36px;
        background: var(--rr-accent, currentColor); color: #fff; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; min-height: 70px;
    }
    .rr.rr-h-monogram .rr-header {
        display: grid; grid-template-columns: 64px 1fr; gap: 12px; align-items: center; border-bottom: 1px solid currentColor;
    }
    .rr.rr-h-monogram .rr-header::before {
        content: attr(data-monogram); font-weight: 800; font-size: 22px;
        background: var(--rr-accent, currentColor); color: #fff; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
    }
    .rr.rr-h-photo-left .rr-header { border-bottom: 1px solid currentColor; }

    /* ── Section title treatments ────────────────────────────── */
    .rr .pv-section h2 { font-size: 11px; font-weight: 800; letter-spacing: 0.18em; margin: 0 0 8px; padding-bottom: 4px; border: none !important; }
    .rr.rr-t-uppercase   .pv-section h2 { text-transform: uppercase; border-bottom: 1.5px solid currentColor !important; }
    .rr.rr-t-capitalized .pv-section h2 { text-transform: capitalize; letter-spacing: 0.04em; font-size: 13px; border-bottom: 1px solid currentColor !important; }
    .rr.rr-t-plain       .pv-section h2 { text-transform: none; letter-spacing: 0.02em; font-weight: 700; font-size: 13px; }
    .rr.rr-t-underline   .pv-section h2 { text-transform: none; font-size: 13px; font-weight: 700; border-bottom: 2px solid currentColor !important; padding-bottom: 2px; display:inline-block; }
    .rr.rr-t-pill        .pv-section h2 { text-transform: uppercase; font-size: 10.5px; padding: 4px 10px; border-radius: 999px; background: rgba(0,0,0,0.05); display: inline-block; border: 1px solid rgba(0,0,0,0.08) !important; }
    .rr.rr-t-bar         .pv-section h2 { text-transform: uppercase; padding-left: 10px; border-left: 4px solid currentColor !important; padding-bottom: 0; }
    .rr.rr-t-bracket     .pv-section h2 { text-transform: none; font-family: 'SFMono-Regular','Menlo',monospace; font-size: 12px; }
    .rr.rr-t-bracket     .pv-section h2::before { content: '[ '; opacity: 0.6; }
    .rr.rr-t-bracket     .pv-section h2::after  { content: ' ]'; opacity: 0.6; }
    .rr.rr-t-numbered { counter-reset: rr-section; }
    .rr.rr-t-numbered .pv-section { counter-increment: rr-section; }
    .rr.rr-t-numbered .pv-section h2::before {
        content: counter(rr-section, decimal-leading-zero) '  ';
        opacity: 0.55; font-weight: 600;
    }

    /* ── Dividers ────────────────────────────────────────────── */
    .rr.rr-d-rule        .pv-section + .pv-section { border-top: 1px solid rgba(0,0,0,0.06); padding-top: 6px; }
    .rr.rr-d-double      .pv-section + .pv-section { border-top: 3px double rgba(0,0,0,0.12); padding-top: 6px; }
    .rr.rr-d-dot         .pv-section + .pv-section { border-top: 1px dotted rgba(0,0,0,0.18); padding-top: 6px; }
    .rr.rr-d-accent-bar  .pv-section + .pv-section { position: relative; padding-top: 8px; }
    .rr.rr-d-accent-bar  .pv-section + .pv-section::before {
        content: ''; display:block; width: 32px; height: 3px; border-radius: 2px;
        background: var(--rr-accent, currentColor); margin: 0 0 8px;
    }
    .rr.rr-d-none        .pv-section + .pv-section { border-top: none; }

    /* ── Accent placement ───────────────────────────────────── */
    .rr.rr-a-left-rail   { border-left: 6px solid var(--rr-accent, currentColor); padding-left: 18px; }
    .rr.rr-a-right-rail  { border-right: 6px solid var(--rr-accent, currentColor); padding-right: 18px; }
    .rr.rr-a-top-bar     { border-top: 4px solid var(--rr-accent, currentColor); padding-top: 18px; }
    .rr.rr-a-corner      { position: relative; }
    .rr.rr-a-corner::before {
        content:''; position:absolute; right:0; top:0; width: 0; height: 0;
        border-style: solid; border-width: 0 60px 60px 0; border-color: transparent var(--rr-accent, currentColor) transparent transparent;
    }
</style>
