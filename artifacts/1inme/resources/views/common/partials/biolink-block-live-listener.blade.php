{{-- Instant block live preview listener (shared by the biolink page and the
     slides public page). The editor posts the full drawer/modal form state
     plus the list of fields changed since the form opened; we patch the DOM
     in place for known block types/fields and ack back whether EVERY changed
     field was handled — only then does the editor skip the full iframe
     reload. Only active in editor preview mode (?_preview=1 / ?_editBlock). --}}
<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    if (!params.get('_editBlock') && !params.get('_preview')) return;
            (function () {
                // Convert "settings[items][0][text]" → "settings.items.0.text"
                function dotted(name) {
                    return name.replace(/\]\[/g, '.').replace(/\[/g, '.').replace(/\]/g, '');
                }
                // Wildcard key: numeric segments → "*", returns [key, indexes]
                function wild(key) {
                    var idx = [];
                    var w = key.split('.').map(function (seg) {
                        if (/^\d+$/.test(seg)) { idx.push(parseInt(seg, 10)); return '*'; }
                        return seg;
                    }).join('.');
                    return { key: w, idx: idx };
                }
                function alignClass(el, v) {
                    if (['left', 'center', 'right'].indexOf(v) === -1) return false;
                    el.classList.remove('text-left', 'text-center', 'text-right');
                    el.classList.add('text-' + v);
                    return true;
                }
                function setText(el, v) { if (!el) return false; el.textContent = v; return true; }
                // Text target inside a button-like <a> across the link layouts.
                function linkTextTarget(root) {
                    var a = root.querySelector('a');
                    if (!a) return null;
                    return a.querySelector('span.flex-1')
                        || a.querySelector('p.font-semibold')
                        || (function () {
                            var spans = a.querySelectorAll(':scope span, :scope div span');
                            for (var i = spans.length - 1; i >= 0; i--) {
                                if (!spans[i].querySelector('i, img') && (spans[i].textContent || '').trim() !== '') return spans[i];
                            }
                            return null;
                        })()
                        || (a.children.length === 0 ? a : null);
                }
                function alertText(root, v) {
                    var p = root.querySelector('p');
                    if (!p) return false;
                    // Keep the leading icon, replace the trailing text node(s).
                    var icon = p.querySelector('i');
                    while (p.lastChild && p.lastChild !== icon) p.removeChild(p.lastChild);
                    p.appendChild(document.createTextNode(v));
                    return true;
                }
                var ALERT_CLASSES = {
                    info: ['border-blue-400/30', 'bg-blue-500/10'],
                    success: ['border-green-400/30', 'bg-green-500/10'],
                    warning: ['border-yellow-400/30', 'bg-yellow-500/10'],
                    error: ['border-red-400/30', 'bg-red-500/10']
                };
                function listItemTarget(root, n) {
                    var lis = root.querySelectorAll('li');
                    if (!lis.length || n >= lis.length) return null;
                    return lis[n].querySelector('span:last-child') || lis[n];
                }
                function nthAnchor(root, n) {
                    var as = root.querySelectorAll('a');
                    return n < as.length ? as[n] : null;
                }
                // Rewrite the nth social anchor's destination. Live hrefs
                // route through the click tracker with a ?to= param —
                // rewrite just that param when present.
                function patchSocialAnchor(root, n, v) {
                    var a = nthAnchor(root, n);
                    if (!a) return false;
                    var href = a.getAttribute('href') || '';
                    if (href.indexOf('?to=') !== -1 || href.indexOf('&to=') !== -1) {
                        a.setAttribute('href', href.replace(/([?&]to=)[^&]*/, '$1' + encodeURIComponent(v)));
                    } else {
                        a.setAttribute('href', v);
                    }
                    return true;
                }
                function progressRow(root, n) {
                    var rows = root.querySelectorAll(':scope > div > div');
                    return n < rows.length ? rows[n] : null;
                }
                // Nth repeater card (.glass-block) inside faq/testimonials.
                function glassItem(root, n) {
                    var items = root.querySelectorAll('.glass-block');
                    return n < items.length ? items[n] : null;
                }
                // Replace an element's text while keeping a leading icon <i>.
                function setTextKeepIcon(el, v) {
                    if (!el) return false;
                    var icon = el.querySelector('i');
                    while (el.lastChild && el.lastChild !== icon) el.removeChild(el.lastChild);
                    el.appendChild(document.createTextNode(v));
                    return true;
                }

                // Handlers keyed by block type → dotted field key (numeric
                // segments as "*") → fn(root, value, idx) returning true when
                // the DOM was patched, false to force the reload fallback.
                var LIVE_HANDLERS = {
                    paragraph: {
                        'settings.text': function (root, v) { return setText(root.querySelector('p'), v); },
                        'settings.align': function (root, v) { var d = root.querySelector('div'); return d ? alignClass(d, v) : false; }
                    },
                    heading: {
                        'settings.text': function (root, v) { return setText(root.querySelector('h2'), v); },
                        'settings.align': function (root, v) { var d = root.querySelector('div'); return d ? alignClass(d, v) : false; }
                    },
                    link: {
                        'settings.text': function (root, v) { return setText(linkTextTarget(root), v); },
                        'settings.url': function (root, v) { var a = root.querySelector('a'); if (!a) return false; a.setAttribute('href', v); return true; },
                        'settings.description': function (root, v) { return setText(root.querySelector('a p[class*="text-xs"]'), v); }
                    },
                    cta_button: {
                        'settings.text': function (root, v) { var a = root.querySelector('a'); if (!a) return false; var s = a.querySelector('span'); return setText(s || a, v); },
                        'settings.url': function (root, v) { var a = root.querySelector('a'); if (!a) return false; a.setAttribute('href', v); return true; }
                    },
                    image: {
                        'settings.url': function (root, v) { var img = root.querySelector('img'); if (!img) return false; img.src = v; return true; },
                        'settings.alt': function (root, v) { var img = root.querySelector('img'); if (!img) return false; img.alt = v; return true; }
                    },
                    badge: {
                        'settings.text': function (root, v) { return setText(root.querySelector('span'), v); },
                        'settings.color': function (root, v) { var s = root.querySelector('span'); if (!s) return false; s.style.background = v; return true; },
                        'settings.text_color': function (root, v) { var s = root.querySelector('span'); if (!s) return false; s.style.color = v; return true; }
                    },
                    divider: {
                        'settings.color': function (root, v) { var hr = root.querySelector('hr'); if (!hr) return false; hr.style.borderColor = v; return true; },
                        'settings.style': function (root, v) { var hr = root.querySelector('hr'); if (!hr) return false; hr.style.borderStyle = v; return true; }
                    },
                    spacer: {
                        'settings.height': function (root, v) {
                            var d = root.querySelector('div[style*="height"]') || root.querySelector('div');
                            if (!d) return false;
                            var n = parseInt(v, 10); if (isNaN(n)) return false;
                            d.style.height = n + 'px'; return true;
                        }
                    },
                    alert: {
                        'settings.text': alertText,
                        'settings.type': function (root, v) {
                            var box = root.querySelector('div.rounded-xl');
                            if (!box || !ALERT_CLASSES[v]) return false;
                            Object.keys(ALERT_CLASSES).forEach(function (k) {
                                ALERT_CLASSES[k].forEach(function (c) { box.classList.remove(c); });
                            });
                            ALERT_CLASSES[v].forEach(function (c) { box.classList.add(c); });
                            return true;
                        }
                    },
                    list: {
                        'settings.items.*.text': function (root, v, idx) { return setText(listItemTarget(root, idx[0]), v); }
                    },
                    video: {
                        'settings.url': function (root, v) {
                            var vid = root.querySelector('video');
                            if (!vid) return false;
                            var src = vid.querySelector('source');
                            if (src) src.src = v; else vid.src = v;
                            try { vid.load(); } catch (e) {}
                            return true;
                        }
                    },
                    youtube: {
                        'settings.video_id': function (root, v) {
                            var f = root.querySelector('iframe');
                            if (!f) return false;
                            var id = v;
                            var m = /(?:v=|\/)([\w-]{11})/.exec(v);
                            if (m) id = m[1];
                            f.src = 'https://www.youtube.com/embed/' + encodeURIComponent(id);
                            return true;
                        }
                    },
                    socials: {
                        'settings.platforms.*.url': function (root, v, idx) {
                            return patchSocialAnchor(root, idx[0], v);
                        }
                    },
                    product: {
                        'settings.name': function (root, v) { return setText(root.querySelector('p.font-semibold'), v); },
                        'settings.price': function (root, v) { return setText(root.querySelector('span.font-bold'), v); },
                        'settings.description': function (root, v) { return setText(root.querySelector('p[class*="text-xs"]'), v); },
                        'settings.url': function (root, v) { var a = root.querySelector('a.bio-btn'); if (!a) return false; a.setAttribute('href', v); return true; }
                    },
                    countdown: {
                        'settings.title': function (root, v) { return setText(root.querySelector('p.text-sm'), v); }
                    },
                    price: {
                        'settings.title': function (root, v) { return setText(root.querySelector('p.text-sm'), v); },
                        'settings.amount': function (root, v) { return setText(root.querySelector('span.text-3xl'), v); },
                        'settings.period': function (root, v) { return setText(root.querySelector('span.text-sm'), v); }
                    },
                    progress: {
                        'settings.items.*.label': function (root, v, idx) {
                            var row = progressRow(root, idx[0]); if (!row) return false;
                            return setText(row.querySelector('span'), v);
                        },
                        'settings.items.*.value': function (root, v, idx) {
                            var row = progressRow(root, idx[0]); if (!row) return false;
                            var n = parseInt(v, 10); if (isNaN(n)) n = 0;
                            var pct = row.querySelectorAll('span')[1];
                            if (pct) pct.textContent = n + '%';
                            var bar = row.querySelector('div div');
                            if (!bar) return false;
                            bar.style.width = Math.max(0, Math.min(100, n)) + '%';
                            return true;
                        },
                        'settings.items.*.color': function (root, v, idx) {
                            var row = progressRow(root, idx[0]); if (!row) return false;
                            var bar = row.querySelector('div div'); if (!bar) return false;
                            bar.style.background = v; return true;
                        }
                    },
                    card: {
                        'settings.title': function (root, v) {
                            var t = root.querySelector('.card-container-render > div.mb-3');
                            return setText(t, v);
                        },
                        'settings.gap': function (root, v) {
                            var g = root.querySelector('.card-container-render div[style*="display:grid"], .card-container-render div[style*="display: grid"]');
                            if (!g) return false;
                            var n = parseInt(v, 10); if (isNaN(n)) return false;
                            g.style.gap = n + 'px'; return true;
                        },
                        'settings.padding': function (root, v) {
                            var c = root.querySelector('.card-container-render');
                            if (!c) return false;
                            var n = parseInt(v, 10); if (isNaN(n)) return false;
                            c.style.padding = n + 'px'; return true;
                        },
                        'settings.border_radius': function (root, v) {
                            var c = root.querySelector('.card-container-render');
                            if (!c) return false;
                            var n = parseInt(v, 10); if (isNaN(n)) return false;
                            c.style.borderRadius = n + 'px'; return true;
                        },
                        'settings.columns': function (root, v) {
                            var g = root.querySelector('.card-container-render div[style*="grid-template-columns"]');
                            if (!g) return false;
                            var n = parseInt(v, 10); if (isNaN(n) || n < 1) return false;
                            g.style.gridTemplateColumns = 'repeat(' + n + ', 1fr)';
                            return true;
                        }
                    },
                    faq: {
                        'settings.items.*.question': function (root, v, idx) {
                            var item = glassItem(root, idx[0]); if (!item) return false;
                            // Keep the optional leading item icon inside the span.
                            return setTextKeepIcon(item.querySelector('button span'), v);
                        },
                        'settings.items.*.answer': function (root, v, idx) {
                            var item = glassItem(root, idx[0]); if (!item) return false;
                            return setText(item.querySelector('p'), v);
                        }
                    },
                    testimonials: {
                        'settings.items.*.name': function (root, v, idx) {
                            var item = glassItem(root, idx[0]); if (!item) return false;
                            return setText(item.querySelector('p.font-medium'), v);
                        },
                        'settings.items.*.text': function (root, v, idx) {
                            var item = glassItem(root, idx[0]); if (!item) return false;
                            // The quote is the item's direct-child <p>; the name
                            // <p> is nested inside the avatar header row.
                            return setText(item.querySelector(':scope > p'), v);
                        }
                    },
                    review: {
                        'settings.name': function (root, v) { return setText(root.querySelector('p.font-medium'), v); },
                        'settings.text': function (root, v) { return setText(root.querySelector('.glass-block > p'), v); }
                    },
                    featured_pin: {
                        // Text/description targets differ per link_layout;
                        // plain_text mixes the label with inline nodes, so
                        // the text handler misses it and returns false →
                        // safe reload (description/url may still patch).
                        'settings.text': function (root, v) {
                            var t = root.querySelector('a p.font-bold, a div.font-semibold');
                            return setText(t, v);
                        },
                        'settings.description': function (root, v) {
                            var d = root.querySelector('a .opacity-90, a .opacity-70, a .text-white\\/80');
                            return setText(d, v);
                        },
                        'settings.url': function (root, v) {
                            var a = root.querySelector('a');
                            if (!a) return false;
                            a.setAttribute('href', v);
                            return true;
                        }
                    }
                };
                // Type aliases sharing a renderer / handler set.
                LIVE_HANDLERS.link_big = LIVE_HANDLERS.link;
                LIVE_HANDLERS.list_numbered = LIVE_HANDLERS.list;
                // Grouped socials flatten every group's platforms into one
                // anchor list in DOM order, so the flat anchor index is the
                // sum of the earlier groups' platform counts (derived from the
                // full form snapshot) plus the within-group index.
                LIVE_HANDLERS.socials_multi = {
                    'settings.groups.*.platforms.*.url': function (root, v, idx, fields) {
                        if (!fields) return false;
                        var gi = idx[0], pi = idx[1];
                        var offset = 0;
                        for (var g = 0; g < gi; g++) {
                            var n = 0;
                            while (('settings[groups][' + g + '][platforms][' + n + '][name]') in fields) n++;
                            offset += n;
                        }
                        return patchSocialAnchor(root, offset + pi, v);
                    }
                };
                LIVE_HANDLERS.socials_custom = LIVE_HANDLERS.socials;
                LIVE_HANDLERS.service = LIVE_HANDLERS.product;
                LIVE_HANDLERS.faq_v2 = LIVE_HANDLERS.faq;

                // Per-block style keys we can apply live to the styled wrapper.
                var LIVE_STYLE_KEYS = {
                    'style.font_size': function (el, v) { el.style.fontSize = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.font_weight': function (el, v) { el.style.fontWeight = v; },
                    'style.font_style': function (el, v) { el.style.fontStyle = v; },
                    'style.text_color': function (el, v) { el.style.color = v; },
                    'style.bg_color': function (el, v) { el.style.background = v; },
                    'style.border_radius': function (el, v) { el.style.borderRadius = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.border_width': function (el, v) { el.style.borderWidth = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.border_style': function (el, v) { el.style.borderStyle = v; },
                    'style.border_color': function (el, v) { el.style.borderColor = v; },
                    // Advanced borders (Task #6038): per-corner radius +
                    // per-side style/width/color patch the wrapper live.
                    'style.border_radius_tl': function (el, v) { el.style.borderTopLeftRadius = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.border_radius_tr': function (el, v) { el.style.borderTopRightRadius = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.border_radius_bl': function (el, v) { el.style.borderBottomLeftRadius = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.border_radius_br': function (el, v) { el.style.borderBottomRightRadius = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.padding': function (el, v) { el.style.padding = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.padding_top': function (el, v) { el.style.paddingTop = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.padding_bottom': function (el, v) { el.style.paddingBottom = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.padding_left': function (el, v) { el.style.paddingLeft = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.padding_right': function (el, v) { el.style.paddingRight = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    // Task #6114: vertical margins patch the styled element
                    // (mirrors buildInlineStyle); horizontal margins are
                    // special-cased in applyLiveStyle to hit the wrap.
                    'style.margin_top': function (el, v) { el.style.marginTop = v === '' ? '' : parseInt(v, 10) + 'px'; },
                    'style.margin_bottom': function (el, v) { el.style.marginBottom = v === '' ? '' : parseInt(v, 10) + 'px'; }
                };
                // Text tilt (Task #5954): heading/paragraph partials always
                // emit a [data-tilt-wrap] element, so live rotation works
                // even before any other custom style exists.
                function applyLiveTilt(root, v) {
                    var w = root.querySelector('[data-tilt-wrap]');
                    if (!w) return false;
                    var deg = parseFloat(v);
                    if (isNaN(deg)) deg = 0;
                    deg = Math.max(-30, Math.min(30, deg));
                    w.style.transform = deg === 0 ? '' : 'rotate(' + deg + 'deg)';
                    return true;
                }
                // Hero-photo decoration keys (Task #5944): patch the image
                // block's [data-photo-hero] container in place. Structural
                // keys (mask/frame/accents, last-sticker removal) have no
                // handler and fall back to the safe reload path.
                var PH_STICKER_ANCHORS = {
                    top_left:     { left: '-10px', top: '-10px' },
                    top_right:    { right: '-10px', top: '-10px' },
                    bottom_left:  { left: '-10px', bottom: '-10px' },
                    bottom_right: { right: '-10px', bottom: '-10px' },
                    center_left:  { left: '-12px', top: '50%' },
                    center_right: { right: '-12px', top: '50%' }
                };
                function phClamp(v, lo, hi, dflt) {
                    var n = parseInt(v, 10);
                    if (isNaN(n)) n = dflt;
                    return Math.max(lo, Math.min(hi, n));
                }
                var LIVE_PHOTO_KEYS = {
                    'style._photo_stickers': function (hero, v) {
                        var list = [];
                        if (String(v).trim() !== '') {
                            try { list = JSON.parse(v); } catch (err) { return false; }
                            if (!Array.isArray(list)) return false;
                        }
                        // Emptying the list may make the whole hero container
                        // structurally unnecessary — let the reload handle it.
                        if (!list.length) return false;
                        var frag = document.createDocumentFragment();
                        for (var i = 0; i < Math.min(list.length, 4); i++) {
                            var s = list[i] || {};
                            var url = typeof s.url === 'string' ? s.url : '';
                            if (!url) return false;
                            var pos = PH_STICKER_ANCHORS[s.pos] ? s.pos : 'top_right';
                            var size = phClamp(s.size, 24, 160, 64);
                            var rot = phClamp(s.rotate, -180, 180, 0);
                            var dx = phClamp(s.dx, -80, 80, 0);
                            var dy = phClamp(s.dy, -80, 80, 0);
                            var img = document.createElement('img');
                            img.src = url;
                            img.alt = '';
                            img.setAttribute('aria-hidden', 'true');
                            img.loading = 'lazy';
                            img.className = 'absolute pointer-events-none z-10';
                            img.setAttribute('data-photo-sticker', '');
                            var a = PH_STICKER_ANCHORS[pos];
                            Object.keys(a).forEach(function (k) { img.style[k] = a[k]; });
                            img.style.width = size + 'px';
                            img.style.height = size + 'px';
                            img.style.objectFit = 'contain';
                            var t = 'translate(' + dx + 'px,' + dy + 'px)';
                            if (pos === 'center_left' || pos === 'center_right') t = 'translateY(-50%) ' + t;
                            if (rot !== 0) t += ' rotate(' + rot + 'deg)';
                            img.style.transform = t;
                            frag.appendChild(img);
                        }
                        hero.querySelectorAll('[data-photo-sticker]').forEach(function (el) { el.remove(); });
                        hero.appendChild(frag);
                        return true;
                    },
                    'style._photo_banner_text': function (hero, v) {
                        var b = hero.querySelector('[data-photo-banner]');
                        // Adding or removing the banner is structural — reload.
                        if (!b || String(v).trim() === '') return false;
                        b.textContent = v;
                        return true;
                    },
                    'style._photo_banner_bg': function (hero, v) {
                        var b = hero.querySelector('[data-photo-banner]');
                        if (!b || !v) return false;
                        b.style.background = v;
                        return true;
                    },
                    'style._photo_banner_text_color': function (hero, v) {
                        var b = hero.querySelector('[data-photo-banner]');
                        if (!b || !v) return false;
                        b.style.color = v;
                        return true;
                    },
                    'style._photo_frame_color': function (hero, v) {
                        var strokes = hero.querySelectorAll('[data-photo-frame-stroke]');
                        if (!strokes.length || !v) return false;
                        strokes.forEach(function (el) { el.style.borderColor = v; });
                        return true;
                    },
                    'style._photo_text_stickers': function (hero, v) {
                        var list = [];
                        if (String(v).trim() !== '') {
                            try { list = JSON.parse(v); } catch (err) { return false; }
                            if (!Array.isArray(list)) return false;
                        }
                        // Emptying the list may make the whole hero container
                        // structurally unnecessary — let the reload handle it.
                        if (!list.length) return false;
                        var frag = document.createDocumentFragment();
                        for (var i = 0; i < Math.min(list.length, 4); i++) {
                            var s = list[i] || {};
                            var text = typeof s.text === 'string' ? s.text.trim() : '';
                            if (!text) continue;
                            var pos = PH_STICKER_ANCHORS[s.pos] ? s.pos : 'top_right';
                            var size = phClamp(s.size, 10, 64, 20);
                            var rot = phClamp(s.rotate, -180, 180, 0);
                            var dx = phClamp(s.dx, -80, 80, 0);
                            var dy = phClamp(s.dy, -80, 80, 0);
                            var span = document.createElement('span');
                            span.textContent = text.slice(0, 80);
                            span.className = 'absolute pointer-events-none z-10 font-bold';
                            span.setAttribute('data-photo-text-sticker', '');
                            var a = PH_STICKER_ANCHORS[pos];
                            Object.keys(a).forEach(function (k) { span.style[k] = a[k]; });
                            if (typeof s.color === 'string' && /^#[0-9a-fA-F]{3,8}$/.test(s.color)) span.style.color = s.color;
                            else span.style.color = '#ffffff';
                            var fam = typeof s.font === 'string' ? s.font.replace(/[^a-zA-Z0-9 :_\-]/g, '') : '';
                            if (fam.indexOf('custom:') === 0) fam = fam.slice(7);
                            if (fam) span.style.fontFamily = "'" + fam + "'";
                            span.style.fontSize = size + 'px';
                            span.style.lineHeight = '1.15';
                            span.style.whiteSpace = 'nowrap';
                            span.style.textShadow = '0 1px 6px rgba(0,0,0,0.35)';
                            var t = 'translate(' + dx + 'px,' + dy + 'px)';
                            if (pos === 'center_left' || pos === 'center_right') t = 'translateY(-50%) ' + t;
                            if (rot !== 0) t += ' rotate(' + rot + 'deg)';
                            span.style.transform = t;
                            frag.appendChild(span);
                        }
                        hero.querySelectorAll('[data-photo-text-sticker]').forEach(function (el) { el.remove(); });
                        hero.appendChild(frag);
                        return true;
                    }
                };
                // Per-side borders (Task #6041): style/width/color for each
                // side depend on the sibling fields plus the shorthand
                // fallbacks, so recompute ALL border props from the full
                // form payload — mirroring BiolinkBlock::buildInlineStyle.
                function borderFieldVal(fields, name) {
                    var v = fields['style[' + name + ']'];
                    return v === undefined || v === null ? '' : String(v);
                }
                var BORDER_SIDES = ['top', 'right', 'bottom', 'left'];
                var BORDER_SIDE_PROPS = { top: 'borderTop', right: 'borderRight', bottom: 'borderBottom', left: 'borderLeft' };
                function borderHasSideOverride(fields) {
                    return BORDER_SIDES.some(function (side) {
                        return borderFieldVal(fields, 'border_' + side + '_style') !== ''
                            || borderFieldVal(fields, 'border_' + side + '_width') !== ''
                            || borderFieldVal(fields, 'border_' + side + '_color') !== '';
                    });
                }
                function applyLiveSideBorders(el, fields) {
                    var shStyle = borderFieldVal(fields, 'border_style');
                    var shWidth = borderFieldVal(fields, 'border_width');
                    var shColor = borderFieldVal(fields, 'border_color');
                    if (borderHasSideOverride(fields)) {
                        // Clear any shorthand first, then set each side
                        // explicitly (matches the per-side server branch).
                        el.style.border = '';
                        BORDER_SIDES.forEach(function (side) {
                            var s = borderFieldVal(fields, 'border_' + side + '_style');
                            if (s === '') s = shStyle !== '' ? shStyle : 'none';
                            var w = borderFieldVal(fields, 'border_' + side + '_width');
                            if (w === '') w = shWidth;
                            var c = borderFieldVal(fields, 'border_' + side + '_color');
                            if (c === '') c = shColor;
                            if (s !== 'none' && s !== '' && w !== '' && parseFloat(w) > 0) {
                                el.style[BORDER_SIDE_PROPS[side]] = parseFloat(w) + 'px ' + s + (c !== '' ? ' ' + c : '');
                            } else {
                                el.style[BORDER_SIDE_PROPS[side]] = 'none';
                            }
                        });
                    } else {
                        // No per-side overrides left — revert to the plain
                        // shorthand semantics (server elseif branch).
                        BORDER_SIDES.forEach(function (side) { el.style[BORDER_SIDE_PROPS[side]] = ''; });
                        if (shStyle !== '' && shStyle !== 'none' && shWidth !== '' && parseFloat(shWidth) > 0) {
                            el.style.border = parseFloat(shWidth) + 'px ' + shStyle + (shColor !== '' ? ' ' + shColor : '');
                        } else {
                            el.style.border = '';
                        }
                    }
                    return true;
                }
                function styleTarget(root) {
                    // Card containers carry their unified style on their own
                    // render div (Task #6173) — check it FIRST, because a
                    // .block-styled child inside the card would otherwise be
                    // picked up and patched instead of the container itself.
                    var _bt = root.getAttribute('data-block-type') || '';
                    if (_bt === 'card') return root.querySelector('.card-container-render');
                    // Styled blocks render a .block-styled wrapper; button-like
                    // blocks carry the inline style on the anchor itself. If
                    // neither exists the block has no custom style yet and a
                    // live patch would need a structural change — fall back.
                    return root.querySelector('.block-styled')
                        || root.querySelector('a.bio-btn[style]');
                }
                // Block-level catalog preset CSS, keyed by preset key (torn
                // composites are excluded from the block picker, so they are
                // excluded here too). Resolved server-side from the catalog —
                // never from client input; the live channel only ever sends
                // the KEY, which is looked up in this trusted map.
                var BG_PRESET_CSS = @json(collect(\App\Modules\User\Support\BgPresetCatalog::all())->filter(fn($p) => ($p['group'] ?? '') !== 'torn')->map(fn($p) => rtrim($p['css'], "; \t\n\r"))->toArray());
                function bgPresetLayerCss(css, op) {
                    return 'position:absolute;inset:0;z-index:-1;pointer-events:none;' + css +
                        ';background-attachment:scroll !important;opacity:' + (op / 100) + ';';
                }
                function applyLiveStyle(root, key, value, fields) {
                    if (key === 'style._tilt') return applyLiveTilt(root, value);
                    // Task #6114: horizontal margins live on the block wrap
                    // itself (the page has no side padding); clearing the
                    // field reverts to the container's default child margin.
                    if (key === 'style.margin_left' || key === 'style.margin_right') {
                        var mProp = key === 'style.margin_left' ? 'marginLeft' : 'marginRight';
                        root.style[mProp] = value === '' ? '' : parseInt(value, 10) + 'px';
                        return true;
                    }
                    // Preset background transparency (Task #5988): fade the
                    // block's preset layer live while dragging the slider.
                    // querySelector picks the block's OWN layer first (a
                    // container renders its layer before its children's).
                    // No layer yet (preset just picked) = structural — reload.
                    if (key === 'style.bg_preset_opacity') {
                        var layer = root.querySelector('.block-bg-preset');
                        if (!layer) return false;
                        var op = parseInt(value, 10);
                        if (isNaN(op)) op = 100;
                        layer.style.opacity = String(Math.max(0, Math.min(100, op)) / 100);
                        return true;
                    }
                    // Preset swatch pick/change/remove (Task #5990): create or
                    // rewrite the block's preset layer in place so the swatch
                    // click shows instantly without a preview reload.
                    if (key === 'style.bg_preset_key') {
                        var pLayer = root.querySelector('.block-bg-preset');
                        if (String(value) === '') {
                            // Preset removed (swatch clicked again). Drop the
                            // layer for instant feedback; the dedicated
                            // .block-preset-wrap (skipWrap/button-like blocks)
                            // is structural, so that case still reloads.
                            if (!pLayer) return true;
                            var pParent = pLayer.parentElement;
                            pLayer.remove();
                            if (pParent && pParent.getAttribute('data-live-preset-host') === '1') {
                                // We added the host positioning styles live —
                                // revert them so the DOM matches a fresh
                                // server render with no preset.
                                pParent.style.removeProperty('position');
                                pParent.style.removeProperty('isolation');
                                pParent.style.removeProperty('overflow');
                                pParent.removeAttribute('data-live-preset-host');
                                return true;
                            }
                            // Server-rendered layer: the host keeps inline
                            // position/isolation/overflow from the Blade
                            // template — reload so the preview matches a
                            // fresh no-preset render (layer already gone,
                            // so the swap is visually seamless).
                            return false;
                        }
                        var pCss = BG_PRESET_CSS[String(value)];
                        if (!pCss) return false; // unknown key — safe reload
                        var pOp = 100;
                        if (fields && fields['style[bg_preset_opacity]'] !== undefined) {
                            var pn = parseInt(fields['style[bg_preset_opacity]'], 10);
                            if (!isNaN(pn)) pOp = Math.max(0, Math.min(100, pn));
                        }
                        if (pLayer) {
                            pLayer.style.cssText = bgPresetLayerCss(pCss, pOp);
                            return true;
                        }
                        // No layer yet — create it inside the block's own
                        // positioning host. Containers paint on their render
                        // wrapper; styled blocks on .block-styled. Button-like
                        // blocks without a styled wrapper need the dedicated
                        // .block-preset-wrap (structural) — reload for those.
                        var pType = root.getAttribute('data-block-type') || '';
                        var pHost = (pType === 'card' || pType === 'grid' || pType === 'grid_auto')
                            ? root.querySelector('.card-container-render, .grid-container-render')
                            : root.querySelector('.block-styled');
                        if (!pHost) return false;
                        pHost.style.position = 'relative';
                        pHost.style.isolation = 'isolate';
                        pHost.style.overflow = 'hidden';
                        pHost.setAttribute('data-live-preset-host', '1');
                        pLayer = document.createElement('div');
                        pLayer.className = 'block-bg-preset';
                        pLayer.setAttribute('aria-hidden', 'true');
                        pLayer.style.cssText = bgPresetLayerCss(pCss, pOp);
                        pHost.insertBefore(pLayer, pHost.firstChild);
                        return true;
                    }
                    var pfn = LIVE_PHOTO_KEYS[key];
                    if (pfn) {
                        // Decorations only exist inside an already-rendered
                        // hero container; anything else needs a reload.
                        var hero = root.querySelector('[data-photo-hero]');
                        return hero ? pfn(hero, value) !== false : false;
                    }
                    // Per-side border fields (Task #6041): each side's final
                    // CSS depends on its style+width+color plus the shorthand
                    // fallbacks, so recompute from the full form payload.
                    // Shorthand border edits also reroute here whenever any
                    // per-side override exists, otherwise the naive single-
                    // property patch would clobber the per-side values.
                    var isSideBorderKey = /^style\.border_(top|right|bottom|left)_(style|width|color)$/.test(key);
                    var isShorthandBorderKey = key === 'style.border_style' || key === 'style.border_width' || key === 'style.border_color';
                    if (fields && (isSideBorderKey || (isShorthandBorderKey && borderHasSideOverride(fields)))) {
                        var bEl = styleTarget(root);
                        if (!bEl) return false;
                        return applyLiveSideBorders(bEl, fields);
                    }
                    if (isSideBorderKey) return false;
                    // Per-device block width (Task #6119): patch the grid wrap
                    // itself. The base span drives the inline grid-column; the
                    // desktop override toggles the .md-span class + --md-span
                    // var (only visible at/above the 768px breakpoint, so the
                    // phone-width editor preview correctly keeps the base span).
                    if (key === 'style.grid_span') {
                        var gs = parseInt(value, 10) || 12;
                        root.style.gridColumn = 'span ' + Math.max(1, Math.min(12, gs));
                        return true;
                    }
                    if (key === 'style.grid_span_md') {
                        var gsm = parseInt(value, 10) || 0;
                        if (gsm >= 1 && gsm <= 12) {
                            root.classList.add('md-span');
                            root.style.setProperty('--md-span', String(gsm));
                        } else {
                            root.classList.remove('md-span');
                            root.style.removeProperty('--md-span');
                        }
                        return true;
                    }
                    var fn = LIVE_STYLE_KEYS[key];
                    if (!fn) return false;
                    var el = styleTarget(root);
                    if (!el) return false;
                    fn(el, value);
                    return true;
                }

                window.addEventListener('message', function (e) {
                    if (e.origin !== window.location.origin) return;
                    var d = e.data;
                    if (!d || d.type !== '1inme-block-live') return;
                    // A block can appear more than once on the page (the same
                    // block attached to multiple slides in Slides Mode), so
                    // patch every matching root; handled=true only when EVERY
                    // changed field patched on EVERY instance.
                    var roots = document.querySelectorAll('[data-block-id="' + d.blockId + '"]');
                    var handled = false;
                    if (roots.length && Array.isArray(d.changed) && d.fields) {
                        handled = d.changed.length > 0;
                        roots.forEach(function (root) {
                            var type = d.blockType || root.getAttribute('data-block-type') || '';
                            var handlers = LIVE_HANDLERS[type] || {};
                            d.changed.forEach(function (name) {
                                var key = dotted(name);
                                var value = d.fields[name] !== undefined ? d.fields[name] : '';
                                var ok = false;
                                if (key.indexOf('style.') === 0) {
                                    ok = applyLiveStyle(root, key, value, d.fields);
                                } else if (handlers[key]) {
                                    ok = handlers[key](root, value, [], d.fields) !== false;
                                } else {
                                    var w = wild(key);
                                    if (handlers[w.key]) ok = handlers[w.key](root, value, w.idx, d.fields) !== false;
                                }
                                if (!ok) handled = false;
                            });
                        });
                    }
                    try {
                        if (e.source) e.source.postMessage({ type: '1inme-block-live-ack', blockId: d.blockId, handled: handled }, e.origin);
                    } catch (err) {}
                });
            })();
})();
</script>
