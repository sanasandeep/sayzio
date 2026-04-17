/*! 1inme social-proof widget runtime — vanilla JS, no deps */
(function () {
  if (window.__1inmeSPRuntime) return;
  window.__1inmeSPRuntime = true;

  var RUNTIME = {
    instances: {},                 // uuid -> { cfg, config, cleanups: [] }
    boot: function (cfg) { boot(cfg); },
    renderDraft: function (uuid, config, cfg) { renderDraft(uuid, config, cfg); }
  };

  // Drain queue from loader bootstrap
  window.__1inmeSP = window.__1inmeSP || { queue: [] };
  window.__1inmeSP.loaded = true;
  window.__1inmeSP.boot = RUNTIME.boot;
  window.__1inmeSP.renderDraft = RUNTIME.renderDraft;
  (window.__1inmeSP.queue || []).forEach(boot);
  window.__1inmeSP.queue = [];

  function boot(cfg) {
    if (!cfg || !cfg.uuid) return;
    if (RUNTIME.instances[cfg.uuid]) return;
    RUNTIME.instances[cfg.uuid] = { cfg: cfg, config: null, cleanups: [] };
    fetch(cfg.configUrl, { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (config) {
        RUNTIME.instances[cfg.uuid].config = config;
        run(cfg, config);
      })
      .catch(function () { /* silent */ });
  }

  /**
   * Editor-only: re-render the widget from an in-memory config object,
   * wiping any existing widgets + trigger listeners first.
   */
  function renderDraft(uuid, config, cfg) {
    cfg = cfg || { uuid: uuid, trackUrl: '/sp/' + uuid + '/track', preview: true };
    cfg.uuid = uuid;
    cfg.preview = true;
    // Resolve optional mount target (Element | CSS selector). When provided, ALL
    // rendered widgets are appended into this element with absolute positioning
    // contained inside it (so previews stay inside the editor's preview pane
    // rather than landing on the host page).
    var mountEl = cfg.mountTo || null;
    if (typeof mountEl === 'string') mountEl = document.querySelector(mountEl);
    cfg.mountEl = (mountEl && mountEl.nodeType === 1) ? mountEl : null;

    var inst = RUNTIME.instances[uuid] = RUNTIME.instances[uuid] || { cfg: cfg, config: null, cleanups: [] };
    inst.cfg = cfg;
    // Cleanup previous render
    (inst.cleanups || []).forEach(function (fn) { try { fn(); } catch (e) {} });
    inst.cleanups = [];
    // Wipe widgets for this uuid wherever they live (body OR previous mount)
    document.querySelectorAll('.__1inme_sp[data-uuid="' + cssEsc(uuid) + '"]').forEach(function (n) {
      n.parentNode && n.parentNode.removeChild(n);
    });
    if (cfg.mountEl) {
      // Make sure the mount node is a positioning context so position:fixed children
      // become effectively contained within it (we override fixed -> absolute below).
      var cs = window.getComputedStyle(cfg.mountEl);
      if (cs.position === 'static') cfg.mountEl.style.position = 'relative';
      cfg.mountEl.style.overflow = cfg.mountEl.style.overflow || 'hidden';
    }
    inst.config = config;
    run(cfg, config);
  }

  /**
   * Mount a rendered widget container in the right place. In preview mode with
   * a mountEl we append into that element and convert position:fixed -> absolute
   * so the preview is visually contained.
   */
  function mountWidget(cont, cfg) {
    if (cfg && cfg.mountEl) {
      cont.style.position = 'absolute';
      cont.style.zIndex = '5';
      cfg.mountEl.appendChild(cont);
    } else {
      mountWidget(cont, cfg);
    }
  }

  function cssEsc(s) { return String(s).replace(/[^a-zA-Z0-9_-]/g, ''); }

  function run(cfg, config) {
    if (!config || config.error) return;
    if (!matchesTargeting(config.targeting || {})) return;

    injectStyles();

    var inst = RUNTIME.instances[cfg.uuid];
    var notifications = (config.notifications || []).filter(function (n) { return n && n.is_active !== false; });
    if (!notifications.length) return;

    notifications.forEach(function (n) {
      registerNotification(cfg, config, n, inst);
    });
  }

  /* -------- targeting (campaign-level) -------- */
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
    var re = new RegExp('^' + pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
    return re.test(path);
  }
  function detectDevice() {
    var ua = (navigator.userAgent || '').toLowerCase();
    if (/tablet|ipad/.test(ua)) return 'tablet';
    if (/mobile|android|iphone|ipod/.test(ua)) return 'mobile';
    return 'desktop';
  }

  /* -------- triggers -------- */
  function registerNotification(cfg, config, n, inst) {
    var triggers = (n.triggers && n.triggers.length) ? n.triggers : [{kind: 'on_load', params: {}}];
    var logic = (n.triggers_logic === 'and') ? 'and' : 'or';
    var fired = {};
    var done = false;

    function fire() {
      if (done) return;
      if (logic === 'and') {
        for (var i = 0; i < triggers.length; i++) if (!fired[i]) return;
      }
      done = true;
      // Cleanup any remaining listeners for this notification
      (n.__cleanups || []).forEach(function (fn) { try { fn(); } catch (e) {} });
      n.__cleanups = [];
      try { showNotification(cfg, config, n); } catch (e) { /* swallow */ }
    }

    n.__cleanups = [];
    triggers.forEach(function (tr, idx) {
      var cleanup = setupTrigger(tr, function () {
        fired[idx] = true;
        fire();
      });
      if (cleanup) {
        n.__cleanups.push(cleanup);
        inst.cleanups.push(cleanup);
      }
    });
  }

  function setupTrigger(tr, cb) {
    var k = tr.kind, p = tr.params || {};
    if (k === 'on_load') {
      var t = setTimeout(cb, 0);
      return function () { clearTimeout(t); };
    }
    if (k === 'after_delay') {
      var sec = Math.max(0, +p.seconds || 0);
      var t1 = setTimeout(cb, sec * 1000);
      return function () { clearTimeout(t1); };
    }
    if (k === 'on_scroll') {
      var pct = Math.max(1, Math.min(100, +p.percent || 50));
      function onScroll() {
        var doc = document.documentElement;
        var scrolled = (window.scrollY || doc.scrollTop || 0);
        var total = (doc.scrollHeight - window.innerHeight) || 1;
        if ((scrolled / total) * 100 >= pct) { window.removeEventListener('scroll', onScroll); cb(); }
      }
      window.addEventListener('scroll', onScroll, { passive: true });
      return function () { window.removeEventListener('scroll', onScroll); };
    }
    if (k === 'on_exit_intent') {
      function onExit(e) {
        if (e.clientY <= 0 || e.relatedTarget === null) {
          document.removeEventListener('mouseout', onExit);
          cb();
        }
      }
      document.addEventListener('mouseout', onExit);
      return function () { document.removeEventListener('mouseout', onExit); };
    }
    if (k === 'on_click') {
      var sel = String(p.selector || '');
      if (!sel) return null;
      function onClick(e) {
        var el = e.target;
        while (el && el !== document.body) {
          try { if (el.matches && el.matches(sel)) { document.removeEventListener('click', onClick, true); cb(); return; } } catch (err) {}
          el = el.parentNode;
        }
      }
      document.addEventListener('click', onClick, true);
      return function () { document.removeEventListener('click', onClick, true); };
    }
    if (k === 'after_idle') {
      var idleSec = Math.max(1, +p.seconds || 5);
      var idleT;
      function reset() { clearTimeout(idleT); idleT = setTimeout(function () { cleanup(); cb(); }, idleSec * 1000); }
      function cleanup() {
        clearTimeout(idleT);
        ['mousemove','keydown','scroll','touchstart'].forEach(function (e) { window.removeEventListener(e, reset, { passive: true }); });
      }
      ['mousemove','keydown','scroll','touchstart'].forEach(function (e) { window.addEventListener(e, reset, { passive: true }); });
      reset();
      return cleanup;
    }
    if (k === 'url_contains') {
      var sub = String(p.text || '');
      if (sub && location.href.indexOf(sub) !== -1) {
        var t2 = setTimeout(cb, 0);
        return function () { clearTimeout(t2); };
      }
      return null;
    }
    return null;
  }

  /* -------- container & styles -------- */
  function effectiveDesign(config, n) {
    var base = config.design || {};
    var ov   = (n && n.design_override) || {};
    return {
      position:   ov.position   || base.position   || 'bottom-left',
      theme:      ov.theme      || base.theme      || 'light',
      accent:     ov.accent     || base.accent     || '#7c3aed',
      rounded:    ov.rounded    || base.rounded    || 'lg',
      animation:  ov.animation  || base.animation  || 'slide-up',
      shadow:     (ov.shadow    !== undefined) ? !!ov.shadow    : !!base.shadow,
      show_close: (ov.show_close!== undefined) ? !!ov.show_close: (base.show_close !== false),
    };
  }

  function makeContainer(uuid, d, opts) {
    opts = opts || {};
    var c = document.createElement('div');
    c.setAttribute('data-uuid', uuid);
    var pos = opts.fullBar ? ('bar-' + (opts.barPlacement || 'top')) : (d.position || 'bottom-left');
    c.className = '__1inme_sp __1inme_sp_' + pos + ' __1inme_sp_anim_' + (d.animation || 'slide-up') + ' __1inme_sp_' + (d.theme || 'light');
    if (d.shadow) c.classList.add('__1inme_sp_shadow');
    if (opts.bubble) c.classList.add('__1inme_sp_bubble');
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
.__1inme_sp_bar-top,.__1inme_sp_bar-bottom{left:0;right:0;width:100%;max-width:none}
.__1inme_sp_bar-top{top:0}
.__1inme_sp_bar-bottom{bottom:0}
.__1inme_sp_bubble{width:auto;max-width:none}
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
.__1inme_sp .__sp_btn{background:var(--sp-accent);color:#fff;border:0;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.__1inme_sp .__sp_btn_ghost{background:transparent;color:inherit;border:1px solid rgba(0,0,0,.15);padding:6px 10px;border-radius:8px;font-size:12px;cursor:pointer}
.__1inme_sp_dark .__sp_btn_ghost{border-color:rgba(255,255,255,.2)}
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
.__1inme_sp_bar .__sp_card{border-radius:0;justify-content:center;padding:10px 16px;flex-wrap:wrap}
.__1inme_sp_bubble .__sp_card{border-radius:999px;padding:10px 16px;cursor:pointer}
.__1inme_sp_bubble_icon{width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.__1inme_sp_quote{font-size:14px;font-style:italic;line-height:1.5;margin:0 0 6px}
.__1inme_sp_video_thumb{position:relative;cursor:pointer;border-radius:var(--sp-radius);overflow:hidden;background:#000}
.__1inme_sp_video_thumb::after{content:"\u25B6";position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:36px;color:#fff;text-shadow:0 2px 6px rgba(0,0,0,.5)}
.__1inme_sp_video_modal{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2147483647;display:flex;align-items:center;justify-content:center;padding:20px}
.__1inme_sp_video_modal iframe{width:min(960px,100%);aspect-ratio:16/9;border:0;border-radius:8px}
.__1inme_sp_share_btns{display:flex;gap:8px;flex-wrap:wrap}
.__1inme_sp_share_btn{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;font-size:16px}
.__1inme_sp_thumb_btn{width:38px;height:38px;border-radius:50%;border:1px solid rgba(0,0,0,.1);background:#fff;cursor:pointer;font-size:16px;display:inline-flex;align-items:center;justify-content:center}
.__1inme_sp_dark .__sp_thumb_btn{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15);color:#fff}
.__1inme_sp_thumb_btn:hover{background:var(--sp-accent);color:#fff;border-color:var(--sp-accent)}
`;
    var s = document.createElement('style');
    s.id = '__1inme_sp_styles';
    s.appendChild(document.createTextNode(css));
    document.head.appendChild(s);
  }

  /* -------- track + show/hide -------- */
  function track(cfg, n, kind) {
    if (cfg.preview) return; // editor preview never tracks
    try {
      var body = JSON.stringify({kind: kind, page_url: location.href, notification_id: n && n.id});
      navigator.sendBeacon
        ? navigator.sendBeacon(cfg.trackUrl, new Blob([body], {type: 'application/json'}))
        : fetch(cfg.trackUrl, {method:'POST',headers:{'Content-Type':'application/json'},body:body,credentials:'omit'});
    } catch (e) {}
  }

  function showFor(container, durationMs, onClose) {
    requestAnimationFrame(function () { container.classList.add('__sp_in'); });
    var timer = durationMs > 0 ? setTimeout(close, Math.max(2000, durationMs)) : null;
    function close() {
      if (timer) clearTimeout(timer);
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

  /* -------- main dispatcher -------- */
  function showNotification(cfg, config, n) {
    var design = effectiveDesign(config, n);
    var t = config.targeting || {};
    var dur = (+t.duration || 8) * 1000;
    var s = n.settings || {};
    var inst = RUNTIME.instances[cfg.uuid];

    var fn = RENDERERS[n.type] || RENDERERS.recent_activity;
    fn(cfg, config, n, design, s, dur, inst);
  }

  /* -------- renderers (one per type) -------- */
  var RENDERERS = {};

  // --- helpers shared by renderers ---
  function basicCard(cfg, n, design, html, opts) {
    opts = opts || {};
    var cont = makeContainer(cfg.uuid, design, opts);
    var card = document.createElement('div'); card.className = '__sp_card';
    if (typeof html === 'string') card.innerHTML = html;
    else card.appendChild(html);
    cont.appendChild(card);
    mountWidget(cont, cfg);
    track(cfg, n, 'impression');
    var dur = opts.persist ? 0 : (opts.duration || 8000);
    var closer = showFor(cont, dur);
    withCloseButton(card, design, closer);
    if (opts.onClick) {
      card.style.cursor = 'pointer';
      card.addEventListener('click', function (e) {
        if (e.target.closest('.__sp_close')) return;
        opts.onClick(closer);
      });
    }
    return { container: cont, card: card, closer: closer };
  }

  function dataAvatarFor(name) {
    var initials = (name || '?').split(/\s+/).slice(0,2).map(function(s){return s[0]||'';}).join('').toUpperCase();
    var colors = ['#7c3aed','#ec4899','#06b6d4','#10b981','#f59e0b','#ef4444','#3b82f6'];
    var bg = colors[(name||'').length % colors.length];
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42"><rect width="100%" height="100%" rx="6" fill="'+bg+'"/><text x="50%" y="55%" font-family="sans-serif" font-size="16" font-weight="700" fill="#fff" text-anchor="middle" dominant-baseline="middle">'+initials+'</text></svg>';
    return 'data:image/svg+xml;base64,' + btoa(svg);
  }

  function renderTpl(tpl, ctx) {
    return String(tpl || '').replace(/\{(\w+)\}/g, function (_, k) {
      return (ctx[k] != null ? String(ctx[k]) : '');
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
  }

  // --- recent_activity (rotates) ---
  RENDERERS.recent_activity = function (cfg, config, n, design, s, dur, inst) {
    var pool = (s.pool && s.pool.length) ? s.pool : [{name:'Sample User', location:'New York', action:'just signed up', time_label:'2 minutes ago'}];
    var t = config.targeting || {};
    var idx = 0, shown = 0, maxShows = +(t.max_per_session || 0);

    function showOne() {
      if (maxShows && shown >= maxShows) return;
      var it = pool[idx % pool.length]; idx++; shown++;
      var card = document.createElement('div'); card.className = '__sp_card';
      var img = document.createElement('img'); img.className = '__sp_img';
      img.src = it.image_url || dataAvatarFor(it.name); img.alt = '';
      card.appendChild(img);
      var body = document.createElement('div'); body.className = '__sp_body';
      var title = document.createElement('p'); title.className = '__sp_title';
      title.textContent = renderTpl(s.title_template || '{name} from {location}', it);
      var text = document.createElement('p'); text.className = '__sp_text';
      text.textContent = renderTpl(s.body_template || '{action}', it);
      var meta = document.createElement('div'); meta.className = '__sp_meta';
      meta.innerHTML = '<span class="__sp_dot"></span>' + escapeHtml(it.time_label || 'just now');
      body.appendChild(title); body.appendChild(text); body.appendChild(meta);
      card.appendChild(body);
      var cont = makeContainer(cfg.uuid, design);
      cont.appendChild(card);
      mountWidget(cont, cfg);
      track(cfg, n, 'impression');
      var closer = showFor(cont, (+t.duration || 5) * 1000);
      withCloseButton(card, design, closer);
      card.addEventListener('click', function (e) {
        if (e.target.closest('.__sp_close')) return;
        track(cfg, n, 'click');
        if (it.link_url) window.open(it.link_url, '_blank');
      });
    }

    showOne();
    var iv = setInterval(function () {
      if (maxShows && shown >= maxShows) { clearInterval(iv); return; }
      showOne();
    }, Math.max(3, +t.interval || 8) * 1000);
    inst.cleanups.push(function () { clearInterval(iv); });
  };

  RENDERERS.visitor_count = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body"><p class="__sp_title"><span class="__sp_dot"></span>'
      + escapeHtml((s.text || '{count} people are viewing this page').replace('{count}', String(config.live_visitors || 0))) + '</p></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.conversion_count = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body"><p class="__sp_title">'
      + escapeHtml((s.text || '{count} people purchased recently').replace('{count}', String(s.count || 0)))
      + '</p></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.social_followers = function (cfg, config, n, design, s, dur) {
    var icons = {instagram:'\uD83D\uDCF7', twitter:'\uD83D\uDC26', facebook:'f', linkedin:'in', tiktok:'\u266B', youtube:'\u25B6'};
    var html = '<div class="__sp_img" style="background:var(--sp-accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">'
      + escapeHtml(icons[s.network] || '\u2605')
      + '</div><div class="__sp_body"><p class="__sp_title">' + escapeHtml(Number(s.count||0).toLocaleString()) + ' followers</p>'
      + '<p class="__sp_text">' + escapeHtml(s.handle || '') + ' on ' + escapeHtml(s.network || '') + '</p></div>';
    basicCard(cfg, n, design, html, {
      duration: dur,
      onClick: function () { if (s.url) { track(cfg, n, 'click'); window.open(s.url, '_blank'); } }
    });
  };

  RENDERERS.trust_badge = function (cfg, config, n, design, s, dur) {
    var rating = Math.max(0, Math.min(5, +s.rating || 5));
    var fullStars = Math.round(rating);
    var stars = '★★★★★☆☆☆☆☆'.slice(5 - fullStars, 10 - fullStars);
    var html = '<div class="__sp_body"><p class="__sp_title"><span class="__1inme_sp_stars">' + stars + '</span> ' + escapeHtml(rating.toFixed(1)) + '/5</p>'
      + '<p class="__sp_text">' + escapeHtml(Number(s.reviews || 0).toLocaleString()) + ' reviews ' + escapeHtml(s.label || '') + '</p></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.review = function (cfg, config, n, design, s, dur, inst) {
    var items = (s.items && s.items.length) ? s.items : [{author:'Customer', text:'Loved it!', rating:5}];
    var idx = 0;
    function showOne() {
      var it = items[idx % items.length]; idx++;
      var r = Math.max(0, Math.min(5, +it.rating || 5));
      var stars = '★★★★★☆☆☆☆☆'.slice(5 - r, 10 - r);
      var html = '<div class="__sp_body">'
        + '<div class="__1inme_sp_stars">' + stars + '</div>'
        + '<p class="__sp_text">\u201C' + escapeHtml(it.text || '') + '\u201D</p>'
        + '<div class="__sp_meta">— ' + escapeHtml(it.author || 'Customer') + '</div>'
        + '</div>';
      basicCard(cfg, n, design, html, { duration: dur });
    }
    showOne();
    if (s.rotate !== false) {
      var iv = setInterval(showOne, Math.max(4, +(config.targeting?.interval || 10)) * 1000);
      inst.cleanups.push(function () { clearInterval(iv); });
    }
  };

  RENDERERS.testimonial_quote = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="padding:6px 4px">'
      + '<p class="__1inme_sp_quote">\u201C' + escapeHtml(s.quote || '') + '\u201D</p>'
      + '<div class="__sp_meta"><strong>' + escapeHtml(s.author || '') + '</strong>'
      + (s.role ? ' — ' + escapeHtml(s.role) : '') + '</div></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.email_signup = function (cfg, config, n, design, s, dur) {
    var card = document.createElement('div'); card.className = '__sp_card';
    card.style.flexDirection = 'column'; card.style.alignItems = 'stretch'; card.style.cursor = 'default';
    var title = document.createElement('p'); title.className = '__sp_title'; title.textContent = s.title || 'Subscribe';
    var text  = document.createElement('p'); text.className  = '__sp_text';  text.textContent  = s.body  || '';
    var row   = document.createElement('div'); row.style.display = 'flex'; row.style.gap = '6px'; row.style.marginTop = '8px';
    var input = document.createElement('input'); input.type = 'email'; input.placeholder = 'you@example.com'; input.className = '__sp_input';
    var btn   = document.createElement('button'); btn.className = '__sp_btn'; btn.textContent = s.cta || 'Subscribe';
    row.appendChild(input); row.appendChild(btn);
    card.appendChild(title); if (s.body) card.appendChild(text); card.appendChild(row);
    var cont = makeContainer(cfg.uuid, design);
    cont.appendChild(card); mountWidget(cont, cfg);
    track(cfg, n, 'impression');
    var closer = showFor(cont, 0);
    withCloseButton(card, design, closer);
    btn.addEventListener('click', function () {
      if (!input.value || input.value.indexOf('@') === -1) { input.focus(); return; }
      track(cfg, n, 'conversion');
      btn.textContent = '✓ Subscribed'; btn.disabled = true; input.disabled = true;
      setTimeout(closer, 1500);
    });
  };

  RENDERERS.exit_offer = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="padding:6px 4px">'
      + '<p class="__sp_title" style="font-size:15px">' + escapeHtml(s.title || '') + '</p>'
      + '<p class="__sp_text" style="white-space:normal;margin:6px 0 10px">' + escapeHtml(s.body || '') + '</p>'
      + '<a href="' + escapeHtml(s.cta_url || '#') + '" class="__sp_btn" target="_blank" rel="noopener">' + escapeHtml(s.cta || 'Claim') + '</a>'
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0 });
    r.card.querySelector('a.__sp_btn').addEventListener('click', function () { track(cfg, n, 'conversion'); });
  };

  RENDERERS.feedback_thumbs = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="display:flex;gap:10px;align-items:center">'
      + '<p class="__sp_text" style="white-space:normal;margin:0;flex:1">' + escapeHtml(s.question || 'Was this helpful?') + '</p>'
      + '<button class="__sp_thumb_btn" data-v="up">👍</button>'
      + '<button class="__sp_thumb_btn" data-v="down">👎</button>'
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0 });
    r.card.querySelectorAll('.__sp_thumb_btn').forEach(function (b) {
      b.addEventListener('click', function () {
        track(cfg, n, 'conversion');
        r.card.querySelector('.__sp_body').innerHTML = '<p class="__sp_text" style="margin:0">Thanks for your feedback!</p>';
        setTimeout(r.closer, 1500);
      });
    });
  };

  RENDERERS.countdown = function (cfg, config, n, design, s, dur, inst) {
    var endsAt = new Date(s.ends_at || Date.now() + 3600000).getTime();
    var html = '<div class="__sp_body"><p class="__sp_title">' + escapeHtml(s.title || 'Limited offer ends in') + '</p>'
      + '<p class="__sp_text" style="font-size:18px;font-weight:700;color:var(--sp-accent)" data-cd></p></div>';
    var r = basicCard(cfg, n, design, html, { duration: 0 });
    var time = r.card.querySelector('[data-cd]');
    function pad(x){return (x<10?'0':'')+x;}
    function tick() {
      var diff = endsAt - Date.now();
      if (diff <= 0) { time.textContent = s.expired_text || 'Expired'; clearInterval(iv); return; }
      var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000), m = Math.floor((diff%3600000)/60000), sec = Math.floor((diff%60000)/1000);
      time.textContent = (d?d+'d ':'') + pad(h)+':'+pad(m)+':'+pad(sec);
    }
    tick(); var iv = setInterval(tick, 1000);
    inst.cleanups.push(function () { clearInterval(iv); });
  };

  RENDERERS.flash_sale = function (cfg, config, n, design, s, dur, inst) {
    var endsAt = new Date(s.ends_at || Date.now() + 3600000).getTime();
    var html = '<div class="__sp_body" style="padding:4px">'
      + '<p class="__sp_title" style="color:var(--sp-accent);font-size:13px;text-transform:uppercase;letter-spacing:1px">' + escapeHtml(s.title || 'Flash sale') + '</p>'
      + '<p class="__sp_text" style="font-size:22px;font-weight:800;margin:2px 0">' + escapeHtml(s.discount || '') + '</p>'
      + '<p class="__sp_meta" data-cd>Ends in —</p>'
      + (s.cta ? '<a href="' + escapeHtml(s.cta_url || '#') + '" class="__sp_btn" style="margin-top:8px" target="_blank" rel="noopener">' + escapeHtml(s.cta) + '</a>' : '')
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0 });
    var time = r.card.querySelector('[data-cd]');
    function pad(x){return (x<10?'0':'')+x;}
    function tick() {
      var diff = endsAt - Date.now();
      if (diff <= 0) { time.textContent = 'Ended'; clearInterval(iv); return; }
      var h = Math.floor(diff/3600000), m = Math.floor((diff%3600000)/60000), sec = Math.floor((diff%60000)/1000);
      time.textContent = 'Ends in ' + pad(h)+':'+pad(m)+':'+pad(sec);
    }
    tick(); var iv = setInterval(tick, 1000);
    inst.cleanups.push(function () { clearInterval(iv); });
    var a = r.card.querySelector('a.__sp_btn');
    if (a) a.addEventListener('click', function () { track(cfg, n, 'click'); });
  };

  RENDERERS.low_stock = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_img" style="background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:20px">🔥</div>'
      + '<div class="__sp_body"><p class="__sp_title">Low stock</p>'
      + '<p class="__sp_text">' + escapeHtml((s.text || 'Only {count} left in stock!').replace('{count}', String(s.count || 0))) + '</p></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.price_drop = function (cfg, config, n, design, s, dur) {
    var text = (s.text || 'Price dropped from {old} to {new}!')
      .replace('{old}', '<s style="opacity:.6">' + escapeHtml(s.old_price || '') + '</s>')
      .replace('{new}', '<strong style="color:var(--sp-accent)">' + escapeHtml(s.new_price || '') + '</strong>');
    var html = '<div class="__sp_img" style="background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:20px">↓</div>'
      + '<div class="__sp_body"><p class="__sp_title">Price drop</p>'
      + '<p class="__sp_text">' + text + '</p></div>';
    basicCard(cfg, n, design, html, { duration: dur });
  };

  RENDERERS.announcement_bar = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="display:flex;gap:10px;align-items:center;justify-content:center;width:100%;flex-wrap:wrap">'
      + '<span style="font-size:14px;font-weight:500">' + escapeHtml(s.text || '') + '</span>'
      + (s.cta_label ? '<a href="' + escapeHtml(s.cta_url || '#') + '" class="__sp_btn" style="padding:5px 11px;font-size:12px" target="_blank" rel="noopener">' + escapeHtml(s.cta_label) + '</a>' : '')
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0, fullBar: true, barPlacement: s.placement === 'bottom' ? 'bottom' : 'top' });
    r.container.classList.add('__1inme_sp_bar');
    var a = r.card.querySelector('a.__sp_btn');
    if (a) a.addEventListener('click', function () { track(cfg, n, 'click'); });
  };

  RENDERERS.sticky_cta = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="display:flex;gap:10px;align-items:center;justify-content:center;width:100%">'
      + '<span style="font-size:14px;font-weight:500">' + escapeHtml(s.text || '') + '</span>'
      + (s.cta_label ? '<a href="' + escapeHtml(s.cta_url || '#') + '" class="__sp_btn" target="_blank" rel="noopener">' + escapeHtml(s.cta_label) + '</a>' : '')
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0, fullBar: true, barPlacement: 'bottom' });
    r.container.classList.add('__1inme_sp_bar');
    var a = r.card.querySelector('a.__sp_btn');
    if (a) a.addEventListener('click', function () { track(cfg, n, 'conversion'); });
  };

  RENDERERS.cookie_consent = function (cfg, config, n, design, s, dur) {
    var html = '<div class="__sp_body" style="display:flex;gap:10px;align-items:center;justify-content:center;width:100%;flex-wrap:wrap">'
      + '<div style="flex:1;min-width:200px"><strong>' + escapeHtml(s.title || 'We use cookies') + '</strong> — '
      + '<span style="opacity:.85">' + escapeHtml(s.body || '') + '</span>'
      + (s.policy_url ? ' <a href="' + escapeHtml(s.policy_url) + '" style="text-decoration:underline" target="_blank" rel="noopener">Learn more</a>' : '')
      + '</div>'
      + '<button class="__sp_btn_ghost" data-act="reject">' + escapeHtml(s.reject_label || 'Reject') + '</button>'
      + '<button class="__sp_btn" data-act="accept">' + escapeHtml(s.accept_label || 'Accept') + '</button>'
      + '</div>';
    var r = basicCard(cfg, n, design, html, { duration: 0, fullBar: true, barPlacement: 'bottom' });
    r.container.classList.add('__1inme_sp_bar');
    r.card.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () {
        track(cfg, n, b.dataset.act === 'accept' ? 'conversion' : 'click');
        r.closer();
      });
    });
  };

  RENDERERS.whatsapp_chat = function (cfg, config, n, design, s, dur) {
    var phone = String(s.phone || '').replace(/[^0-9+]/g, '');
    var url = 'https://wa.me/' + phone.replace(/[^0-9]/g, '') + (s.message ? ('?text=' + encodeURIComponent(s.message)) : '');
    var html = '<span class="__1inme_sp_bubble_icon" style="background:#25d366;border-radius:50%;width:32px;height:32px">'
      + '<svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M20.5 3.5A11.5 11.5 0 0 0 3.6 19l-1.6 5 5.1-1.6a11.5 11.5 0 0 0 17.4-9.9c0-3-1.2-5.9-3.4-8zM12 21.4c-1.7 0-3.4-.5-4.9-1.4l-.4-.2-3 .9.9-2.9-.2-.4A9.4 9.4 0 1 1 21.4 12 9.4 9.4 0 0 1 12 21.4zm5.4-7c-.3-.1-1.7-.9-2-1s-.5-.1-.7.2c-.2.3-.7.9-.9 1.1-.2.2-.4.2-.7 0-.3-.1-1.2-.4-2.4-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.2-.7-1.7-1-2.3-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.7.4-.3.3-.9.9-.9 2.2 0 1.3 1 2.6 1.1 2.8.2.2 1.9 2.9 4.7 4.1 2.8 1.1 2.8.7 3.3.7s1.7-.7 2-1.4c.2-.7.2-1.2.2-1.4 0-.2-.2-.3-.5-.4z"/></svg>'
      + '</span><span style="font-weight:600">' + escapeHtml(s.label || 'Chat') + '</span>';
    var r = basicCard(cfg, n, design, html, {
      duration: 0, bubble: true,
      onClick: function () { track(cfg, n, 'click'); window.open(url, '_blank'); }
    });
    r.card.style.background = '#25d366'; r.card.style.color = '#fff'; r.card.style.borderColor = 'transparent';
  };

  RENDERERS.click_to_call = function (cfg, config, n, design, s, dur) {
    var phone = String(s.phone || '');
    var html = '<span class="__1inme_sp_bubble_icon" style="background:var(--sp-accent);border-radius:50%;width:32px;height:32px">📞</span>'
      + '<span style="font-weight:600">' + escapeHtml(s.label || 'Call') + '</span>';
    basicCard(cfg, n, design, html, {
      duration: 0, bubble: true,
      onClick: function () { track(cfg, n, 'click'); window.location.href = 'tel:' + phone; }
    });
  };

  RENDERERS.video_popup = function (cfg, config, n, design, s, dur) {
    var thumbHtml = '<div class="__sp_body" style="cursor:pointer">'
      + '<div class="__1inme_sp_video_thumb" style="height:90px;width:160px"></div>'
      + '<p class="__sp_text" style="margin-top:6px;text-align:center">' + escapeHtml(s.thumbnail_text || 'Watch') + '</p>'
      + '</div>';
    var r = basicCard(cfg, n, design, thumbHtml, {
      duration: 0,
      onClick: function () {
        track(cfg, n, 'click');
        var modal = document.createElement('div'); modal.className = '__1inme_sp_video_modal';
        var iframe = document.createElement('iframe');
        iframe.src = String(s.video_url || '');
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
        iframe.allowFullscreen = true;
        modal.appendChild(iframe);
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });
        mountWidget(modal, cfg);
      }
    });
  };

  RENDERERS.share_buttons = function (cfg, config, n, design, s, dur) {
    var nets = (s.networks && s.networks.length) ? s.networks : ['twitter','facebook','linkedin'];
    var url = encodeURIComponent(s.url || location.href);
    var text = encodeURIComponent(s.text || document.title || '');
    var BG = {twitter:'#1da1f2', facebook:'#1877f2', linkedin:'#0a66c2', whatsapp:'#25d366', telegram:'#26a5e4', email:'#6b7280'};
    var URLS = {
      twitter:  'https://twitter.com/intent/tweet?text=' + text + '&url=' + url,
      facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
      linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + url,
      whatsapp: 'https://wa.me/?text=' + text + '%20' + url,
      telegram: 'https://t.me/share/url?url=' + url + '&text=' + text,
      email:    'mailto:?subject=' + text + '&body=' + url
    };
    var ICONS = {twitter:'𝕏', facebook:'f', linkedin:'in', whatsapp:'W', telegram:'✈', email:'@'};
    var btns = nets.map(function (k) {
      return '<a href="' + URLS[k] + '" class="__1inme_sp_share_btn" target="_blank" rel="noopener" style="background:' + (BG[k] || '#6b7280') + '">' + (ICONS[k] || '?') + '</a>';
    }).join('');
    var html = '<div class="__sp_body"><p class="__sp_title" style="margin-bottom:8px">' + escapeHtml(s.text || 'Share') + '</p>'
      + '<div class="__1inme_sp_share_btns">' + btns + '</div></div>';
    var r = basicCard(cfg, n, design, html, { duration: 0 });
    r.card.querySelectorAll('a.__1inme_sp_share_btn').forEach(function (a) {
      a.addEventListener('click', function () { track(cfg, n, 'click'); });
    });
  };

  RENDERERS.custom_html = function (cfg, config, n, design, s, dur) {
    var card = document.createElement('div'); card.className = '__sp_card'; card.style.padding = '0'; card.style.cursor = 'default';
    var wrap = document.createElement('div'); wrap.innerHTML = sanitizeHtml(s.html || '');
    card.appendChild(wrap);
    var cont = makeContainer(cfg.uuid, design);
    cont.appendChild(card); mountWidget(cont, cfg);
    track(cfg, n, 'impression');
    var closer = showFor(cont, 0);
    withCloseButton(card, design, closer);
  };

  /* -------- HTML sanitizer (for custom_html) -------- */
  function sanitizeHtml(html) {
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
      var s = String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
      s = s.replace(/\son[a-z]+\s*=\s*"[^"]*"/gi, '');
      s = s.replace(/\son[a-z]+\s*=\s*'[^']*'/gi, '');
      return s;
    }
  }
})();
