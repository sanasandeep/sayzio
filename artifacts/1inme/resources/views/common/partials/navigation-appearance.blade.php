{{--
    Navigation appearance: an admin default plus a per-user override, applied
    to three surfaces.

    WHAT CHANGED, AND WHY THE RULES ARE NOW GENERATED

    There used to be one `sidebar` area. Rail navigation puts a 72px icon rail
    beside a panel, so "the sidebar" is two surfaces, and a dark rail with a
    light panel - the reason to have the layout at all - could not be said.
    `sidebar` is therefore split into `rail` and `panel`.

    Every rule below used to be typed once per area by hand. Two areas became
    three, and the sidebar block alone is twenty-odd rules, so they are emitted
    from one template per area instead. The declarations are unchanged; only
    the selector differs, which is what keeps a rule from existing for the
    panel and quietly not for the rail.

    SCOPES

      rail   .navrail - inside .sidebar-shell, so it REDEFINES the
             inherited custom properties for its own subtree. That is what
             lets the two columns disagree.
      panel  the shell itself, which is also the whole of the classic
             sidebar when rail navigation is off, plus the mobile drawer.
      topbar the header.

    The legacy `sidebar` value seeds rail AND panel, here and in the JS, so
    nobody who had chosen a dark sidebar wakes up on the page theme.
--}}
@php
    use App\Modules\Common\Support\NavigationAppearance as NavAppearance;

    $navDefaults = NavAppearance::defaults();
    $navStorageKey = 'sayzio_navigation_' . (auth()->id() ?? 'guest');

    $navScopes = [
        'rail'   => '.navrail',
        'panel'  => ':is(.sidebar-shell,.navigation-mobile,.navrail-panel)',
        'topbar' => '.header-v2',
    ];
    // The areas that carry sidebar chrome - links, logo, upgrade card.
    $navChromeAreas = ['rail', 'panel'];

    $navOn = static fn (string $a): string => 'html[data-' . $a . '-appearance]:not([data-' . $a . '-appearance="theme"])';
    $navIs = static fn (string $a, string $p): string => 'html[data-' . $a . '-appearance="' . $p . '"]';
@endphp
<script>
(function () {
    const defaults = @json($navDefaults);
    const key = @json($navStorageKey);
    const areas = @json(array_keys($navScopes));
    const presets = @json(NavAppearance::PALETTES);
    const darkInk = @json(NavAppearance::DARK_PRESETS);
    const LEGACY = @json(NavAppearance::LEGACY_AREA);

    // 'color' was the only gradient before there were three.
    function mode(value) { return value === 'color' ? 'gradient-medium' : value; }

    function read() {
        try {
            const value = JSON.parse(localStorage.getItem(key));
            if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
            // A saved `sidebar` choice becomes the same choice on both columns.
            const legacy = mode(value[LEGACY]);
            if (legacy) {
                if (value.rail === undefined) value.rail = legacy;
                if (value.panel === undefined) value.panel = legacy;
            }
            areas.forEach(area => { value[area] = mode(value[area]); });
            return value;
        } catch (e) { return {}; }
    }

    function apply(prefs) {
        const root = document.documentElement;
        areas.forEach(area => {
            const chosen = mode(prefs[area]);
            const fallback = mode(defaults[area]);
            const ok = v => v === 'theme' || presets[v];
            const selected = ok(chosen) ? chosen : (ok(fallback) ? fallback : 'theme');
            root.dataset[area + 'Appearance'] = selected;
            const palette = presets[selected] || presets.light;
            root.dataset[area + 'Logo'] = darkInk.includes(selected) ? 'dark' : 'light';
            ['surface', 'ink', 'muted'].forEach((token, index) =>
                root.style.setProperty('--navigation-' + area + '-' + token, palette[index]));
        });
        root.dataset.topbarIncludesLogo = String(typeof prefs.logo === 'boolean' ? prefs.logo : defaults.logo === true);
    }

    window.sayzioNavigation = {
        read,
        areas,
        save(prefs) { try { localStorage.setItem(key, JSON.stringify(prefs)); } catch (e) {} apply(prefs); },
        reset() { try { localStorage.removeItem(key); } catch (e) {} apply({}); }
    };
    apply(read());
    window.addEventListener('storage', e => { if (e.key === key || e.key === null) apply(read()); });
})();
</script>
<style>
@foreach($navScopes as $navArea => $navScope)
@php($navSel = $navOn($navArea) . ' ' . $navScope)
/* ---------------- {{ $navArea }} ---------------- */
{!! $navIs($navArea, 'light') !!} {!! $navScope !!} { --nav-control:#fff;--nav-surface:#fff;--nav-ink:#172033;--nav-muted:#566176;--nav-line:#dce2ed;--nav-active:#e8edff;--nav-active-ink:#2948d5; }
{{-- MEASURED, not chosen by eye: the dark preset was painting
     rgb(17,24,39) -- blue is the brightest channel by 22 points, which is
     why it read as navy rather than as a dark sidebar. Every token in the
     set was from the same slate family, so the cast compounded: a navy
     ground, a navy rule and a navy active fill. The replacements are the
     neutral equivalents at the same lightness, so the preset is the same
     DARK, just without the hue. --}}
{{-- "sidebar should sync with body colors" (Sana). It did not, and the
     reason was measurable: every surface this product paints carries a
     violet lean and runs dark -- body #07060b, hero #0e0e18, cards
     #100f17 -- while this preset was Tailwind zinc, neutral and at
     luminance 24 the BRIGHTEST plane on the page. The chrome was
     sitting above the content instead of under it.

     These values come from the app's own ramp rather than from a
     generic scale: --nav-surface is aurora's --bg-sidebar, --nav-control
     is its --bg-card, and the inks are its --text-primary and
     --text-muted. Measured after, on the live page: body 6.6, sidebar
     10.7, hero 14.7, cards 15.8 -- a stack that descends to the chrome,
     with the violet lean shared all the way down. The base dark theme's
     body is #0a0a0f, so the same literals sit right there too.

     They are literals and must stay literals: the scope block below
     defines --bg-card, --bg-sidebar and --text-primary FROM these, so
     reading them back here would be a custom-property cycle, and a
     cycle computes to nothing rather than to the fallback. --}}
{!! $navIs($navArea, 'dark') !!} {!! $navScope !!} { --nav-control:#100f17;--nav-surface:#0b0a11;--nav-ink:#f3f1f7;--nav-muted:#9b97ac;--nav-line:#191823;--nav-active:#201f2e;--nav-active-ink:#f3f1f7; }
html[data-{{ $navArea }}-appearance^="gradient-"] {!! $navScope !!} { --nav-control:#352f70;--nav-surface:var(--navigation-{{ $navArea }}-surface);--nav-ink:var(--navigation-{{ $navArea }}-ink);--nav-muted:var(--navigation-{{ $navArea }}-muted);--nav-line:color-mix(in srgb,var(--nav-ink) 22%,transparent);--nav-active:color-mix(in srgb,var(--nav-ink) 14%,transparent);--nav-active-ink:var(--nav-ink); }
{!! $navIs($navArea, 'gradient-light') !!} {!! $navScope !!} { --nav-control:#f2f1fc; }
{!! $navIs($navArea, 'gradient-dark') !!} {!! $navScope !!} { --nav-control:#171f35; }
{!! $navSel !!} {
    background:var(--nav-surface)!important;color:var(--nav-ink)!important;
    --bg-dropdown:var(--nav-control);--bg-card:var(--nav-control);--bg-card-hover:var(--nav-active);--sidebar-bg:var(--nav-surface);--sidebar-link:var(--nav-muted);--sidebar-link-hover-bg:var(--nav-active);--sidebar-link-hover-text:var(--nav-ink);--bg-sidebar:var(--nav-surface);--bg-sidebar-mobile:var(--nav-surface);--bg-header:var(--nav-surface);--text-primary:var(--nav-ink);--text-secondary:var(--nav-muted);--text-muted:var(--nav-muted);--text-dimmed:var(--nav-muted);--text-faint:var(--nav-muted);--border-subtle:var(--nav-line);--border-glass:var(--nav-line);--border-strong:var(--nav-line);--sidebar-active-bg:var(--nav-active);--sidebar-active-text:var(--nav-active-ink);
}
@if(in_array($navArea, $navChromeAreas, true))
{{-- The two navs spell "active" differently: a classic .sidebar-link
     takes `.active`, a rail tile takes `.is-active`. These rules named
     only the first, so every preset painted the rail's selected tile
     with the muted ink meant for the unselected ones -- invisible until
     the rail scope was corrected, because before that none of this
     block reached the rail at all. --}}
{!! $navSel !!} :is(.sidebar-link,.navrail-btn) { color:var(--nav-muted)!important; }
{!! $navSel !!} :is(.sidebar-link.active,.navrail-btn.is-active) { color:var(--nav-active-ink)!important;background:var(--nav-active)!important; }
{!! $navSel !!} :is(.sidebar-link.active,.navrail-btn.is-active) :is(i,.nav-icon-wrap) { color:var(--nav-active-ink)!important; }
{{-- The resting icon keeps its own tint UNDER a preset too. Painting
     every one of them --nav-muted meant the tints only ever showed for
     accounts that had not chosen an appearance, which is the opposite
     of what an appearance is for. Mixing rather than replacing keeps
     the preset's character -- its muted ink is still the base -- while
     each row stays identifiable. Measured on the live page: 7 rows,
     6 distinct colours, where before it was 7 rows and 1. --}}
{!! $navSel !!} :is(.sidebar-link,.navrail-btn):not(.active,.is-active) :is(i,.nav-icon-wrap) { color:color-mix(in srgb, var(--nav-tint, var(--nav-muted)) 55%, var(--nav-muted))!important; }
{!! $navSel !!} :is(.sidebar-link,.navrail-btn):is(:hover,:focus-visible) { background:var(--nav-active)!important;color:var(--nav-ink)!important; }
{!! $navSel !!} :is(.sidebar-link,.navrail-btn):is(:hover,:focus-visible) :is(i,.nav-icon-wrap) { color:var(--nav-ink)!important; }
{!! $navSel !!} .upgrade-card { background:var(--nav-control)!important;border:1px solid var(--nav-line)!important; }
{!! $navSel !!} :is(#workspace-switcher > button,.admin-switch-btn,.logout-btn) {
    background:transparent!important;color:var(--nav-ink)!important;border-color:var(--nav-line)!important;
}
{!! $navSel !!} :is(#workspace-switcher > button,.admin-switch-btn,.logout-btn):is(:hover,:focus-visible),
{!! $navSel !!} #workspace-switcher > button[aria-expanded="true"] {
    background:var(--nav-active)!important;color:var(--nav-ink)!important;
}
{!! $navSel !!} #workspace-switcher :is(button,a).hover\:bg-black\/5:is(:hover,:focus-visible) {
    background:var(--nav-active)!important;color:var(--nav-ink)!important;
}
{!! $navOn($navArea) !!}[data-{{ $navArea }}-logo="dark"] {!! $navScope !!} .brand-logo--light { display:none!important; }
{!! $navOn($navArea) !!}[data-{{ $navArea }}-logo="dark"] {!! $navScope !!} .brand-logo--dark { display:block!important; }
{!! $navOn($navArea) !!}[data-{{ $navArea }}-logo="light"] {!! $navScope !!} .brand-logo--light { display:block!important; }
{!! $navOn($navArea) !!}[data-{{ $navArea }}-logo="light"] {!! $navScope !!} .brand-logo--dark { display:none!important; }
@else
{!! $navSel !!} .header-icon-btn { background:var(--nav-active)!important;color:var(--nav-ink)!important;border-color:var(--nav-line)!important; }
@endif
@endforeach

/* The rail sits inside the shell, so the divider has to come from the rail's
   own palette - otherwise a dark rail beside a light panel is separated by a
   light rule that disappears into the rail. */
html[data-rail-appearance]:not([data-rail-appearance="theme"]) .navrail {
    border-right:1px solid var(--nav-line)!important;
}

:root { --navigation-theme-surface:var(--bg-header);--navigation-theme-ink:var(--text-primary); }

.navigation-picker { position:fixed;right:20px;top:80px;z-index:100;width:min(360px,calc(100vw - 32px));padding:24px;border-radius:20px;background:var(--bg-dropdown);color:var(--text-primary);border:1px solid var(--border-strong);box-shadow:0 20px 60px #0003; }
.navigation-picker label { display:block;margin:16px 0;font-size:14px; }
.navigation-picker select { width:100%;margin-top:8px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--bg-card);color:var(--text-primary); }

/* The edge toggle lives outside the logo wrapper, so resolve its palette
   explicitly. It sits on the shell, which the panel area owns. */
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
.sidebar-shell .sidebar-edge-toggle:hover { filter:brightness(1.12); }

html[data-topbar-includes-logo="true"] .navigation-logo {background:var(--navigation-topbar-surface)!important;--text-primary:var(--navigation-topbar-ink);border-color:color-mix(in srgb,var(--navigation-topbar-ink) 20%,transparent)!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"] .navigation-logo {background:var(--bg-header)!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="dark"] .navigation-logo .brand-logo--light {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="dark"] .navigation-logo .brand-logo--dark {display:block!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="light"] .navigation-logo .brand-logo--dark {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-logo="light"] .navigation-logo .brand-logo--light {display:block!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"]:not(.light-mode) .navigation-logo .brand-logo--light {display:none!important;}
html[data-topbar-includes-logo="true"][data-topbar-appearance="theme"]:not(.light-mode) .navigation-logo .brand-logo--dark {display:block!important;}
</style>
