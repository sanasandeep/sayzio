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
    $cfgJson = [
        'enabled'             => true,
        'policyVersion'       => $cc['policy_version'],
        'rememberDays'        => $cc['remember_days'],
        'repromptOnChange'    => $cc['reprompt_on_change'],
        'geoScope'            => $cc['geo_scope'],
        'geoCountries'        => $cc['geo_scope'] === 'eu' ? CookieConsentConfig::EU_COUNTRIES : $cc['geo_countries'],
        'requestCountry'      => $cfCountry ?: null,
        'scrollAcceptance'    => $cc['scroll_acceptance'],
        'blockUntilConsent'   => $cc['block_until_consent'],
        'layout'              => $cc['layout'],
        'position'            => $cc['position'],
        'theme'               => $cc['theme'],
        'accent'              => $cc['accent'],
        'showReopenButton'    => $cc['show_reopen_button'],
        'copy'                => $cc['copy'],
        'categories'          => array_values($cc['categories']),
        'cookieName'          => '1inme_cookie_consent',
    ];
@endphp
<style>
    .cc-host * { box-sizing: border-box; }
    .cc-host {
        position: fixed; z-index: 2147483600; font-family: 'Space Grotesk', system-ui, sans-serif;
    }
    .cc-card {
        background: var(--cc-bg, #ffffff); color: var(--cc-fg, #111827);
        border: 1px solid var(--cc-border, rgba(0,0,0,0.08));
        border-radius: 16px; box-shadow: 0 24px 60px rgba(0,0,0,0.25);
        padding: 18px 18px 14px; max-width: 440px; width: calc(100vw - 24px);
    }
    .cc-card.cc-banner { max-width: none; width: 100%; }
    .cc-card h3 { margin: 0 0 6px; font-size: 16px; font-weight: 600; }
    .cc-card p { margin: 0 0 12px; font-size: 13px; line-height: 1.55; color: var(--cc-muted, #4b5563); }
    .cc-card a.cc-policy { color: var(--cc-accent, #7c3aed); text-decoration: underline; }
    .cc-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .cc-btn { border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border: 0; font-family: inherit; }
    .cc-btn-primary { background: var(--cc-accent, #7c3aed); color: #fff; }
    .cc-btn-secondary { background: transparent; color: var(--cc-fg, #111827); border: 1px solid var(--cc-border, rgba(0,0,0,0.12)); }
    .cc-btn-link { background: transparent; color: var(--cc-muted, #4b5563); padding: 8px 8px; }
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

    .cc-host[data-layout="modal"] {
        inset: 0; display: flex; align-items: center; justify-content: center;
        background: rgba(8, 10, 20, 0.55); padding: 12px;
    }
    .cc-host[data-layout="banner"] { left: 0; right: 0; padding: 12px; }
    .cc-host[data-layout="banner"][data-position="bottom-center"],
    .cc-host[data-layout="banner"][data-position="bottom-left"],
    .cc-host[data-layout="banner"][data-position="bottom-right"] { bottom: 0; }
    .cc-host[data-layout="banner"][data-position="top-center"] { top: 0; }
    .cc-host[data-layout="corner"] { padding: 12px; }
    .cc-host[data-layout="corner"][data-position="bottom-center"] { left: 50%; transform: translateX(-50%); bottom: 0; }
    .cc-host[data-layout="corner"][data-position="bottom-left"]   { left: 0; bottom: 0; }
    .cc-host[data-layout="corner"][data-position="bottom-right"]  { right: 0; bottom: 0; }
    .cc-host[data-layout="corner"][data-position="top-center"]    { left: 50%; transform: translateX(-50%); top: 0; }

    .cc-host[data-theme="dark"] .cc-card,
    .cc-host[data-theme="auto"].cc-is-dark .cc-card {
        --cc-bg: #111827; --cc-fg: #f9fafb; --cc-muted: #9ca3af; --cc-border: rgba(255,255,255,0.10);
    }

    .cc-reopen {
        position: fixed; bottom: 14px; left: 14px; z-index: 2147483500;
        background: var(--cc-accent, #7c3aed); color: #fff; border: 0; border-radius: 999px;
        width: 38px; height: 38px; cursor: pointer; box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        display: none; align-items: center; justify-content: center; font-size: 16px;
    }
    .cc-reopen.cc-show { display: inline-flex; }
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
        if (!cfg.requestCountry) {
            // No country header — fail open (show prompt) since we can't prove they're outside scope.
            return true;
        }
        return list.indexOf((cfg.requestCountry || '').toUpperCase()) !== -1;
    }

    const state = {
        decision: readDecision(),
        host: null,
        reopenBtn: null,
    };

    // Apply pending scripts when consent is granted for a category.
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
    function acceptAll() {
        const o = {}; allCats().forEach(c => o[c] = true); persist(o); hide();
    }
    function rejectAll() {
        const o = {}; allCats().forEach(c => o[c] = false); persist(o); hide();
    }

    function escapeHtml(s){return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

    function buildHost() {
        const host = document.createElement('div');
        host.className = 'cc-host';
        host.setAttribute('data-layout', cfg.layout);
        host.setAttribute('data-position', cfg.position);
        host.setAttribute('data-theme', cfg.theme);
        host.style.setProperty('--cc-accent', cfg.accent);
        if (cfg.theme === 'auto') {
            try {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) host.classList.add('cc-is-dark');
            } catch (e) {}
        }

        const card = document.createElement('div');
        card.className = 'cc-card' + (cfg.layout === 'banner' ? ' cc-banner' : '');
        const initial = state.decision ? state.decision.c : defaultConsents();

        const policyHref = escapeHtml(cfg.copy.policy_link_url || '/cookies');
        card.innerHTML = `
            <h3>${escapeHtml(cfg.copy.title)}</h3>
            <p>${escapeHtml(cfg.copy.body)} <a class="cc-policy" href="${policyHref}">${escapeHtml(cfg.copy.policy_link_label)}</a>.</p>
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
            </div>
            <div class="cc-actions">
                <button type="button" class="cc-btn cc-btn-primary" data-act="accept">${escapeHtml(cfg.copy.accept_all)}</button>
                <button type="button" class="cc-btn cc-btn-secondary" data-act="reject">${escapeHtml(cfg.copy.reject_all)}</button>
                <button type="button" class="cc-btn cc-btn-link" data-act="customize">${escapeHtml(cfg.copy.customize)}</button>
                <button type="button" class="cc-btn cc-btn-secondary" data-act="save" hidden>${escapeHtml(cfg.copy.save)}</button>
            </div>
        `;
        host.appendChild(card);

        card.addEventListener('click', function(ev){
            const t = ev.target.closest('[data-act]');
            if (!t) return;
            const act = t.getAttribute('data-act');
            if (act === 'accept') acceptAll();
            else if (act === 'reject') rejectAll();
            else if (act === 'customize') {
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
        ensureReopen();
    }
    function ensureReopen() {
        if (!cfg.showReopenButton) return;
        if (state.reopenBtn) { state.reopenBtn.classList.add('cc-show'); return; }
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'cc-reopen cc-show';
        b.title = 'Manage cookie preferences';
        b.innerHTML = '<i class="fas fa-cookie-bite"></i>';
        b.style.setProperty('--cc-accent', cfg.accent);
        b.addEventListener('click', function(){ b.classList.remove('cc-show'); show(); });
        document.body.appendChild(b);
        state.reopenBtn = b;
    }

    function init() {
        // Apply previously stored consents to scripts that loaded with this page.
        if (state.decision && state.decision.c) {
            applyConsents(state.decision.c);
            ensureReopen();
            return;
        }
        if (!inGeoScope()) {
            // Outside scope → treat as "everything allowed" so legitimate analytics still run.
            const o = {}; allCats().forEach(c => o[c] = true);
            applyConsents(o);
            return;
        }
        show();
    }
    return {
        init: init,
        open: function(){ show(); },
        clear: function(){ clearCookie(COOKIE); state.decision = null; },
    };
})();
document.addEventListener('DOMContentLoaded', function(){ try { window.__cookieConsent.init(); } catch(e) { console && console.warn && console.warn('cookie-consent', e); } });
</script>
@endif
