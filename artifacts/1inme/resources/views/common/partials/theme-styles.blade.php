<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved === 'light') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        --bg-body: #06010f;
        --bg-sidebar: rgba(8,3,20,0.75);
        --bg-sidebar-mobile: rgba(8,3,20,0.97);
        --bg-header: rgba(8,3,20,0.55);
        --bg-glass: rgba(255,255,255,0.035);
        --bg-glass-light: rgba(255,255,255,0.055);
        --bg-glass-hover: rgba(255,255,255,0.08);
        --bg-glass-input: rgba(255,255,255,0.045);
        --bg-glass-input-focus: rgba(255,255,255,0.08);
        --bg-card: rgba(255,255,255,0.028);
        --bg-card-hover: rgba(255,255,255,0.055);
        --border-glass: rgba(255,255,255,0.07);
        --border-glass-light: rgba(255,255,255,0.1);
        --border-subtle: rgba(255,255,255,0.045);
        --text-primary: #f0edf6;
        --text-secondary: rgba(240,237,246,0.78);
        --text-muted: rgba(240,237,246,0.5);
        --text-dimmed: rgba(240,237,246,0.42);
        --text-faint: rgba(240,237,246,0.28);
        --text-label: rgba(240,237,246,0.1);
        --sidebar-link: rgba(240,237,246,0.45);
        --sidebar-link-hover-bg: rgba(255,255,255,0.055);
        --sidebar-link-hover-text: rgba(240,237,246,0.88);
        --sidebar-active-bg: rgba(139,92,246,0.1);
        --sidebar-active-border: rgba(139,92,246,0.2);
        --sidebar-active-text: #a78bfa;
        --accent: #8b5cf6;
        --accent-light: #a78bfa;
        --accent-glow: rgba(139,92,246,0.5);
        --glow-1: rgba(139,92,246,0.08);
        --glow-2: rgba(168,85,247,0.05);
        --scrollbar-thumb: rgba(255,255,255,0.08);
        --scrollbar-thumb-hover: rgba(255,255,255,0.15);
        --overlay-bg: rgba(0,0,0,0.75);
        --card-shadow: 0 8px 32px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.03);
        --noise-opacity: 0.018;
    }

    html.light-mode {
        --bg-body: #f0edf6;
        --bg-sidebar: rgba(255,255,255,0.85);
        --bg-sidebar-mobile: rgba(255,255,255,0.98);
        --bg-header: rgba(255,255,255,0.72);
        --bg-glass: rgba(255,255,255,0.55);
        --bg-glass-light: rgba(255,255,255,0.7);
        --bg-glass-hover: rgba(255,255,255,0.85);
        --bg-glass-input: rgba(0,0,0,0.04);
        --bg-glass-input-focus: rgba(0,0,0,0.06);
        --bg-card: rgba(255,255,255,0.72);
        --bg-card-hover: rgba(255,255,255,0.88);
        --border-glass: rgba(124,58,237,0.08);
        --border-glass-light: rgba(124,58,237,0.12);
        --border-subtle: rgba(0,0,0,0.05);
        --text-primary: #1a1025;
        --text-secondary: rgba(26,16,37,0.82);
        --text-muted: rgba(26,16,37,0.78);
        --text-dimmed: rgba(26,16,37,0.7);
        --text-faint: rgba(26,16,37,0.62);
        --text-label: rgba(26,16,37,0.55);
        --sidebar-link: rgba(26,16,37,0.48);
        --sidebar-link-hover-bg: rgba(139,92,246,0.06);
        --sidebar-link-hover-text: rgba(26,16,37,0.88);
        --sidebar-active-bg: rgba(139,92,246,0.08);
        --sidebar-active-border: rgba(139,92,246,0.18);
        --sidebar-active-text: #7c3aed;
        --accent: #7c3aed;
        --accent-light: #8b5cf6;
        --accent-glow: rgba(124,58,237,0.3);
        --glow-1: rgba(139,92,246,0.04);
        --glow-2: rgba(168,85,247,0.03);
        --scrollbar-thumb: rgba(0,0,0,0.1);
        --scrollbar-thumb-hover: rgba(0,0,0,0.18);
        --overlay-bg: rgba(0,0,0,0.25);
        --card-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 32px rgba(124,58,237,0.06), inset 0 1px 0 rgba(255,255,255,0.8);
        --noise-opacity: 0.006;
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
        backdrop-filter: blur(40px) saturate(1.4);
        -webkit-backdrop-filter: blur(40px) saturate(1.4);
        border: 1px solid var(--border-glass);
        box-shadow: var(--card-shadow);
    }
    .glass-light {
        background: var(--bg-glass-light);
        backdrop-filter: blur(20px) saturate(1.2);
        -webkit-backdrop-filter: blur(20px) saturate(1.2);
        border: 1px solid var(--border-glass-light);
    }
    .glass-hover:hover { background: var(--bg-glass-hover); }

    .card-premium {
        position: relative;
        background: var(--bg-card);
        backdrop-filter: blur(40px) saturate(1.3);
        -webkit-backdrop-filter: blur(40px) saturate(1.3);
        border: 1px solid var(--border-glass);
        border-radius: 1.25rem;
        box-shadow: var(--card-shadow);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .card-premium::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        padding: 1px;
        background: linear-gradient(135deg, rgba(139,92,246,0.15), transparent 40%, transparent 60%, rgba(168,85,247,0.08));
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.4s;
    }
    .card-premium:hover {
        background: var(--bg-card-hover);
        transform: translateY(-3px);
        box-shadow: 0 16px 48px rgba(0,0,0,0.35), 0 0 40px rgba(139,92,246,0.06);
        border-color: rgba(139,92,246,0.12);
    }
    .card-premium:hover::before { opacity: 1; }

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
        backdrop-filter: blur(40px) saturate(1.3);
        -webkit-backdrop-filter: blur(40px) saturate(1.3);
        border: 1px solid var(--border-glass);
        border-radius: 1.25rem;
        padding: 1.25rem 1.5rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: var(--stat-accent, linear-gradient(90deg, #8b5cf6, #a78bfa));
        border-radius: 1.25rem 1.25rem 0 0;
        transition: height 0.4s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
    }
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 60px;
        background: var(--stat-accent, linear-gradient(180deg, rgba(139,92,246,0.06), transparent));
        opacity: 0;
        transition: opacity 0.4s;
        pointer-events: none;
    }
    .stat-card:hover {
        transform: translateY(-4px) scale(1.01);
        background: var(--bg-card-hover);
        box-shadow: 0 16px 48px rgba(0,0,0,0.3), 0 0 30px var(--stat-glow, rgba(139,92,246,0.08));
        border-color: var(--stat-border-color, rgba(139,92,246,0.15));
    }
    .stat-card:hover::before { height: 3px; }
    .stat-card:hover::after { opacity: 1; }

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

    .bg-mesh {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }
    .bg-mesh::before {
        content: '';
        position: absolute;
        width: 130%;
        height: 130%;
        top: -15%;
        left: -15%;
        background:
            radial-gradient(ellipse 800px 600px at 20% 15%, rgba(139,92,246,0.12), transparent),
            radial-gradient(ellipse 600px 400px at 80% 80%, rgba(168,85,247,0.08), transparent),
            radial-gradient(ellipse 500px 350px at 50% 5%, rgba(99,102,241,0.06), transparent),
            radial-gradient(ellipse 400px 300px at 70% 30%, rgba(236,72,153,0.04), transparent);
        animation: aurora 25s ease-in-out infinite alternate;
    }
    .bg-mesh::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        background:
            radial-gradient(circle 200px at 20% 70%, rgba(139,92,246,0.06), transparent),
            radial-gradient(circle 150px at 75% 20%, rgba(168,85,247,0.04), transparent);
        animation: aurora 30s ease-in-out infinite alternate-reverse;
    }
    html.light-mode .bg-mesh::before {
        background:
            radial-gradient(ellipse 800px 600px at 20% 15%, rgba(139,92,246,0.06), transparent),
            radial-gradient(ellipse 600px 400px at 80% 80%, rgba(168,85,247,0.04), transparent),
            radial-gradient(ellipse 500px 350px at 50% 5%, rgba(99,102,241,0.03), transparent),
            radial-gradient(ellipse 400px 300px at 70% 30%, rgba(236,72,153,0.02), transparent);
    }
    html.light-mode .bg-mesh::after {
        background:
            radial-gradient(circle 200px at 20% 70%, rgba(139,92,246,0.03), transparent),
            radial-gradient(circle 150px at 75% 20%, rgba(168,85,247,0.02), transparent);
    }

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

    .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-glass-input);
        color: var(--text-muted);
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.75rem;
        border: 1px solid var(--border-glass);
        transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
        backdrop-filter: blur(10px);
    }
    .btn-ghost:hover {
        background: var(--bg-glass-hover);
        color: var(--text-primary);
        border-color: rgba(139,92,246,0.2);
        box-shadow: 0 0 20px rgba(139,92,246,0.06);
        transform: translateY(-1px);
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
        backdrop-filter: blur(10px);
    }

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
        border-radius: 0.75rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        outline: none;
        backdrop-filter: blur(10px);
    }
    .theme-input:focus {
        background: var(--bg-glass-input-focus);
        border-color: rgba(139,92,246,0.5);
        box-shadow: 0 0 0 3px rgba(139,92,246,0.1), 0 0 20px rgba(139,92,246,0.06);
    }
    .theme-input::placeholder { color: var(--text-dimmed); }

    html.light-mode select option { background-color: #f0edf6 !important; color: #1a1025 !important; }

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
