{{--
    Site-wide AI Assistant launcher + chat panel.
    Surface is auto-detected by the controller based on auth state.
    Renders nothing if the assistant is disabled for this surface.
--}}
@php
    $__sa_surface = $surface ?? (auth()->check() ? 'app' : 'marketing');
    $__sa_cfg = \App\Services\AI\SiteAssistantSettings::get();
    $__sa_route_name = optional(\Illuminate\Support\Facades\Route::current())->getName();
    $__sa_path = '/' . ltrim(request()->path() === '/' ? '' : request()->path(), '/');
    $__sa_route_hint = \App\Modules\Common\Models\SiteAssistantPageHint::resolve($__sa_route_name, $__sa_path, $__sa_surface);
@endphp
@if(\App\Services\AI\SiteAssistantSettings::isEnabledFor($__sa_surface) && !($__sa_route_hint && $__sa_route_hint->disable_widget))
<div id="site-assistant-root"
     data-surface="{{ $__sa_surface }}"
     data-route="{{ optional(\Illuminate\Support\Facades\Route::current())->getName() }}"
     data-path="{{ $__sa_path }}"
     data-title="{{ trim(strip_tags(View::yieldContent('title') ?: '')) ?: config('app.name') }}"
     data-position="{{ $__sa_cfg['launcher_position'] }}"
     data-accent="{{ $__sa_cfg['accent_color'] }}"
     data-avatar="{{ \App\Services\AI\SiteAssistantSettings::avatarUrlFor($__sa_cfg) }}"
     data-brand="{{ \App\Services\AI\SiteAssistantSettings::brandNameFor($__sa_cfg) }}"
     data-peek-avatar="{{ asset('branding/zio-bot-peek.png') }}"
     data-bootstrap-url="{{ url('/assistant/bootstrap') }}"
     data-session-url="{{ url('/assistant/session') }}"
     data-message-url="{{ url('/assistant/message') }}"
     data-stream-url="{{ url('/assistant/stream') }}"
     data-choice-url="{{ url('/assistant/choice') }}"
     data-handoff-url="{{ url('/assistant/handoff') }}"
     data-low-balance-click-url="{{ url('/assistant/low-balance-click') }}">
</div>
<style>
/* Brand-gradient launcher: chat-tag silhouette with aura, breath, sheen, sparkle, and tooltip.
   IDLE = small + subtle (40px, ~55% opacity). HOVER = grows to full size (68px) with
   100% opacity, sheen, and a satisfying spring-bounce. */
.sa-launcher-wrap{
  position:fixed;bottom:24px;z-index:99999;width:68px;height:68px;
  /* Idle state: shrink the whole widget + fade aura/ring/sparkles together */
  transform:scale(.6);transform-origin:bottom right;opacity:.65;
  transition:transform .35s cubic-bezier(.34,1.56,.64,1), opacity .25s ease, bottom .3s ease;
  /* The wrapper is purely a positioning shell — its layout box, the decorative
     aura/ring pseudo-elements, and the idle scale() padding must NOT swallow
     clicks. Only the actual button (and the live tooltip) are interactive, so
     the clickable hit area always matches the visible launcher. */
  pointer-events:none;
}
.sa-launcher-wrap.sa-pos-right{right:24px}
.sa-launcher-wrap.sa-pos-left{left:24px;transform-origin:bottom left}
/* Hover/focus: spring up to full size + full opacity */
.sa-launcher-wrap:hover,
.sa-launcher-wrap:focus-within{
  transform:scale(1);opacity:1;
  animation:sa-launcher-pop .55s cubic-bezier(.34,1.56,.64,1);
}
@keyframes sa-launcher-pop{
  0%  {transform:scale(.6)}
  55% {transform:scale(1.12)}
  100%{transform:scale(1)}
}
#sa-launcher{
  position:absolute;inset:0;width:68px;height:68px;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;border:0;color:#fff;z-index:2;
  pointer-events:auto; /* re-enable clicks on the button itself (wrap is none) */
  /* No background blob, ring, or glassy shadow — just the mascot head floating. */
  background:transparent;box-shadow:none;
}
#sa-launcher:active{transform:scale(.96)}
#sa-launcher:focus-visible{outline:2px solid #90acff;outline-offset:3px;border-radius:16px}
#sa-launcher .sa-icon-bubble{position:relative;z-index:1;filter:drop-shadow(0 1px 2px rgba(0,0,0,.25))}
/* Zio Bot mascot — the entire launcher face, gentle float so the character
   feels alive while floating on its own with no background behind it. */
#sa-launcher .sa-icon-mascot{position:relative;z-index:1;width:64px;height:64px;object-fit:contain;filter:drop-shadow(0 3px 6px rgba(15,23,42,.3));pointer-events:none;animation:sa-mascot-float 4s ease-in-out infinite}
@keyframes sa-mascot-float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-2px) rotate(-3deg)}}
@media (prefers-reduced-motion:reduce){#sa-launcher .sa-icon-mascot{animation:none}}
#sa-launcher .sa-spark{
  position:absolute;color:#fff;filter:drop-shadow(0 0 6px rgba(255,255,255,.85));z-index:1;
  animation:sa-spark-orbit 4.2s ease-in-out infinite;
}
#sa-launcher .sa-spark.s1{top:8px;right:11px;animation-delay:0s}
#sa-launcher .sa-spark.s2{bottom:14px;left:10px;animation-delay:.9s;transform:scale(.7)}
#sa-launcher .sa-spark.s3{top:18px;left:9px;animation-delay:1.7s;transform:scale(.55)}
@keyframes sa-spark-orbit{
  0%,100%{opacity:.25;transform:translate(0,0) scale(.7) rotate(0)}
  50%{opacity:1;transform:translate(2px,-2px) scale(1.05) rotate(20deg)}
}
/* Speech-bubble tooltip popping out next to the launcher */
.sa-tooltip{
  position:absolute;bottom:calc(100% + 14px);
  background:#fff;color:#0f172a;
  padding:9px 30px 9px 14px;
  border-radius:14px;font-size:13px;line-height:1.35;font-weight:500;
  font-family:'Space Grotesk','system-ui',sans-serif;
  box-shadow:0 12px 30px -8px rgba(15,23,42,.35),0 4px 10px -4px rgba(15,23,42,.2);
  max-width:240px;white-space:normal;
  opacity:0;transform:translateY(10px) scale(.9);transform-origin:bottom right;
  pointer-events:none;cursor:pointer;
  transition:opacity .22s ease, transform .35s cubic-bezier(.34,1.56,.64,1);
}
.sa-tooltip.sa-pos-right{right:0;transform-origin:bottom right}
.sa-tooltip.sa-pos-left{left:0;transform-origin:bottom left}
.sa-tooltip.sa-show{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;animation:sa-tip-bounce .6s ease-out .12s 1}
.sa-tooltip::after{
  content:"";position:absolute;bottom:-6px;width:14px;height:14px;
  background:#fff;transform:rotate(45deg);
  box-shadow:3px 3px 6px -3px rgba(15,23,42,.25);
}
.sa-tooltip.sa-pos-right::after{right:18px}
.sa-tooltip.sa-pos-left::after{left:18px}
.sa-tooltip-text{display:inline-block;min-height:1em}
.sa-tooltip-text::after{content:"\200B"}
.sa-tooltip-close{
  position:absolute;top:4px;right:6px;width:18px;height:18px;
  background:transparent;border:0;color:#64748b;font-size:16px;line-height:1;
  cursor:pointer;padding:0;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
}
.sa-tooltip-close:hover{background:rgba(15,23,42,.08);color:#0f172a}
@keyframes sa-tip-bounce{
  0%{transform:translateY(0) scale(1)}
  40%{transform:translateY(-3px) scale(1.03)}
  100%{transform:translateY(0) scale(1)}
}
@media (prefers-reduced-motion:reduce){
  #sa-launcher,#sa-launcher .sa-spark,#sa-launcher .sa-icon-mascot{animation:none}
  .sa-tooltip,.sa-tooltip.sa-show{transition:none;animation:none}
}
#sa-panel-wrap{position:fixed;bottom:90px;width:380px;max-width:calc(100vw - 24px);z-index:99999;display:none;flex-direction:column;align-items:stretch}
#sa-panel-wrap.sa-pos-right{right:20px}
#sa-panel-wrap.sa-pos-left{left:20px}
#sa-panel-wrap.sa-open{display:flex}
#sa-panel{position:relative;width:100%;height:560px;max-height:calc(100vh - 120px);background:#0f172a;color:#e2e8f0;border-radius:16px;box-shadow:0 25px 60px rgba(0,0,0,.5);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.08);font-family:'Space Grotesk','system-ui',sans-serif}
/* Zio Bot mascot peeking over the top edge of the chat panel: it sits above
   the panel (a flex sibling) and dips ~12px into the top edge so its hands read
   as gripping the border. Centered so it never covers the header text/close. */
#sa-peek{align-self:center;width:104px;max-width:60%;margin-bottom:-12px;pointer-events:none;z-index:1;line-height:0;transform-origin:bottom center;animation:sa-peek-rise .85s cubic-bezier(.22,1,.36,1) both,sa-peek-bob 4.5s ease-in-out 1s infinite}
#sa-peek img{width:100%;height:auto;display:block;filter:drop-shadow(0 6px 10px rgba(15,23,42,.35))}
@keyframes sa-peek-rise{0%{opacity:0;transform:translateY(34px)}100%{opacity:1;transform:translateY(0)}}
@keyframes sa-peek-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@media (max-width:480px){#sa-panel-wrap{width:calc(100vw - 16px);right:8px!important;left:8px!important;bottom:80px}#sa-panel{height:calc(100vh - 100px)}#sa-peek{width:84px}}
@media (prefers-reduced-motion:reduce){#sa-peek{animation:none;opacity:1}}
.sa-header{padding:14px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.06)}
.sa-header img,.sa-header .sa-avatar{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff}
.sa-header img{object-fit:contain;padding:1px}
.sa-header h4{margin:0;font-size:14px;font-weight:600;color:#fff}
.sa-header .sa-sub{font-size:11px;opacity:.65}
.sa-close{margin-left:auto;background:transparent;border:0;color:#94a3b8;font-size:18px;cursor:pointer}
.sa-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}
.sa-msg{max-width:85%;padding:10px 12px;border-radius:14px;font-size:13.5px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}
.sa-msg.user{align-self:flex-end;background:var(--sa-accent,#3d6bff);color:#fff;border-bottom-right-radius:4px}
.sa-msg.assistant{align-self:flex-start;background:rgba(255,255,255,.06);color:#e2e8f0;border-bottom-left-radius:4px}
.sa-msg.error{align-self:center;background:rgba(239,68,68,.12);color:#fca5a5;font-size:12px;border-radius:8px}
.sa-cutoff{align-self:flex-start;display:flex;align-items:center;gap:8px;font-size:11.5px;color:#fbbf24;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25);padding:6px 10px;border-radius:8px;margin-top:-2px;max-width:85%}
.sa-cutoff button{background:rgba(251,191,36,.18);border:1px solid rgba(251,191,36,.35);color:#fde68a;font-size:11.5px;padding:3px 10px;border-radius:999px;cursor:pointer;font-family:inherit}
.sa-cutoff button:hover{background:rgba(251,191,36,.32)}
.sa-cutoff button:disabled{opacity:.5;cursor:not-allowed}
.sa-blocks{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.sa-buttons{display:flex;flex-wrap:wrap;gap:6px}
.sa-btn{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);color:#fff;padding:6px 12px;border-radius:999px;font-size:12px;cursor:pointer}
.sa-btn:hover{background:var(--sa-accent,#3d6bff);border-color:transparent}
.sa-list{display:flex;flex-direction:column;gap:6px;max-height:240px;overflow-y:auto;padding-right:4px}
.sa-list-item{display:flex;gap:10px;align-items:flex-start;padding:8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:10px;cursor:pointer}
.sa-list-item:hover{background:rgba(255,255,255,.08)}
.sa-list-item img{width:40px;height:40px;border-radius:8px;object-fit:cover;flex-shrink:0}
.sa-list-item .sa-li-title{font-size:13px;font-weight:600;color:#fff}
.sa-list-item .sa-li-desc{font-size:11px;opacity:.75;margin-top:2px}
.sa-image{max-width:100%;border-radius:10px}
.sa-form{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:8px}
.sa-form input,.sa-form textarea{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.1);color:#fff;padding:8px 10px;border-radius:8px;font-size:13px;font-family:inherit;width:100%;box-sizing:border-box}
.sa-form button{background:var(--sa-accent,#3d6bff);color:#fff;border:0;padding:8px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600}
.sa-suggested{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 8px}
.sa-suggested .sa-btn{font-size:11.5px}
.sa-low-balance{display:none;margin:0 10px 6px;padding:7px 10px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);border-radius:8px;color:#fde68a;font-size:11.5px;line-height:1.35;align-items:center;gap:8px;flex-wrap:wrap}
.sa-low-balance.sa-show{display:flex}
.sa-low-balance .sa-lb-msg{flex:1;min-width:0}
.sa-low-balance .sa-lb-cta{flex-shrink:0;background:rgba(251,191,36,.22);border:1px solid rgba(251,191,36,.45);color:#fde68a;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;text-decoration:none;font-family:inherit;cursor:pointer;white-space:nowrap}
.sa-low-balance .sa-lb-cta:hover{background:rgba(251,191,36,.36);color:#fff}
.sa-input-row{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(255,255,255,.06)}
.sa-input-row textarea{flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#fff;padding:8px 10px;border-radius:10px;resize:none;font-size:13px;font-family:inherit;max-height:120px;min-height:36px}
.sa-input-row button{background:var(--sa-accent,#3d6bff);border:0;color:#fff;padding:0 14px;border-radius:10px;cursor:pointer;font-size:14px}
.sa-input-row button:disabled{opacity:.5;cursor:not-allowed}
.sa-typing{align-self:flex-start;font-size:11px;color:#94a3b8;padding:0 4px}
.sa-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #0f172a}
/* (.sa-launcher-wrap rules consolidated above with the new launcher styles.) */
/* --- Light-mode overrides: follow the site theme (html.light-mode), keep purple accent + brand launcher intact --- */
html.light-mode #sa-panel{background:#ffffff;color:#1e293b;border:1px solid rgba(15,23,42,.1);box-shadow:0 25px 60px rgba(15,23,42,.18)}
html.light-mode .sa-header{border-bottom:1px solid rgba(15,23,42,.08)}
html.light-mode .sa-header img,html.light-mode .sa-header .sa-avatar{background:rgba(15,23,42,.06);color:#0f172a}
html.light-mode .sa-header h4{color:#0f172a}
html.light-mode .sa-close{color:#64748b}
html.light-mode .sa-close:hover{color:#0f172a}
html.light-mode .sa-msg.assistant{background:rgba(15,23,42,.05);color:#1e293b}
html.light-mode .sa-msg.error{background:rgba(239,68,68,.1);color:#b91c1c}
html.light-mode .sa-cutoff{color:#b45309;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.4)}
html.light-mode .sa-cutoff button{background:rgba(251,191,36,.2);border:1px solid rgba(251,191,36,.5);color:#92400e}
html.light-mode .sa-cutoff button:hover{background:rgba(251,191,36,.35)}
html.light-mode .sa-btn{background:rgba(15,23,42,.05);border:1px solid rgba(15,23,42,.12);color:#1e293b}
html.light-mode .sa-btn:hover{background:var(--sa-accent,#3d6bff);border-color:transparent;color:#fff}
html.light-mode .sa-list-item{background:rgba(15,23,42,.03);border:1px solid rgba(15,23,42,.08)}
html.light-mode .sa-list-item:hover{background:rgba(15,23,42,.06)}
html.light-mode .sa-list-item .sa-li-title{color:#0f172a}
html.light-mode .sa-form{background:rgba(15,23,42,.03);border:1px solid rgba(15,23,42,.08)}
html.light-mode .sa-form input,html.light-mode .sa-form textarea{background:#ffffff;border:1px solid rgba(15,23,42,.15);color:#1e293b}
html.light-mode .sa-low-balance{background:rgba(251,191,36,.14);border:1px solid rgba(251,191,36,.4);color:#92400e}
html.light-mode .sa-low-balance .sa-lb-cta{background:rgba(251,191,36,.24);border:1px solid rgba(251,191,36,.5);color:#92400e}
html.light-mode .sa-low-balance .sa-lb-cta:hover{background:rgba(251,191,36,.4);color:#0f172a}
html.light-mode .sa-input-row{border-top:1px solid rgba(15,23,42,.08)}
html.light-mode .sa-input-row textarea{background:rgba(15,23,42,.04);border:1px solid rgba(15,23,42,.12);color:#1e293b}
html.light-mode .sa-typing{color:#64748b}
html.light-mode .sa-badge{border:2px solid #ffffff}
</style>
<script>
// Server-resolved localized chrome strings, exposed up front so the
// initial DOM build (subheading) and any pre-bootstrap JS branches
// (none today, but defensive for future async edits) use the
// visitor's language instead of the English defaults. Bootstrap
// later overwrites window.__SA_CHROME with the same values once the
// fetch completes — keeping a single source of truth at runtime.
window.__SA_SUBHEADING = @json(\App\Services\AI\SiteAssistantSettings::subheadingFor($__sa_cfg));
// Assistant display name ("Zio Bot" by default) shown in the chat header.
window.__SA_BRAND = @json(\App\Services\AI\SiteAssistantSettings::brandNameFor($__sa_cfg));
// Rotating tooltip messages. We seed with a few inline brand defaults
// passed through Laravel's translator (so installs that ship matching
// translation files can localize them) and merge in the admin's
// already-localized starter prompts — `starterPromptsFor` resolves the
// Accept-Language header for us, so the visitor sees prompts in their
// language whenever the admin has provided overrides.
@php
    $__sa_tooltip_seed = [
        __('Need a hand? 👋'),
        __('Ask me anything'),
        __('Tips for this page?'),
    ];
    $__sa_tooltip_admin = \App\Services\AI\SiteAssistantSettings::starterPromptsFor($__sa_cfg);
    $__sa_tooltips = array_values(array_unique(array_filter(array_map(
        'strval',
        array_merge($__sa_tooltip_seed, is_array($__sa_tooltip_admin) ? $__sa_tooltip_admin : [])
    ))));
@endphp
window.__SA_TOOLTIPS = @json($__sa_tooltips);
window.__SA_CHROME = {
  subheading:        @json(\App\Services\AI\SiteAssistantSettings::subheadingFor($__sa_cfg)),
  typing_indicator:  @json(\App\Services\AI\SiteAssistantSettings::typingIndicatorFor($__sa_cfg)),
  handoff_note:      @json(\App\Services\AI\SiteAssistantSettings::handoffNoteFor($__sa_cfg)),
  cutoff_notice:     @json(\App\Services\AI\SiteAssistantSettings::cutoffNoticeFor($__sa_cfg)),
  cutoff_retry_label:@json(\App\Services\AI\SiteAssistantSettings::cutoffRetryLabelFor($__sa_cfg)),
  error_network:     @json(\App\Services\AI\SiteAssistantSettings::errorNetworkFor($__sa_cfg)),
  error_generic:     @json(\App\Services\AI\SiteAssistantSettings::errorGenericFor($__sa_cfg))
};
(function(){
  var root=document.getElementById('site-assistant-root');
  if(!root) return;
  var ds=root.dataset;
  var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  var TOKEN_KEY='sa_visitor_token_v1';
  var token=localStorage.getItem(TOKEN_KEY)||'';
  var open=false, busy=false, bootstrapped=false, cfg=null, templates=[], unread=0;
  var messages=[];
  // Localized chrome strings live here so every renderer reads from
  // a single source — initialised from the server-rendered globals
  // and then overwritten when bootstrap returns (admin edits made
  // between render and open are picked up that way).
  var CHROME = Object.assign({
    subheading: 'How can I help?',
    typing_indicator: 'Assistant is typing…',
    handoff_note: 'Our team will reply by email.',
    cutoff_notice: '⚠ This reply was cut off —',
    cutoff_retry_label: 'Retry',
    error_network: 'Network error.',
    error_generic: 'Sorry, something went wrong.'
  }, window.__SA_CHROME || {});

  function pageMeta(){
    return {
      route: ds.route || '',
      path: ds.path || '/',
      title: ds.title || document.title || '',
      url: location.href
    };
  }
  // The mounted widget knows its surface (marketing vs app) because
  // it's rendered by the corresponding layout. Sending it explicitly
  // keeps backend behavior consistent for logged-in users browsing
  // marketing pages.
  var SURFACE = (ds.surface === 'app' || ds.surface === 'marketing') ? ds.surface : '';
  function jpost(url, body){
    body = body || {};
    if (SURFACE && !body.surface) body.surface = SURFACE;
    return fetch(url, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body: JSON.stringify(body||{})
    }).then(function(r){ return r.json(); });
  }

  function el(tag,attrs,children){
    var e=document.createElement(tag);
    if(attrs){ for(var k in attrs){
      if(k==='style' && typeof attrs[k]==='object'){ for(var s in attrs[k]) e.style[s]=attrs[k][s]; }
      else if(k==='class') e.className=attrs[k];
      else if(k==='html') e.innerHTML=attrs[k];
      else if(k.indexOf('on')===0 && typeof attrs[k]==='function') e.addEventListener(k.slice(2),attrs[k]);
      else e.setAttribute(k,attrs[k]);
    }}
    if(children){
      (Array.isArray(children)?children:[children]).forEach(function(c){
        if(c==null) return;
        if(typeof c==='string') e.appendChild(document.createTextNode(c));
        else e.appendChild(c);
      });
    }
    return e;
  }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
  }
  function mdLite(s){
    s=escapeHtml(s);
    s=s.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');
    s=s.replace(/\*([^*]+)\*/g,'<em>$1</em>');
    s=s.replace(/`([^`]+)`/g,'<code>$1</code>');
    s=s.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener" style="color:#90acff;text-decoration:underline">$1</a>');
    s=s.replace(/\n/g,'<br>');
    return s;
  }

  // ── DOM build ─────────────────────────────────────────────────
  var pos=ds.position==='bottom-left'?'sa-pos-left':'sa-pos-right';
  var launcherWrap=el('div',{class:'sa-launcher-wrap '+pos});
  var launcher=el('button',{id:'sa-launcher',class:pos,type:'button','aria-label':'Open assistant',
    style:{'--sa-accent':ds.accent||'#3d6bff'}}, '');
  // Face of the assistant: the Zio Bot mascot rides on the brand-gradient
  // button. When an admin hasn't set an avatar the resolver already hands
  // us the bundled mascot, so ds.avatar is effectively always present; the
  // chat-bubble glyph stays as a defensive fallback if it's ever empty.
  var launcherFace = ds.avatar
    ? '<img class="sa-icon-mascot" src="'+escapeHtml(ds.avatar)+'" alt="" aria-hidden="true">'
    : '<svg class="sa-icon-bubble" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      +  '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H12l-4 4v-4H6.5A2.5 2.5 0 0 1 4 13.5v-8z"/>'
      +  '<path d="M12 6.2l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9z" fill="currentColor" stroke="none"/>'
      +'</svg>';
  launcher.innerHTML=''
    +launcherFace
    +'<svg class="sa-spark s1" width="8" height="8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>'
    +'<svg class="sa-spark s2" width="8" height="8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>'
    +'<svg class="sa-spark s3" width="8" height="8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>';
  var badge=el('span',{class:'sa-badge',style:{display:'none'}}, '0');
  launcher.appendChild(badge);
  launcherWrap.appendChild(launcher);
  // Speech-bubble tooltip — anchored to the launcher wrapper so it
  // automatically follows the configured left/right position. Hidden
  // by default; the scheduler below pops it out at randomized
  // intervals while the chat panel is closed.
  var tooltip=el('div',{class:'sa-tooltip '+pos,role:'button',tabindex:'-1','aria-hidden':'true'});
  var tooltipText=el('span',{class:'sa-tooltip-text'},'');
  var tooltipClose=el('button',{class:'sa-tooltip-close',type:'button','aria-label':'Dismiss'},'×');
  tooltip.appendChild(tooltipText);
  tooltip.appendChild(tooltipClose);
  launcherWrap.appendChild(tooltip);
  document.body.appendChild(launcherWrap);

  var panelWrap=el('div',{id:'sa-panel-wrap',class:pos});
  var panel=el('div',{id:'sa-panel',class:pos,style:{'--sa-accent':ds.accent||'#3d6bff'}});
  // Decorative mascot that peeks over the top edge of the panel. Sits above the
  // panel (a flex sibling, not clipped by the panel's overflow:hidden) and dips
  // into the top edge so it reads as a character gripping the border.
  if(ds.peekAvatar){
    var peek=el('div',{id:'sa-peek','aria-hidden':'true',html:'<img src="'+escapeHtml(ds.peekAvatar)+'" alt="">'});
    panelWrap.appendChild(peek);
  }
  // Subheading is rendered server-side using the localized
  // `subheading` field exposed by the partial, so visitors with a
  // non-English Accept-Language never see English chrome flash before
  // bootstrap arrives. Bootstrap re-applies it (in case admin edits
  // happened mid-pageload) but the initial value is already correct.
  var subInit = (typeof window.__SA_SUBHEADING==='string' && window.__SA_SUBHEADING) ? window.__SA_SUBHEADING : 'How can I help?';
  var header=el('div',{class:'sa-header',html:'<div><h4>'+escapeHtml(window.__SA_BRAND||'Assistant')+'</h4><div class="sa-sub" id="sa-sub">'+escapeHtml(subInit)+'</div></div>'});
  var closeBtn=el('button',{class:'sa-close',type:'button','aria-label':'Close'},'×');
  closeBtn.onclick=function(){ togglePanel(false); };
  header.appendChild(closeBtn);
  panel.appendChild(header);

  var suggested=el('div',{class:'sa-suggested',id:'sa-suggested'});
  panel.appendChild(suggested);
  var body=el('div',{class:'sa-body',id:'sa-body'});
  panel.appendChild(body);
  var lowBalance=el('div',{class:'sa-low-balance',id:'sa-low-balance'});
  panel.appendChild(lowBalance);
  var inputRow=el('div',{class:'sa-input-row'});
  // Initial placeholder/label use the built-in English defaults; the
  // bootstrap response (which honours admin per-locale overrides via
  // the visitor's Accept-Language header) updates these as soon as it
  // arrives so visitors with non-English browsers don't see English
  // chrome flash before the localized strings come in.
  var ta=el('textarea',{rows:'1',placeholder:'Type a message…',id:'sa-input'});
  ta.addEventListener('keydown',function(e){
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMessage(); }
  });
  var sendBtn=el('button',{type:'button',id:'sa-send'}, 'Send');
  sendBtn.onclick=sendMessage;
  inputRow.appendChild(ta); inputRow.appendChild(sendBtn);
  panel.appendChild(inputRow);
  panelWrap.appendChild(panel);
  document.body.appendChild(panelWrap);

  launcher.onclick=function(){ togglePanel(!open); };

  function togglePanel(on){
    open = !!on;
    panelWrap.classList.toggle('sa-open', open);
    if(open){
      unread=0; badge.style.display='none';
      hideTooltip();
      if(!bootstrapped){ bootstrap(); }
      setTimeout(function(){ ta.focus(); }, 50);
    }
  }
  function setUnread(n){ unread=n; if(n>0){ badge.textContent=String(n); badge.style.display='flex'; } else { badge.style.display='none'; } }

  function bootstrap(){
    bootstrapped=true;
    fetch(ds.bootstrapUrl + (SURFACE ? ('?surface=' + encodeURIComponent(SURFACE)) : ''),{credentials:'same-origin',headers:{'Accept':'application/json'}})
      .then(function(r){return r.json();})
      .then(function(data){
        cfg=data; templates=data.templates||[];
        // Refresh the localized chrome cache from bootstrap so any
        // edits since render-time are picked up. Each key is optional
        // — missing keys keep the server-rendered initial value.
        ['subheading','typing_indicator','handoff_note','cutoff_notice','cutoff_retry_label','error_network','error_generic'].forEach(function(k){
          if(typeof data[k]==='string' && data[k]) CHROME[k]=data[k];
        });
        // Subheading is shown in the header until the first message is
        // rendered, then replaced with the typing/handoff note. We
        // re-apply it here too so admin edits made between render and
        // open are picked up without a hard refresh.
        var subEl=document.getElementById('sa-sub');
        if(subEl && !data.greeting){ subEl.textContent = CHROME.subheading; }
        if(data.greeting){ document.getElementById('sa-sub').textContent=''; }
        // Apply localized chrome (placeholder + Send label) — falls
        // back to the English defaults already set on the elements
        // when the admin hasn't customized them.
        if(data.input_placeholder){ ta.setAttribute('placeholder', data.input_placeholder); }
        if(data.send_label){ sendBtn.textContent = data.send_label; }
        return jpost(ds.sessionUrl,{ visitor_token: token, page: pageMeta() });
      })
      .then(function(s){
        if(!s||!s.ok){
          if(s && s.visitor_token){ token=s.visitor_token; localStorage.setItem(TOKEN_KEY, token); }
          return;
        }
        token=s.visitor_token; localStorage.setItem(TOKEN_KEY, token);
        body.innerHTML='';
        if(s.messages && s.messages.length){
          s.messages.forEach(renderMessage);
        } else {
          // greeting
          var g = (cfg&&cfg.greeting) || s.greeting || @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_GREETING);
          renderMessage({role:'assistant', content: g, blocks:null});
        }
        // Page-aware suggestions sourced from admin page hints sit on
        // top of the static starter_prompts so visitors see the most
        // contextually relevant options first.
        var combined = [].concat(s.page_suggestions || [], s.starter_prompts || (cfg&&cfg.starter_prompts) || []);
        renderSuggested(combined);
        if(s.handed_off){ disableInput(true, CHROME.handoff_note); }
        renderLowBalance(s.low_balance);
        scrollBottom();
      });
  }

  // Pre-send credit warning. Server already decided whether to surface
  // a number or just a generic hint (anonymous visitors never see the
  // exact balance), so the widget just renders whatever message it
  // gets back. Hides itself when the signal goes away.
  function renderLowBalance(lb){
    if(!lb || !lb.low || !lb.message){
      lowBalance.classList.remove('sa-show');
      lowBalance.innerHTML='';
      return;
    }
    lowBalance.innerHTML='';
    var msgEl=el('span',{class:'sa-lb-msg'}, lb.message);
    lowBalance.appendChild(msgEl);
    if(lb.topup_url){
      var cta=el('a',{
        class:'sa-lb-cta',
        href: lb.topup_url,
        target: '_self',
        rel: 'noopener'
      }, lb.topup_label || 'Top up');
      cta.addEventListener('click', function(){
        // Fire-and-forget tracking beacon. `keepalive:true` lets the
        // request complete after the page starts navigating away so
        // we don't block the click or lose the event on fast nav.
        try {
          fetch(ds.lowBalanceClickUrl, {
            method:'POST', credentials:'same-origin', keepalive:true,
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({
              visitor_token: token || '',
              surface: SURFACE || undefined,
              target_url: lb.topup_url
            })
          });
        } catch(e) {}
      });
      lowBalance.appendChild(cta);
    }
    lowBalance.classList.add('sa-show');
  }

  function renderSuggested(arr){
    suggested.innerHTML='';
    if(!arr||!arr.length) return;
    arr.forEach(function(p){
      var label = typeof p==='string'? p : (p.label||'');
      if(!label) return;
      var b=el('button',{class:'sa-btn',type:'button'}, label);
      b.onclick=function(){ ta.value=label; sendMessage(); };
      suggested.appendChild(b);
    });
  }

  function disableInput(off, note){
    ta.disabled=!!off; sendBtn.disabled=!!off;
    if(note){ document.getElementById('sa-sub').textContent=note; }
  }

  function renderMessage(m){
    var d=el('div',{class:'sa-msg '+(m.role==='user'?'user':'assistant')});
    if(m.content){ d.innerHTML = mdLite(m.content); }
    body.appendChild(d);
    if(m.blocks && m.blocks.length){
      var wrap=el('div',{class:'sa-blocks'});
      m.blocks.forEach(function(b){ renderBlock(b, wrap); });
      body.appendChild(wrap);
    }
    if(!open){ setUnread(unread+1); }
    scrollBottom();
  }

  function renderBlock(b, wrap){
    if(!b||!b.type) return;
    if(b.type==='buttons'){
      var row=el('div',{class:'sa-buttons'});
      (b.options||[]).forEach(function(opt){
        var btn=el('button',{class:'sa-btn',type:'button'}, opt.label||opt.value||'');
        btn.onclick=function(){ submitChoice({label:opt.label,value:opt.value,template:b.template||null}); };
        row.appendChild(btn);
      });
      wrap.appendChild(row);
    } else if(b.type==='list'){
      var list=el('div',{class:'sa-list'});
      (b.options||[]).forEach(function(opt){
        var item=el('div',{class:'sa-list-item'});
        if(opt.thumbnail){ item.appendChild(el('img',{src:opt.thumbnail,alt:''})); }
        var info=el('div',{},[
          el('div',{class:'sa-li-title'}, opt.title||opt.label||''),
          opt.description ? el('div',{class:'sa-li-desc'}, opt.description) : null
        ]);
        item.appendChild(info);
        item.onclick=function(){ submitChoice({label:opt.title||opt.label,value:opt.value||opt.action||'',template:b.template||null}); };
        list.appendChild(item);
      });
      wrap.appendChild(list);
    } else if(b.type==='image'){
      (b.images||[b]).forEach(function(img){
        if(img && img.src){
          wrap.appendChild(el('img',{class:'sa-image',src:img.src,alt:img.alt||''}));
        }
      });
    } else if(b.type==='form'){
      var form=el('div',{class:'sa-form'});
      var inputs={};
      (b.fields||[]).forEach(function(f){
        var input;
        if(f.type==='textarea'){ input=el('textarea',{placeholder:f.label||f.name,rows:'3'}); }
        else { input=el('input',{type:f.type||'text',placeholder:f.label||f.name}); }
        if(f.required) input.setAttribute('required','required');
        inputs[f.name||f.label]=input;
        form.appendChild(input);
      });
      var submit=el('button',{type:'button'}, b.submit_label||'Submit');
      submit.onclick=function(){
        var values={};
        var ok=true;
        Object.keys(inputs).forEach(function(k){
          var v=inputs[k].value.trim();
          if(inputs[k].hasAttribute('required') && !v){ ok=false; inputs[k].style.borderColor='#ef4444'; }
          values[k]=v;
        });
        if(!ok) return;
        if(b.action==='handoff'){
          submitHandoff(values);
        } else {
          submitChoice({label:b.submit_label||'Submitted', values:values, template:b.template||null});
        }
      };
      form.appendChild(submit);
      wrap.appendChild(form);
    }
  }

  function scrollBottom(){ body.scrollTop=body.scrollHeight; }

  function appendTyping(){
    var t=el('div',{class:'sa-typing',id:'sa-typing'}, CHROME.typing_indicator);
    body.appendChild(t); scrollBottom();
  }
  function removeTyping(){ var t=document.getElementById('sa-typing'); if(t) t.remove(); }

  function sendMessage(){
    if(busy) return;
    var text=ta.value.trim(); if(!text) return;
    ta.value=''; busy=true; sendBtn.disabled=true;
    renderMessage({role:'user',content:text});
    streamMessage(text).finally(function(){ busy=false; sendBtn.disabled=false; });
  }

  // Retry a previous prompt after a mid-stream cutoff. Reuses the same
  // text without re-rendering the visitor's message bubble (it's still
  // visible above the cut-off reply). The id of the partial assistant
  // message is forwarded so the server can flag the new user message
  // as a retry of that specific cut-off reply (admin transcript view).
  function retryPrompt(text, retryOfId){
    if(busy || !text) return;
    busy=true; sendBtn.disabled=true;
    streamMessage(text, retryOfId).finally(function(){ busy=false; sendBtn.disabled=false; });
  }

  // Streaming via fetch + ReadableStream so the assistant reply paints
  // word-by-word. We pre-render an empty assistant bubble and append
  // each token as it arrives. Final `done` event swaps the bubble's
  // content with the sanitized server-side message (handles blocks).
  function streamMessage(text, retryOfId){
    var bubble=el('div',{class:'sa-msg assistant'},'');
    body.appendChild(bubble); scrollBottom();
    var streamed='';
    var doneReceived=false;
    var fellBack=false;
    // Fallback to the non-streaming /assistant/message endpoint when SSE
    // is unavailable (corporate proxies that strip event-stream, older
    // browsers without ReadableStream, upstream errors, or the
    // connection drops mid-stream). The fellBack guard makes the retry
    // idempotent so we never double-POST or double-render.
    function fallback(){
      if(fellBack || doneReceived) return;
      fellBack=true;
      if(bubble && bubble.parentNode) bubble.remove();
      appendTyping();
      return jpost(ds.messageUrl, { visitor_token: token, message: text, page: pageMeta() })
        .then(handleTurn)
        .catch(function(){
          removeTyping();
          renderMessage({role:'assistant',content: CHROME.error_network});
        });
    }
    return fetch(ds.streamUrl, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'text/event-stream'},
      body: JSON.stringify({ visitor_token: token, message: text, page: pageMeta(), surface: SURFACE || undefined, retry_of_message_id: retryOfId || undefined })
    }).then(function(res){
      var ct=(res.headers && res.headers.get && res.headers.get('Content-Type'))||'';
      if(!res.ok || !res.body || ct.indexOf('text/event-stream')<0){
        return fallback();
      }
      var reader=res.body.getReader(), dec=new TextDecoder(), buf='';
      function pump(){
        return reader.read().then(function(r){
          if(r.done){
            // Stream closed without a `done` event. Either the proxy
            // buffered and dropped the event-stream entirely, or the
            // upstream connection was cut mid-reply. In both cases we
            // fall back to /assistant/message so the user still gets a
            // complete answer instead of a truncated bubble.
            if(!doneReceived) return fallback();
            return;
          }
          buf += dec.decode(r.value, {stream:true});
          var idx;
          while((idx = buf.indexOf('\n\n')) >= 0){
            var frame = buf.slice(0, idx); buf = buf.slice(idx+2);
            var event='message', data='';
            frame.split('\n').forEach(function(line){
              if(line.indexOf('event:')===0) event=line.slice(6).trim();
              else if(line.indexOf('data:')===0) data += line.slice(5).trim();
            });
            if(!data) continue;
            var parsed; try { parsed=JSON.parse(data); } catch(e){ continue; }
            if(event==='token' && parsed.delta){
              streamed += parsed.delta;
              // Hide JSON-envelope mid-stream by stripping fenced blocks for display only.
              var visible = streamed.replace(/```json[\s\S]*?(```|$)/g,'');
              bubble.innerHTML = mdLite(visible);
              scrollBottom();
            } else if(event==='done'){
              doneReceived=true;
              bubble.remove();
              if(parsed.assistant_message) renderMessage(parsed.assistant_message);
              if(parsed.handed_off) disableInput(true, CHROME.handoff_note);
              if('low_balance' in parsed) renderLowBalance(parsed.low_balance);
            } else if(event==='error'){
              // Error is a terminal SSE event — mark the stream as
              // resolved so the r.done branch below does not also kick
              // off the non-streaming fallback.
              doneReceived=true;
              if(parsed.visitor_token){ token=parsed.visitor_token; localStorage.setItem(TOKEN_KEY, token); }
              // If the server already streamed some tokens before failing,
              // keep the partial bubble in view and offer a retry. Otherwise
              // fall back to the centered error toast as before.
              if(parsed.partial && streamed){
                var partialMsgId = parsed.assistant_message_id || null;
                var note=el('div',{class:'sa-cutoff'});
                note.appendChild(el('span',{}, CHROME.cutoff_notice + ' '));
                var retryBtn=el('button',{type:'button'}, CHROME.cutoff_retry_label);
                retryBtn.onclick=function(){
                  if(busy) return;
                  retryBtn.disabled=true;
                  note.remove(); bubble.remove();
                  retryPrompt(text, partialMsgId);
                };
                note.appendChild(retryBtn);
                body.appendChild(note); scrollBottom();
              } else {
                bubble.remove();
                var d=el('div',{class:'sa-msg error'}, parsed.error || CHROME.error_generic);
                body.appendChild(d); scrollBottom();
              }
            } else if(event==='user'){
              // user_message acks — already rendered locally.
            }
          }
          return pump();
        });
      }
      return pump();
    }).catch(function(){
      // Network failure on the SSE request — try the legacy endpoint
      // before surfacing any error to the user.
      return fallback();
    });
  }

  function submitChoice(choice){
    if(busy) return;
    busy=true; sendBtn.disabled=true;
    renderMessage({role:'user',content: choice.label||'Selected'}); appendTyping();
    jpost(ds.choiceUrl,{ visitor_token: token, choice: choice, page: pageMeta() })
      .then(handleTurn).catch(function(){ removeTyping(); })
      .finally(function(){ busy=false; sendBtn.disabled=false; });
  }

  function submitHandoff(values){
    if(busy) return;
    busy=true; sendBtn.disabled=true; appendTyping();
    jpost(ds.handoffUrl,{
      visitor_token: token,
      name: values.name||values.Name||'',
      email: values.email||values.Email||'',
      message: values.message||values.Message||'',
      page: pageMeta()
    }).then(function(res){
      removeTyping();
      if(res && res.ok){
        renderMessage(res.assistant_message);
        disableInput(true, CHROME.handoff_note);
      } else if(res && res.error){
        renderMessage({role:'assistant',content:res.error});
      }
    }).finally(function(){ busy=false; sendBtn.disabled=false; });
  }

  function handleTurn(res){
    removeTyping();
    if(!res||!res.ok){
      // Server may rotate the token (e.g. auth state changed) — adopt
      // the new one and tell the user to retry.
      if(res && res.visitor_token){ token=res.visitor_token; localStorage.setItem(TOKEN_KEY, token); }
      var msg=(res&&res.error) || CHROME.error_generic;
      var d=el('div',{class:'sa-msg error'},msg); body.appendChild(d); scrollBottom();
      return;
    }
    if(res.assistant_message) renderMessage(res.assistant_message);
    if(res.handed_off) disableInput(true, CHROME.handoff_note);
    if('low_balance' in res) renderLowBalance(res.low_balance);
  }

  // ── Tooltip scheduler ─────────────────────────────────────────
  // Pops the speech bubble next to the launcher at randomized
  // intervals while the chat is closed. Respects prefers-reduced-
  // motion, suppresses while inputs are focused or the user is
  // scrolling/typing, and remembers per-session dismissal so it
  // doesn't keep nagging the same visitor.
  var TOOLTIP_DISMISS_KEY='sa_tooltip_dismissed_v1';
  var tooltipMessages=Array.isArray(window.__SA_TOOLTIPS)?window.__SA_TOOLTIPS.filter(function(s){return s && typeof s==='string';}):[];
  var tooltipDismissed=false;
  try { tooltipDismissed = sessionStorage.getItem(TOOLTIP_DISMISS_KEY)==='1'; } catch(e){}
  var prefersReducedMotion=(window.matchMedia && window.matchMedia('(prefers-reduced-motion:reduce)').matches) || false;
  var tooltipTimer=null, tooltipHideTimer=null, tooltipTyperTimer=null;
  var lastScrollAt=0;
  var lastShownIndex=-1;
  window.addEventListener('scroll', function(){ lastScrollAt = Date.now(); }, {passive:true});

  function isInputFocused(){
    var ae=document.activeElement;
    if(!ae || ae===document.body) return false;
    if(ae===ta) return true;
    var t=(ae.tagName||'').toLowerCase();
    return t==='input'||t==='textarea'||t==='select'||ae.isContentEditable;
  }
  function pickTooltipMessage(){
    if(!tooltipMessages.length) return null;
    if(tooltipMessages.length===1) return tooltipMessages[0];
    var i;
    do { i = Math.floor(Math.random()*tooltipMessages.length); }
    while(i===lastShownIndex);
    lastShownIndex=i;
    return tooltipMessages[i];
  }
  function hideTooltip(){
    if(tooltipHideTimer){ clearTimeout(tooltipHideTimer); tooltipHideTimer=null; }
    if(tooltipTyperTimer){ clearInterval(tooltipTyperTimer); tooltipTyperTimer=null; }
    tooltip.classList.remove('sa-show');
    tooltip.setAttribute('aria-hidden','true');
  }
  function dismissTooltipForSession(){
    tooltipDismissed=true;
    try { sessionStorage.setItem(TOOLTIP_DISMISS_KEY,'1'); } catch(e){}
    if(tooltipTimer){ clearTimeout(tooltipTimer); tooltipTimer=null; }
    hideTooltip();
  }
  function showTooltip(){
    if(open || tooltipDismissed) return;
    if(isInputFocused()) return;
    if(Date.now() - lastScrollAt < 1500) return;
    var msg = pickTooltipMessage();
    if(!msg) return;
    if(tooltipHideTimer){ clearTimeout(tooltipHideTimer); tooltipHideTimer=null; }
    if(tooltipTyperTimer){ clearInterval(tooltipTyperTimer); tooltipTyperTimer=null; }
    tooltip.setAttribute('aria-hidden','false');
    tooltip.classList.add('sa-show');
    if(prefersReducedMotion){
      tooltipText.textContent = msg;
    } else {
      tooltipText.textContent = '';
      var i=0;
      tooltipTyperTimer=setInterval(function(){
        i++;
        tooltipText.textContent = msg.slice(0, i);
        if(i>=msg.length){ clearInterval(tooltipTyperTimer); tooltipTyperTimer=null; }
      }, 28);
    }
    var visibleMs = Math.min(8000, Math.max(4500, msg.length*70 + 2200));
    tooltipHideTimer=setTimeout(hideTooltip, visibleMs);
  }
  function scheduleTooltip(initial){
    if(tooltipTimer){ clearTimeout(tooltipTimer); tooltipTimer=null; }
    if(prefersReducedMotion || tooltipDismissed) return;
    if(!tooltipMessages.length) return;
    // First popup arrives a few seconds after load to feel inviting
    // but not aggressive; subsequent popups wait 25–55s with jitter
    // so the launcher feels alive without being spammy.
    var delay = initial
      ? (5000 + Math.random()*4000)
      : (25000 + Math.random()*30000);
    tooltipTimer=setTimeout(function(){
      showTooltip();
      scheduleTooltip(false);
    }, delay);
  }

  // Clicking the bubble itself opens the chat panel (and counts as a
  // soft dismissal — the user clearly engaged). The × button closes
  // the bubble without opening chat and suppresses it for the rest
  // of the session.
  tooltip.addEventListener('click', function(e){
    if(e.target===tooltipClose || (tooltipClose.contains && tooltipClose.contains(e.target))) return;
    // Clicking the bubble counts as engagement for this session — we
    // suppress further popups even after a page reload so the visitor
    // isn't re-nudged once they've already responded once.
    dismissTooltipForSession();
    togglePanel(true);
  });
  tooltipClose.addEventListener('click', function(e){
    e.stopPropagation();
    dismissTooltipForSession();
  });
  // Stop scheduling while the user actively interacts with form fields
  // anywhere on the page. We don't pause on every focus event (that
  // would thrash the timer); the in-flight popup just no-ops if a
  // field is focused at fire time.
  window.addEventListener('pagehide', function(){
    if(tooltipTimer){ clearTimeout(tooltipTimer); tooltipTimer=null; }
  });

  // ── Keep clear of the cookie-consent banner ───────────────────
  // The consent host renders at a far higher z-index than the launcher
  // and, in bottom corner/pill/banner layouts, parks its card in the
  // same bottom corner — physically covering the launcher and stealing
  // its clicks. We watch for the consent host and lift the launcher (and
  // its open panel) above the card so the launcher stays clickable.
  //
  // Two distinct defences are needed and the second was the regression:
  //   1. A *physical* nudge (saComputeBottom) moves the launcher's bottom
  //      offset up so it no longer sits underneath a bottom-corner card.
  //   2. A *stacking* lift (saApplyOffset) raises the launcher above the
  //      consent host's z-index whenever the host is present. Without it,
  //      modal/takeover layouts render a full-screen `.cc-backdrop`
  //      (pointer-events:auto, z ~2.1B) that blankets the whole viewport —
  //      including the corner — and eats every launcher click even though
  //      the centered card never physically overlaps the corner. Raising
  //      the launcher above the host lets the button poke through, while
  //      the wrapper stays pointer-events:none so the backdrop keeps
  //      blocking the rest of the page (consent behaviour is unchanged).
  var SA_BASE_BOTTOM = 24;
  var SA_PANEL_GAP = 66; // matches the default 90px panel bottom (24 + 66)
  // Sits just above the consent host (z-index 2147483600) yet below the
  // signed-32-bit ceiling, so the launcher button reliably out-stacks the
  // backdrop while the host is up, and falls back to the stylesheet's
  // default (99999) once the banner is gone.
  var SA_Z_OVER_COOKIE = '2147483646';
  var saSide = (pos === 'sa-pos-left') ? 'left' : 'right';
  var ccCardRO = null;

  function saComputeBottom(){
    var host = document.querySelector('.cc-host');
    if (!host) return SA_BASE_BOTTOM;
    var layout = host.getAttribute('data-layout') || '';
    if (layout === 'modal' || layout === 'takeover') return SA_BASE_BOTTOM;
    var card = host.querySelector('.cc-card');
    if (!card) return SA_BASE_BOTTOM;
    var r = card.getBoundingClientRect();
    if (!r.width || !r.height) return SA_BASE_BOTTOM;
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    // Generous launcher footprint (button grows to ~68px on hover) + breathing room.
    var pad = 88;
    var ll, lr;
    if (saSide === 'right') { lr = vw - SA_BASE_BOTTOM; ll = lr - pad; }
    else { ll = SA_BASE_BOTTOM; lr = ll + pad; }
    var lt = vh - SA_BASE_BOTTOM - pad; // launcher top edge
    var lb = vh - SA_BASE_BOTTOM;       // launcher bottom edge
    var overlapX = r.right > ll && r.left < lr;
    var overlapY = r.bottom > lt && r.top < lb;
    if (!(overlapX && overlapY)) return SA_BASE_BOTTOM;
    var lifted = Math.round(vh - r.top) + 14;
    // Never push the launcher off the top of the viewport.
    return Math.max(SA_BASE_BOTTOM, Math.min(lifted, vh - 80));
  }

  function saApplyOffset(){
    // Stacking lift: while the consent host is on the page, out-stack it
    // (and its full-screen backdrop) so the launcher button — and an open
    // panel — stay clickable across every layout, including modal/takeover.
    // When the banner is dismissed we clear the inline value so the launcher
    // drops back to the stylesheet default and never leaves a stray overlay
    // hovering above the rest of the UI.
    var hostUp = !!document.querySelector('.cc-host');
    var z = hostUp ? SA_Z_OVER_COOKIE : '';
    if (launcherWrap.style.zIndex !== z) launcherWrap.style.zIndex = z;
    if (panelWrap.style.zIndex !== z) panelWrap.style.zIndex = z;

    var bottom = saComputeBottom();
    var bpx = bottom + 'px';
    if (launcherWrap.style.bottom !== bpx) launcherWrap.style.bottom = bpx;
    // Keep the open panel sitting just above the (possibly lifted) launcher.
    // On mobile the stylesheet pins the wrapper with !important, so this inline
    // value is ignored there — which is the behaviour we want.
    var ppx = (bottom + SA_PANEL_GAP) + 'px';
    if (panelWrap.style.bottom !== ppx) panelWrap.style.bottom = ppx;
  }

  function saWatchCookieCard(){
    if (ccCardRO) { try { ccCardRO.disconnect(); } catch(e){} ccCardRO = null; }
    if (typeof ResizeObserver === 'undefined') return;
    var card = document.querySelector('.cc-host .cc-card');
    if (!card) return;
    try {
      ccCardRO = new ResizeObserver(function(){ saApplyOffset(); });
      ccCardRO.observe(card);
    } catch(e){}
  }

  saApplyOffset();
  saWatchCookieCard();
  try {
    new MutationObserver(function(){ saApplyOffset(); saWatchCookieCard(); })
      .observe(document.body, { childList: true });
  } catch(e){}
  window.addEventListener('resize', saApplyOffset, { passive: true });

  scheduleTooltip(true);
})();
</script>
@endif
