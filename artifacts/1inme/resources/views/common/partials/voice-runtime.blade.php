{{--
    Shared voice-runtime core — the single source of truth for the parts of
    the voice agent that MUST stay identical across every surface that hosts a
    mic:

      • the admin floating widget (Alpine, partials/voice-assistant.blade.php)
      • the Zio chat panel composer (plain JS, common/partials/site-assistant)

    Only the drift-prone, UI-agnostic logic lives here so a fix in one place
    can never silently miss the other:

      • sendTurn()         — the /user/ai/voice/turn request: the exact turn
                             payload shape (audio + context{messages,
                             confirmed_tools, surface}) and a normalized
                             response object both surfaces render from.
      • applyToolResults() — the surface bridge: dispatch `voice-action`
                             CustomEvents for client_action results and return
                             the navigate_to target (the caller defers it until
                             the spoken reply finishes).
      • loadCaps()         — the /user/ai/voice/capabilities fetch + fallback.

    Recording (MediaRecorder) and rendering stay per-surface because they are
    bound to each surface's own DOM/state model; this module owns no UI.

    Defined idempotently so layouts that include both hosting partials only
    register one runtime (the right-hand IIFE is skipped on the second include).
--}}
<script>
window.VoiceRuntime = window.VoiceRuntime || (function () {
    function controlLabels() {
        return Array.from(document.querySelectorAll('button,a,label,input,select,textarea,[role="button"]'))
            .filter(function(n){return n.getClientRects().length && !n.closest('#site-assistant-root,#sa-panel,#sa-panel-wrap,.zio-guide') && n.type!=='password';})
            .map(function(n){return (n.getAttribute('aria-label') || n.getAttribute('placeholder') || n.innerText || '').trim().replace(/\s+/g,' ').slice(0,120);})
            .filter(Boolean).filter(function(v,i,a){return a.indexOf(v)===i;}).slice(0,80);
    }
    // POST one voice turn. Single source for the turn payload shape and for
    // parsing the server reply into a normalized object the callers render.
    // opts: { url, csrf, blob, messages, confirmedTools }
    // Resolves to { ok, status, error, transcript, reply, pending, credits,
    //               balance, toolResults, audioBase64 }. Rejects only on a
    //               network failure (callers show "Network error — retry").
    function sendTurn(opts) {
        opts = opts || {};
        var blob = opts.blob;
        var fd = new FormData();
        var ext = (blob && blob.type && blob.type.indexOf('webm') >= 0) ? 'webm' : 'ogg';
        if (blob) fd.append('audio', blob, 'voice.' + ext);
        fd.append('context', JSON.stringify({
            messages: opts.messages || [],
            visitor_token: opts.visitorToken || null,
            confirmed_tools: opts.confirmedTools || {},
            surface: Object.assign({name:'app'}, window.__voiceSurface || {}, {controls: controlLabels(),path:location.pathname})
        }));
        return fetch(opts.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': opts.csrf, 'Accept': 'application/json' },
            body: fd,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                json = json || {};
                return {
                    ok: res.ok,
                    status: res.status,
                    error: json.error || json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : null),
                    transcript: json.transcript || null,
                    reply: json.reply || null,
                    pending: json.pending_confirmations || [],
                    credits: json.credits || null,
                    balance: (json.balance != null ? json.balance : null),
                    toolResults: json.tool_results || [],
                    audioBase64: json.audio_base64 || null
                };
            });
        });
    }

    // Surface bridge: client_action → a `voice-action` window event the focused
    // surface listens for; navigate_to is returned so the caller can defer it
    // until the spoken reply has finished playing.
    function applyToolResults(results) {
        var nav = null;
        (results || []).forEach(function (tr) {
            var r = (tr && tr.result) || {};
            if (r.client_action) {
                window.dispatchEvent(new CustomEvent('voice-action', { detail: r.client_action }));
            }
            if (r.navigate_to) {
                try { var target = new URL(r.navigate_to, location.origin); if (target.origin === location.origin) nav = target.href; } catch(e) {}
            }
        });
        return nav;
    }

    // A virtual pointer highlights app controls; it never moves the OS mouse,
    // clicks, fills a secret, or opens the system picker without a user gesture.
    var guide = null, timer = null, guidePosition = null;
    function clearGuide() {
        if (guide) guide.remove(); guide = null; guidePosition = null; clearTimeout(timer);
    }
    function showGuide(action) {
        clearGuide();
        var label = String(action.label || '').trim().toLowerCase();
        if (!label) return;
        var nodes = Array.from(document.querySelectorAll('button,a,label,input,select,textarea,[role="button"]'));
        var target = nodes.find(function(n) {
            if(n.closest('#site-assistant-root,#sa-panel,#sa-panel-wrap,.zio-guide')) return false;
            if (!n.getClientRects().length || n.disabled) return false;
            var name = n.getAttribute('aria-label') || n.getAttribute('placeholder') || n.innerText || '';
            return name.trim().replace(/\s+/g,' ').toLowerCase() === label;
        });
        guide = document.createElement('div'); guide.className = 'zio-guide';
        guide.setAttribute('role','status');
        var text = document.createElement('span');
        text.textContent = target ? (action.instruction || 'Here is your next step.') : 'I could not find that control on this page. '+(action.instruction || '');
        var close = document.createElement('button'); close.type='button'; close.textContent='×'; close.setAttribute('aria-label','Dismiss Zio guidance'); close.onclick=clearGuide;
        guide.append(text,close); document.body.appendChild(guide);
        if(target) {
            target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
            var position = function() {
                if(!guide || !target.isConnected) return;
                var r=target.getBoundingClientRect();
                guide.style.left=Math.max(8,Math.min(r.left,innerWidth-300))+'px';
                guide.style.top=Math.max(8,Math.min(r.bottom+12,innerHeight-100))+'px';
                guide.style.setProperty('--zio-x', r.left+'px'); guide.style.setProperty('--zio-y',r.top+'px');
                guide.style.setProperty('--zio-w',r.width+'px'); guide.style.setProperty('--zio-h',r.height+'px');
            };
            guidePosition = position; position(); setTimeout(position,500);
            guide.classList.add('zio-guide-found');
        } else { guide.style.right='16px'; guide.style.bottom='24px'; }
        timer=setTimeout(clearGuide,20000);
    }
    window.addEventListener('voice-action',function(e) { if(e.detail && e.detail.type==='zio_guide') showGuide(e.detail); });
    window.addEventListener('keydown',function(e) { if(e.key==='Escape') clearGuide(); });
    window.addEventListener('resize',clearGuide);
    window.addEventListener('scroll',function(){if(guidePosition)guidePosition();},{passive:true});

    var navigationTimer = null;
    function navigateWithGuide(url, summary) {
        var target; try { target = new URL(url, location.origin); } catch(e) {return;}
        if(target.origin !== location.origin) return;
        if(target.pathname+target.search===location.pathname+location.search){window.dispatchEvent(new CustomEvent('zio-navigation-current'));return;}
        clearTimeout(navigationTimer); clearGuide();
        var link=Array.from(document.querySelectorAll('a[href]')).find(function(a){return a.href===target.href && a.getClientRects().length && !a.closest('#site-assistant-root');});
        showGuide({label:link ? (link.getAttribute('aria-label') || link.innerText).trim() : '', instruction:summary||'Opening your page…'});
        if(!guide){guide=document.createElement('div');guide.className='zio-guide';guide.style.right='24px';guide.style.bottom='100px';guide.setAttribute('role','status');document.body.appendChild(guide);}
        guide.replaceChildren();
        var note=document.createElement('span');note.textContent='↗ '+(summary||'Opening your page…');
        var cancel=document.createElement('button');cancel.type='button';cancel.textContent='Cancel';cancel.style.fontSize='14px';
        cancel.onclick=function(){clearTimeout(navigationTimer);try{sessionStorage.removeItem('zio_navigation_handoff');}catch(e){}clearGuide();};
        guide.append(note,cancel);
        try{sessionStorage.setItem('zio_navigation_handoff',JSON.stringify({path:target.pathname+target.search,at:Date.now()}));}catch(e){}
        navigationTimer=setTimeout(function(){window.location.assign(target.href);},2200);
    }

    // Capabilities catalogue ("What I can do"). Resolves to the parsed JSON, or
    // a friendly fallback shape on any failure so callers can render blindly.
    function loadCaps(url) {
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { tools: {}, limitations: ['Could not load capabilities.'] }; });
    }

    return { navigateWithGuide: navigateWithGuide, controlLabels: controlLabels, sendTurn: sendTurn, applyToolResults: applyToolResults, loadCaps: loadCaps };
})();
</script>

<style>
.zio-guide{position:fixed;z-index:2147483647;max-width:min(292px,calc(100vw - 16px));display:flex;align-items:flex-start;gap:10px;padding:14px 16px;background:#fff;color:#17213a;border:1px solid #c7d2fe;border-radius:16px;box-shadow:0 12px 40px #17213a26;font:500 14px/1.5 system-ui}
.zio-guide button{background:transparent;border:0;color:#64748b;font-size:22px;cursor:pointer}
.zio-guide-found:before{content:'↖';position:absolute;top:-29px;left:10px;color:#4562ff;font-size:30px}
.zio-guide-found:after{content:'';position:fixed;pointer-events:none;left:var(--zio-x);top:var(--zio-y);width:var(--zio-w);height:var(--zio-h);border:3px solid #4562ff;border-radius:10px;box-shadow:0 0 0 7px #4562ff26;animation:zio-pulse 1.8s ease-in-out infinite}
@keyframes zio-pulse{50%{box-shadow:0 0 0 12px #4562ff12}}
@media(prefers-reduced-motion:reduce){.zio-guide-found:after{animation:none}}
</style>
