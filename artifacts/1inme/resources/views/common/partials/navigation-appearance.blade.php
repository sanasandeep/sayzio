@php
    $navigationDefaults = \App\Modules\Admin\Models\AppSetting::get('navigation_appearance', []);
    $navigationStorageKey = 'sayzio_navigation_' . (auth()->id() ?? 'guest');
@endphp
<script>
(function () {
    const defaults = @json($navigationDefaults);
    const key = @json($navigationStorageKey);
    const presets = {
        light: ['#ffffff','#172033','#566176'],
        dark: ['#111827','#f8fafc','#cbd5e1'],
        'gradient-light': ['linear-gradient(135deg,#edf4ff 0%,#f0eaff 55%,#e4f6f5 100%)','#172033','#49556c'],
        'gradient-medium': ['linear-gradient(135deg,#4f46b5 0%,#6751bb 55%,#296d88 100%)','#ffffff','#ede9fe'],
        'gradient-dark': ['linear-gradient(135deg,#111c38 0%,#2b1d48 55%,#12383c 100%)','#ffffff','#d9e1f2']
    };
    function read() { try { const value = JSON.parse(localStorage.getItem(key)); if (!value || typeof value !== 'object' || Array.isArray(value)) return {}; ['sidebar','topbar'].forEach(area => {if(value[area] === 'color') value[area] = 'gradient-medium';}); return value; } catch (e) { return {}; } }
    function mode(value) { return value === 'color' ? 'gradient-medium' : value; }
    function apply(prefs) {
        const root = document.documentElement;
        ['sidebar', 'topbar'].forEach(area => {
            const chosen = mode(prefs[area]);
            const fallback = mode(defaults[area]);
            const selected = chosen === 'theme' || presets[chosen] ? chosen : (fallback === 'theme' || presets[fallback] ? fallback : 'theme');
            root.dataset[area + 'Appearance'] = selected;
            const palette = presets[selected] || presets.light;
            root.dataset[area + 'Logo'] = ['dark','gradient-medium','gradient-dark'].includes(selected) ? 'dark' : 'light';
            ['surface','ink','muted'].forEach((token,index) => root.style.setProperty('--navigation-' + area + '-' + token,palette[index]));
        });
        root.dataset.topbarIncludesLogo = String(typeof prefs.logo === 'boolean' ? prefs.logo : defaults.logo === true);
    }
    window.sayzioNavigation = {
        read,
        save(prefs) { try { localStorage.setItem(key, JSON.stringify(prefs)); } catch (e) {} apply(prefs); },
        reset() { try { localStorage.removeItem(key); } catch (e) {} apply({}); }
    };
    apply(read());
    window.addEventListener('storage', e => { if (e.key === key || e.key === null) apply(read()); });
})();
</script>
<style>
html[data-sidebar-appearance="light"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="light"] .header-v2 { --nav-control:#fff;--nav-surface:#fff;--nav-ink:#172033;--nav-muted:#566176;--nav-line:#dce2ed;--nav-active:#e8edff;--nav-active-ink:#2948d5; }
html[data-sidebar-appearance="dark"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="dark"] .header-v2 { --nav-control:#111827;--nav-surface:#111827;--nav-ink:#f8fafc;--nav-muted:#b8c3d5;--nav-line:#334155;--nav-active:#293955;--nav-active-ink:#fff; }
html[data-sidebar-appearance^="gradient-"] :is(.sidebar-shell,.navigation-mobile) { --nav-control:#352f70;--nav-surface:var(--navigation-sidebar-surface);--nav-ink:var(--navigation-sidebar-ink);--nav-muted:var(--navigation-sidebar-muted);--nav-line:color-mix(in srgb,var(--nav-ink) 22%,transparent);--nav-active:color-mix(in srgb,var(--nav-ink) 14%,transparent);--nav-active-ink:var(--nav-ink); }
html[data-topbar-appearance^="gradient-"] .header-v2 { --nav-control:#352f70;--nav-surface:var(--navigation-topbar-surface);--nav-ink:var(--navigation-topbar-ink);--nav-muted:var(--navigation-topbar-muted);--nav-line:color-mix(in srgb,var(--nav-ink) 22%,transparent);--nav-active:color-mix(in srgb,var(--nav-ink) 14%,transparent);--nav-active-ink:var(--nav-ink); }
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance]:not([data-topbar-appearance="theme"]) .header-v2 {
    background:var(--nav-surface)!important;color:var(--nav-ink)!important;
    --bg-dropdown:var(--nav-control);--bg-card:var(--nav-control);--bg-card-hover:var(--nav-active);--sidebar-bg:var(--nav-surface);--sidebar-link:var(--nav-muted);--sidebar-link-hover-bg:var(--nav-active);--sidebar-link-hover-text:var(--nav-ink);--bg-sidebar:var(--nav-surface);--bg-sidebar-mobile:var(--nav-surface);--bg-header:var(--nav-surface);--text-primary:var(--nav-ink);--text-secondary:var(--nav-muted);--text-muted:var(--nav-muted);--text-dimmed:var(--nav-muted);--text-faint:var(--nav-muted);--border-subtle:var(--nav-line);--border-glass:var(--nav-line);--border-strong:var(--nav-line);--sidebar-active-bg:var(--nav-active);--sidebar-active-text:var(--nav-active-ink);
}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile) .sidebar-link { color:var(--nav-muted)!important; }
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile) .sidebar-link.active { color:var(--nav-active-ink)!important;background:var(--nav-active)!important; }
html[data-topbar-appearance]:not([data-topbar-appearance="theme"]) .header-v2 .header-icon-btn { background:var(--nav-active)!important;color:var(--nav-ink)!important;border-color:var(--nav-line)!important; }
.navigation-picker { position:fixed;right:20px;top:80px;z-index:100;width:min(360px,calc(100vw - 32px));padding:24px;border-radius:20px;background:var(--bg-dropdown);color:var(--text-primary);border:1px solid var(--border-strong);box-shadow:0 20px 60px #0003; }
.navigation-picker label { display:block;margin:16px 0;font-size:14px; }
.navigation-picker select { width:100%;margin-top:8px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--bg-card);color:var(--text-primary); }
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"])[data-sidebar-logo="dark"] :is(.sidebar-shell,.navigation-mobile) .brand-logo--light {display:none!important;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"])[data-sidebar-logo="dark"] :is(.sidebar-shell,.navigation-mobile) .brand-logo--dark {display:block!important;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"])[data-sidebar-logo="light"] :is(.sidebar-shell,.navigation-mobile) .brand-logo--light {display:block!important;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"])[data-sidebar-logo="light"] :is(.sidebar-shell,.navigation-mobile) .brand-logo--dark {display:none!important;}
html[data-sidebar-appearance="gradient-light"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="gradient-light"] .header-v2 {--nav-control:#f2f1fc;}
html[data-sidebar-appearance="gradient-dark"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="gradient-dark"] .header-v2 {--nav-control:#171f35;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile) .upgrade-card {background:var(--nav-control)!important;border:1px solid var(--nav-line)!important;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile) .sidebar-link.active :is(i,.nav-icon-wrap) {color:var(--nav-active-ink)!important;}
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile) .sidebar-link:not(.active) :is(i,.nav-icon-wrap) {color:var(--nav-muted)!important;}
:root {--navigation-theme-surface:var(--bg-header);--navigation-theme-ink:var(--text-primary);}
/* The edge toggle lives outside the logo wrapper, so resolve its palette explicitly. */
.sidebar-shell .sidebar-edge-toggle {
    --toggle-surface:var(--nav-control,var(--bg-card));
    --toggle-ink:var(--nav-ink,var(--text-primary));
    background:var(--toggle-surface)!important;
    color:var(--toggle-ink)!important;
    border-color:color-mix(in srgb,var(--toggle-ink) 22%,transparent)!important;
}
html[data-topbar-includes-logo="true"] .sidebar-edge-toggle {
    --toggle-surface:var(--navigation-topbar-surface);
    --toggle-ink:var(--navigation-topbar-ink);
}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"] .sidebar-edge-toggle {
    --toggle-surface:var(--navigation-theme-surface);
    --toggle-ink:var(--navigation-theme-ink);
}
.sidebar-shell .sidebar-edge-toggle:hover {
    filter:brightness(1.12);
}
html[data-topbar-includes-logo="true"] .navigation-logo {background:var(--navigation-topbar-surface)!important;--text-primary:var(--navigation-topbar-ink);border-color:color-mix(in srgb,var(--navigation-topbar-ink) 20%,transparent)!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"] .navigation-logo {background:var(--bg-header)!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="dark"] .navigation-logo .brand-logo--light {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="dark"] .navigation-logo .brand-logo--dark {display:block!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="light"] .navigation-logo .brand-logo--dark {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="light"] .navigation-logo .brand-logo--light {display:block!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"]:not(.light-mode) .navigation-logo .brand-logo--light {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"]:not(.light-mode) .navigation-logo .brand-logo--dark {display:block!important;}
</style>
