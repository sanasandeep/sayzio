/*! 1inme social-proof widget runtime — vanilla JS, no deps */
(function () {
  if (window.__1inmeSPRuntime) return;
  window.__1inmeSPRuntime = true;

  var RUNTIME = {
    instances: {},
    boot: function (cfg) { boot(cfg); }
  };

  // Drain queue from loader bootstrap
  window.__1inmeSP = window.__1inmeSP || { queue: [] };
  window.__1inmeSP.loaded = true;
  window.__1inmeSP.boot = RUNTIME.boot;
  (window.__1inmeSP.queue || []).forEach(boot);
  window.__1inmeSP.queue = [];

  function boot(cfg) {
    if (!cfg || !cfg.uuid) return;
    if (RUNTIME.instances[cfg.uuid]) return;
    RUNTIME.instances[cfg.uuid] = { cfg: cfg, shown: 0 };
    fetch(cfg.configUrl, { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (config) { run(cfg, config); })
      .catch(function () { /* silent */ });
  }

  function run(cfg, config) {
    if (!config || config.error) return;

    if (!matchesTargeting(config.targeting || {})) return;

    injectStyles();

    var container = makeContainer(config.design || {});
    document.body.appendChild(container);

    var t = config.type;
    if (t === 'visitor_count')      renderVisitorCount(cfg, container, config);
    else if (t === 'conversion_count') renderConversionCount(cfg, container, config);
    else if (t === 'email_signup')  renderEmailSignup(cfg, container, config);
    else if (t === 'countdown')     renderCountdown(cfg, container, config);
    else if (t === 'review')        renderReview(cfg, container, config);
    else if (t === 'custom_html')   renderCustomHtml(cfg, container, config);
    else                            renderRecentActivity(cfg, container, config); // default
  }

  /* -------- targeting -------- */
  function matchesTargeting(t) {
    var devices = t.devices || ['desktop','tablet','mobile'];
    var d = detectDevice();
    if (devices.indexOf(d) === -1) return false;

    var path = location.pathname || '/';
    var inc = (t.pages_include || []).filter(Boolean);
    var exc = (t.pages_exclude || []).filter(Boolean);
    if (inc.length && !inc.some(function (p) { return matchPath(path, p); })) return false;
    if (exc.length &&  exc.some(function (p) { return matchPath(path, p); })) return false;
    return true;
  }
  function matchPath(path, pattern) {
    // simple glob: '*' matches any chars
    var re = new RegExp('^' + pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
    return re.test(path);
  }
  function detectDevice() {
    var ua = (navigator.userAgent || '').toLowerCase();
    if (/tablet|ipad/.test(ua)) return 'tablet';
    if (/mobile|android|iphone|ipod/.test(ua)) return 'mobile';
    return 'desktop';
  }

  /* -------- container & styles -------- */
  function makeContainer(d) {
    var c = document.createElement('div');
    c.className = '__1inme_sp __1inme_sp_' + (d.position || 'bottom-left') + ' __1inme_sp_anim_' + (d.animation || 'slide-up') + ' __1inme_sp_' + (d.theme || 'light');
    if (d.shadow) c.classList.add('__1inme_sp_shadow');
    var radius = ({sm:'6px',md:'10px',lg:'14px',xl:'20px',full:'999px'})[d.rounded || 'lg'];
    c.style.setProperty('--sp-accent', d.accent || '#7c3aed');
    c.style.setProperty('--sp-radius', radius);
    return c;
  }

  function injectStyles() {
    if (document.getElementById('__1inme_sp_styles')) return;
    var css = `
.__1inme_sp{position:fixed;z-index:2147483647;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.4;max-width:340px;width:calc(100% - 32px);box-sizing:border-box}
.__1inme_sp_bottom-left{left:16px;bottom:16px}
.__1inme_sp_bottom-right{right:16px;bottom:16px}
.__1inme_sp_top-left{left:16px;top:16px}
.__1inme_sp_top-right{right:16px;top:16px}
.__1inme_sp .__sp_card{background:#fff;color:#111;border-radius:var(--sp-radius);padding:12px 14px;display:flex;gap:10px;align-items:center;border:1px solid rgba(0,0,0,.06);position:relative;cursor:pointer}
.__1inme_sp_dark .__sp_card{background:#111;color:#f5f5f5;border-color:rgba(255,255,255,.08)}
.__1inme_sp_shadow .__sp_card{box-shadow:0 12px 30px rgba(0,0,0,.18)}
.__1inme_sp .__sp_img{width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0;background:rgba(0,0,0,.05)}
.__1inme_sp .__sp_body{flex:1;min-width:0}
.__1inme_sp .__sp_title{font-weight:600;font-size:13.5px;margin:0 0 2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.__1inme_sp .__sp_text{font-size:12.5px;opacity:.85;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.__1inme_sp .__sp_meta{font-size:11px;opacity:.55;margin-top:3px}
.__1inme_sp .__sp_close{position:absolute;top:6px;right:8px;background:none;border:0;font-size:14px;cursor:pointer;color:inherit;opacity:.5;padding:2px 6px;line-height:1}
.__1inme_sp .__sp_close:hover{opacity:1}
.__1inme_sp .__sp_btn{background:var(--sp-accent);color:#fff;border:0;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.__1inme_sp .__sp_input{flex:1;border:1px solid rgba(0,0,0,.1);background:rgba(0,0,0,.03);border-radius:8px;padding:7px 10px;font:inherit;outline:none;min-width:0}
.__1inme_sp_dark .__sp_input{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1);color:#fff}
.__1inme_sp .__sp_dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.4);animation:__sp_pulse 1.6s infinite;margin-right:6px;vertical-align:middle}
@keyframes __sp_pulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5)}70%{box-shadow:0 0 0 10px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
.__1inme_sp_anim_slide-up{transform:translateY(120%);opacity:0;transition:transform .35s ease,opacity .35s ease}
.__1inme_sp_anim_slide-up.__sp_in{transform:translateY(0);opacity:1}
.__1inme_sp_anim_fade{opacity:0;transition:opacity .25s ease}
.__1inme_sp_anim_fade.__sp_in{opacity:1}
.__1inme_sp_anim_zoom{transform:scale(.85);opacity:0;transition:transform .25s ease,opacity .25s ease}
.__1inme_sp_anim_zoom.__sp_in{transform:scale(1);opacity:1}
.__1inme_sp_stars{color:#f59e0b;letter-spacing:1px;font-size:13px}
`;
    var s = document.createElement('style');
    s.id = '__1inme_sp_styles';
    s.appendChild(document.createTextNode(css));
    document.head.appendChild(s);
  }

  /* -------- track + show/hide helpers -------- */
  function track(cfg, kind) {
    try {
      navigator.sendBeacon
        ? navigator.sendBeacon(cfg.trackUrl, new Blob(
            [JSON.stringify({kind: kind, page_url: location.href})],
            {type: 'application/json'}
          ))
        : fetch(cfg.trackUrl, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({kind:kind,page_url:location.href}),credentials:'omit'});
    } catch (e) {}
  }

  function showFor(container, durationMs, onClose) {
    requestAnimationFrame(function () { container.classList.add('__sp_in'); });
    var timer = setTimeout(close, Math.max(2000, durationMs));
    function close() {
      clearTimeout(timer);
      container.classList.remove('__sp_in');
      setTimeout(function () {
        if (container.parentNode) container.parentNode.removeChild(container);
        onClose && onClose();
      }, 350);
    }
    return close;
  }

  function withCloseButton(card, design, closer) {
    if (design.show_close === false) return;
    var btn = document.createElement('button');
    btn.className = '__sp_close';
    btn.setAttribute('aria-label', 'Close');
    btn.innerHTML = '&times;';
    btn.addEventListener('click', function (e) { e.stopPropagation(); closer(); });
    card.appendChild(btn);
  }

  /* -------- renderers -------- */

  function renderRecentActivity(cfg, container, config) {
    var items = (config.items || []).slice();
    if (!items.length && config.settings && config.settings.simulated_pool) {
      items = config.settings.simulated_pool;
    }
    if (!items.length) {
      // fallback demo so users see it works even before they curate any items
      items = [{name:'Sample User', location:'New York', action:'just signed up', time_label:'2 minutes ago'}];
    }
    var t = config.targeting || {};
    var idx = 0;
    var maxShows = +(t.max_per_session || 0);
    var shown = 0;
    var design = config.design || {};

    function showOne() {
      if (maxShows && shown >= maxShows) return;
      var it = items[idx % items.length]; idx++; shown++;

      // Re-create container each show so animations re-fire
      var cont = makeContainer(design);
      var card = document.createElement('div'); card.className = '__sp_card';
      var img = document.createElement('img'); img.className = '__sp_img';
      img.src = it.image_url || dataAvatarFor(it.name);
      img.alt = '';
      card.appendChild(img);
      var body = document.createElement('div'); body.className = '__sp_body';
      var title = document.createElement('p'); title.className = '__sp_title';
      title.textContent = renderTpl(config.settings && config.settings.title_template || '{name} from {location}', it);
      body.appendChild(title);
      var text = document.createElement('p'); text.className = '__sp_text';
      text.textContent = renderTpl(config.settings && config.settings.body_template || '{action}', it);
      body.appendChild(text);
      var meta = document.createElement('div'); meta.className = '__sp_meta';
      meta.innerHTML = '<span class="__sp_dot"></span>' + (it.time_label || 'just now');
      body.appendChild(meta);
      card.appendChild(body);

      cont.appendChild(card);
      document.body.appendChild(cont);
      track(cfg, 'impression');
      var closer = showFor(cont, (+t.duration || 5) * 1000);
      withCloseButton(card, design, closer);
      card.addEventListener('click', function () {
        track(cfg, 'click');
        if (it.link_url) window.open(it.link_url, '_blank');
      });
    }

    setTimeout(function () {
      showOne();
      var iv = setInterval(function () {
        if (maxShows && shown >= maxShows) { clearInterval(iv); return; }
        showOne();
      }, Math.max(3, +t.interval || 8) * 1000);
    }, Math.max(0, +t.delay || 3) * 1000);
  }

  function renderVisitorCount(cfg, container, config) {
    var s = config.settings || {};
    var design = config.design || {};
    var card = document.createElement('div'); card.className = '__sp_card';
    var body = document.createElement('div'); body.className = '__sp_body';
    var title = document.createElement('p'); title.className = '__sp_title';
    title.innerHTML = '<span class="__sp_dot"></span>' + (s.text || '{count} people are viewing this page').replace('{count}', String(config.live_visitors || 0));
    body.appendChild(title);
    card.appendChild(body);
    container.appendChild(card);
    document.body.appendChild(container);
    track(cfg, 'impression');
    var closer = showFor(container, ((+config.targeting?.duration) || 8) * 1000);
    withCloseButton(card, design, closer);
  }

  function renderConversionCount(cfg, container, config) {
    var s = config.settings || {};
    var design = config.design || {};
    var card = document.createElement('div'); card.className = '__sp_card';
    var body = document.createElement('div'); body.className = '__sp_body';
    var p = document.createElement('p'); p.className = '__sp_title';
    p.textContent = (s.text || '{count} people purchased recently').replace('{count}', String(s.count || 0));
    body.appendChild(p);
    card.appendChild(body);
    container.appendChild(card);
    document.body.appendChild(container);
    track(cfg, 'impression');
    var closer = showFor(container, ((+config.targeting?.duration) || 6) * 1000);
    withCloseButton(card, design, closer);
  }

  function renderEmailSignup(cfg, container, config) {
    var s = config.settings || {};
    var design = config.design || {};
    var card = document.createElement('div'); card.className = '__sp_card'; card.style.flexDirection = 'column'; card.style.alignItems = 'stretch'; card.style.cursor = 'default';
    var title = document.createElement('p'); title.className = '__sp_title'; title.textContent = s.title || 'Subscribe';
    var text  = document.createElement('p'); text.className  = '__sp_text';  text.textContent  = s.body  || '';
    var row   = document.createElement('div'); row.style.display = 'flex'; row.style.gap = '6px'; row.style.marginTop = '8px';
    var input = document.createElement('input'); input.type = 'email'; input.placeholder = 'you@example.com'; input.className = '__sp_input';
    var btn   = document.createElement('button'); btn.className = '__sp_btn'; btn.textContent = s.cta || 'Subscribe';
    row.appendChild(input); row.appendChild(btn);
    card.appendChild(title); if (s.body) card.appendChild(text); card.appendChild(row);
    container.appendChild(card);
    document.body.appendChild(container);
    track(cfg, 'impression');
    var closer = showFor(container, 60 * 1000);
    withCloseButton(card, design, closer);
    btn.addEventListener('click', function () {
      if (!input.value || input.value.indexOf('@') === -1) { input.focus(); return; }
      track(cfg, 'conversion');
      btn.textContent = '✓ Subscribed';
      btn.disabled = true;
      input.disabled = true;
      setTimeout(closer, 1500);
    });
  }

  function renderCountdown(cfg, container, config) {
    var s = config.settings || {};
    var design = config.design || {};
    var endsAt = new Date(s.ends_at || Date.now() + 3600000).getTime();
    var card = document.createElement('div'); card.className = '__sp_card';
    var body = document.createElement('div'); body.className = '__sp_body';
    var title = document.createElement('p'); title.className = '__sp_title'; title.textContent = s.title || 'Limited offer ends in';
    var time  = document.createElement('p'); time.className  = '__sp_text';  time.style.fontSize = '18px'; time.style.fontWeight = '700'; time.style.color = 'var(--sp-accent)';
    body.appendChild(title); body.appendChild(time); card.appendChild(body); container.appendChild(card);
    document.body.appendChild(container);
    track(cfg, 'impression');
    var closer = showFor(container, 24 * 3600 * 1000);
    withCloseButton(card, design, closer);
    function tick() {
      var diff = endsAt - Date.now();
      if (diff <= 0) { time.textContent = s.expired_text || 'Expired'; clearInterval(iv); return; }
      var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000), m = Math.floor((diff%3600000)/60000), sec = Math.floor((diff%60000)/1000);
      time.textContent = (d?d+'d ':'') + pad(h)+':'+pad(m)+':'+pad(sec);
    }
    function pad(n){ return (n<10?'0':'')+n; }
    tick(); var iv = setInterval(tick, 1000);
  }

  function renderReview(cfg, container, config) {
    var items = (config.settings && config.settings.items) || [];
    if (!items.length) items = [{author:'Customer', text:'Loved it!', rating:5}];
    var design = config.design || {};
    var idx = 0;
    function showOne() {
      var it = items[idx % items.length]; idx++;
      var cont = makeContainer(design);
      var card = document.createElement('div'); card.className = '__sp_card';
      var body = document.createElement('div'); body.className = '__sp_body';
      var stars = document.createElement('div'); stars.className = '__1inme_sp_stars';
      var r = Math.max(0, Math.min(5, +it.rating || 5));
      stars.textContent = '★★★★★☆☆☆☆☆'.slice(5-r, 10-r);
      var text = document.createElement('p'); text.className = '__sp_text'; text.textContent = '“' + (it.text || '') + '”';
      var meta = document.createElement('div'); meta.className = '__sp_meta'; meta.textContent = '— ' + (it.author || 'Customer');
      body.appendChild(stars); body.appendChild(text); body.appendChild(meta);
      card.appendChild(body); cont.appendChild(card);
      document.body.appendChild(cont);
      track(cfg, 'impression');
      var closer = showFor(cont, ((+config.targeting?.duration) || 7) * 1000);
      withCloseButton(card, design, closer);
    }
    setTimeout(function () {
      showOne();
      if ((config.settings || {}).rotate !== false) {
        setInterval(showOne, Math.max(4, +(config.targeting?.interval || 10)) * 1000);
      }
    }, Math.max(0, +(config.targeting?.delay || 3)) * 1000);
  }

  function renderCustomHtml(cfg, container, config) {
    var s = config.settings || {};
    var design = config.design || {};
    var card = document.createElement('div'); card.className = '__sp_card'; card.style.padding = '0';
    var wrap = document.createElement('div'); wrap.innerHTML = sanitizeHtml(s.html || '');
    card.appendChild(wrap);
    container.appendChild(card);
    document.body.appendChild(container);
    track(cfg, 'impression');
    var closer = showFor(container, 30 * 1000);
    withCloseButton(card, design, closer);
  }

  /* -------- utils -------- */
  function renderTpl(tpl, ctx) {
    return String(tpl || '').replace(/\{(\w+)\}/g, function (_, k) {
      return (ctx[k] != null ? String(ctx[k]) : '');
    });
  }
  function dataAvatarFor(name) {
    var initials = (name || '?').split(/\s+/).slice(0,2).map(function(s){return s[0]||'';}).join('').toUpperCase();
    var colors = ['#7c3aed','#ec4899','#06b6d4','#10b981','#f59e0b','#ef4444','#3b82f6'];
    var bg = colors[(name||'').length % colors.length];
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42"><rect width="100%" height="100%" rx="6" fill="'+bg+'"/><text x="50%" y="55%" font-family="sans-serif" font-size="16" font-weight="700" fill="#fff" text-anchor="middle" dominant-baseline="middle">'+initials+'</text></svg>';
    return 'data:image/svg+xml;base64,' + btoa(svg);
  }
  function sanitizeHtml(html) {
    // Defense-in-depth: parse with DOMParser, walk tree, drop dangerous tags +
    // strip on* event handlers + reject javascript:/vbscript:/data: URIs on
    // navigational/embed attributes. Server already sanitizes; this is belt+braces.
    var BAD_TAGS = {SCRIPT:1,IFRAME:1,OBJECT:1,EMBED:1,LINK:1,META:1,STYLE:1,FORM:1,BASE:1};
    var URL_ATTRS = {href:1,src:1,action:1,formaction:1,background:1,cite:1,poster:1,data:1};
    try {
      var doc = new DOMParser().parseFromString('<div>' + String(html || '') + '</div>', 'text/html');
      var root = doc.body.firstChild;
      if (!root) return '';
      var walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT, null);
      var nodes = []; var n;
      while ((n = walker.nextNode())) nodes.push(n);
      nodes.forEach(function (el) {
        if (BAD_TAGS[el.tagName]) { el.parentNode && el.parentNode.removeChild(el); return; }
        // Remove every attribute whose name starts with "on" or whose value
        // is a dangerous URI scheme on a navigational attr.
        for (var i = el.attributes.length - 1; i >= 0; i--) {
          var attr = el.attributes[i];
          var name = attr.name.toLowerCase();
          if (name.indexOf('on') === 0) { el.removeAttribute(attr.name); continue; }
          if (URL_ATTRS[name]) {
            var v = (attr.value || '').replace(/\s+/g, '').toLowerCase();
            if (v.indexOf('javascript:') === 0 || v.indexOf('vbscript:') === 0 || v.indexOf('data:') === 0) {
              el.removeAttribute(attr.name);
            }
          }
        }
      });
      return root.innerHTML;
    } catch (e) {
      // Fallback to regex if DOMParser misbehaves
      var s = String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
      s = s.replace(/<(iframe|object|embed|form|meta|link|style)\b[\s\S]*?<\/\1>/gi, '');
      s = s.replace(/<(iframe|object|embed|form|meta|link|style)\b[^>]*\/?>(?!)?/gi, '');
      s = s.replace(/\son[a-z]+\s*=\s*"[^"]*"/gi, '');
      s = s.replace(/\son[a-z]+\s*=\s*'[^']*'/gi, '');
      s = s.replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
      s = s.replace(/(href|src|action|formaction|background|cite|poster|data)\s*=\s*("|')\s*(javascript|vbscript|data)\s*:/gi, '$1=$2#');
      return s;
    }
  }
})();
