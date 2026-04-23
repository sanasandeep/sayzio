@php
    use App\Modules\Common\Support\CookieConsentConfig;
    $surface = $surface ?? 'site';
    $isOwner = $isOwner ?? false;

    $cc = null;
    if (!$isOwner && CookieConsentConfig::shouldRender($surface)) {
        $route = optional(request()->route())->getName();
        // Don't render on policy/legal pages themselves (the visitor needs to read them).
        $policyRoutes = ['site.cookies', 'site.privacy', 'site.terms', 'site.gdpr', 'site.refunds'];
        if (!in_array($route, $policyRoutes, true)) {
            $cc = CookieConsentConfig::get();
        }
    }

    // Country header set by Cloudflare; null when running behind a different
    // proxy. Geo enforcement is best-effort — the JS double-checks too.
    $cfCountry = strtoupper((string) request()->server('HTTP_CF_IPCOUNTRY', ''));
@endphp
@if($cc)
@php
    $effective = CookieConsentConfig::effectiveFor($cc, $surface);
    $cfgJson = [
        'enabled'             => true,
        'surface'             => $surface,
        'policyVersion'       => $cc['policy_version'],
        'rememberDays'        => $cc['remember_days'],
        'repromptOnChange'    => $cc['reprompt_on_change'],
        'geoScope'            => $cc['geo_scope'],
        'geoCountries'        => $cc['geo_scope'] === 'eu' ? CookieConsentConfig::EU_COUNTRIES : $cc['geo_countries'],
        'requestCountry'      => $cfCountry ?: null,
        'scrollAcceptance'    => $cc['scroll_acceptance'],
        'blockUntilConsent'   => $cc['block_until_consent'],
        'layout'              => $effective['layout'],
        'position'            => $effective['position'],
        'size'                => $cc['size'],
        'maxWidth'            => $cc['max_width'],
        'radius'              => $cc['radius'],
        'theme'               => $cc['theme'],
        'accent'              => $cc['accent'],
        'buttons'             => $cc['buttons'],
        'backdrop'            => $cc['backdrop'],
        'animation'           => $cc['animation'],
        'entranceDelay'       => $cc['entrance_delay'],
        'headerLogoEnabled'   => $cc['header_logo_enabled'],
        'headerLogoUrl'       => $cc['header_logo_url'],
        'showPolicyLink'      => $cc['show_policy_link'],
        'copy'                => $cc['copy'],
        'categories'          => array_values($cc['categories']),
        'cookieName'          => '1inme_cookie_consent',
    ];
@endphp
<style>
    .cc-host * { box-sizing: border-box; }
    .cc-host {
        position: fixed; z-index: 2147483600; font-family: 'Space Grotesk', system-ui, sans-serif;
        pointer-events: none;
    }
    .cc-host .cc-card,
    .cc-host .cc-backdrop,
    .cc-host .cc-pos { pointer-events: auto; }

    .cc-backdrop {
        position: absolute; inset: 0; background: rgba(8,10,20,0.55);
    }

    .cc-pos { position: absolute; }
    .cc-card {
        background: var(--cc-bg, #ffffff); color: var(--cc-fg, #111827);
        border: 1px solid var(--cc-border, rgba(0,0,0,0.08));
        border-radius: var(--cc-radius, 16px);
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);
        padding: 18px 18px 14px;
    }
    .cc-card.cc-stretch { width: 100%; }
    .cc-header { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .cc-header img.cc-logo { width: 26px; height: 26px; border-radius: 6px; object-fit: cover; flex-shrink: 0; }
    .cc-card h3 { margin: 0; font-size: 16px; font-weight: 600; }
    .cc-card p { margin: 0 0 12px; font-size: 13px; line-height: 1.55; color: var(--cc-muted, #4b5563); }
    .cc-card a.cc-policy { color: var(--cc-accent, #7c3aed); text-decoration: underline; }

    .cc-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .cc-btn { border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; line-height: 1.2; }

    .cc-cats { margin: 4px 0 12px; display: grid; gap: 8px; max-height: 250px; overflow-y: auto; }
    .cc-cat { padding: 10px 12px; border: 1px solid var(--cc-border, rgba(0,0,0,0.08)); border-radius: 10px; }
    .cc-cat-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .cc-cat-name { font-size: 13px; font-weight: 600; }
    .cc-cat-desc { font-size: 12px; color: var(--cc-muted, #4b5563); margin-top: 4px; line-height: 1.5; }
    .cc-cat-cookies { font-size: 11px; color: var(--cc-muted, #4b5563); margin-top: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; opacity: 0.8; word-break: break-all; }
    .cc-switch { position: relative; width: 36px; height: 20px; flex-shrink: 0; }
    .cc-switch input { opacity: 0; width: 0; height: 0; }
    .cc-switch .cc-slider { position: absolute; inset: 0; cursor: pointer; background: rgba(0,0,0,0.18); border-radius: 999px; transition: .2s; }
    .cc-switch .cc-slider:before { content: ''; position: absolute; height: 16px; width: 16px; left: 2px; top: 2px; background: #fff; border-radius: 50%; transition: .2s; }
    .cc-switch input:checked + .cc-slider { background: var(--cc-accent, #7c3aed); }
    .cc-switch input:checked + .cc-slider:before { transform: translateX(16px); }
    .cc-switch input:disabled + .cc-slider { opacity: 0.5; cursor: not-allowed; }

    /* Inline bar variant: slim row pinned to the bottom edge. */
    .cc-card.cc-inline {
        display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 0;
    }
    .cc-card.cc-inline .cc-inline-text { flex: 1; min-width: 0; }
    .cc-card.cc-inline h3 { font-size: 13px; font-weight: 600; display: inline; margin-right: 6px; }
    .cc-card.cc-inline p { display: inline; font-size: 12.5px; margin: 0; }
    .cc-card.cc-inline .cc-actions { flex-shrink: 0; }

    /* Pill variant: compact floating badge with title + actions. */
    .cc-card.cc-pill {
        border-radius: 999px; padding: 8px 8px 8px 18px;
        display: flex; align-items: center; gap: 12px;
    }
    .cc-card.cc-pill h3 { font-size: 13.5px; font-weight: 600; }
    .cc-card.cc-pill p { display: none; }
    .cc-card.cc-pill .cc-actions { flex-shrink: 0; }
    .cc-card.cc-pill .cc-btn { padding: 6px 12px; font-size: 12.5px; border-radius: 999px; }

    /* Takeover layout: full-screen container, card centered. */
    .cc-host[data-layout="takeover"] {
        inset: 0; display: flex; align-items: center; justify-content: center; padding: 16px;
    }
    .cc-host[data-layout="takeover"] .cc-card {
        max-width: var(--cc-card-max, 560px); width: 100%;
        padding: 28px 28px 22px;
    }

    .cc-host[data-layout="modal"] {
        inset: 0; display: flex; align-items: center; justify-content: center; padding: 12px;
    }
    .cc-host[data-layout="modal"] .cc-card {
        max-width: var(--cc-card-max, 440px); width: calc(100vw - 24px);
    }

    .cc-host[data-layout="banner"] { left: 0; right: 0; padding: 12px; }
    .cc-host[data-layout="banner"][data-position^="bottom"] { bottom: 0; }
    .cc-host[data-layout="banner"][data-position^="top"]    { top: 0; }
    .cc-host[data-layout="banner"][data-position^="middle"] { top: 50%; transform: translateY(-50%); }

    .cc-host[data-layout="inline"] { left: 0; right: 0; bottom: 0; padding: 0; }

    .cc-host[data-layout="corner"], .cc-host[data-layout="pill"] { padding: 12px; }

    /* Position helpers shared by corner + pill */
    .cc-host[data-layout="corner"] .cc-card,
    .cc-host[data-layout="pill"]   .cc-card { max-width: var(--cc-card-max, 360px); }
    .cc-host[data-layout="corner"][data-position="bottom-center"],
    .cc-host[data-layout="pill"][data-position="bottom-center"]   { left: 50%; transform: translateX(-50%); bottom: 0; }
    .cc-host[data-layout="corner"][data-position="bottom-left"],
    .cc-host[data-layout="pill"][data-position="bottom-left"]     { left: 0; bottom: 0; }
    .cc-host[data-layout="corner"][data-position="bottom-right"],
    .cc-host[data-layout="pill"][data-position="bottom-right"]    { right: 0; bottom: 0; }
    .cc-host[data-layout="corner"][data-position="top-center"],
    .cc-host[data-layout="pill"][data-position="top-center"]      { left: 50%; transform: translateX(-50%); top: 0; }
    .cc-host[data-layout="corner"][data-position="top-left"],
    .cc-host[data-layout="pill"][data-position="top-left"]        { left: 0; top: 0; }
    .cc-host[data-layout="corner"][data-position="top-right"],
    .cc-host[data-layout="pill"][data-position="top-right"]       { right: 0; top: 0; }
    .cc-host[data-layout="corner"][data-position="middle-left"],
    .cc-host[data-layout="pill"][data-position="middle-left"]     { left: 0; top: 50%; transform: translateY(-50%); }
    .cc-host[data-layout="corner"][data-position="middle-right"],
    .cc-host[data-layout="pill"][data-position="middle-right"]    { right: 0; top: 50%; transform: translateY(-50%); }

    .cc-host[data-theme="dark"] .cc-card,
    .cc-host[data-theme="auto"].cc-is-dark .cc-card {
        --cc-bg: #111827; --cc-fg: #f9fafb; --cc-muted: #9ca3af; --cc-border: rgba(255,255,255,0.10);
    }

    /* Animations */
    @keyframes cc-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes cc-slide-up { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes cc-slide-down { from { opacity: 0; transform: translateY(-16px); } to { opacity: 1; transform: translateY(0); } }
    .cc-host[data-anim="fade"]       .cc-card { animation: cc-fade .25s ease both; }
    .cc-host[data-anim="slide-up"]   .cc-card { animation: cc-slide-up .28s ease both; }
    .cc-host[data-anim="slide-down"] .cc-card { animation: cc-slide-down .28s ease both; }
    .cc-host[data-anim="fade"]       .cc-backdrop { animation: cc-fade .25s ease both; }
    .cc-host[data-anim="slide-up"]   .cc-backdrop { animation: cc-fade .25s ease both; }
    .cc-host[data-anim="slide-down"] .cc-backdrop { animation: cc-fade .25s ease both; }

    /* Footer reopen link is rendered inside the page footer; the
       legacy floating cookie icon has been retired. */
    .cc-footer-link {
        background: transparent; border: 0; padding: 0; margin: 0;
        font: inherit; color: inherit; cursor: pointer; text-decoration: underline;
    }
    .cc-footer-link:focus-visible { outline: 2px solid var(--cc-accent, #7c3aed); outline-offset: 2px; border-radius: 4px; }
</style>
<script>
window.__cookieConsent = window.__cookieConsent || (function(){
    const cfg = @json($cfgJson);
    const COOKIE = cfg.cookieName;

    function readCookie(name) {
        const m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[-.]/g,'\\$&') + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : null;
    }
    function writeCookie(name, value, days) {
        const exp = new Date(Date.now() + days*86400000).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; expires=' + exp + '; SameSite=Lax';
    }
    function clearCookie(name) {
        document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    }

    function readDecision() {
        try {
            const raw = readCookie(COOKIE);
            if (!raw) return null;
            const obj = JSON.parse(raw);
            if (cfg.repromptOnChange && (obj.v|0) !== (cfg.policyVersion|0)) return null;
            return obj;
        } catch (e) { return null; }
    }

    function inGeoScope() {
        if (cfg.geoScope === 'all') return true;
        const list = cfg.geoCountries || [];
        if (!cfg.requestCountry) return true; // fail open
        return list.indexOf((cfg.requestCountry || '').toUpperCase()) !== -1;
    }

    const state = {
        decision: readDecision(),
        host: null,
    };

    function activateCategory(cat) {
        document.querySelectorAll('script[type="text/plain"][data-consent-category="' + cat + '"]').forEach(function(s){
            const fresh = document.createElement('script');
            for (let i=0; i<s.attributes.length; i++) {
                const a = s.attributes[i];
                if (a.name === 'type' || a.name === 'data-consent-category') continue;
                fresh.setAttribute(a.name, a.value);
            }
            fresh.text = s.textContent;
            s.parentNode.insertBefore(fresh, s);
            s.parentNode.removeChild(s);
        });
    }
    function applyConsents(consents) {
        Object.keys(consents || {}).forEach(function(cat){
            if (consents[cat]) activateCategory(cat);
        });
        try { window.dispatchEvent(new CustomEvent('inme-cookie-consent', { detail: consents })); } catch (e) {}
    }

    function persist(consents) {
        const obj = { v: cfg.policyVersion, t: Date.now(), c: consents };
        writeCookie(COOKIE, JSON.stringify(obj), cfg.rememberDays);
        state.decision = obj;
        applyConsents(consents);
    }

    function allCats() { return cfg.categories.map(c => c.id); }
    function defaultConsents() {
        const o = {};
        cfg.categories.forEach(c => { o[c.id] = !!c.default_on; });
        return o;
    }
    function acceptAll() { const o = {}; allCats().forEach(c => o[c] = true);  persist(o); hide(); }
    function rejectAll() { const o = {}; allCats().forEach(c => o[c] = false); persist(o); hide(); }

    function escapeHtml(s){return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

    function btnInlineStyle(role) {
        const b = (cfg.buttons || {})[role] || {};
        const style = b.style || 'solid';
        if (style === 'outline') return `background:transparent; color:${b.bg}; border:1.5px solid ${b.bg};`;
        if (style === 'link')    return `background:transparent; color:${b.bg}; border:0; text-decoration:underline; padding:8px 8px;`;
        return `background:${b.bg}; color:${b.text}; border:0;`;
    }

    function buildHost() {
        // One-shot overrides (e.g. inline/pill -> modal for the customize sheet) take effect for this render only;
        // cfg itself is left untouched so subsequent reopens use the admin-configured layout.
        const liveLayout   = state.layoutOverride   || cfg.layout;
        const livePosition = state.positionOverride || cfg.position;
        const host = document.createElement('div');
        host.className = 'cc-host';
        host.setAttribute('role', 'dialog');
        host.setAttribute('aria-modal', 'true');
        host.setAttribute('aria-label', cfg.copy.title || 'Cookie preferences');
        host.setAttribute('data-layout', liveLayout);
        host.setAttribute('data-position', livePosition);
        host.setAttribute('data-theme', cfg.theme);
        host.setAttribute('data-anim', cfg.animation || 'none');
        host.style.setProperty('--cc-accent', cfg.accent);
        host.style.setProperty('--cc-radius', (cfg.radius|0) + 'px');

        // size → max-width
        const sizeShrink = cfg.size === 'compact' ? 0.85 : (cfg.size === 'wide' ? 1.15 : 1);
        host.style.setProperty('--cc-card-max', Math.round((cfg.maxWidth || 440) * sizeShrink) + 'px');

        if (cfg.theme === 'auto') {
            try {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) host.classList.add('cc-is-dark');
            } catch (e) {}
        }

        // Backdrop only for modal/takeover.
        const wantsBackdrop = (liveLayout === 'modal' || liveLayout === 'takeover') && cfg.backdrop && cfg.backdrop.show;
        if (wantsBackdrop) {
            const bd = document.createElement('div');
            bd.className = 'cc-backdrop';
            const dim = Math.max(0, Math.min(100, cfg.backdrop.dim|0)) / 100;
            bd.style.background = `rgba(8,10,20,${dim})`;
            if (cfg.backdrop.blur) {
                bd.style.backdropFilter = 'blur(6px)';
                bd.style.webkitBackdropFilter = 'blur(6px)';
            }
            host.appendChild(bd);
        }

        const card = document.createElement('div');
        card.className = 'cc-card';
        if (liveLayout === 'banner')   card.classList.add('cc-stretch');
        if (liveLayout === 'inline')   card.classList.add('cc-inline');
        if (liveLayout === 'pill')     card.classList.add('cc-pill');

        const initial = state.decision ? state.decision.c : defaultConsents();
        const policyHref = escapeHtml(cfg.copy.policy_link_url || '/cookies');
        const showPolicy = cfg.showPolicyLink !== false;
        const policyHtml = showPolicy
            ? ` <a class="cc-policy" href="${policyHref}">${escapeHtml(cfg.copy.policy_link_label)}</a>.`
            : '';

        const headerHtml = (cfg.headerLogoEnabled && cfg.headerLogoUrl)
            ? `<div class="cc-header"><img class="cc-logo" src="${escapeHtml(cfg.headerLogoUrl)}" alt=""><h3>${escapeHtml(cfg.copy.title)}</h3></div>`
            : `<h3 style="margin-bottom:6px;">${escapeHtml(cfg.copy.title)}</h3>`;

        let mainHtml;
        if (liveLayout === 'inline') {
            mainHtml = `
                <div class="cc-inline-text">
                    <h3>${escapeHtml(cfg.copy.title)}</h3>
                    <p>${escapeHtml(cfg.copy.body)}${policyHtml}</p>
                </div>`;
        } else if (liveLayout === 'pill') {
            mainHtml = `<h3>${escapeHtml(cfg.copy.title)}</h3>`;
        } else {
            mainHtml = `${headerHtml}
                <p>${escapeHtml(cfg.copy.body)}${policyHtml}</p>`;
        }

        const catsHtml = `
            <div class="cc-cats" hidden>
                <div class="cc-cat" style="opacity:.85">
                    <div class="cc-cat-head">
                        <div><div class="cc-cat-name">Essential</div><div class="cc-cat-desc">Required for the site to work (login, security, theme). Always on.</div></div>
                        <label class="cc-switch"><input type="checkbox" checked disabled><span class="cc-slider"></span></label>
                    </div>
                </div>
                ${cfg.categories.map(c => `
                <div class="cc-cat">
                    <div class="cc-cat-head">
                        <div>
                            <div class="cc-cat-name">${escapeHtml(c.name)}</div>
                            <div class="cc-cat-desc">${escapeHtml(c.description)}</div>
                            ${c.cookies ? `<div class="cc-cat-cookies">${escapeHtml(c.cookies)}</div>` : ''}
                        </div>
                        <label class="cc-switch">
                            <input type="checkbox" data-cat="${escapeHtml(c.id)}" ${initial[c.id] ? 'checked' : ''}>
                            <span class="cc-slider"></span>
                        </label>
                    </div>
                </div>`).join('')}
            </div>`;

        const actionsHtml = `
            <div class="cc-actions">
                <button type="button" class="cc-btn" data-act="accept" style="${btnInlineStyle('primary')}">${escapeHtml(cfg.copy.accept_all)}</button>
                <button type="button" class="cc-btn" data-act="reject" style="${btnInlineStyle('secondary')}">${escapeHtml(cfg.copy.reject_all)}</button>
                <button type="button" class="cc-btn" data-act="customize" style="${btnInlineStyle('tertiary')}">${escapeHtml(cfg.copy.customize)}</button>
                <button type="button" class="cc-btn" data-act="save" hidden style="${btnInlineStyle('primary')}">${escapeHtml(cfg.copy.save)}</button>
            </div>`;

        // Inline / pill don't show the cats list — switch to modal-style if customize is hit.
        card.innerHTML = mainHtml + (liveLayout === 'pill' || liveLayout === 'inline' ? '' : catsHtml) + actionsHtml;
        host.appendChild(card);

        card.addEventListener('click', function(ev){
            const t = ev.target.closest('[data-act]');
            if (!t) return;
            const act = t.getAttribute('data-act');
            if (act === 'accept') acceptAll();
            else if (act === 'reject') rejectAll();
            else if (act === 'customize') {
                // For inline/pill, re-render as modal so visitors can actually toggle categories.
                // Use a one-shot override instead of mutating cfg, so any later reopen still uses the original layout.
                if (liveLayout === 'inline' || liveLayout === 'pill') {
                    host.remove(); state.host = null;
                    state.layoutOverride = 'modal';
                    state.positionOverride = 'bottom-center';
                    show();
                    // Auto-open the categories list in the new modal.
                    requestAnimationFrame(() => {
                        const newCats = state.host && state.host.querySelector('.cc-cats');
                        const newCustomize = state.host && state.host.querySelector('[data-act="customize"]');
                        const newSave = state.host && state.host.querySelector('[data-act="save"]');
                        if (newCats) newCats.hidden = false;
                        if (newCustomize) newCustomize.hidden = true;
                        if (newSave) newSave.hidden = false;
                    });
                    return;
                }
                card.querySelector('.cc-cats').hidden = false;
                t.hidden = true;
                card.querySelector('[data-act="save"]').hidden = false;
            } else if (act === 'save') {
                const o = defaultConsents();
                card.querySelectorAll('input[data-cat]').forEach(i => { o[i.getAttribute('data-cat')] = i.checked; });
                persist(o); hide();
            }
        });

        return host;
    }

    function show() {
        if (state.host) return;
        state.host = buildHost();
        document.body.appendChild(state.host);
        if (cfg.scrollAcceptance) {
            const onScroll = function(){
                if (window.scrollY > 80) {
                    window.removeEventListener('scroll', onScroll);
                    acceptAll();
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
        }
    }
    function hide() {
        if (state.host) { state.host.remove(); state.host = null; }
    }

    function init() {
        if (state.decision && state.decision.c) {
            applyConsents(state.decision.c);
            return;
        }
        if (!inGeoScope()) {
            const o = {}; allCats().forEach(c => o[c] = true);
            applyConsents(o);
            return;
        }
        const delay = Math.max(0, Math.min(30, (cfg.entranceDelay|0))) * 1000;
        if (delay > 0) setTimeout(show, delay); else show();
    }
    return {
        init: init,
        open: function(){ show(); },
        clear: function(){ clearCookie(COOKIE); state.decision = null; },
    };
})();

// Public hook for the footer "Cookie preferences" link.
window.openCookiePreferences = function(ev){
    if (ev && ev.preventDefault) ev.preventDefault();
    try { window.__cookieConsent.open(); } catch (e) {}
    return false;
};

document.addEventListener('DOMContentLoaded', function(){ try { window.__cookieConsent.init(); } catch(e) { console && console.warn && console.warn('cookie-consent', e); } });
</script>
@endif
