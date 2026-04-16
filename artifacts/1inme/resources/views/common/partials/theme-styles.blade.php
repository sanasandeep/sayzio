<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved === 'light') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        --bg-body: #0f0f11;
        --bg-sidebar: #131316;
        --bg-sidebar-mobile: #131316;
        --bg-header: #131316;
        --bg-glass: #1a1a1d;
        --bg-glass-light: #1f1f23;
        --bg-glass-hover: #24242a;
        --bg-glass-input: #1a1a1d;
        --bg-glass-input-focus: #22222a;
        --bg-card: #17171a;
        --bg-card-hover: #1b1b1f;
        --border-glass: #26262c;
        --border-glass-light: #2d2d34;
        --border-subtle: #1f1f24;
        --text-primary: #f5f5f7;
        --text-secondary: #d4d4d8;
        --text-muted: #a1a1aa;
        --text-dimmed: #71717a;
        --text-faint: #52525b;
        --text-label: #71717a;
        --sidebar-link: #a1a1aa;
        --sidebar-link-hover-bg: #1f1f23;
        --sidebar-link-hover-text: #f5f5f7;
        --sidebar-active-bg: rgba(139,92,246,0.14);
        --sidebar-active-border: rgba(139,92,246,0.28);
        --sidebar-active-text: #a78bfa;
        --accent: #8b5cf6;
        --accent-light: #a78bfa;
        --accent-glow: rgba(139,92,246,0.35);
        --glow-1: rgba(139,92,246,0);
        --glow-2: rgba(168,85,247,0);
        --scrollbar-thumb: #2d2d34;
        --scrollbar-thumb-hover: #3f3f46;
        --overlay-bg: rgba(0,0,0,0.7);
        --card-shadow: 0 1px 2px rgba(0,0,0,0.3);
        --noise-opacity: 0;

        /* Multi-color accent palette (used for stat cards, badges, tints) */
        --c-primary:   #8b5cf6;  --c-primary-soft:   rgba(139,92,246,0.12);
        --c-success:   #10b981;  --c-success-soft:   rgba(16,185,129,0.14);
        --c-info:      #06b6d4;  --c-info-soft:      rgba(6,182,212,0.14);
        --c-warning:   #f59e0b;  --c-warning-soft:   rgba(245,158,11,0.14);
        --c-danger:    #ef4444;  --c-danger-soft:    rgba(239,68,68,0.14);
        --c-pink:      #ec4899;  --c-pink-soft:      rgba(236,72,153,0.14);
        --c-indigo:    #6366f1;  --c-indigo-soft:    rgba(99,102,241,0.14);
        --c-teal:      #14b8a6;  --c-teal-soft:      rgba(20,184,166,0.14);
    }

    html.light-mode {
        --bg-body: #f5f5f7;
        --bg-sidebar: #ffffff;
        --bg-sidebar-mobile: #ffffff;
        --bg-header: #ffffff;
        --bg-glass: #ffffff;
        --bg-glass-light: #ffffff;
        --bg-glass-hover: #fafafa;
        --bg-glass-input: #ffffff;
        --bg-glass-input-focus: #ffffff;
        --bg-card: #ffffff;
        --bg-card-hover: #ffffff;
        --border-glass: #e5e7eb;
        --border-glass-light: #e5e7eb;
        --border-subtle: #f1f2f4;
        --text-primary: #0f172a;
        --text-secondary: #334155;
        --text-muted: #64748b;
        --text-dimmed: #94a3b8;
        --text-faint: #cbd5e1;
        --text-label: #94a3b8;
        --sidebar-link: #475569;
        --sidebar-link-hover-bg: #f4f4f5;
        --sidebar-link-hover-text: #0f172a;
        --sidebar-active-bg: rgba(124,58,237,0.08);
        --sidebar-active-border: rgba(124,58,237,0.18);
        --sidebar-active-text: #7c3aed;
        --accent: #7c3aed;
        --accent-light: #8b5cf6;
        --accent-glow: rgba(124,58,237,0.18);
        --glow-1: rgba(139,92,246,0);
        --glow-2: rgba(168,85,247,0);
        --scrollbar-thumb: #d4d4d8;
        --scrollbar-thumb-hover: #a1a1aa;
        --overlay-bg: rgba(15,23,42,0.28);
        --card-shadow: 0 1px 2px rgba(15,23,42,0.04);
        --noise-opacity: 0;

        --c-primary:   #7c3aed;  --c-primary-soft:   #f3efff;
        --c-success:   #10b981;  --c-success-soft:   #e6f7f0;
        --c-info:      #06b6d4;  --c-info-soft:      #e0f7fb;
        --c-warning:   #f59e0b;  --c-warning-soft:   #fef4e0;
        --c-danger:    #ef4444;  --c-danger-soft:    #fde9e9;
        --c-pink:      #ec4899;  --c-pink-soft:      #fdeaf3;
        --c-indigo:    #6366f1;  --c-indigo-soft:    #eceffe;
        --c-teal:      #14b8a6;  --c-teal-soft:      #e2f7f4;
    }

    [x-cloak] { display: none !important; }

    body {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.5s cubic-bezier(0.4,0,0.2,1), color 0.3s ease;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden;
    }

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
        border-radius: 0.875rem;
    }
    .glass-light {
        background: var(--bg-glass-light);
        border: 1px solid var(--border-glass-light);
        border-radius: 0.875rem;
    }
    .glass-hover:hover { background: var(--bg-glass-hover); }

    .card-premium {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 0.875rem;
        box-shadow: var(--card-shadow);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        overflow: hidden;
    }
    .card-premium::before { display: none; }
    .card-premium:hover {
        background: var(--bg-card-hover);
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        border-color: var(--border-glass-light);
    }
    html.light-mode .card-premium:hover {
        box-shadow: 0 2px 4px rgba(15,23,42,0.04), 0 8px 24px rgba(15,23,42,0.06);
        border-color: #d4d4d8;
    }

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
        background: linear-gradient(135deg, rgba(139,92,246,0.4), rgba(168,85,247,0.15), rgba(139,92,246,0.05));
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
        border: 1px solid var(--border-glass);
        border-radius: 0.875rem;
        padding: 1.25rem 1.5rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--stat-accent, var(--c-primary));
        transition: none;
    }
    .stat-card::after { display: none; }
    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        border-color: var(--border-glass-light);
    }
    html.light-mode .stat-card:hover {
        box-shadow: 0 2px 4px rgba(15,23,42,0.04), 0 8px 24px rgba(15,23,42,0.06);
        border-color: #d4d4d8;
    }
    /* Multi-color stat card variants */
    .stat-card.tint-primary  { --stat-accent: var(--c-primary); }
    .stat-card.tint-success  { --stat-accent: var(--c-success); }
    .stat-card.tint-info     { --stat-accent: var(--c-info); }
    .stat-card.tint-warning  { --stat-accent: var(--c-warning); }
    .stat-card.tint-danger   { --stat-accent: var(--c-danger); }
    .stat-card.tint-pink     { --stat-accent: var(--c-pink); }
    .stat-card.tint-indigo   { --stat-accent: var(--c-indigo); }
    .stat-card.tint-teal     { --stat-accent: var(--c-teal); }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.45rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.75rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        color: var(--sidebar-link);
        letter-spacing: 0.01em;
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
        border: 1px solid var(--sidebar-active-border);
        box-shadow: 0 0 20px rgba(139,92,246,0.06);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 20px;
        background: linear-gradient(180deg, #8b5cf6, #a78bfa);
        border-radius: 0 4px 4px 0;
    }
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
    .sidebar-link:hover .nav-icon-wrap {
        transform: scale(1.08);
    }
    .sidebar-link.active .nav-icon-wrap {
        background: rgba(139,92,246,0.15);
        box-shadow: 0 0 12px rgba(139,92,246,0.15);
        color: var(--sidebar-active-text);
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
        gap: 0.5rem;
        padding: 0.5rem 1.25rem;
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 20px rgba(139,92,246,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        letter-spacing: 0.01em;
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
        box-shadow: 0 8px 32px rgba(139,92,246,0.4), 0 0 60px rgba(139,92,246,0.12);
        transform: translateY(-2px);
    }
    .btn-primary:hover::before { opacity: 1; }
    .btn-primary:active { transform: translateY(0); }
    html.light-mode .btn-primary {
        box-shadow: 0 1px 2px rgba(15,15,17,0.08), 0 4px 12px rgba(124,58,237,0.22);
    }
    html.light-mode .btn-primary:hover {
        box-shadow: 0 2px 6px rgba(15,15,17,0.1), 0 8px 20px rgba(124,58,237,0.28);
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
        border-color: rgba(139,92,246,0.3);
        box-shadow: 0 0 16px rgba(139,92,246,0.1);
    }
    .theme-toggle-btn .toggle-knob {
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #8b5cf6, #a78bfa);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.55rem;
        color: white;
        box-shadow: 0 2px 10px rgba(139,92,246,0.4);
    }
    html.light-mode .theme-toggle-btn .toggle-knob {
        transform: translateX(1.25rem);
        background: linear-gradient(135deg, #f59e0b, #f97316);
        box-shadow: 0 2px 10px rgba(245,158,11,0.4);
    }

    .gradient-text {
        background: linear-gradient(135deg, #c084fc, #8b5cf6, #6366f1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    html.light-mode .gradient-text {
        background: linear-gradient(135deg, #7c3aed, #6d28d9, #4f46e5);
        -webkit-background-clip: text;
        background-clip: text;
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

    .shimmer {
        position: relative;
        overflow: hidden;
    }
    .shimmer::after {
        content: '';
        position: absolute;
        top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.04), transparent);
        animation: shimmer 4s ease-in-out infinite;
    }

    .upgrade-card {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(139,92,246,0.12), rgba(168,85,247,0.06));
        border: 1px solid rgba(139,92,246,0.15);
    }
    .upgrade-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 100%; height: 100%;
        background: radial-gradient(circle, rgba(139,92,246,0.1), transparent 70%);
        animation: float-slow 15s ease-in-out infinite;
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
        0%, 100% { box-shadow: 0 0 20px rgba(139,92,246,0.15); }
        50% { box-shadow: 0 0 40px rgba(139,92,246,0.25); }
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
        background: rgba(139,92,246,0.3);
        border-radius: 50%;
        animation: float-particle linear infinite;
    }
    html.light-mode .particle {
        background: rgba(139,92,246,0.15);
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
</script>
