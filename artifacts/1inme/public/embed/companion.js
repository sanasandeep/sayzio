/*!
 * Sayzio AI Companion embed bundle
 * ---------------------------------------------------------------
 * Drop into any HTML page:
 *   <script src="https://YOUR-Sayzio-DOMAIN/embed/companion.js"
 *           data-companion="cmp_xxxxxxxxxxxxxxxxxxxx"
 *           defer></script>
 *
 * The script appends a floating launcher + chat panel to the
 * embedding site, talks to /companion/{publicId}/message via fetch,
 * and persists the visitor token in localStorage so returning users
 * resume their conversation.
 *
 * No external dependencies. ~6 KB minified.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) {
        var scripts = document.getElementsByTagName('script');
        for (var i = scripts.length - 1; i >= 0; i--) {
            if ((scripts[i].src || '').indexOf('/embed/companion.js') !== -1) {
                script = scripts[i];
                break;
            }
        }
    }
    if (!script) return;

    var publicId = script.getAttribute('data-companion') || '';
    if (!/^cmp_[a-z0-9]{20}$/.test(publicId)) {
        console.warn('[Sayzio Companion] Missing or invalid data-companion attribute.');
        return;
    }

    var srcUrl;
    try { srcUrl = new URL(script.src); } catch (_) { return; }
    var baseOrigin  = srcUrl.protocol + '//' + srcUrl.host;
    var endpoint    = baseOrigin + '/companion/' + publicId + '/message';
    var sessionUrl  = baseOrigin + '/companion/' + publicId + '/session';
    var rateUrl     = baseOrigin + '/companion/' + publicId + '/rate';
    var storageKey  = '1inme_companion_visitor_' + publicId;
    var sessionKey  = '1inme_companion_session_' + publicId;
    var sessionToken = null;
    function loadSession(){
        try { var raw = sessionStorage.getItem(sessionKey); if(!raw) return null; var p = JSON.parse(raw); if(!p || !p.token || (p.exp||0) < Math.floor(Date.now()/1000)+30) return null; return p.token; } catch(_) { return null; }
    }
    function saveSession(token, ttl){
        try { sessionStorage.setItem(sessionKey, JSON.stringify({ token: token, exp: Math.floor(Date.now()/1000) + (ttl||1800) })); } catch(_) {}
    }
    function ensureSession(cb){
        if (sessionToken) return cb(sessionToken);
        sessionToken = loadSession();
        if (sessionToken) return cb(sessionToken);
        // Mint a session token bound to this embedding origin. The
        // server validates Origin against the companion allow-list,
        // so a cross-origin caller without a valid session can't
        // burn the owner's credits even if the public id leaks.
        fetch(sessionUrl, { method:'POST', credentials:'omit', headers:{'Content-Type':'application/json','Accept':'application/json'} })
            .then(function(r){ return r.json().then(function(d){ return {status:r.status,data:d}; }); })
            .then(function(res){ if (res.status===200 && res.data && res.data.session_token) { sessionToken = res.data.session_token; saveSession(sessionToken, res.data.expires_in||1800); } cb(sessionToken); })
            .catch(function(){ cb(null); });
    }

    var accent      = script.getAttribute('data-accent')      || '#3d6bff';
    var position    = script.getAttribute('data-position')    || 'bottom-right';
    var launcherTxt = script.getAttribute('data-label')       || 'Chat';
    var greeting    = script.getAttribute('data-greeting')    || '';
    var placeholder = script.getAttribute('data-placeholder') || 'Ask me anything…';
    var theme       = script.getAttribute('data-theme')       || 'auto';
    var avatarUrl   = script.getAttribute('data-avatar')      || '';
    var showBranding = script.getAttribute('data-show-branding') !== '0';
    var brandText   = script.getAttribute('data-brand-text')  || '';
    var brandUrl    = script.getAttribute('data-brand-url')   || '';

    function visitorToken() {
        try {
            var t = localStorage.getItem(storageKey);
            if (t) return t;
        } catch (_) { /* private mode */ }
        return '';
    }
    function rememberToken(t) {
        try { if (t) localStorage.setItem(storageKey, t); } catch (_) {}
    }

    var prefersDark = false;
    try { prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches; } catch (_) {}
    var dark = theme === 'dark' || (theme === 'auto' && prefersDark);

    var styles = [
        '.imc-root,.imc-root *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}',
        '.imc-root{position:fixed;z-index:2147483000;}',
        '.imc-root.imc-bottom-right{right:18px;bottom:18px;}',
        '.imc-root.imc-bottom-left{left:18px;bottom:18px;}',
        '.imc-launcher{display:flex;align-items:center;gap:8px;padding:12px 16px;border:0;border-radius:9999px;color:#fff;font-weight:600;cursor:pointer;box-shadow:0 12px 32px rgba(0,0,0,.18);font-size:14px;}',
        '.imc-launcher:hover{filter:brightness(1.05);}',
        '.imc-bubble{position:absolute;bottom:64px;max-width:240px;padding:10px 12px;border-radius:14px;background:#fff;color:#111;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.12);}',
        '.imc-root.imc-bottom-right .imc-bubble{right:0;}',
        '.imc-root.imc-bottom-left  .imc-bubble{left:0;}',
        '.imc-bubble-close{position:absolute;top:-8px;right:-8px;width:20px;height:20px;border-radius:9999px;border:0;background:#111;color:#fff;font-size:11px;cursor:pointer;}',
        '.imc-panel{position:absolute;bottom:64px;width:360px;max-width:calc(100vw - 24px);height:520px;max-height:calc(100vh - 80px);display:flex;flex-direction:column;border-radius:18px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.22);background:#fff;color:#111;border:1px solid rgba(0,0,0,.06);}',
        '.imc-root.imc-bottom-right .imc-panel{right:0;}',
        '.imc-root.imc-bottom-left  .imc-panel{left:0;}',
        '.imc-dark .imc-panel{background:#0b0b10;color:#f5f5f7;border-color:rgba(255,255,255,.08);}',
        '.imc-header{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;color:#fff;}',
        '.imc-header h3{margin:0;font-size:14px;font-weight:600;}',
        '.imc-close{background:transparent;border:0;color:#fff;font-size:18px;cursor:pointer;}',
        '.imc-body{flex:1;overflow:auto;padding:12px 14px;background:transparent;}',
        '.imc-msg{max-width:82%;padding:9px 12px;border-radius:14px;margin-bottom:8px;font-size:13px;line-height:1.4;white-space:pre-wrap;word-wrap:break-word;}',
        '.imc-msg.imc-u{margin-left:auto;background:#eef2ff;color:#111;border-bottom-right-radius:4px;}',
        '.imc-dark .imc-msg.imc-u{background:rgba(61,107,255,.18);color:#f5f5f7;}',
        '.imc-msg.imc-a{background:#f5f5f7;color:#111;border-bottom-left-radius:4px;}',
        '.imc-dark .imc-msg.imc-a{background:rgba(255,255,255,.06);color:#f5f5f7;}',
        '.imc-msg.imc-e{background:#fef2f2;color:#991b1b;}',
        '.imc-cite{margin-top:6px;font-size:10px;opacity:.6;}',
        '.imc-input-row{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(0,0,0,.08);background:#fff;}',
        '.imc-dark .imc-input-row{background:#0b0b10;border-color:rgba(255,255,255,.08);}',
        '.imc-input-row textarea{flex:1;border:1px solid rgba(0,0,0,.12);border-radius:12px;padding:8px 10px;font-size:13px;resize:none;height:40px;background:#fff;color:#111;outline:none;}',
        '.imc-dark .imc-input-row textarea{background:#15151c;color:#f5f5f7;border-color:rgba(255,255,255,.12);}',
        '.imc-input-row button{border:0;border-radius:12px;color:#fff;padding:0 14px;font-size:13px;font-weight:600;cursor:pointer;}',
        '.imc-input-row button[disabled]{opacity:.5;cursor:not-allowed;}',
        '.imc-foot{font-size:10px;text-align:center;padding:6px;opacity:.55;}',
        '.imc-foot a{color:inherit;text-decoration:underline;}',
        '.imc-typing{display:inline-flex;gap:4px;padding:0 4px;}',
        '.imc-typing span{width:6px;height:6px;border-radius:9999px;background:currentColor;opacity:.4;animation:imc-blink 1s infinite;}',
        '.imc-typing span:nth-child(2){animation-delay:.15s;}',
        '.imc-typing span:nth-child(3){animation-delay:.3s;}',
        '@keyframes imc-blink{0%,80%,100%{opacity:.2;}40%{opacity:1;}}',
    ].join('\n');

    var styleEl = document.createElement('style');
    styleEl.appendChild(document.createTextNode(styles));
    document.head.appendChild(styleEl);

    var root = document.createElement('div');
    root.className = 'imc-root imc-' + (position === 'bottom-left' ? 'bottom-left' : 'bottom-right') + (dark ? ' imc-dark' : '');
    document.body.appendChild(root);

    var launcher = document.createElement('button');
    launcher.className = 'imc-launcher';
    launcher.style.background = accent;
    var launcherIcon = avatarUrl
        ? '<img src="' + escapeAttr(avatarUrl) + '" alt="" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">'
        : '<span aria-hidden="true">💬</span>';
    launcher.innerHTML = launcherIcon + '<span>' + escapeHtml(launcherTxt) + '</span>';
    root.appendChild(launcher);

    var bubble = null;
    if (greeting) {
        bubble = document.createElement('div');
        bubble.className = 'imc-bubble';
        bubble.innerHTML = escapeHtml(greeting) + '<button class="imc-bubble-close" aria-label="Dismiss">×</button>';
        root.appendChild(bubble);
        bubble.querySelector('.imc-bubble-close').addEventListener('click', function (e) {
            e.stopPropagation();
            bubble.remove();
            bubble = null;
        });
    }

    var panel = document.createElement('div');
    panel.className = 'imc-panel';
    panel.style.display = 'none';
    panel.innerHTML =
        '<div class="imc-header" style="background:' + accent + '">'
        +   '<h3>' + escapeHtml(launcherTxt) + '</h3>'
        +   '<button class="imc-close" aria-label="Close">×</button>'
        + '</div>'
        + '<div class="imc-body" role="log" aria-live="polite"></div>'
        + '<form class="imc-input-row">'
        +   '<textarea placeholder="' + escapeAttr(placeholder) + '" rows="1" aria-label="Message"></textarea>'
        +   '<button type="submit" style="background:' + accent + '">Send</button>'
        + '</form>'
        + footerHtml();
    root.appendChild(panel);

    var body  = panel.querySelector('.imc-body');
    var form  = panel.querySelector('form');
    var input = panel.querySelector('textarea');
    var send  = panel.querySelector('button[type=submit]');

    function open() {
        panel.style.display = 'flex';
        if (bubble) { bubble.remove(); bubble = null; }
        setTimeout(function () { try { input.focus(); } catch (_) {} }, 50);
    }
    function close() { panel.style.display = 'none'; }
    launcher.addEventListener('click', function () {
        panel.style.display === 'flex' ? close() : open();
    });
    panel.querySelector('.imc-close').addEventListener('click', close);

    function appendMessage(role, text, citations) {
        var d = document.createElement('div');
        d.className = 'imc-msg imc-' + (role === 'user' ? 'u' : (role === 'error' ? 'e' : 'a'));
        d.textContent = text;
        if (citations && citations.length) {
            var c = document.createElement('div');
            c.className = 'imc-cite';
            c.textContent = 'Sources: ' + citations.map(function (x) { return x.title || x.type || ''; }).filter(Boolean).join(', ');
            d.appendChild(c);
        }
        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
        return d;
    }
    function appendTyping() {
        var d = document.createElement('div');
        d.className = 'imc-msg imc-a';
        d.innerHTML = '<span class="imc-typing"><span></span><span></span><span></span></span>';
        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
        return d;
    }

    var sending = false;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sending) return;
        var text = (input.value || '').trim();
        if (!text) return;
        appendMessage('user', text);
        input.value = '';
        sending = true;
        send.disabled = true;
        var typing = appendTyping();

        ensureSession(function(tok){
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ message: text, visitor_token: visitorToken(), session_token: tok }),
            credentials: 'omit',
            mode: 'cors',
        }).then(function (r) {
            return r.json().then(function (data) { return { status: r.status, data: data }; });
        }).then(function (res) {
            typing.remove();
            var data = res.data || {};
            if (res.status === 200 && data.ok) {
                rememberToken(data.visitor_token);
                appendMessage('assistant', data.answer || '', data.citations || [], data.message_id);
            } else {
                if (res.status === 403) { sessionToken = null; try { sessionStorage.removeItem(sessionKey); } catch(_){} }
                appendMessage('error', data.error || 'Sorry, something went wrong.');
            }
        }).catch(function () {
            typing.remove();
            appendMessage('error', 'Network error. Please try again.');
        }).finally(function () {
            sending = false;
            send.disabled = false;
            try { input.focus(); } catch (_) {}
        });
        }); // ensureSession
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function escapeAttr(s) { return escapeHtml(s); }

    // Footer mirrors the server-side brandingConfig() resolution: hidden
    // when branding is off, custom text/link when set, else the default.
    function footerHtml() {
        if (!showBranding) return '';
        if (brandText) {
            var label = escapeHtml(brandText);
            var inner = brandUrl
                ? '<a href="' + escapeAttr(brandUrl) + '" target="_blank" rel="noopener">' + label + '</a>'
                : label;
            return '<div class="imc-foot">' + inner + '</div>';
        }
        return '<div class="imc-foot">Powered by Sayzio AI Companion</div>';
    }
})();
