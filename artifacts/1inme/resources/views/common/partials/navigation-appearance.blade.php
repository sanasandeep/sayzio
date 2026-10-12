@php
    $navigationDefaults = \App\Modules\Admin\Models\AppSetting::get('navigation_appearance', []);
    $navigationStorageKey = 'sayzio_navigation_' . (auth()->id() ?? 'guest');
@endphp
<script>
(function () {
    const defaults = @json($navigationDefaults);
    const key = @json($navigationStorageKey);
    const modes = ['theme', 'light', 'dark', 'color'];
    function read() { try { return JSON.parse(localStorage.getItem(key)) || {}; } catch (e) { return {}; } }
    function validColor(value) { return /^#[0-9a-f]{6}$/i.test(value || ''); }
    function apply(prefs) {
        const root = document.documentElement;
        ['sidebar', 'topbar'].forEach(area => {
            const mode = modes.includes(prefs[area]) ? prefs[area] : (modes.includes(defaults[area]) ? defaults[area] : 'theme');
            root.dataset[area + 'Appearance'] = mode;
            const color = validColor(prefs[area + 'Color']) ? prefs[area + 'Color'] : (validColor(defaults.color) ? defaults.color : '#4338ca');
            root.style.setProperty('--navigation-' + area + '-color', color);
            const rgb = color.slice(1).match(/../g).map(v => parseInt(v, 16) / 255).map(v => v <= .04045 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4));
            const light = rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722 > .179;
            root.dataset[area + 'Logo'] = mode === 'light' || (mode === 'color' && light) ? 'light' : 'dark';
            root.style.setProperty('--navigation-' + area + '-ink', light ? '#111827' : '#ffffff');
            root.style.setProperty('--navigation-' + area + '-muted', light ? '#253047' : '#e2e8f0');
        });
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
html[data-sidebar-appearance="light"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="light"] .header-v2 { --nav-surface:#fff;--nav-ink:#172033;--nav-muted:#566176;--nav-line:#dce2ed;--nav-active:#e8edff;--nav-active-ink:#2948d5; }
html[data-sidebar-appearance="dark"] :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance="dark"] .header-v2 { --nav-surface:#111827;--nav-ink:#f8fafc;--nav-muted:#b8c3d5;--nav-line:#334155;--nav-active:#293955;--nav-active-ink:#fff; }
html[data-sidebar-appearance="color"] :is(.sidebar-shell,.navigation-mobile) { --nav-surface:var(--navigation-sidebar-color);--nav-ink:var(--navigation-sidebar-ink);--nav-muted:var(--navigation-sidebar-muted);--nav-line:color-mix(in srgb,var(--nav-ink) 22%,transparent);--nav-active:color-mix(in srgb,var(--nav-ink) 14%,transparent);--nav-active-ink:var(--nav-ink); }
html[data-topbar-appearance="color"] .header-v2 { --nav-surface:var(--navigation-topbar-color);--nav-ink:var(--navigation-topbar-ink);--nav-muted:var(--navigation-topbar-muted);--nav-line:color-mix(in srgb,var(--nav-ink) 22%,transparent);--nav-active:color-mix(in srgb,var(--nav-ink) 14%,transparent);--nav-active-ink:var(--nav-ink); }
html[data-sidebar-appearance]:not([data-sidebar-appearance="theme"]) :is(.sidebar-shell,.navigation-mobile),html[data-topbar-appearance]:not([data-topbar-appearance="theme"]) .header-v2 {
    background:var(--nav-surface)!important;color:var(--nav-ink)!important;
    --bg-dropdown:var(--nav-surface);--bg-card:var(--nav-surface);--bg-card-hover:var(--nav-active);--sidebar-bg:var(--nav-surface);--sidebar-link:var(--nav-muted);--sidebar-link-hover-bg:var(--nav-active);--sidebar-link-hover-text:var(--nav-ink);--bg-sidebar:var(--nav-surface);--bg-sidebar-mobile:var(--nav-surface);--bg-header:var(--nav-surface);--text-primary:var(--nav-ink);--text-secondary:var(--nav-muted);--text-muted:var(--nav-muted);--text-dimmed:var(--nav-muted);--text-faint:var(--nav-muted);--border-subtle:var(--nav-line);--border-glass:var(--nav-line);--border-strong:var(--nav-line);--sidebar-active-bg:var(--nav-active);--sidebar-active-text:var(--nav-active-ink);
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
</style>
