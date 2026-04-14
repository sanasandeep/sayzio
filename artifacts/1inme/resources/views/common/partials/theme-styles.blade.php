<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved === 'light') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        --bg-body: #0a0612;
        --bg-sidebar: rgba(10,6,18,0.85);
        --bg-sidebar-mobile: rgba(10,6,18,0.97);
        --bg-header: rgba(10,6,18,0.65);
        --bg-glass: rgba(255,255,255,0.03);
        --bg-glass-light: rgba(255,255,255,0.05);
        --bg-glass-hover: rgba(255,255,255,0.07);
        --bg-glass-input: rgba(255,255,255,0.05);
        --bg-glass-input-focus: rgba(255,255,255,0.08);
        --bg-card: rgba(255,255,255,0.025);
        --bg-card-hover: rgba(255,255,255,0.05);
        --border-glass: rgba(255,255,255,0.06);
        --border-glass-light: rgba(255,255,255,0.08);
        --border-subtle: rgba(255,255,255,0.04);
        --text-primary: #f0edf6;
        --text-secondary: rgba(240,237,246,0.78);
        --text-muted: rgba(240,237,246,0.5);
        --text-dimmed: rgba(240,237,246,0.42);
        --text-faint: rgba(240,237,246,0.28);
        --text-label: rgba(240,237,246,0.1);
        --sidebar-link: rgba(240,237,246,0.45);
        --sidebar-link-hover-bg: rgba(255,255,255,0.05);
        --sidebar-link-hover-text: rgba(240,237,246,0.85);
        --sidebar-active-bg: rgba(139,92,246,0.12);
        --sidebar-active-border: rgba(139,92,246,0.2);
        --sidebar-active-text: #a78bfa;
        --accent: #8b5cf6;
        --accent-light: #a78bfa;
        --accent-glow: rgba(139,92,246,0.4);
        --glow-1: rgba(139,92,246,0.06);
        --glow-2: rgba(168,85,247,0.04);
        --scrollbar-thumb: rgba(255,255,255,0.08);
        --scrollbar-thumb-hover: rgba(255,255,255,0.15);
        --overlay-bg: rgba(0,0,0,0.7);
        --card-shadow: 0 4px 32px rgba(0,0,0,0.4);
        --noise-opacity: 0.015;
    }

    html.light-mode {
        --bg-body: #f4f2f8;
        --bg-sidebar: rgba(255,255,255,0.92);
        --bg-sidebar-mobile: rgba(255,255,255,0.98);
        --bg-header: rgba(255,255,255,0.78);
        --bg-glass: rgba(255,255,255,0.65);
        --bg-glass-light: rgba(255,255,255,0.75);
        --bg-glass-hover: rgba(255,255,255,0.85);
        --bg-glass-input: rgba(0,0,0,0.03);
        --bg-glass-input-focus: rgba(0,0,0,0.05);
        --bg-card: rgba(255,255,255,0.7);
        --bg-card-hover: rgba(255,255,255,0.85);
        --border-glass: rgba(0,0,0,0.06);
        --border-glass-light: rgba(0,0,0,0.08);
        --border-subtle: rgba(0,0,0,0.04);
        --text-primary: #1a1025;
        --text-secondary: rgba(26,16,37,0.82);
        --text-muted: rgba(26,16,37,0.52);
        --text-dimmed: rgba(26,16,37,0.48);
        --text-faint: rgba(26,16,37,0.3);
        --text-label: rgba(26,16,37,0.1);
        --sidebar-link: rgba(26,16,37,0.48);
        --sidebar-link-hover-bg: rgba(139,92,246,0.06);
        --sidebar-link-hover-text: rgba(26,16,37,0.88);
        --sidebar-active-bg: rgba(139,92,246,0.08);
        --sidebar-active-border: rgba(139,92,246,0.18);
        --sidebar-active-text: #7c3aed;
        --accent: #7c3aed;
        --accent-light: #8b5cf6;
        --accent-glow: rgba(124,58,237,0.3);
        --glow-1: rgba(139,92,246,0.03);
        --glow-2: rgba(168,85,247,0.02);
        --scrollbar-thumb: rgba(0,0,0,0.1);
        --scrollbar-thumb-hover: rgba(0,0,0,0.18);
        --overlay-bg: rgba(0,0,0,0.25);
        --card-shadow: 0 4px 24px rgba(0,0,0,0.06);
        --noise-opacity: 0.008;
    }

    [x-cloak] { display: none !important; }

    body {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.4s ease, color 0.3s ease;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
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
        backdrop-filter: blur(24px) saturate(1.2);
        -webkit-backdrop-filter: blur(24px) saturate(1.2);
        border: 1px solid var(--border-glass);
        box-shadow: var(--card-shadow);
    }
    .glass-light {
        background: var(--bg-glass-light);
        backdrop-filter: blur(16px) saturate(1.1);
        -webkit-backdrop-filter: blur(16px) saturate(1.1);
        border: 1px solid var(--border-glass-light);
    }
    .glass-hover:hover { background: var(--bg-glass-hover); }

    .card-premium {
        background: var(--bg-card);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-premium:hover {
        background: var(--bg-card-hover);
        transform: translateY(-2px);
        box-shadow: 0 8px 40px rgba(0,0,0,0.3);
        border-color: var(--border-glass-light);
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
        background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(168,85,247,0.1), rgba(139,92,246,0.05));
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
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid var(--border-glass);
        border-radius: 1.25rem;
        padding: 1.25rem 1.5rem;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--stat-accent, linear-gradient(90deg, #8b5cf6, #a78bfa));
        border-radius: 1.25rem 1.25rem 0 0;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        background: var(--bg-card-hover);
        box-shadow: 0 12px 48px rgba(0,0,0,0.25);
    }
    .stat-card:hover::before { opacity: 1; }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.625rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        color: var(--sidebar-link);
        letter-spacing: 0.01em;
    }
    .sidebar-link:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }
    .sidebar-link.active {
        background: var(--sidebar-active-bg);
        color: var(--sidebar-active-text);
        border: 1px solid var(--sidebar-active-border);
    }
    .sidebar-link.active i { color: var(--sidebar-active-text); }
    .sidebar-link i {
        font-size: 0.8rem;
        width: 1.25rem;
        text-align: center;
    }

    .bg-mesh {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        background:
            radial-gradient(ellipse 600px 400px at 15% 20%, rgba(139,92,246,0.07), transparent),
            radial-gradient(ellipse 500px 350px at 85% 75%, rgba(168,85,247,0.05), transparent),
            radial-gradient(ellipse 400px 300px at 50% 10%, rgba(99,102,241,0.04), transparent);
    }
    html.light-mode .bg-mesh {
        background:
            radial-gradient(ellipse 600px 400px at 15% 20%, rgba(139,92,246,0.04), transparent),
            radial-gradient(ellipse 500px 350px at 85% 75%, rgba(168,85,247,0.03), transparent),
            radial-gradient(ellipse 400px 300px at 50% 10%, rgba(99,102,241,0.02), transparent);
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1.25rem;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 0.625rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(139,92,246,0.25);
        letter-spacing: 0.01em;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        box-shadow: 0 6px 24px rgba(139,92,246,0.35);
        transform: translateY(-1px);
    }

    .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-glass-input);
        color: var(--text-muted);
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.625rem;
        border: 1px solid var(--border-glass);
        transition: all 0.2s;
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

    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--scrollbar-thumb-hover); }

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
        border-radius: 0.625rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        transition: all 0.2s;
        outline: none;
    }
    .theme-input:focus {
        background: var(--bg-glass-input-focus);
        border-color: rgba(139,92,246,0.4);
        box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
    }
    .theme-input::placeholder { color: var(--text-dimmed); }

    html.light-mode select option { background-color: #f4f2f8 !important; color: #1a1025 !important; }

    .theme-toggle-btn {
        position: relative;
        width: 2.75rem;
        height: 1.5rem;
        border-radius: 9999px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        padding: 0 0.1875rem;
    }
    .theme-toggle-btn .toggle-knob {
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #8b5cf6, #a78bfa);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.55rem;
        color: white;
        box-shadow: 0 2px 8px rgba(139,92,246,0.35);
    }
    html.light-mode .theme-toggle-btn .toggle-knob {
        transform: translateX(1.25rem);
        background: linear-gradient(135deg, #f59e0b, #f97316);
        box-shadow: 0 2px 8px rgba(245,158,11,0.35);
    }

    @keyframes float-slow {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(20px, -15px) scale(1.05); }
        66% { transform: translate(-10px, 10px) scale(0.98); }
    }
    @keyframes shimmer {
        0% { background-position: -200% center; }
        100% { background-position: 200% center; }
    }
    .animate-float-slow { animation: float-slow 20s ease-in-out infinite; }
    .animate-float-slow-delay { animation: float-slow 25s ease-in-out infinite reverse; }
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
        html.light-mode [class*="bg-[#0a0612]"] {
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
