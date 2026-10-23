{{-- Workspace chrome only: public pages, authored link content and light mode keep their palettes. --}}
<style>
html.sayzio-workspace:not(.light-mode) {
    --bg-body: #101113;
    --bg-header: #141518;
    --bg-card: #191b1f;
    --bg-card-hover: #202328;
    --bg-glass: #191b1f;
    --bg-glass-light: #202328;
    --bg-glass-hover: #25282e;
    --bg-glass-input: #202328;
    --bg-glass-input-focus: #292d34;
    --bg-dropdown: #202328;
    --bg-sidebar: #15171a;
    --bg-sidebar-mobile: #15171a;
    --text-primary: #e9ebef;
    --text-secondary: #c9ced7;
    --text-muted: #a0a8b5;
    --text-dimmed: #929baa;
    --text-faint: #929baa;
    --border-glass: rgba(255,255,255,.10);
    --border-glass-light: rgba(255,255,255,.16);
    --lg-bg: #191b1f;
    --lg-bg-solid: #191b1f;
    --lg-bg-opaque: #202328;
    --lg-blur: blur(16px);
    --glow-1: transparent;
    --glow-2: transparent;
    --glow-3: transparent;
    --noise-opacity: 0;
    --aurora-edge: linear-gradient(#343840, #343840);
}
/* Override configured large navigation washes, while retaining readable controls. */
html.sayzio-workspace:not(.light-mode) :is(.sidebar-shell,.navigation-mobile,.navrail-panel,.navrail,.navrail-account,.header-v2) {
    --nav-surface: #15171a;
    --nav-control: #202328;
    --nav-ink: #e9ebef;
    --nav-muted: #a0a8b5;
    --nav-line: rgba(255,255,255,.10);
    --nav-active: #292d34;
    --nav-active-ink: #e9ebef;
    background: #15171a !important;
    color: #e9ebef !important;
}
html.sayzio-workspace:not(.light-mode) body[data-app-layout] { background: #101113 !important; }
html.sayzio-workspace:not(.light-mode) :is(.bg-mesh,.particles) { display: none; }
html.sayzio-workspace:not(.light-mode) .page-hero {
    background: #191b1f !important;
    background-image: none !important;
    border: 1px solid var(--border-glass) !important;
}
html.sayzio-workspace:not(.light-mode) .page-hero > .hero-row {
    background: #191b1f !important;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}
html.sayzio-workspace:not(.light-mode) .page-hero > .page-hero-deco { opacity: .12; }
html.sayzio-workspace:not(.light-mode) .page-hero :is(.cribbon,.cribbon-grid) { opacity: .08; }
/* Keep the metric color as a small rail, rather than a luminous card-sized fill. */
html.sayzio-workspace:not(.light-mode) :is(.kpi-hero,.kpi-cell,.ov-count,.folder-kpi) {
    background: #191b1f !important;
    background-image: none !important;
    border: 1px solid var(--border-glass) !important;
    border-top: 2px solid color-mix(in srgb, var(--ov-color, #8295cc) 65%, #191b1f) !important;
    box-shadow: 0 4px 14px rgba(0,0,0,.12) !important;
    color: var(--text-primary);
}
html.sayzio-workspace:not(.light-mode) :is(.kpi-hero,.kpi-cell,.ov-count,.folder-kpi):hover {
    transform: none;
    box-shadow: 0 4px 14px rgba(0,0,0,.18) !important;
}
html.sayzio-workspace:not(.light-mode) .ov-count::after { background: none; }
html.sayzio-workspace:not(.light-mode) :is(.kpi-hero-sub,.kpi-cell-note,.ov-count-bottom p) { color: var(--text-muted); }
html.sayzio-workspace:not(.light-mode) :is(.kpi-hero,.kpi-cell,.ov-count,.folder-kpi) .ov-count-art {
    opacity: .4;
    filter: saturate(.45);
    animation: none;
}
html.sayzio-workspace:not(.light-mode) .stat-card::after { opacity: .12; }
</style>
