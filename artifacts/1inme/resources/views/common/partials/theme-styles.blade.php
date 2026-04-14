<script>
(function(){
    const saved = localStorage.getItem('1inme_theme');
    if(saved === 'light') document.documentElement.classList.add('light-mode');
})();
</script>
<style>
    :root {
        --bg-body: #0f0a1a;
        --bg-sidebar: rgba(15,10,26,0.8);
        --bg-sidebar-mobile: rgba(15,10,26,0.95);
        --bg-header: rgba(15,10,26,0.6);
        --bg-glass: rgba(255,255,255,0.04);
        --bg-glass-light: rgba(255,255,255,0.06);
        --bg-glass-hover: rgba(255,255,255,0.08);
        --bg-glass-input: rgba(255,255,255,0.06);
        --bg-glass-input-focus: rgba(255,255,255,0.1);
        --border-glass: rgba(255,255,255,0.08);
        --border-glass-light: rgba(255,255,255,0.1);
        --border-subtle: rgba(255,255,255,0.05);
        --text-primary: #ffffff;
        --text-secondary: rgba(255,255,255,0.8);
        --text-muted: rgba(255,255,255,0.5);
        --text-dimmed: rgba(255,255,255,0.3);
        --text-faint: rgba(255,255,255,0.2);
        --text-label: rgba(255,255,255,0.1);
        --sidebar-link: rgba(255,255,255,0.5);
        --sidebar-link-hover-bg: rgba(255,255,255,0.06);
        --sidebar-link-hover-text: rgba(255,255,255,0.9);
        --sidebar-active-bg: rgba(124,58,237,0.2);
        --sidebar-active-border: rgba(124,58,237,0.3);
        --sidebar-active-text: #a855f7;
        --glow-1: rgba(124,58,237,0.08);
        --glow-2: rgba(168,85,247,0.06);
        --scrollbar-thumb: rgba(255,255,255,0.1);
        --scrollbar-thumb-hover: rgba(255,255,255,0.2);
        --overlay-bg: rgba(0,0,0,0.6);
        --card-shadow: 0 4px 24px rgba(0,0,0,0.3);
    }

    html.light-mode {
        --bg-body: #f0eef5;
        --bg-sidebar: rgba(255,255,255,0.85);
        --bg-sidebar-mobile: rgba(255,255,255,0.97);
        --bg-header: rgba(255,255,255,0.7);
        --bg-glass: rgba(255,255,255,0.6);
        --bg-glass-light: rgba(255,255,255,0.7);
        --bg-glass-hover: rgba(255,255,255,0.8);
        --bg-glass-input: rgba(0,0,0,0.04);
        --bg-glass-input-focus: rgba(0,0,0,0.06);
        --border-glass: rgba(0,0,0,0.08);
        --border-glass-light: rgba(0,0,0,0.1);
        --border-subtle: rgba(0,0,0,0.06);
        --text-primary: #1a1025;
        --text-secondary: rgba(26,16,37,0.85);
        --text-muted: rgba(26,16,37,0.55);
        --text-dimmed: rgba(26,16,37,0.4);
        --text-faint: rgba(26,16,37,0.2);
        --text-label: rgba(26,16,37,0.12);
        --sidebar-link: rgba(26,16,37,0.5);
        --sidebar-link-hover-bg: rgba(124,58,237,0.06);
        --sidebar-link-hover-text: rgba(26,16,37,0.9);
        --sidebar-active-bg: rgba(124,58,237,0.1);
        --sidebar-active-border: rgba(124,58,237,0.25);
        --sidebar-active-text: #7c3aed;
        --glow-1: rgba(124,58,237,0.04);
        --glow-2: rgba(168,85,247,0.03);
        --scrollbar-thumb: rgba(0,0,0,0.12);
        --scrollbar-thumb-hover: rgba(0,0,0,0.2);
        --overlay-bg: rgba(0,0,0,0.3);
        --card-shadow: 0 4px 24px rgba(0,0,0,0.08);
    }

    [x-cloak] { display: none !important; }
    body { font-family: 'Space Grotesk', system-ui, sans-serif; background: var(--bg-body); color: var(--text-primary); transition: background 0.3s, color 0.3s; }
    .glass { background: var(--bg-glass); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-glass); }
    .glass-light { background: var(--bg-glass-light); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--border-glass-light); }
    .glass-hover:hover { background: var(--bg-glass-hover); }
    .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 1rem; font-size: 0.875rem; border-radius: 0.75rem; transition: all 0.2s; color: var(--sidebar-link); }
    .sidebar-link:hover { background: var(--sidebar-link-hover-bg); color: var(--sidebar-link-hover-text); }
    .sidebar-link.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); border: 1px solid var(--sidebar-active-border); }
    .sidebar-link.active i { color: var(--sidebar-active-text); }
    .bg-glow { position: fixed; top: -200px; right: -200px; width: 500px; height: 500px; background: radial-gradient(circle, var(--glow-1) 0%, transparent 70%); pointer-events: none; z-index: 0; }
    .bg-glow-2 { position: fixed; bottom: -200px; left: -200px; width: 400px; height: 400px; background: radial-gradient(circle, var(--glow-2) 0%, transparent 70%); pointer-events: none; z-index: 0; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--scrollbar-thumb-hover); }

    .theme-text-primary { color: var(--text-primary); }
    .theme-text-secondary { color: var(--text-secondary); }
    .theme-text-muted { color: var(--text-muted); }
    .theme-text-dimmed { color: var(--text-dimmed); }
    .theme-text-faint { color: var(--text-faint); }
    .theme-border { border-color: var(--border-subtle); }
    .theme-input { background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary); }
    .theme-input:focus { background: var(--bg-glass-input-focus); border-color: var(--sidebar-active-border); }
    .theme-input::placeholder { color: var(--text-dimmed); }

    html.light-mode select option { background-color: #f0eef5 !important; color: #1a1025 !important; }

    .theme-toggle-btn {
        position: relative;
        width: 3.25rem;
        height: 1.75rem;
        border-radius: 9999px;
        background: var(--bg-glass-light);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        padding: 0 0.25rem;
    }
    .theme-toggle-btn .toggle-knob {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #7c3aed, #a855f7);
        transition: transform 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        color: white;
        box-shadow: 0 2px 8px rgba(124,58,237,0.4);
    }
    html.light-mode .theme-toggle-btn .toggle-knob {
        transform: translateX(1.5rem);
        background: linear-gradient(135deg, #f59e0b, #f97316);
        box-shadow: 0 2px 8px rgba(245,158,11,0.4);
    }
</style>
<script>
(function(){
    var css = `
        html.light-mode [class*="text-white"]:not([class*="bg-purple-"]):not([class*="bg-emerald-"]):not([class*="bg-red-"]):not([class*="bg-gradient"]):not(.toggle-knob):not([class*="bg-amber-"]):not([class*="bg-blue-"]):not([class*="bg-green-"]):not([class*="bg-pink-"]):not([class*="bg-yellow-"]):not([class*="bg-cyan-"]) {
            color: var(--text-primary) !important;
        }
        html.light-mode [class*="text-white/"]:not([class*="bg-purple-"]):not([class*="bg-emerald-"]):not([class*="bg-gradient"]):not(.toggle-knob) {
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
        html.light-mode [class*="bg-[#0f0a1a]"] {
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
