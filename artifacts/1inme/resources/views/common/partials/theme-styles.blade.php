<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved !== 'dark') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        /* Premium dark — sidebar/cards distinct from body */
        --bg-body: #0a0b10;
        --bg-sidebar: #0e0f15;
        --bg-sidebar-mobile: #0e0f15;
        --bg-header: #0e0f15;
        --bg-glass: #13141b;
        --bg-glass-light: #181922;
        --bg-glass-hover: #1c1d27;
        --bg-glass-input: #0f1015;
        --bg-glass-input-focus: #14151c;
        --bg-card: #0a0b10;
        --bg-card-hover: #10111a;
        --border-glass: #2a2d38;
        --border-glass-light: #3a3e4c;
        --border-subtle: #16171d;
        --text-primary: #f5f6fa;
        --text-secondary: #cdcfd6;
        --text-muted: #9498a3;
        --text-dimmed: #6b6e78;
        --text-faint: #4d505a;
        --text-label: #6b6e78;
        --sidebar-link: #9498a3;
        --sidebar-link-hover-bg: #1a1c24;
        --sidebar-link-hover-text: #f5f6fa;
        --sidebar-active-bg: rgba(139,92,246,0.14);
        --sidebar-active-border: rgba(139,92,246,0.30);
        --sidebar-active-text: #c4b5fd;
        --accent: #8b5cf6;
        --accent-light: #a78bfa;
        --accent-glow: rgba(139,92,246,0.35);
        --glow-1: rgba(139,92,246,0);
        --glow-2: rgba(168,85,247,0);
        --scrollbar-thumb: #2a2c35;
        --scrollbar-thumb-hover: #3a3d48;
        --overlay-bg: rgba(0,0,0,0.7);
        --card-shadow: 0 1px 2px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.02);
        --card-shadow-hover: 0 8px 24px -8px rgba(0,0,0,0.5), 0 0 0 1px var(--border-glass-light);
        --noise-opacity: 0;
        --radius-card: 0.75rem;
        --radius-pill: 999px;

        /* Multi-color accent palette (used for stat cards, badges, tints) */
        --c-primary:   #8b5cf6;  --c-primary-soft:   rgba(139,92,246,0.14);
        --c-success:   #10b981;  --c-success-soft:   rgba(16,185,129,0.14);
        --c-info:      #06b6d4;  --c-info-soft:      rgba(6,182,212,0.14);
        --c-warning:   #f59e0b;  --c-warning-soft:   rgba(245,158,11,0.14);
        --c-danger:    #ef4444;  --c-danger-soft:    rgba(239,68,68,0.14);
        --c-pink:      #ec4899;  --c-pink-soft:      rgba(236,72,153,0.14);
        --c-indigo:    #6366f1;  --c-indigo-soft:    rgba(99,102,241,0.14);
        --c-teal:      #14b8a6;  --c-teal-soft:      rgba(20,184,166,0.14);
    }

    html.light-mode {
        /* Metronic demo1 inspired — flat, clean, ultra-light surfaces.
           Body is a soft neutral gray so pure-white cards visibly lift off the
           page; borders are darker than before so card edges are obvious. */
        --bg-body: #eef0f5;
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
        --text-primary: #071437;
        --text-secondary: #252f4a;
        --text-muted: #4b5675;
        --text-dimmed: #78829d;
        --text-faint: #b5b5c3;
        --text-label: #78829d;
        --sidebar-link: #4b5675;
        --sidebar-link-hover-bg: #f9f9f9;
        --sidebar-link-hover-text: #071437;
        --sidebar-active-bg: #f3eeff;
        --sidebar-active-border: transparent;
        --sidebar-active-text: #7c3aed;
        --accent: #7c3aed;
        --accent-light: #8b5cf6;
        --accent-glow: rgba(124,58,237,0.14);
        --glow-1: rgba(124,58,237,0);
        --glow-2: rgba(168,85,247,0);
        --scrollbar-thumb: #e4e6ef;
        --scrollbar-thumb-hover: #b5b5c3;
        --overlay-bg: rgba(7,20,55,0.28);
        --card-shadow: 0 3px 4px rgba(7,20,55,0.03);
        --card-shadow-hover: 0 6px 14px rgba(7,20,55,0.06);
        --noise-opacity: 0;

        --c-primary:   #7c3aed;  --c-primary-soft:   #f3eeff;
        --c-success:   #17c653;  --c-success-soft:   #e9f9ee;
        --c-info:      #1b84ff;  --c-info-soft:      #e9f3ff;
        --c-warning:   #f6c000;  --c-warning-soft:   #fff5d6;
        --c-danger:    #f8285a;  --c-danger-soft:    #fde7ec;
        --c-pink:      #ec4899;  --c-pink-soft:      #fdeaf3;
        --c-indigo:    #6366f1;  --c-indigo-soft:    #eceffe;
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

    .glass {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        box-shadow: var(--card-shadow);
        border-radius: var(--radius-card);
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
        background: var(--bg-card);
        border: 1.5px solid var(--border-glass);
        border-radius: var(--radius-card);
        box-shadow: var(--card-shadow);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        overflow: hidden;
    }
    /* Roomier inner spacing for cards — boosts the common Tailwind padding
       utilities applied as direct children. Specificity .card-premium > .pX
       wins over the bare utility, so existing markup is upgraded automatically
       without touching individual blade files. */
    .card-premium > .p-3  { padding: 1.25rem; }
    .card-premium > .p-4  { padding: 1.5rem; }
    .card-premium > .p-5  { padding: 1.875rem; }
    .card-premium > .p-6  { padding: 2.25rem; }
    .card-premium > .px-4 { padding-left: 1.5rem;   padding-right: 1.5rem; }
    .card-premium > .px-5 { padding-left: 1.875rem; padding-right: 1.875rem; }
    .card-premium > .px-6 { padding-left: 2.25rem;  padding-right: 2.25rem; }
    .card-premium > .py-3 { padding-top: 1.125rem;  padding-bottom: 1.125rem; }
    .card-premium > .py-4 { padding-top: 1.375rem;  padding-bottom: 1.375rem; }
    .card-premium > .py-5 { padding-top: 1.625rem;  padding-bottom: 1.625rem; }
    .card-premium > .py-6 { padding-top: 1.875rem;  padding-bottom: 1.875rem; }
    .card-premium::before { display: none; }
    .card-premium:hover {
        border-color: var(--border-glass-light);
        box-shadow: var(--card-shadow-hover);
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
        background: linear-gradient(135deg, rgba(124,58,237,0.4), rgba(139,92,246,0.15), rgba(124,58,237,0.05));
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
        background: var(--bg-card);
        border: 1.5px solid var(--border-glass);
        border-radius: var(--radius-card);
        padding: 1.875rem 1.875rem 2rem;
        box-shadow: var(--card-shadow);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
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
        background: radial-gradient(closest-side, var(--stat-tint-soft, rgba(139,92,246,0.22)), transparent 72%);
        filter: blur(6px);
        pointer-events: none;
        z-index: 0;
        opacity: 0.9;
        animation: deco-float 14s ease-in-out infinite;
    }
    .stat-card > * { position: relative; z-index: 1; }
    .stat-card::before, .stat-card::after { display: none !important; }
    .stat-card:hover {
        border-color: var(--border-glass-light);
        box-shadow: var(--card-shadow-hover);
    }
    html.light-mode .stat-card.tint-primary,
    html.light-mode .stat-card.tint-success,
    html.light-mode .stat-card.tint-info,
    html.light-mode .stat-card.tint-warning,
    html.light-mode .stat-card.tint-danger,
    html.light-mode .stat-card.tint-pink,
    html.light-mode .stat-card.tint-indigo,
    html.light-mode .stat-card.tint-teal { background: var(--bg-card) !important; }
    /* Multi-color stat card variants — smooth gradient + tinted SVG/orb */
    .stat-card.tint-primary  { background: linear-gradient(135deg, var(--c-primary-soft), transparent 110%); --stat-tint: var(--c-primary); --stat-tint-soft: var(--c-primary-soft); }
    .stat-card.tint-success  { background: linear-gradient(135deg, var(--c-success-soft), transparent 110%); --stat-tint: var(--c-success); --stat-tint-soft: var(--c-success-soft); }
    .stat-card.tint-info     { background: linear-gradient(135deg, var(--c-info-soft),    transparent 110%); --stat-tint: var(--c-info);    --stat-tint-soft: var(--c-info-soft); }
    .stat-card.tint-warning  { background: linear-gradient(135deg, var(--c-warning-soft), transparent 110%); --stat-tint: var(--c-warning); --stat-tint-soft: var(--c-warning-soft); }
    .stat-card.tint-danger   { background: linear-gradient(135deg, var(--c-danger-soft),  transparent 110%); --stat-tint: var(--c-danger);  --stat-tint-soft: var(--c-danger-soft); }
    .stat-card.tint-pink     { background: linear-gradient(135deg, var(--c-pink-soft),    transparent 110%); --stat-tint: var(--c-pink);    --stat-tint-soft: var(--c-pink-soft); }
    .stat-card.tint-indigo   { background: linear-gradient(135deg, var(--c-indigo-soft),  transparent 110%); --stat-tint: var(--c-indigo);  --stat-tint-soft: var(--c-indigo-soft); }
    .stat-card.tint-teal     { background: linear-gradient(135deg, var(--c-teal-soft),    transparent 110%); --stat-tint: var(--c-teal);    --stat-tint-soft: var(--c-teal-soft); }

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

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.5rem;
        transition: background 0.15s ease, color 0.15s ease;
        color: var(--sidebar-link);
        letter-spacing: -0.005em;
        position: relative;
        margin-bottom: 2px;
        text-decoration: none;
    }
    .sidebar-link:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }
    .sidebar-link.active {
        background: var(--sidebar-active-bg);
        color: var(--sidebar-active-text);
        font-weight: 600;
    }
    .sidebar-link.active::before { display: none; }
    .sidebar-link .nav-icon-wrap {
        width: 32px;
        height: 32px;
        min-width: 32px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s;
        color: inherit;
    }
    .sidebar-link .nav-icon-wrap i {
        font-size: 0.85rem;
        color: inherit;
        transition: transform 0.2s;
    }
    .sidebar-link .nav-icon-wrap {
        background: var(--bg-glass-hover);
        color: var(--nav-tint, var(--text-muted));
    }
    .sidebar-link:hover .nav-icon-wrap {
        background: var(--nav-tint-soft, var(--bg-glass-light));
        color: var(--nav-tint, var(--text-primary));
    }
    .sidebar-link.active {
        background: var(--nav-tint-soft, var(--sidebar-active-bg));
        color: var(--nav-tint, var(--sidebar-active-text));
        font-weight: 600;
    }
    .sidebar-link.active .nav-icon-wrap {
        background: transparent;
        color: var(--nav-tint, var(--sidebar-active-text));
    }
    .sidebar-link.active::after {
        content: ''; position: absolute;
        left: -0.875rem; top: 50%; transform: translateY(-50%);
        width: 3px; height: 18px; border-radius: 0 3px 3px 0;
        background: var(--nav-tint, var(--sidebar-active-text));
    }
    .sidebar-link .nav-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-link > i {
        font-size: 0.85rem;
        width: 1.25rem;
        text-align: center;
        flex-shrink: 0;
        transition: transform 0.2s;
        color: inherit;
    }
    .sidebar-link.active > i { color: var(--sidebar-active-text); }
    .sidebar-link:hover > i { transform: scale(1.08); }

    .bg-mesh { display: none; }

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
        border-radius: 0.5rem;
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
        background: #6d28d9;
        box-shadow: 0 2px 4px rgba(0,0,0,0.12);
        transform: translateY(-1px);
    }
    .btn-primary:hover::before { opacity: 0; }
    .btn-primary:active { transform: translateY(0); }
    html.light-mode .btn-primary {
        box-shadow: 0 1px 2px rgba(109,40,217,0.20), inset 0 1px 0 rgba(255,255,255,0.18);
    }
    html.light-mode .btn-primary:hover {
        background: #5b21b6;
        box-shadow: 0 4px 12px -2px rgba(109,40,217,0.35), inset 0 1px 0 rgba(255,255,255,0.18);
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
        border-radius: 0.5rem;
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

    html.light-mode select option { background-color: #ffffff !important; color: #0f172a !important; }

    .theme-toggle-btn {
        position: relative;
        width: 2.75rem;
        height: 1.5rem;
        border-radius: 9999px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        display: flex;
        align-items: center;
        padding: 0 0.1875rem;
    }
    .theme-toggle-btn:hover {
        border-color: rgba(124,58,237,0.3);
        box-shadow: 0 0 16px rgba(124,58,237,0.1);
    }
    .theme-toggle-btn .toggle-knob {
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #7c3aed, #8b5cf6);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.55rem;
        color: white;
        box-shadow: 0 2px 10px rgba(124,58,237,0.4);
    }
    html.light-mode .theme-toggle-btn .toggle-knob {
        transform: translateX(1.25rem);
        background: linear-gradient(135deg, #f59e0b, #f97316);
        box-shadow: 0 2px 10px rgba(245,158,11,0.4);
    }

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
        background: linear-gradient(135deg, rgba(124,58,237,0.10), rgba(168,85,247,0.04));
        border: 1px solid rgba(124,58,237,0.18);
    }
    html.light-mode .upgrade-card {
        background: linear-gradient(135deg, #f5f0ff, #fafaff);
        border-color: #e6d9ff;
    }
    .upgrade-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 100%; height: 100%;
        background: radial-gradient(circle, rgba(124,58,237,0.1), transparent 70%);
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
        0%, 100% { box-shadow: 0 0 20px rgba(124,58,237,0.15); }
        50% { box-shadow: 0 0 40px rgba(124,58,237,0.25); }
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
        background: rgba(124,58,237,0.3);
        border-radius: 50%;
        animation: float-particle linear infinite;
    }
    html.light-mode .particle {
        background: rgba(124,58,237,0.15);
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
</style>
<script>
(function(){
    var css = `
        html.light-mode [class*="text-white"]:not([class*="bg-purple-"]):not([class*="bg-emerald-"]):not([class*="bg-red-"]):not([class*="bg-gradient"]):not(.toggle-knob):not([class*="bg-amber-"]):not([class*="bg-blue-"]):not([class*="bg-green-"]):not([class*="bg-pink-"]):not([class*="bg-yellow-"]):not([class*="bg-cyan-"]):not([class*="bg-indigo-"]):not(.btn-primary) {
            color: var(--text-primary) !important;
        }
        html.light-mode [class*="text-white/"]:not([class*="bg-purple-"]):not([class*="bg-emerald-"]):not([class*="bg-gradient"]):not(.toggle-knob):not(.btn-primary) {
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
        html.light-mode [class*="text-gray-3"],
        html.light-mode [class*="text-gray-4"] {
            color: var(--text-muted) !important;
        }
        html.light-mode [class*="text-gray-5"],
        html.light-mode [class*="text-gray-6"] {
            color: var(--text-dimmed) !important;
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
        html.light-mode input:not([type="color"]):not([type="checkbox"]):not([type="radio"]),
        html.light-mode textarea,
        html.light-mode select {
            color: var(--text-primary) !important;
        }
        html.light-mode input::placeholder,
        html.light-mode textarea::placeholder {
            color: var(--text-dimmed) !important;
        }
        html.light-mode .glass {
            background: var(--bg-glass) !important;
            border-color: var(--border-glass) !important;
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
            violet: 'rgba(139,92,246,', purple: 'rgba(168,85,247,',
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
