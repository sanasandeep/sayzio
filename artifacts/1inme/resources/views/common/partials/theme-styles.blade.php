<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved !== 'dark') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        /* Tells the browser/OS to render native form controls
           (select option lists, scrollbars, date pickers, etc.) using their
           dark variants so they don't flash white against the dark canvas. */
        color-scheme: dark;
        /* Glassmorphic Dark — translucent frosted surfaces over a near-black
           canvas with purple/cyan/pink ambient blooms (see .bg-mesh below). */
        --bg-body: #0a0a0f;
        --bg-sidebar: rgba(255,255,255,0.02);
        --bg-sidebar-mobile: rgba(14,15,21,0.85);
        --bg-header: rgba(255,255,255,0.01);
        --bg-glass: rgba(255,255,255,0.03);
        --bg-glass-light: rgba(255,255,255,0.05);
        --bg-glass-hover: rgba(255,255,255,0.06);
        --bg-glass-input: rgba(255,255,255,0.04);
        --bg-glass-input-focus: rgba(255,255,255,0.07);
        --bg-card: rgba(255,255,255,0.04);
        --bg-card-hover: rgba(255,255,255,0.06);
        --border-glass: rgba(255,255,255,0.10);
        --border-glass-light: rgba(255,255,255,0.16);
        --border-subtle: rgba(255,255,255,0.06);
        --border-strong: rgba(255,255,255,0.18);
        /* Legacy "soft" aliases still referenced by older inner pages
           (analytics, visitors, billing, security, …). Defined once here as
           aliases of the shared glass tokens so those surfaces inherit the
           theme and flip per mode, instead of falling back to currentColor /
           transparent when the variable is undefined. */
        --bg-soft: var(--bg-glass);
        --surface-soft: var(--bg-glass);
        --border-soft: var(--border-glass);
        --accent-soft: var(--c-primary-soft);
        /* Second family of undefined-but-referenced aliases found on inner
           pages (api-keys, delivery-projects, contacts, resume, dialer,
           settings, leads, slides/creator-profile editors). Each was written
           as `var(--x, <literal>)` where --x was never declared, so the literal
           dark/white fallback always won regardless of theme (light-mode
           legibility bug). Defined here as aliases of the shared tokens so they
           flip per mode instead of freezing on the fallback. */
        --surface: var(--bg-glass);
        --surface-1: var(--bg-glass);
        --surface-2: var(--bg-glass-hover);
        --surface-glass: var(--bg-glass);
        --border: var(--border-glass);
        --text: var(--text-primary);
        --bg-input: var(--bg-glass-input);
        --bg-card-alt: var(--bg-card);
        --sidebar-bg: var(--bg-body);
        --color-primary-soft: var(--c-primary-soft);
        --text-primary: #ffffff;
        --text-secondary: #e2e8f0;
        --text-muted: #94a3b8;
        --text-dimmed: #64748b;
        --text-faint: #475569;
        --text-subtle: rgba(255,255,255,0.30);
        --text-label: #94a3b8;
        --color-primary-400: #5c83ff;
        --sidebar-link: #94a3b8;
        --sidebar-link-hover-bg: rgba(255,255,255,0.05);
        --sidebar-link-hover-text: #ffffff;
        --sidebar-active-bg: rgba(255,255,255,0.10);
        --sidebar-active-border: rgba(61,107,255,0.50);
        --sidebar-active-text: #ffffff;
        --accent: #7d9bff;
        --accent-light: #9c92ff;
        --accent-glow: rgba(61,107,255,0.40);
        --glow-1: rgba(61,107,255,0.20);
        --glow-2: rgba(110,97,255,0.20);
        --glow-3: rgba(215,109,255,0.10);
        --scrollbar-thumb: rgba(255,255,255,0.10);
        --scrollbar-thumb-hover: rgba(255,255,255,0.20);
        --overlay-bg: rgba(0,0,0,0.7);
        --card-shadow: 0 1px 2px rgba(0,0,0,0.35), 0 4px 12px -4px rgba(0,0,0,0.30), inset 0 1px 0 rgba(255,255,255,0.04);
        --card-shadow-hover: 0 18px 44px -12px rgba(0,0,0,0.65), inset 0 1px 0 rgba(255,255,255,0.06), 0 0 0 1px var(--border-glass-light);
        --noise-opacity: 0.03;
        --radius-card: 1rem;
        --radius-pill: 999px;

        /* Multi-color accent palette — purple/cyan/pink-led to match GlassDark */
        --c-primary:   #7d9bff;  --c-primary-soft:   rgba(61,107,255,0.18);
        --c-success:   #34d399;  --c-success-soft:   rgba(52,211,153,0.16);
        --c-info:      #67e8f9;  --c-info-soft:      rgba(34,211,238,0.18);
        --c-warning:   #fbbf24;  --c-warning-soft:   rgba(251,191,36,0.16);
        --c-danger:    #fb7185;  --c-danger-soft:    rgba(251,113,133,0.16);
        --c-pink:      #e29bff;  --c-pink-soft:      rgba(215,109,255,0.18);
        --c-indigo:    #9c92ff;  --c-indigo-soft:    rgba(110,97,255,0.18);
        --c-teal:      #2dd4bf;  --c-teal-soft:      rgba(20,184,166,0.16);
    }

    html.light-mode {
        /* Switch native UI controls back to the light variant in light mode. */
        color-scheme: light;
        /* Metronic demo1 inspired — flat, clean, ultra-light surfaces.
           Body is a soft neutral gray so pure-white cards visibly lift off the
           page; borders are darker than before so card edges are obvious. */
        --bg-body: #f4f6fa;
        --bg-sidebar: #ffffff;
        --bg-sidebar-mobile: #ffffff;
        --bg-header: #ffffff;
        --bg-glass: #ffffff;
        --bg-glass-light: #ffffff;
        --bg-glass-hover: #f4f5f9;
        --bg-glass-input: #ffffff;
        --bg-glass-input-focus: #ffffff;
        --bg-card: #ffffff;
        --bg-card-hover: #ffffff;
        --border-glass: #dbdfe9;
        --border-glass-light: #c4c8d3;
        --border-subtle: #e1e3ea;
        --border-strong: #cbd5e1;
        --text-primary: #071437;
        --text-secondary: #252f4a;
        --text-muted: #4b5675;
        --text-dimmed: #5e6884;
        --text-faint: #6b7491;
        --text-subtle: rgba(7,20,55,0.40);
        --text-label: #5e6884;
        --sidebar-link: #4b5675;
        --sidebar-link-hover-bg: #f9f9f9;
        --sidebar-link-hover-text: #071437;
        --sidebar-active-bg: #eaf0ff;
        --sidebar-active-border: transparent;
        --sidebar-active-text: #3d6bff;
        --accent: #3d6bff;
        --accent-light: #5c83ff;
        --accent-glow: rgba(61,107,255,0.14);
        --glow-1: rgba(61,107,255,0);
        --glow-2: rgba(110,97,255,0);
        --scrollbar-thumb: #e4e6ef;
        --scrollbar-thumb-hover: #b5b5c3;
        --overlay-bg: rgba(7,20,55,0.65);
        --card-shadow: 0 1px 2px rgba(7,20,55,0.04), 0 4px 12px -2px rgba(7,20,55,0.05);
        --card-shadow-hover: 0 10px 28px -6px rgba(7,20,55,0.12), 0 3px 8px rgba(7,20,55,0.06);
        --noise-opacity: 0;

        --c-primary:   #3d6bff;  --c-primary-soft:   #eaf0ff;
        --c-success:   #17c653;  --c-success-soft:   #e9f9ee;
        --c-info:      #1b84ff;  --c-info-soft:      #e9f3ff;
        --c-warning:   #f6c000;  --c-warning-soft:   #fff5d6;
        --c-danger:    #f8285a;  --c-danger-soft:    #fde7ec;
        --c-pink:      #d76dff;  --c-pink-soft:      #f9eaff;
        --c-indigo:    #6e61ff;  --c-indigo-soft:    #ecebff;
        --c-teal:      #14b8a6;  --c-teal-soft:      #e2f7f4;
    }

    [x-cloak] { display: none !important; }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        font-feature-settings: 'cv02','cv03','cv04','cv11';
        font-size: 14px;
        line-height: 1.5;
        letter-spacing: -0.005em;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.4s cubic-bezier(0.4,0,0.2,1), color 0.2s ease;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden;
    }
    h1, h2, h3, h4, h5 {
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
        letter-spacing: -0.022em;
        color: var(--text-primary);
    }
    h1 { font-weight: 700; }
    h2, h3, h4 { font-weight: 700; }
    .num, .stat-value, .stat-num { font-variant-numeric: tabular-nums; font-feature-settings: 'tnum' 1; }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
        opacity: var(--noise-opacity);
        pointer-events: none;
        z-index: 1;
    }

    .glass, .card-premium, .stat-card {
        background: rgba(255, 255, 255, 0.04) !important;
        border: 1px solid transparent !important;
        border-radius: 1.5rem !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 12px 32px rgba(0, 0, 0, 0.4) !important;
    }
    @supports (backdrop-filter: blur(8px)) {
        html:not(.light-mode) .glass,
        html:not(.light-mode) .card-premium,
        html:not(.light-mode) .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.01) 100%) !important;
            backdrop-filter: blur(6px) saturate(180%) brightness(1.1) !important;
            -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.1) !important;
        }
    }
    html.light-mode .glass, html.light-mode .card-premium, html.light-mode .stat-card {
        background: rgba(255, 255, 255, 0.15) !important;
        border: 1px solid transparent !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 12px 32px rgba(0, 0, 0, 0.08) !important;
    }
    @supports (backdrop-filter: blur(8px)) {
        html.light-mode .glass,
        html.light-mode .card-premium,
        html.light-mode .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.1) 100%) !important;
            backdrop-filter: blur(6px) saturate(180%) brightness(1.05) !important;
            -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.05) !important;
        }
    }
    .glass-light {
        background: var(--bg-glass-light);
        border: 1px solid var(--border-glass-light);
        border-radius: 0.875rem;
    }
    .glass-hover:hover { background: var(--bg-glass-hover); }

    /* ===== CARD SYSTEM (Metronic demo1 — flat, 1.5px border, micro shadow) ===== */
    .card-premium {
        position: relative;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        overflow: hidden;
    }
    /* Roomier inner spacing for cards — boosts the common Tailwind padding
       utilities applied as direct children. Specificity .card-premium > .pX
       wins over the bare utility, so existing markup is upgraded automatically
       without touching individual blade files. */
    .card-premium > .p-3  { padding: 1.5rem; }
    .card-premium > .p-4  { padding: 1.875rem; }
    .card-premium > .p-5  { padding: 2.25rem; }
    .card-premium > .p-6  { padding: 2.75rem; }
    .card-premium > .px-4 { padding-left: 1.875rem; padding-right: 1.875rem; }
    .card-premium > .px-5 { padding-left: 2.25rem;  padding-right: 2.25rem; }
    .card-premium > .px-6 { padding-left: 2.75rem;  padding-right: 2.75rem; }
    .card-premium > .py-3 { padding-top: 1.375rem;  padding-bottom: 1.375rem; }
    .card-premium > .py-4 { padding-top: 1.625rem;  padding-bottom: 1.625rem; }
    .card-premium > .py-5 { padding-top: 1.875rem;  padding-bottom: 1.875rem; }
    .card-premium > .py-6 { padding-top: 2.25rem;   padding-bottom: 2.25rem; }
    .card-premium::before { display: none; }
    .card-premium:hover {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 20px 45px rgba(0, 0, 0, 0.6) !important;
    }
    html.light-mode .card-premium:hover {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 20px 45px rgba(0, 0, 0, 0.12) !important;
    }

    /* Card decorations disabled in Metronic flat style */
    .card-premium.card-decorated::after { display: none; }
    .card-premium.card-decorated > * { position: relative; z-index: 1; }

    /* Doubled spacing between stacked cards (was space-y-6 = 1.5rem) */
    .card-premium + .card-premium,
    .card-premium + .stat-card,
    .stat-card + .card-premium,
    .stat-card + .stat-card { margin-top: 3rem; }
    /* Inside Tailwind grid layouts (gap-X) we don't override — grid handles spacing. */
    .grid > .card-premium + .card-premium,
    .grid > .stat-card + .stat-card,
    .flex > .card-premium + .card-premium,
    .flex > .stat-card + .stat-card { margin-top: 0; }

    .gradient-border {
        position: relative;
        background: var(--bg-glass);
        border: 1px solid transparent;
        background-clip: padding-box;
    }
    .gradient-border::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(61,107,255,0.4), rgba(110,97,255,0.15), rgba(61,107,255,0.05));
        z-index: -1;
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask-composite: xor;
        -webkit-mask-composite: xor;
        padding: 1px;
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 1.875rem 1.875rem 2rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }
    /* Animated SVG decorative wave — drifting in the background of every stat card */
    .stat-card::before {
        content: '';
        display: block;
        position: absolute;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 200' preserveAspectRatio='none'%3E%3Cpath fill='none' stroke='currentColor' stroke-width='1.2' stroke-opacity='0.18' d='M0,140 C60,100 120,180 200,120 S320,80 320,140'/%3E%3Cpath fill='none' stroke='currentColor' stroke-width='1' stroke-opacity='0.12' d='M0,80 C80,40 160,120 240,60 S320,30 320,90'/%3E%3Ccircle cx='280' cy='40' r='2.5' fill='currentColor' fill-opacity='0.2'/%3E%3Ccircle cx='40' cy='160' r='2' fill='currentColor' fill-opacity='0.18'/%3E%3Ccircle cx='200' cy='30' r='1.5' fill='currentColor' fill-opacity='0.15'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-size: 100% 100%;
        color: var(--stat-tint, var(--accent));
        pointer-events: none;
        z-index: 0;
        animation: deco-drift 22s ease-in-out infinite;
    }
    /* Soft floating gradient orb on top-right of the stat card */
    .stat-card::after {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 180px; height: 180px;
        background: radial-gradient(closest-side, var(--stat-tint-soft, rgba(61,107,255,0.22)), transparent 72%);
        filter: blur(6px);
        pointer-events: none;
        z-index: 0;
        opacity: 0.9;
        animation: deco-float 14s ease-in-out infinite;
    }
    .stat-card > * { position: relative; z-index: 1; }
    .stat-card::before, .stat-card::after { display: none !important; }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 20px 45px rgba(0, 0, 0, 0.6) !important;
    }
    html.light-mode .stat-card:hover {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 20px 45px rgba(0, 0, 0, 0.12) !important;
    }
    /* Subtle colored top accent — reuses the per-tile --stat-accent gradient set
       inline in markup; adds quiet brand-colored depth without heavy chrome.
       Placed after the ::before display:none reset above so it wins. */
    .stat-card::before {
        content: '' !important;
        display: block !important;
        position: absolute;
        top: 0; left: 0; right: 0; bottom: auto;
        height: 3px;
        width: auto;
        background: var(--stat-accent, linear-gradient(90deg, var(--accent), var(--accent-light))) !important;
        background-size: auto !important;
        opacity: 0.85;
        animation: none !important;
        z-index: 2;
        pointer-events: none;
    }
    html.light-mode .stat-card::before { opacity: 1; }
    @media (prefers-reduced-motion: reduce) {
        .stat-card:hover { transform: none; }
    }
    /* Multi-color stat card variants — tinted SVG/orb */
    .stat-card.tint-primary  { --stat-tint: var(--c-primary); --stat-tint-soft: var(--c-primary-soft); }
    .stat-card.tint-success  { --stat-tint: var(--c-success); --stat-tint-soft: var(--c-success-soft); }
    .stat-card.tint-info     { --stat-tint: var(--c-info);    --stat-tint-soft: var(--c-info-soft); }
    .stat-card.tint-warning  { --stat-tint: var(--c-warning); --stat-tint-soft: var(--c-warning-soft); }
    .stat-card.tint-danger   { --stat-tint: var(--c-danger);  --stat-tint-soft: var(--c-danger-soft); }
    .stat-card.tint-pink     { --stat-tint: var(--c-pink);    --stat-tint-soft: var(--c-pink-soft); }
    .stat-card.tint-indigo   { --stat-tint: var(--c-indigo);  --stat-tint-soft: var(--c-indigo-soft); }
    .stat-card.tint-teal     { --stat-tint: var(--c-teal);    --stat-tint-soft: var(--c-teal-soft); }

    /* ===== Sidebar nav scrollbar (thin, subtle) ===== */
    .sidebar-nav-scroll {
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
        scrollbar-gutter: stable;
    }
    .sidebar-nav-scroll:hover { scrollbar-color: var(--scrollbar-thumb) transparent; }
    .sidebar-nav-scroll::-webkit-scrollbar { width: 6px; }
    .sidebar-nav-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-nav-scroll::-webkit-scrollbar-thumb {
        background: transparent;
        border-radius: 3px;
        transition: background 0.2s ease;
    }
    .sidebar-nav-scroll:hover::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); }
    .sidebar-nav-scroll::-webkit-scrollbar-thumb:hover { background: var(--scrollbar-thumb-hover); }

    /* ============ Modern sidebar nav (v3) ============
       Clean, minimal — no per-item chip background. Icon takes the accent
       color only on hover/active. Active row is a subtle tinted pill with
       a 3px leading bar; ample breathing room and crisp typography. */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5625rem 0.75rem 0.5625rem 0.9375rem;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 8px;
        transition: background-color .18s ease, color .18s ease, transform .18s ease;
        color: var(--sidebar-link);
        letter-spacing: -0.005em;
        position: relative;
        margin: 1px 0;
        text-decoration: none;
        line-height: 1.1;
    }
    .sidebar-link:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }

    .sidebar-link .nav-icon-wrap {
        width: 28px;
        height: 28px;
        min-width: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: transparent;
        border: none;
        border-radius: 0;
        color: var(--text-muted);
        transition: color .18s ease, transform .18s ease;
    }
    .sidebar-link .nav-icon-wrap i {
        font-size: 1.15rem;
        line-height: 1;
        color: inherit;
    }
    .sidebar-link:hover .nav-icon-wrap {
        color: var(--nav-tint, var(--accent));
        transform: translateX(1px);
    }

    .sidebar-link .nav-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
    }

    /* Active row */
    .sidebar-link.active {
        background: var(--nav-tint-soft, rgba(61,107,255,0.14));
        color: var(--nav-tint, var(--sidebar-active-text));
        font-weight: 600;
    }
    .sidebar-link.active .nav-icon-wrap {
        color: var(--nav-tint, var(--sidebar-active-text));
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: -0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 22px;
        border-radius: 0 3px 3px 0;
        background: var(--nav-tint, var(--accent));
    }
    .sidebar-link.active::after { display: none; }

    /* Trailing arrow on hover (subtle affordance) */
    .sidebar-link::after {
        content: '\f105'; /* fa-chevron-right */
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        font-size: 9px;
        margin-left: auto;
        opacity: 0;
        color: var(--text-faint);
        transform: translateX(-4px);
        transition: opacity .18s ease, transform .18s ease;
    }
    .sidebar-link:hover::after {
        opacity: 1;
        transform: translateX(0);
    }
    .sidebar-link.active::after { display: none; }

    /* Section header — slimmer, more spaced */
    .section-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 18px 14px 7px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.14em;
        color: var(--text-faint);
    }
    .section-header::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, var(--border-subtle), transparent);
    }

    /* Plain icon (no wrap) variant — keeps backwards compat */
    .sidebar-link > i {
        font-size: 1.05rem;
        width: 28px;
        text-align: center;
        flex-shrink: 0;
        transition: color .18s ease;
        color: var(--text-muted);
    }
    .sidebar-link:hover > i { color: var(--nav-tint, var(--accent)); }
    .sidebar-link.active > i { color: var(--nav-tint, var(--sidebar-active-text)); }

    /* ===== Ambient gradient blooms (dark mode only) ===== */
    .bg-mesh {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }
    .bg-mesh::before,
    .bg-mesh::after,
    .bg-mesh > .bloom {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(120px);
        mix-blend-mode: screen;
        pointer-events: none;
    }
    .bg-mesh::before {
        top: -20%; left: -10%;
        width: 50%; height: 50%;
        background: var(--glow-1, transparent);
        animation: aurora 22s ease-in-out infinite;
    }
    .bg-mesh::after {
        bottom: -10%; right: -10%;
        width: 40%; height: 60%;
        background: var(--glow-2, transparent);
        animation: aurora 26s ease-in-out infinite reverse;
    }
    /* Pink mid bloom — added via injected child for variety */
    .bg-mesh .bloom-pink {
        top: 20%; right: 18%;
        width: 30%; height: 30%;
        background: var(--glow-3, transparent);
        filter: blur(100px);
        animation: float-slow 28s ease-in-out infinite;
    }
    html.light-mode .bg-mesh,
    html.light-mode .bg-mesh::before,
    html.light-mode .bg-mesh::after,
    html.light-mode .bg-mesh > .bloom { display: none; }

    /* ===== Frosted-glass treatment (dark mode only) ===== */
    html:not(.light-mode) .gradient-border {
        backdrop-filter: blur(24px) saturate(140%);
        -webkit-backdrop-filter: blur(24px) saturate(140%);
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5625rem 1.125rem;
        background: var(--accent);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 9999px;
        transition: background 0.15s ease, box-shadow 0.2s ease, transform 0.15s ease;
        box-shadow: 0 1px 2px rgba(15,23,42,0.08), inset 0 1px 0 rgba(255,255,255,0.12);
        letter-spacing: -0.005em;
        position: relative;
        overflow: hidden;
    }
    .btn-primary::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent 50%);
        opacity: 0;
        transition: opacity 0.3s;
    }
    .btn-primary:hover {
        background: #2342c7;
        box-shadow: 0 2px 4px rgba(0,0,0,0.12);
        transform: translateY(-1px);
    }
    .btn-primary:hover::before { opacity: 0; }
    .btn-primary:active { transform: translateY(0); }
    html.light-mode .btn-primary {
        box-shadow: 0 1px 2px rgba(61,107,255,0.20), inset 0 1px 0 rgba(255,255,255,0.18);
    }
    html.light-mode .btn-primary:hover {
        background: #2139a1;
        box-shadow: 0 4px 12px -2px rgba(61,107,255,0.35), inset 0 1px 0 rgba(255,255,255,0.18);
    }

    /* Gradient variant of the primary button — same geometry as .btn-primary,
       but the surface is a brand-blue gradient built from the theme-flipping
       accent tokens so it stays legible in both modes. */
    .btn-primary-gradient {
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
        color: white;
    }
    .btn-primary-gradient:hover {
        background: linear-gradient(135deg, var(--accent-light), var(--accent));
        box-shadow: 0 4px 14px -4px var(--accent-glow);
        transform: translateY(-1px);
    }
    .btn-primary-gradient:active { transform: translateY(0); }
    html.light-mode .btn-primary-gradient {
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
        color: white;
        box-shadow: 0 1px 2px rgba(61,107,255,0.20), inset 0 1px 0 rgba(255,255,255,0.18);
    }
    html.light-mode .btn-primary-gradient:hover {
        background: linear-gradient(135deg, var(--accent-light), var(--accent));
        box-shadow: 0 4px 12px -2px rgba(61,107,255,0.35), inset 0 1px 0 rgba(255,255,255,0.18);
    }

    .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-glass-input);
        color: var(--text-secondary);
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 9999px;
        border: 1px solid var(--border-glass);
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .btn-ghost:hover {
        background: var(--bg-glass-hover);
        color: var(--text-primary);
        border-color: var(--border-glass-light);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-radius: 9999px;
    }
    /* Multi-color soft badges (Metronic-style) */
    .badge-primary { background: var(--c-primary-soft); color: var(--c-primary); }
    .badge-success { background: var(--c-success-soft); color: var(--c-success); }
    .badge-info    { background: var(--c-info-soft);    color: var(--c-info); }
    .badge-warning { background: var(--c-warning-soft); color: var(--c-warning); }
    .badge-danger  { background: var(--c-danger-soft);  color: var(--c-danger); }
    .badge-pink    { background: var(--c-pink-soft);    color: var(--c-pink); }
    .badge-indigo  { background: var(--c-indigo-soft);  color: var(--c-indigo); }
    .badge-teal    { background: var(--c-teal-soft);    color: var(--c-teal); }

    html {
        scroll-behavior: smooth;
    }

    * {
        scrollbar-width: thin;
        scrollbar-color: var(--scrollbar-thumb) transparent;
    }

    ::-webkit-scrollbar {
        width: 4px;
        height: 4px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: var(--scrollbar-thumb);
        border-radius: 100px;
        transition: background 0.3s ease;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--scrollbar-thumb-hover);
    }
    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    .theme-text-primary { color: var(--text-primary); }
    .theme-text-secondary { color: var(--text-secondary); }
    .theme-text-muted { color: var(--text-muted); }
    .theme-text-dimmed { color: var(--text-dimmed); }
    .theme-text-faint { color: var(--text-faint); }
    .theme-border { border-color: var(--border-subtle); }

    .theme-input {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        border-radius: 0.5rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        outline: none;
    }
    .theme-input:focus {
        background: var(--bg-glass-input-focus);
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--c-primary-soft);
    }
    .theme-input::placeholder { color: var(--text-dimmed); }

    /* Explicit option colors so dropdown popups always match the theme even
       on browsers that don't fully honor color-scheme for <option> rendering. */
    html:not(.light-mode) select option { background-color: #1e1b2e !important; color: #f1f5f9 !important; }
    html.light-mode select option { background-color: #ffffff !important; color: #0f172a !important; }

    /* Theme toggle is now rendered as a standard .header-icon-btn (see
       user/layouts/app.blade.php) so it matches the search/notification
       buttons. The pill/track/knob rules below are intentionally removed. */

    .gradient-text {
        color: var(--text-primary);
        background: none;
        -webkit-text-fill-color: currentColor;
    }
    html.light-mode .gradient-text {
        color: var(--text-primary);
        background: none;
    }

    .glow-icon {
        position: relative;
    }
    .glow-icon::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: inherit;
        background: inherit;
        filter: blur(12px);
        opacity: 0;
        transition: opacity 0.4s;
        z-index: -1;
    }
    .group:hover .glow-icon::after { opacity: 0.6; }

    .shimmer { position: relative; }
    .shimmer::after { content: none; }

    .upgrade-card {
        position: relative;
        overflow: hidden;
        border-radius: 0.75rem;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(61,107,255,0.10), rgba(110,97,255,0.04));
        border: 1px solid rgba(61,107,255,0.18);
    }
    html.light-mode .upgrade-card {
        background: linear-gradient(135deg, #eff3ff, #fafbff);
        border-color: #d3e0ff;
    }
    .upgrade-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 100%; height: 100%;
        background: radial-gradient(circle, rgba(61,107,255,0.1), transparent 70%);
        animation: float-slow 15s ease-in-out infinite;
    }

    @keyframes deco-drift {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33%      { transform: translate(8px, -6px) scale(1.04); }
        66%      { transform: translate(-6px, 5px) scale(0.97); }
    }
    @keyframes deco-float {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.85; }
        50%      { transform: translate(-14px, 12px) scale(1.08); opacity: 1; }
    }
    @keyframes aurora {
        0% { transform: translate(0,0) rotate(0deg); }
        25% { transform: translate(2%,-1%) rotate(0.5deg); }
        50% { transform: translate(-1%,2%) rotate(-0.3deg); }
        75% { transform: translate(1.5%,0.5%) rotate(0.2deg); }
        100% { transform: translate(-0.5%,-1.5%) rotate(-0.5deg); }
    }
    @keyframes float-slow {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(25px, -20px) scale(1.05); }
        66% { transform: translate(-15px, 12px) scale(0.97); }
    }
    @keyframes shimmer {
        0% { left: -100%; }
        50%,100% { left: 200%; }
    }
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 20px rgba(61,107,255,0.15); }
        50% { box-shadow: 0 0 40px rgba(61,107,255,0.25); }
    }
    @keyframes gradient-shift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    @keyframes float-particle {
        0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-10vh) rotate(720deg); opacity: 0; }
    }
    @keyframes border-dance {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
    }
    .animate-float-slow { animation: float-slow 20s ease-in-out infinite; }
    .animate-float-slow-delay { animation: float-slow 28s ease-in-out infinite reverse; }
    .animate-pulse-glow { animation: pulse-glow 3s ease-in-out infinite; }

    .particles {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }
    .particle {
        position: absolute;
        width: 2px;
        height: 2px;
        background: rgba(61,107,255,0.3);
        border-radius: 50%;
        animation: float-particle linear infinite;
    }
    html.light-mode .particle {
        background: rgba(61,107,255,0.15);
    }

    @media (prefers-reduced-motion: reduce) {
        .bg-mesh::before, .bg-mesh::after,
        .animate-float-slow, .animate-float-slow-delay,
        .animate-pulse-glow, .particle,
        .stat-card::after, .card-premium::before {
            animation: none !important;
        }
        .shimmer::after {
            animation: none !important;
        }
        .btn-primary:hover::before {
            animation: none !important;
        }
    }
    /* Brand logo: show light variant in light mode, dark variant in dark mode. */
    .brand-logo { display: none; }
    .brand-logo--dark { display: inline-block; }
    html.light-mode .brand-logo--dark { display: none; }
    html.light-mode .brand-logo--light { display: inline-block; }
    /* Force the dark-bg logo variant regardless of page theme — used on
       always-dark surfaces like the auth-hero photo pane where the
       light-mode logo would wash out against the dark image. */
    .force-dark-logo .brand-logo--light { display: none !important; }
    .force-dark-logo .brand-logo--dark  { display: inline-block !important; }
    html.light-mode .force-dark-logo .brand-logo--light { display: none !important; }
    html.light-mode .force-dark-logo .brand-logo--dark  { display: inline-block !important; }

    /* ===== Global search / command palette modal (theme-aware) =====
       Replaces hardcoded dark colors with the shared light-mode-aware tokens
       so the modal is legible in both themes. Dark defaults below match the
       previous hardcoded values; html.light-mode overrides supply readable
       light surfaces where a single token can't cover both themes. */
    .gsm-backdrop {
        background: rgba(7,7,15,0.78);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    html.light-mode .gsm-backdrop { background: var(--overlay-bg); }

    .gsm-panel {
        background: linear-gradient(180deg, rgba(20,20,32,0.96), rgba(13,13,20,0.98));
        border: 1px solid var(--border-glass);
    }
    html.light-mode .gsm-panel {
        background: var(--bg-card);
        box-shadow: var(--card-shadow-hover);
    }

    .gsm-divider { border-color: var(--border-glass); }

    .gsm-search-input { color: var(--text-primary); }
    .gsm-search-input::placeholder { color: var(--text-dimmed); opacity: 1; }

    .gsm-icon-muted { color: var(--text-dimmed); }
    .gsm-icon-faint { color: var(--text-faint); }

    .gsm-group-header { color: var(--text-dimmed); }

    .gsm-row { color: var(--text-secondary); border-color: transparent; }
    .gsm-row:hover { background: var(--bg-glass-hover); }
    .gsm-row.is-selected {
        background: var(--bg-glass-input-focus);
        border-color: var(--border-glass-light);
    }
    html.light-mode .gsm-row.is-selected {
        background: var(--c-primary-soft);
        border-color: var(--border-glass-light);
    }

    .gsm-row-icon {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: #7d9bff;
    }
    html.light-mode .gsm-row-icon { color: var(--accent); }

    .gsm-kbd {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
    }

    .gsm-footer {
        color: var(--text-dimmed);
        border-color: var(--border-glass);
        background: rgba(0,0,0,0.30);
    }
    html.light-mode .gsm-footer { background: var(--bg-glass-hover); }

    .gsm-empty { color: var(--text-dimmed); }
    .gsm-empty-query { color: var(--text-secondary); }

    /* =====================================================================
       LIGHT MODE — accent text colors & very-light grays
       The global utility remap (injected below) handles white / gray / slate
       / zinc / neutral / stone text. Tailwind's LIGHT accent shades
       (*-100/200/300/400, e.g. text-blue-400, text-emerald-300) are tuned
       for the dark canvas and go low-contrast on the white light-mode surface.
       They're used overwhelmingly as colored labels/icons and on SOFT same-hue
       tints (e.g. bg-emerald-500/10) which stay pale in light mode, so darkening
       the text to a saturated, readable value improves contrast everywhere.
       (Audited: no solid/gradient saturated bg pairs a light accent text, so
       no white-on-color labels are harmed.) Dark mode is untouched — these
       rules only apply under html.light-mode.
       ===================================================================== */
    html.light-mode .text-blue-100,  html.light-mode .text-blue-200,  html.light-mode .text-blue-300,  html.light-mode .text-blue-400,
    html.light-mode .text-indigo-100,  html.light-mode .text-indigo-200,  html.light-mode .text-indigo-300,  html.light-mode .text-indigo-400 { color: #2342c7 !important; }
    html.light-mode .text-fuchsia-100, html.light-mode .text-fuchsia-200, html.light-mode .text-fuchsia-300, html.light-mode .text-fuchsia-400 { color: #a21caf !important; }
    html.light-mode .text-pink-100,    html.light-mode .text-pink-200,    html.light-mode .text-pink-300,    html.light-mode .text-pink-400 { color: #be185d !important; }
    html.light-mode .text-rose-100,    html.light-mode .text-rose-200,    html.light-mode .text-rose-300,    html.light-mode .text-rose-400 { color: #be123c !important; }
    html.light-mode .text-red-100,     html.light-mode .text-red-200,     html.light-mode .text-red-300,     html.light-mode .text-red-400 { color: #b91c1c !important; }
    html.light-mode .text-emerald-100, html.light-mode .text-emerald-200, html.light-mode .text-emerald-300, html.light-mode .text-emerald-400,
    html.light-mode .text-green-100,   html.light-mode .text-green-200,   html.light-mode .text-green-300,   html.light-mode .text-green-400 { color: #047857 !important; }
    html.light-mode .text-teal-100,    html.light-mode .text-teal-200,    html.light-mode .text-teal-300,    html.light-mode .text-teal-400 { color: #0f766e !important; }
    html.light-mode .text-amber-100,   html.light-mode .text-amber-200,   html.light-mode .text-amber-300,   html.light-mode .text-amber-400,
    html.light-mode .text-yellow-100,  html.light-mode .text-yellow-200,  html.light-mode .text-yellow-300,  html.light-mode .text-yellow-400 { color: #b45309 !important; }
    html.light-mode .text-orange-100,  html.light-mode .text-orange-200,  html.light-mode .text-orange-300,  html.light-mode .text-orange-400 { color: #c2410c !important; }
    html.light-mode .text-sky-100,     html.light-mode .text-sky-200,     html.light-mode .text-sky-300,     html.light-mode .text-sky-400 { color: #0369a1 !important; }
    html.light-mode .text-cyan-100,    html.light-mode .text-cyan-200,    html.light-mode .text-cyan-300,    html.light-mode .text-cyan-400 { color: #0e7490 !important; }
    html.light-mode .text-blue-100,    html.light-mode .text-blue-200,    html.light-mode .text-blue-300,    html.light-mode .text-blue-400 { color: #1d4ed8 !important; }
    html.light-mode .text-indigo-100,  html.light-mode .text-indigo-200,  html.light-mode .text-indigo-300,  html.light-mode .text-indigo-400 { color: #4338ca !important; }
</style>
<script>
(function(){
    var css = `
        html.light-mode [class*="text-white"]:not([class*="bg-indigo-"]):not([class*="bg-blue-"]):not([class*="bg-fuchsia-"]):not([class*="bg-emerald-"]):not([class*="bg-red-"]):not([class*="bg-rose-"]):not([class*="bg-gradient"]):not(.toggle-knob):not([class*="bg-amber-"]):not([class*="bg-orange-"]):not([class*="bg-blue-"]):not([class*="bg-sky-"]):not([class*="bg-green-"]):not([class*="bg-teal-"]):not([class*="bg-lime-"]):not([class*="bg-pink-"]):not([class*="bg-yellow-"]):not([class*="bg-cyan-"]):not([class*="bg-indigo-"]):not([class*="bg-primary-"]):not(.btn-primary) {
            color: var(--text-primary) !important;
        }
        html.light-mode [class*="text-white/"]:not([class*="bg-indigo-"]):not([class*="bg-blue-"]):not([class*="bg-fuchsia-"]):not([class*="bg-emerald-"]):not([class*="bg-red-"]):not([class*="bg-rose-"]):not([class*="bg-gradient"]):not(.toggle-knob):not([class*="bg-amber-"]):not([class*="bg-orange-"]):not([class*="bg-blue-"]):not([class*="bg-sky-"]):not([class*="bg-green-"]):not([class*="bg-teal-"]):not([class*="bg-lime-"]):not([class*="bg-pink-"]):not([class*="bg-yellow-"]):not([class*="bg-cyan-"]):not([class*="bg-indigo-"]):not([class*="bg-primary-"]):not(.btn-primary) {
            color: var(--text-muted) !important;
        }
        html.light-mode [class*="text-white/8"],
        html.light-mode [class*="text-white/7"] {
            color: var(--text-secondary) !important;
        }
        html.light-mode [class*="text-white/6"],
        html.light-mode [class*="text-white/5"] {
            color: var(--text-muted) !important;
        }
        html.light-mode [class*="text-white/4"],
        html.light-mode [class*="text-white/3"] {
            color: var(--text-dimmed) !important;
        }
        html.light-mode [class*="text-white/2"],
        html.light-mode [class*="text-white/1"] {
            color: var(--text-faint) !important;
        }
        /* Low-opacity primary tints (chips/badges like bg-primary-500/10)
           are nearly white in light mode, so the bg-primary- exemption
           above must NOT keep their text white. Near-solid fills
           (e.g. bg-primary-600/90) are intentionally excluded. */
        html.light-mode [class*="text-white"][class*="bg-primary-500/1"],
        html.light-mode [class*="text-white"][class*="bg-primary-500/2"],
        html.light-mode [class*="text-white"][class*="bg-primary-500/3"],
        html.light-mode [class*="text-white"][class*="bg-primary-400/1"],
        html.light-mode [class*="text-white"][class*="bg-primary-400/2"],
        html.light-mode [class*="text-white"][class*="bg-primary-400/3"],
        html.light-mode [class*="text-white"][class*="bg-primary-600/1"],
        html.light-mode [class*="text-white"][class*="bg-primary-600/2"],
        html.light-mode [class*="text-white"][class*="bg-primary-600/3"] {
            color: var(--text-primary) !important;
        }
        html.light-mode [class*="border-white/"] {
            border-color: var(--border-glass) !important;
        }
        html.light-mode [class*="border-white/5"] {
            border-color: var(--border-subtle) !important;
        }
        html.light-mode [class*="bg-white/5"],
        html.light-mode [class*="bg-white/[0.0"] {
            background-color: var(--bg-glass-input) !important;
        }
        html.light-mode [class*="bg-white/10"],
        html.light-mode [class*="bg-white/1"] {
            background-color: var(--bg-glass-input-focus) !important;
        }
        html.light-mode [class*="placeholder-white"]::placeholder,
        html.light-mode [class*="placeholder-gray"]::placeholder {
            color: var(--text-dimmed) !important;
        }
        html.light-mode [class*="bg-[#1a1025]"],
        html.light-mode [class*="bg-[#0f0a1a]"],
        html.light-mode [class*="bg-[#0a0612]"],
        html.light-mode [class*="bg-[#0d0818]"],
        html.light-mode [class*="bg-[#06010f]"] {
            background-color: var(--bg-body) !important;
        }
        html.light-mode [class*="border-gray-6"],
        html.light-mode [class*="border-gray-7"] {
            border-color: var(--border-glass) !important;
        }
        html.light-mode [class*="hover:bg-white"]:hover {
            background-color: var(--bg-glass-hover) !important;
        }
        html.light-mode [class*="hover:text-white"]:hover {
            color: var(--text-primary) !important;
        }
        /* ----- Translucent BLACK backgrounds (used for overlays, dropdowns,
           and dim scrims) become subtle dark tints over light surfaces so
           content stays readable instead of producing near-black panels. */
        html.light-mode [class*="bg-black/5"],
        html.light-mode [class*="bg-black/[0.0"] {
            background-color: var(--bg-glass-input) !important;
        }
        html.light-mode [class*="bg-black/1"],
        html.light-mode [class*="bg-black/2"],
        html.light-mode [class*="bg-black/3"] {
            background-color: rgba(15,23,42,0.06) !important;
        }
        html.light-mode [class*="bg-black/4"],
        html.light-mode [class*="bg-black/5"]:not([class*="bg-black/50"]) {
            background-color: rgba(15,23,42,0.10) !important;
        }
        html.light-mode [class*="bg-black/50"],
        html.light-mode [class*="bg-black/6"],
        html.light-mode [class*="bg-black/7"],
        html.light-mode [class*="bg-black/8"] {
            background-color: var(--overlay-bg) !important;
        }
        html.light-mode [class*="hover:bg-black"]:hover {
            background-color: var(--bg-glass-hover) !important;
        }
        html.light-mode [class*="border-black/"] {
            border-color: var(--border-glass) !important;
        }
        /* ----- Tailwind palette text colors (gray/zinc/slate/neutral/stone)
           sometimes used in dashboard widgets, often inside dynamic class
           ternaries (vault tabs, status badges) — map them onto our muted
           tokens so they read on the light surface. The gray palette gets the
           exact same tier treatment as slate/zinc here. */
        html.light-mode [class*="text-gray-1"],
        html.light-mode [class*="text-gray-2"],
        html.light-mode [class*="text-zinc-1"],
        html.light-mode [class*="text-zinc-2"],
        html.light-mode [class*="text-slate-1"],
        html.light-mode [class*="text-slate-2"],
        html.light-mode [class*="text-neutral-1"],
        html.light-mode [class*="text-neutral-2"],
        html.light-mode [class*="text-stone-1"],
        html.light-mode [class*="text-stone-2"] {
            color: var(--text-muted) !important;
        }
        html.light-mode [class*="text-gray-3"],
        html.light-mode [class*="text-gray-4"],
        html.light-mode [class*="text-zinc-3"],
        html.light-mode [class*="text-zinc-4"],
        html.light-mode [class*="text-slate-3"],
        html.light-mode [class*="text-slate-4"],
        html.light-mode [class*="text-neutral-3"],
        html.light-mode [class*="text-neutral-4"],
        html.light-mode [class*="text-stone-3"],
        html.light-mode [class*="text-stone-4"] {
            color: var(--text-muted) !important;
        }
        html.light-mode [class*="text-gray-5"],
        html.light-mode [class*="text-gray-6"],
        html.light-mode [class*="text-zinc-5"],
        html.light-mode [class*="text-zinc-6"],
        html.light-mode [class*="text-slate-5"],
        html.light-mode [class*="text-slate-6"],
        html.light-mode [class*="text-neutral-5"],
        html.light-mode [class*="text-neutral-6"],
        html.light-mode [class*="text-stone-5"],
        html.light-mode [class*="text-stone-6"] {
            color: var(--text-dimmed) !important;
        }
        /* ----- Solid mid-gray fills used as status dots (e.g. inactive plan
           badge: bg-gray-400) go faint on white. Lift them to a readable
           neutral. Exact-class selectors only, so translucent badges
           (bg-gray-500/20) and the off-state toggle track (bg-gray-300) are
           intentionally left untouched. */
        html.light-mode .bg-gray-400,
        html.light-mode .bg-gray-500 {
            background-color: var(--text-dimmed) !important;
        }
        /* ----- Dark Tailwind backgrounds (slate/zinc/gray/neutral/stone 7xx-9xx)
           lift to white so light dashboards don't get heavy dark slabs. */
        html.light-mode [class*="bg-slate-7"],
        html.light-mode [class*="bg-slate-8"],
        html.light-mode [class*="bg-slate-9"],
        html.light-mode [class*="bg-zinc-7"],
        html.light-mode [class*="bg-zinc-8"],
        html.light-mode [class*="bg-zinc-9"],
        html.light-mode [class*="bg-gray-7"],
        html.light-mode [class*="bg-gray-8"],
        html.light-mode [class*="bg-gray-9"],
        html.light-mode [class*="bg-neutral-7"],
        html.light-mode [class*="bg-neutral-8"],
        html.light-mode [class*="bg-neutral-9"],
        html.light-mode [class*="bg-stone-7"],
        html.light-mode [class*="bg-stone-8"],
        html.light-mode [class*="bg-stone-9"] {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }
        /* ----- Inline ad-hoc dark surface (rgba near-black popovers) becomes
           an elevated white card. Targets the common dropdown/popover pattern
           background: rgba(20,20,32,0.95). */
        html.light-mode [style*="rgba(20,20,32"],
        html.light-mode [style*="rgba(14,15,21"],
        html.light-mode [style*="rgba(10,10,15"] {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-glass) !important;
        }
        html.light-mode input:not([type="color"]):not([type="checkbox"]):not([type="radio"]),
        html.light-mode textarea,
        html.light-mode select {
            color: var(--text-primary) !important;
        }
        html.light-mode input::placeholder,
        html.light-mode textarea::placeholder {
            color: var(--text-dimmed) !important;
        }
        html.light-mode .divide-white\\/5 > * + * {
            border-color: var(--border-subtle) !important;
        }
    `;
    var style = document.createElement('style');
    style.setAttribute('id', 'light-mode-overrides');
    style.textContent = css;
    if (document.head) {
        document.head.appendChild(style);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            document.head.appendChild(style);
        });
    }
})();

/* Auto-decorate cards that contain an icon header (highlight cards)
   by tagging them with .card-decorated and inheriting the section's accent. */
(function(){
    function tintFromIcon(icon){
        var cls = icon.className || '';
        var m = cls.match(/text-(violet|purple|emerald|green|cyan|teal|amber|yellow|orange|red|rose|pink|fuchsia|indigo|blue|sky)-(300|400|500|600)/);
        if(!m) return null;
        var map = {
            violet: 'rgba(61,107,255,', purple: 'rgba(110,97,255,',
            emerald: 'rgba(16,185,129,', green: 'rgba(34,197,94,',
            cyan: 'rgba(6,182,212,', teal: 'rgba(20,184,166,', sky: 'rgba(14,165,233,',
            amber: 'rgba(245,158,11,', yellow: 'rgba(234,179,8,', orange: 'rgba(249,115,22,',
            red: 'rgba(239,68,68,', rose: 'rgba(244,63,94,'  ,
            pink: 'rgba(236,72,153,', fuchsia: 'rgba(217,70,239,'  ,
            indigo: 'rgba(99,102,241,', blue: 'rgba(59,130,246,'
        };
        return map[m[1]] ? map[m[1]] + '0.20)' : null;
    }
    function decorate(){
        document.querySelectorAll('.card-premium:not(.card-decorated)').forEach(function(card){
            var icon = card.querySelector('[class*="fa-"]');
            if(!icon) return;
            var tint = tintFromIcon(icon);
            if(tint) card.style.setProperty('--card-deco', tint);
            card.classList.add('card-decorated');
        });
    }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', decorate);
    } else { decorate(); }
    document.addEventListener('alpine:initialized', decorate);
    var mo = new MutationObserver(function(muts){
        for(var i=0;i<muts.length;i++){ if(muts[i].addedNodes && muts[i].addedNodes.length){ decorate(); break; } }
    });
    if(document.body) mo.observe(document.body, {childList:true, subtree:true});
    else document.addEventListener('DOMContentLoaded', function(){ mo.observe(document.body, {childList:true, subtree:true}); });
})();
</script>
