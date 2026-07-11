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
    // Voice agent eligibility — only meaningful on the authenticated app
    // surface. When available the full voice agent (record → turn → spoken
    // reply → confirmations → hands-free → surface bridge + "What I can do")
    // is hosted inside this panel's composer (mic button) so there is one
    // launcher. Plan-gated users get a mic that routes to the upgrade gate.
    $__sa_voice_available = false;
    $__sa_voice_gated = false;
    if ($__sa_surface === 'app' && auth()->check()) {
        try {
            $__sa_voice_available = \App\Services\AI\AiEngineSettings::voiceAllowedFor(auth()->user());
            $__sa_voice_gated = !$__sa_voice_available
                && \App\Services\AI\AiEngineSettings::isEnabled()
                && \App\Services\AI\AiEngineSettings::voiceEnabled();
        } catch (\Throwable $e) {}
    }
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
     data-quick-contact-url="{{ url('/assistant/quick-contact') }}"
     data-low-balance-click-url="{{ url('/assistant/low-balance-click') }}"
     data-send-code-url="{{ url('/assistant/auth/send-code') }}"
     data-verify-code-url="{{ url('/assistant/auth/verify-code') }}"
     data-voice-available="{{ $__sa_voice_available ? '1' : '0' }}"
     data-voice-gated="{{ $__sa_voice_gated ? '1' : '0' }}"
     data-voice-turn-url="{{ $__sa_voice_available ? route('user.ai.voice.turn') : '' }}"
     data-voice-cap-url="{{ $__sa_voice_available ? route('user.ai.voice.capabilities') : '' }}"
     data-voice-gate-url="{{ ($__sa_voice_available || $__sa_voice_gated) ? route('user.ai.voice.show') : '' }}">
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
@media (max-width:480px){#sa-panel-wrap{width:calc(100vw - 16px);right:8px!important;left:8px!important;bottom:80px}#sa-panel{height:calc(100vh - 100px);height:calc(100dvh - 100px)}#sa-peek{width:84px}}
@media (prefers-reduced-motion:reduce){#sa-peek{animation:none;opacity:1}}
.sa-header{padding:14px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.06)}
.sa-header img,.sa-header .sa-avatar{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff}
.sa-header img{object-fit:contain;padding:1px}
.sa-header h4{margin:0;font-size:14px;font-weight:600;color:#fff}
.sa-header .sa-sub{font-size:11px;opacity:.65}
.sa-close{margin-left:auto;display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:transparent;border:0;color:#94a3b8;font-size:20px;line-height:1;border-radius:8px;cursor:pointer;transition:background .15s ease,color .15s ease}
.sa-close:hover{background:rgba(255,255,255,.08);color:#e2e8f0}
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
.sa-qc-tabs{display:flex;gap:6px;flex-wrap:wrap}
.sa-qc-tab{flex:1;min-width:80px;background:rgba(255,255,255,.06)!important;border:1px solid rgba(255,255,255,.12)!important;color:#cbd5e1!important;padding:7px 8px!important;border-radius:8px!important;font-size:12px!important;font-weight:500!important;cursor:pointer}
.sa-qc-tab.sa-qc-on{background:var(--sa-accent,#3d6bff)!important;border-color:transparent!important;color:#fff!important}
.sa-actions{display:flex;padding:12px 14px 2px}
.sa-contact-btn{display:inline-flex;align-items:center;gap:7px;background:rgba(61,107,255,.12);border:1px solid rgba(61,107,255,.34);color:#c7d4ff;font-size:12.5px;font-weight:600;font-family:inherit;line-height:1;padding:9px 15px;border-radius:999px;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease,transform .15s ease}
.sa-contact-btn:hover{background:var(--sa-accent,#3d6bff);border-color:transparent;color:#fff;transform:translateY(-1px)}
.sa-contact-btn:active{transform:translateY(0)}
.sa-contact-btn svg{width:14px;height:14px}
.sa-contact{flex:1;overflow-y:auto;padding:14px;display:none;flex-direction:column;gap:10px}
.sa-contact.sa-show{display:flex}
.sa-contact-back{align-self:flex-start;display:inline-flex;align-items:center;gap:5px;background:transparent;border:0;color:#94a3b8;font-size:12px;cursor:pointer;font-family:inherit;padding:0}
.sa-contact-back:hover{color:#e2e8f0}
.sa-contact-intro{font-size:12.5px;color:#cbd5e1;line-height:1.45}
.sa-contact-err{font-size:12px;color:#fca5a5}
.sa-contact-done{padding:16px 8px;text-align:center;font-size:13px;color:#e2e8f0;line-height:1.5}
.sa-gate{flex-direction:column;gap:8px;align-items:stretch;text-align:center}
.sa-gate-note{font-size:12.5px;color:#cbd5e1;line-height:1.4}
.sa-gate-cta{display:block;width:100%;border:0;cursor:pointer;background:var(--sa-accent,#3d6bff);color:#fff;text-decoration:none;padding:9px 14px;border-radius:10px;font-size:13px;font-weight:600}
.sa-gate-cta:hover{filter:brightness(1.08)}
.sa-gate-cta:disabled{opacity:.6;cursor:default}
.sa-gate-input{width:100%;box-sizing:border-box;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);color:#e6ecff;border-radius:10px;padding:9px 12px;font-size:13px;outline:none}
.sa-gate-input::placeholder{color:#94a3b8}
.sa-gate-input:focus{border-color:var(--sa-accent,#3d6bff)}
.sa-gate-tabs{display:flex;gap:6px}
.sa-gate-tab{flex:1;cursor:pointer;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.14);color:#cbd5e1;border-radius:9px;padding:6px 8px;font-size:12px;font-weight:600}
.sa-gate-tab.sa-gate-on{background:var(--sa-accent,#3d6bff);border-color:var(--sa-accent,#3d6bff);color:#fff}
.sa-gate-hint{font-size:11.5px;color:#9fb4ff;line-height:1.4}
.sa-gate-err{font-size:11.5px;color:#fca5a5;line-height:1.4}
.sa-gate-alt{font-size:11.5px;color:#94a3b8;text-decoration:underline}
.sa-gate-alt:hover{color:#cbd5e1}
.sa-gate-trap{position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none}
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
html.light-mode .sa-qc-tab{background:rgba(15,23,42,.05)!important;border:1px solid rgba(15,23,42,.12)!important;color:#475569!important}
html.light-mode .sa-qc-tab.sa-qc-on{background:var(--sa-accent,#3d6bff)!important;color:#fff!important;border-color:transparent!important}
html.light-mode .sa-contact-btn{background:rgba(61,107,255,.09);border-color:rgba(61,107,255,.28);color:#2742a8}
html.light-mode .sa-contact-btn:hover{background:var(--sa-accent,#3d6bff);border-color:transparent;color:#fff}
html.light-mode .sa-contact-back{color:#64748b}
html.light-mode .sa-contact-back:hover{color:#1e293b}
html.light-mode .sa-contact-intro{color:#475569}
html.light-mode .sa-contact-done{color:#1e293b}
html.light-mode .sa-gate-note{color:#475569}
html.light-mode .sa-gate-input{background:#fff;border-color:#cbd5e1;color:#0f172a}
html.light-mode .sa-gate-input::placeholder{color:#94a3b8}
html.light-mode .sa-gate-tab{background:#f1f5f9;border-color:#cbd5e1;color:#475569}
html.light-mode .sa-gate-tab.sa-gate-on{color:#fff}
html.light-mode .sa-gate-hint{color:#4f6bd8}
html.light-mode .sa-gate-alt{color:#64748b}
html.light-mode .sa-low-balance{background:rgba(251,191,36,.14);border:1px solid rgba(251,191,36,.4);color:#92400e}
html.light-mode .sa-low-balance .sa-lb-cta{background:rgba(251,191,36,.24);border:1px solid rgba(251,191,36,.5);color:#92400e}
html.light-mode .sa-low-balance .sa-lb-cta:hover{background:rgba(251,191,36,.4);color:#0f172a}
html.light-mode .sa-input-row{border-top:1px solid rgba(15,23,42,.08)}
html.light-mode .sa-input-row textarea{background:rgba(15,23,42,.04);border:1px solid rgba(15,23,42,.12);color:#1e293b}
html.light-mode .sa-typing{color:#64748b}
html.light-mode .sa-badge{border:2px solid #ffffff}
/* ── Voice agent (mic in composer + activity strip + capabilities) ───── */
.sa-mic{flex:0 0 auto;width:40px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);color:#cbd5e1;border-radius:10px;cursor:pointer;position:relative;padding:0;transition:background .15s,color .15s,border-color .15s}
.sa-mic:hover{background:rgba(255,255,255,.12);color:#fff}
.sa-mic:disabled{opacity:.5;cursor:default}
.sa-mic svg{width:18px;height:18px}
.sa-mic.sa-mic-rec{background:#ef4444;border-color:transparent;color:#fff;animation:sa-mic-pulse 1.3s ease-in-out infinite}
@keyframes sa-mic-pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}50%{box-shadow:0 0 0 7px rgba(239,68,68,0)}}
.sa-mic-lock{position:absolute;top:-5px;right:-5px;width:15px;height:15px;border-radius:50%;background:#fbbf24;color:#0f172a;font-size:9px;line-height:1;display:flex;align-items:center;justify-content:center;border:2px solid #0d1118;font-weight:700}
.sa-voice{padding:6px 12px 0;display:flex;flex-direction:column;gap:6px}
.sa-voice-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sa-voice-tool{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#cbd5e1;font-size:11px;font-family:inherit;padding:4px 9px;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;line-height:1}
.sa-voice-tool:hover{background:rgba(255,255,255,.12);color:#fff}
.sa-voice-tool.sa-on{background:rgba(16,185,129,.2);border-color:rgba(16,185,129,.4);color:#6ee7b7}
.sa-voice-status{font-size:11px;color:#94a3b8;margin-left:auto;text-align:right}
.sa-voice-confirm{display:none;flex-direction:column;gap:6px;border:1px solid rgba(251,191,36,.3);background:rgba(251,191,36,.08);border-radius:10px;padding:8px}
.sa-voice-confirm.sa-show{display:flex}
.sa-voice-confirm .sa-vc-title{font-size:11px;color:#fbbf24;font-weight:600}
.sa-voice-confirm .sa-vc-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.sa-voice-confirm .sa-vc-label{font-size:11px;color:#fde68a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.sa-voice-confirm .sa-vc-acts{display:flex;gap:6px;flex:0 0 auto}
.sa-vc-yes{background:rgba(16,185,129,.85);border:0;color:#fff;font-size:11px;font-family:inherit;padding:3px 10px;border-radius:6px;cursor:pointer}
.sa-vc-no{background:rgba(255,255,255,.12);border:0;color:#e2e8f0;font-size:11px;font-family:inherit;padding:3px 10px;border-radius:6px;cursor:pointer}
.sa-voice-credits{display:none;font-size:10.5px;color:#94a3b8}
.sa-voice-credits.sa-show{display:block}
.sa-vcaps{position:absolute;inset:0;background:inherit;display:none;flex-direction:column;overflow:hidden}
.sa-vcaps.sa-show{display:flex}
.sa-vcaps-body{flex:1;overflow-y:auto;padding:6px 14px 14px;display:flex;flex-direction:column;gap:12px}
.sa-vcaps-h{font-size:11px;text-transform:capitalize;letter-spacing:.02em;color:#90acff;font-weight:700;margin:0 0 4px}
.sa-vcaps-h.sa-vcaps-cant{color:#fca5a5}
.sa-vcaps-list{display:flex;flex-direction:column;gap:6px}
.sa-vcaps-item{display:flex;flex-direction:column;gap:1px}
.sa-vcaps-name{font-size:12px;color:#e2e8f0;font-weight:600}
.sa-vcaps-desc{font-size:11px;color:#94a3b8;line-height:1.35}
.sa-vcaps-loading{font-size:12px;color:#94a3b8;padding:8px 0}
@media (prefers-reduced-motion:reduce){.sa-mic.sa-mic-rec{animation:none}}
html.light-mode .sa-mic{background:rgba(15,23,42,.04);border:1px solid rgba(15,23,42,.12);color:#475569}
html.light-mode .sa-mic:hover{background:rgba(15,23,42,.08);color:#0f172a}
html.light-mode .sa-mic-lock{border-color:#fff}
html.light-mode .sa-voice-tool{background:rgba(15,23,42,.05);border-color:rgba(15,23,42,.12);color:#475569}
html.light-mode .sa-voice-tool:hover{background:rgba(15,23,42,.1);color:#0f172a}
html.light-mode .sa-voice-tool.sa-on{background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.4);color:#047857}
html.light-mode .sa-voice-status{color:#64748b}
html.light-mode .sa-voice-confirm{border-color:rgba(180,83,9,.3);background:rgba(251,191,36,.12)}
html.light-mode .sa-voice-confirm .sa-vc-title{color:#b45309}
html.light-mode .sa-voice-confirm .sa-vc-label{color:#92400e}
html.light-mode .sa-vc-no{background:rgba(15,23,42,.08);color:#334155}
html.light-mode .sa-voice-credits{color:#64748b}
html.light-mode .sa-vcaps-h{color:#3551c8}
html.light-mode .sa-vcaps-h.sa-vcaps-cant{color:#dc2626}
html.light-mode .sa-vcaps-name{color:#1e293b}
html.light-mode .sa-vcaps-desc,html.light-mode .sa-vcaps-loading{color:#64748b}
</style>
{{-- Shared voice-runtime core (turn payload + surface bridge + capabilities),
     also used by the floating widget partial — defined idempotently. --}}
@include('common.partials.voice-runtime')
<script>
// Server-resolved localized chrome strings, exposed up front so the
// initial DOM build (subheading) and any pre-bootstrap JS branches
// (none today, but defensive for future async edits) use the
// visitor's language instead of the English defaults. Bootstrap
// later overwrites window.__SA_CHROME with the same values once the
// fetch completes — keeping a single source of truth at runtime.
window.__SA_SUBHEADING = @json(\App\Services\AI\SiteAssistantSettings::subheadingFor($__sa_cfg));
// Assistant display name ("Ask Zio" by default) shown in the chat header.
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
  error_generic:     @json(\App\Services\AI\SiteAssistantSettings::errorGenericFor($__sa_cfg)),
  auth_required:     @json(\App\Services\AI\SiteAssistantSettings::authRequiredNoteFor($__sa_cfg))
};
// Login URL the gate CTA points to when chat requires authentication.
window.__SA_LOGIN_URL = @json(url('/login'));
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
    typing_indicator: 'Ask Zio is typing…',
    handoff_note: 'Our team will reply by email.',
    cutoff_notice: '⚠ This reply was cut off —',
    cutoff_retry_label: 'Retry',
    error_network: 'Network error.',
    error_generic: 'Sorry, something went wrong.',
    auth_required: 'Please log in to chat with us.'
  }, window.__SA_CHROME || {});
  // Login gate state — set from the bootstrap response. When the chat
  // requires authentication (anonymous visitor + admin gate on), the
  // composer is swapped for a login CTA and message sending is blocked
  // client-side (the server enforces it too).
  var AUTH_REQUIRED=false;
  var LOGIN_URL=(typeof window.__SA_LOGIN_URL==='string' && window.__SA_LOGIN_URL) ? window.__SA_LOGIN_URL : '/login';
  // Voice agent config (computed server-side, app surface only). When
  // available the mic in the composer drives the full voice agent inside
  // this panel; when plan-gated the mic routes to the upgrade gate.
  var VOICE_AVAILABLE = ds.voiceAvailable === '1';
  var VOICE_GATED     = ds.voiceGated === '1';
  var VOICE_TURN_URL  = ds.voiceTurnUrl || '';
  var VOICE_CAP_URL   = ds.voiceCapUrl || '';
  var VOICE_GATE_URL  = ds.voiceGateUrl || '';

  // Voice runtime state + element refs. These MUST be declared before the DOM
  // build below: buildMicButton()/buildVoiceStrip()/buildCapsPane() run during
  // construction and assign the element refs (and read MIC_SVG). Declaring them
  // later would re-initialise the refs back to null AFTER the build, silently
  // breaking credits, confirmation chips, the capabilities pane and the deferred
  // audio playback (and rendering MIC_SVG as the literal string "undefined").
  var vrRecording=false, vrRec=null, vrChunks=[], vrLastAudio=null;
  var vrMessages=[], vrPending=[], vrHandsFree=false, vrPendingNav=null;
  var voiceStatusEl=null, voiceConfirmEl=null, voiceCreditsEl=null;
  var voicePlayer=null, capsPaneEl=null, capsBodyEl=null, capsLoaded=false;
  // Mic icon SVG (matches the standalone widget glyph).
  var MIC_SVG='<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/></svg>';
  // Which passwordless methods the in-chat login form may offer. Updated
  // from the bootstrap response; when both are false the gate falls back
  // to the full-page login CTA.
  var EMAIL_OTP_ENABLED=true, MOBILE_LOGIN_ENABLED=false;

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
    }).then(function(r){
      // The web session expired/was revoked mid-chat: every gated
      // /assistant/* call now 401s. Re-show the in-chat login gate in
      // place instead of surfacing a raw error, and tag the parsed body
      // so callers skip their error rendering. The anonymous visitor_token
      // / conversation is left untouched.
      if (r.status === 401){
        handleUnauthorized();
        return r.json().then(function(j){ j=j||{}; j.__unauthorized=true; return j; },
                             function(){ return {ok:false, __unauthorized:true}; });
      }
      return r.json();
    });
  }

  // Session-expiry recovery: flip on the login gate so the visitor can
  // sign back in without losing the visible conversation. Safe to call
  // repeatedly (showLoginGate rebuilds the composer each time).
  function handleUnauthorized(){
    AUTH_REQUIRED = true;
    removeTyping();
    showLoginGate();
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
  // "Contact us" entry point: opens the multi-channel quick-contact form
  // (Call back / WhatsApp / Email) right inside the panel. This is the
  // former standalone quick-contact widget, folded into the assistant so
  // there's a single floating launcher. Unlike the chat, it is NOT login-
  // gated — it posts to /assistant/quick-contact, which is anonymous-
  // friendly and lands in the admin Contact Inbox.
  var PHONE_SVG='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
  var contactBtn=el('button',{class:'sa-contact-btn',type:'button','aria-label':@json(__('Contact us')),html:PHONE_SVG+'<span>'+escapeHtml(@json(__('Contact us')))+'</span>'});
  contactBtn.onclick=function(){ openContact(); };
  var closeBtn=el('button',{class:'sa-close',type:'button','aria-label':'Close'},'×');
  closeBtn.onclick=function(){ togglePanel(false); };
  header.appendChild(closeBtn);
  panel.appendChild(header);

  // "Contact us" entry point lives on its own action row directly below
  // the header so it reads as a distinct, one-tap action with breathing
  // room — no longer squeezed between the brand title and the close (×).
  var actions=el('div',{class:'sa-actions'});
  actions.appendChild(contactBtn);
  panel.appendChild(actions);

  var suggested=el('div',{class:'sa-suggested',id:'sa-suggested'});
  panel.appendChild(suggested);
  var body=el('div',{class:'sa-body',id:'sa-body'});
  panel.appendChild(body);
  var contactPane=el('div',{class:'sa-contact',id:'sa-contact'});
  panel.appendChild(contactPane);
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
  // Voice mic — only when the agent is hosted in this panel (app surface,
  // engine on). Available users get the full voice agent; plan-gated users
  // get a mic with a lock badge that routes to the upgrade gate.
  // (MIC_SVG + voice state vars are declared above, before the DOM build, so
  // the builders that run during construction don't get undefined refs.)
  var micBtn=null;
  if(VOICE_AVAILABLE || VOICE_GATED){
    micBtn=buildMicButton();
    inputRow.appendChild(ta); inputRow.appendChild(micBtn); inputRow.appendChild(sendBtn);
  } else {
    inputRow.appendChild(ta); inputRow.appendChild(sendBtn);
  }
  // Voice activity strip (hands-free toggle, "What I can do", status,
  // confirmation chips, credits) lives directly above the composer.
  if(VOICE_AVAILABLE){ panel.appendChild(buildVoiceStrip()); }
  panel.appendChild(inputRow);
  // Capabilities ("What I can do") overlay pane, hidden until requested.
  if(VOICE_AVAILABLE){ panel.appendChild(buildCapsPane()); }
  panelWrap.appendChild(panel);
  document.body.appendChild(panelWrap);

  launcher.onclick=function(){ togglePanel(!open); };

  function togglePanel(on){
    open = !!on;
    panelWrap.classList.toggle('sa-open', open);
    saSyncPanelViewport();
    if(open){
      unread=0; badge.style.display='none';
      hideTooltip();
      if(!bootstrapped){
        // Show an immediate typing-indicator placeholder so the panel
        // body is never visually empty while the two sequential async
        // calls (bootstrap → session) complete.  The session handler
        // clears it via body.innerHTML='' before rendering the greeting.
        if(!body.children.length){
          var initLoader=el('div',{class:'sa-typing',id:'sa-init-loader'},CHROME.typing_indicator||'Loading…');
          body.appendChild(initLoader);
        }
        bootstrap();
      }
      // Focus the textarea only when it is still the active composer
      // (i.e. the login gate hasn't replaced it yet).  AUTH_REQUIRED is
      // false here on first open (set server-side in __SA_CHROME) so the
      // focus fires correctly; after the gate renders its own idInput
      // focus takes over.
      setTimeout(function(){
        if(!AUTH_REQUIRED && document.contains(ta)){ ta.focus(); }
      }, 80);
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
        // Login gate: the server tells us whether this surface requires
        // authentication for the anonymous visitor. Capture it (and the
        // localized note + login URL) so the composer is swapped for a
        // login CTA once the greeting renders.
        AUTH_REQUIRED = !!data.auth_required;
        if(typeof data.auth_required_note==='string' && data.auth_required_note){ CHROME.auth_required = data.auth_required_note; }
        if(typeof data.login_url==='string' && data.login_url){ LOGIN_URL = data.login_url; }
        if(typeof data.email_otp_enabled==='boolean'){ EMAIL_OTP_ENABLED = data.email_otp_enabled; }
        if(typeof data.mobile_login_enabled==='boolean'){ MOBILE_LOGIN_ENABLED = data.mobile_login_enabled; }
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
        // Always clear the initial loading placeholder regardless of
        // whether the session succeeds — a failed session should show
        // an error state, not a stuck "typing…" loader.
        var initL=document.getElementById('sa-init-loader');
        if(initL) initL.remove();
        if(!s||!s.ok){
          if(s && s.visitor_token){ token=s.visitor_token; localStorage.setItem(TOKEN_KEY, token); }
          // Session failed but bootstrap told us auth is required: show
          // the login gate so the panel is still usable.
          if(AUTH_REQUIRED){ renderSuggested([]); showLoginGate(); }
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
        // When login is required we suppress starter prompts (they'd
        // only hit a 401) and swap the composer for a login CTA.
        if(AUTH_REQUIRED){
          renderSuggested([]);
          showLoginGate();
        } else {
          renderSuggested(combined);
        }
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

  // In-chat passwordless login/signup. Replaces the composer with a 2-step
  // form (identifier → 6-digit code). Login == signup: a brand-new account is
  // created server-side on first successful verification. Honeypot + time-trap
  // mirror the quick-contact form. When no OTP method is enabled we fall back
  // to the full-page login CTA.
  function showLoginGate(){
    var row=document.querySelector('#sa-panel .sa-input-row');
    if(!row) return;
    row.innerHTML='';
    row.classList.add('sa-gate');

    // No passwordless method available → full-page login fallback only.
    if(!EMAIL_OTP_ENABLED && !MOBILE_LOGIN_ENABLED){
      row.appendChild(el('div',{class:'sa-gate-note'}, CHROME.auth_required || @json(__('Please log in to chat with us.'))));
      row.appendChild(el('a',{class:'sa-gate-cta',href:LOGIN_URL,target:'_self',rel:'noopener'}, @json(__('Log in'))));
      return;
    }

    var openedAt=Date.now();
    var step='identifier', busyG=false;
    var type = EMAIL_OTP_ENABLED ? 'email' : 'mobile';

    var note=el('div',{class:'sa-gate-note'}, CHROME.auth_required || @json(__('Sign in or create your account to start chatting — no password needed.')));
    var tabs=el('div',{class:'sa-gate-tabs'});
    // Honeypot: a decoy field kept off-screen; bots fill it, humans don't.
    var trap=el('input',{type:'text',name:'website',tabindex:'-1',autocomplete:'off','aria-hidden':'true',class:'sa-gate-trap'});
    var idInput=el('input',{class:'sa-gate-input',type:'email',autocomplete:'email',placeholder:@json(__('you@example.com'))});
    var codeInput=el('input',{class:'sa-gate-input',type:'text',inputmode:'numeric',autocomplete:'one-time-code',maxlength:'6',placeholder:@json(__('6-digit code'))});
    codeInput.style.display='none';
    var hint=el('div',{class:'sa-gate-hint'}); hint.style.display='none';
    var errBox=el('div',{class:'sa-gate-err'}); errBox.style.display='none';
    var primary=el('button',{class:'sa-gate-cta',type:'button'}, @json(__('Send code')));
    var fallback=el('a',{class:'sa-gate-alt',href:LOGIN_URL,target:'_self',rel:'noopener'}, @json(__('Log in on full page')));

    function showErr(m){ errBox.textContent=m; errBox.style.display=''; }
    function clearErr(){ errBox.style.display='none'; }
    function setType(t){
      type=t;
      idInput.setAttribute('type', t==='email'?'email':'tel');
      idInput.setAttribute('placeholder', t==='email'? @json(__('you@example.com')) : @json(__('Phone (with country code)')));
      Array.prototype.forEach.call(tabs.children,function(b){ b.classList.toggle('sa-gate-on', b.getAttribute('data-t')===t); });
    }

    if(EMAIL_OTP_ENABLED && MOBILE_LOGIN_ENABLED){
      [['email',@json(__('Email'))],['mobile',@json(__('Phone'))]].forEach(function(p){
        var b=el('button',{type:'button',class:'sa-gate-tab','data-t':p[0]}, p[1]);
        b.onclick=function(){ setType(p[0]); idInput.focus(); };
        tabs.appendChild(b);
      });
    }

    function doSend(){
      if(busyG) return;
      var idv=(idInput.value||'').trim();
      if(!idv){ idInput.style.borderColor='#ef4444'; return; }
      idInput.style.borderColor=''; busyG=true; primary.disabled=true;
      primary.textContent=@json(__('Sending…')); clearErr();
      jpost(ds.sendCodeUrl, { identifier:idv, type:type, website:(trap.value||''), elapsed_ms:(Date.now()-openedAt) })
        .then(function(d){
          if(d && d.ok){
            step='code';
            tabs.style.display='none'; idInput.style.display='none';
            codeInput.style.display=''; codeInput.value='';
            hint.textContent = d.demo_reveal ? d.demo_reveal : (d.message || @json(__('We sent you a code. Enter it below.')));
            hint.style.display='';
            setTimeout(function(){ codeInput.focus(); },50);
          } else {
            showErr((d&&d.error) || @json(__('Something went wrong. Please try again.')));
          }
        })
        .catch(function(){ showErr(@json(__('Network error. Please try again.'))); })
        .finally(function(){ busyG=false; primary.disabled=false; primary.textContent = step==='code'? @json(__('Verify & continue')) : @json(__('Send code')); });
    }

    function doVerify(){
      if(busyG) return;
      var code=(codeInput.value||'').trim();
      if(code.length<6){ codeInput.style.borderColor='#ef4444'; return; }
      codeInput.style.borderColor=''; busyG=true; primary.disabled=true;
      primary.textContent=@json(__('Verifying…')); clearErr();
      jpost(ds.verifyCodeUrl, { identifier:(idInput.value||'').trim(), type:type, code:code, website:(trap.value||''), elapsed_ms:(Date.now()-openedAt) })
        .then(function(d){
          if(d && d.ok){ onLoginSuccess(); return; }
          if(d && d.twofactor){
            if(d.login_url){ LOGIN_URL=d.login_url; fallback.href=LOGIN_URL; }
            showErr(d.error || @json(__('Finish signing in on the login page to complete two-factor.')));
            return;
          }
          showErr((d&&d.error) || @json(__('Invalid or expired code.')));
        })
        .catch(function(){ showErr(@json(__('Network error. Please try again.'))); })
        .finally(function(){ busyG=false; primary.disabled=false; primary.textContent=@json(__('Verify & continue')); });
    }

    primary.onclick=function(){ step==='code' ? doVerify() : doSend(); };
    idInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); doSend(); } });
    codeInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); doVerify(); } });

    row.appendChild(note);
    if(EMAIL_OTP_ENABLED && MOBILE_LOGIN_ENABLED) row.appendChild(tabs);
    row.appendChild(idInput);
    row.appendChild(codeInput);
    row.appendChild(trap);
    row.appendChild(hint);
    row.appendChild(errBox);
    row.appendChild(primary);
    row.appendChild(fallback);
    if(EMAIL_OTP_ENABLED && MOBILE_LOGIN_ENABLED) setType(type);
    setTimeout(function(){ idInput.focus(); },50);
  }

  // Put the normal composer back after a successful in-chat login.
  function restoreComposer(){
    var row=document.querySelector('#sa-panel .sa-input-row');
    if(!row) return;
    row.innerHTML='';
    row.classList.remove('sa-gate');
    if(micBtn){ row.appendChild(ta); row.appendChild(micBtn); row.appendChild(sendBtn); }
    else { row.appendChild(ta); row.appendChild(sendBtn); }
    ta.disabled=false; sendBtn.disabled=false;
  }

  // Same-origin (blade) success: a web session now exists. Restore the
  // composer and re-bootstrap so the greeting renders as the signed-in user.
  function onLoginSuccess(){
    AUTH_REQUIRED=false;
    restoreComposer();
    bootstrapped=false;
    bootstrap();
  }

  // ─────────────────────────────────────────────────────────────────────
  // Voice agent — the full record → turn → spoken reply → confirmation →
  // hands-free → surface-bridge runtime, hosted inside this panel so the
  // dashboard has ONE launcher. Mirrors the standalone widget's logic but
  // in plain JS and rendered into the Zio chat body. Only wired up when
  // VOICE_AVAILABLE; a gated mic just routes to the upgrade page.
  // (Voice state vars + MIC_SVG are declared above, before the DOM build, so the
  // builders that run during construction don't get their refs clobbered.)
  function buildMicButton(){
    var b=el('button',{type:'button',class:'sa-mic','aria-label':'Talk to Zio',title: VOICE_GATED ? 'Upgrade to use voice' : 'Tap to talk', html: MIC_SVG});
    if(VOICE_GATED){
      b.appendChild(el('span',{class:'sa-mic-lock','aria-hidden':'true'},'★'));
      b.onclick=function(){ if(VOICE_GATE_URL) window.location.assign(VOICE_GATE_URL); };
    } else {
      b.onclick=onMicClick;
    }
    return b;
  }

  function buildVoiceStrip(){
    var strip=el('div',{class:'sa-voice',id:'sa-voice'});
    var bar=el('div',{class:'sa-voice-bar'});
    var hf=el('button',{type:'button',class:'sa-voice-tool',id:'sa-voice-hf'},'Hands-free: off');
    hf.onclick=function(){
      vrHandsFree=!vrHandsFree;
      hf.classList.toggle('sa-on', vrHandsFree);
      hf.textContent='Hands-free: '+(vrHandsFree?'on':'off');
      if(vrHandsFree && !vrRecording && !vrPending.length){ startRecording(); }
    };
    var caps=el('button',{type:'button',class:'sa-voice-tool',id:'sa-voice-caps'},'What I can do');
    caps.onclick=openCaps;
    voiceStatusEl=el('span',{class:'sa-voice-status',id:'sa-voice-status'},'');
    bar.appendChild(hf); bar.appendChild(caps); bar.appendChild(voiceStatusEl);
    voiceConfirmEl=el('div',{class:'sa-voice-confirm',id:'sa-voice-confirm'});
    voiceCreditsEl=el('div',{class:'sa-voice-credits',id:'sa-voice-credits'},'');
    strip.appendChild(bar); strip.appendChild(voiceConfirmEl); strip.appendChild(voiceCreditsEl);
    // Hidden audio element for the spoken reply.
    voicePlayer=el('audio',{style:{display:'none'}});
    voicePlayer.addEventListener('ended', afterReply);
    strip.appendChild(voicePlayer);
    return strip;
  }

  function buildCapsPane(){
    capsPaneEl=el('div',{class:'sa-vcaps',id:'sa-vcaps'});
    var head=el('div',{class:'sa-header'});
    var ttl=el('div',{html:'<h4>What I can do</h4>'});
    var back=el('button',{type:'button',class:'sa-close','aria-label':'Back'},'×');
    back.onclick=closeCaps;
    head.appendChild(ttl); head.appendChild(back);
    capsBodyEl=el('div',{class:'sa-vcaps-body'});
    capsPaneEl.appendChild(head); capsPaneEl.appendChild(capsBodyEl);
    return capsPaneEl;
  }

  function setVoiceStatus(s){ if(voiceStatusEl) voiceStatusEl.textContent=s||''; }
  function updateMicUI(){
    if(!micBtn) return;
    micBtn.classList.toggle('sa-mic-rec', vrRecording);
    micBtn.title = vrRecording ? 'Stop and send' : 'Tap to talk';
  }

  function onMicClick(){
    if(vrRecording){ return stopRecording(); }
    if(!open){ togglePanel(true); }
    startRecording();
  }

  function startRecording(){
    if(!navigator.mediaDevices || typeof MediaRecorder==='undefined'){
      setVoiceStatus('Voice recording is not supported here.'); return;
    }
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
      var mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
      vrRec = new MediaRecorder(stream, mime ? {mimeType:mime} : undefined);
      vrChunks=[];
      vrRec.ondataavailable=function(e){ if(e.data && e.data.size) vrChunks.push(e.data); };
      vrRec.onstop=function(){
        stream.getTracks().forEach(function(t){ t.stop(); });
        var blob=new Blob(vrChunks, {type: vrRec.mimeType || 'audio/webm'});
        vrLastAudio=blob;
        sendTurn(blob, null);
      };
      vrRec.start();
      vrRecording=true; updateMicUI(); setVoiceStatus('Listening…');
    }).catch(function(){ setVoiceStatus('Microphone permission denied.'); });
  }

  function stopRecording(){
    if(vrRec && vrRecording){
      vrRecording=false; updateMicUI(); setVoiceStatus('Thinking…');
      try{ vrRec.stop(); }catch(e){}
    }
  }

  function sendTurn(blob, confirmedTools){
    // Turn payload shape, POST, and response normalization live in the shared
    // VoiceRuntime so this panel mic can never drift from the floating widget.
    window.VoiceRuntime.sendTurn({
      url: VOICE_TURN_URL,
      csrf: csrf,
      blob: blob,
      messages: vrMessages,
      confirmedTools: confirmedTools || {}
    }).then(function(r){
      if(!r.ok){ setVoiceStatus(r.error || ('Request failed ('+r.status+').')); return; }
      if(r.transcript){ vrMessages.push({role:'user',content:r.transcript}); renderMessage({role:'user',content:r.transcript}); }
      if(r.reply){ vrMessages.push({role:'assistant',content:r.reply}); renderMessage({role:'assistant',content:r.reply}); }
      vrPending = r.pending;
      setVoiceStatus('');
      updateCredits(r.credits, (r.balance!=null?r.balance:null));
      renderConfirmations();
      // Surface bridge (client_action dispatch + navigate_to) is shared too;
      // the returned nav is deferred until the spoken reply finishes.
      vrPendingNav = window.VoiceRuntime.applyToolResults(r.toolResults);
      if(r.audioBase64 && voicePlayer){
        voicePlayer.src='data:audio/mpeg;base64,'+r.audioBase64;
        var p=voicePlayer.play();
        if(p && p.catch){ p.catch(function(){ afterReply(); }); }
      } else {
        afterReply();
      }
    }).catch(function(){ setVoiceStatus('Network error — please retry.'); });
  }

  function afterReply(){
    if(vrPendingNav){ var url=vrPendingNav; vrPendingNav=null; window.location.assign(url); return; }
    if(vrHandsFree && !vrRecording && !vrPending.length){ startRecording(); }
  }

  function renderConfirmations(){
    if(!voiceConfirmEl) return;
    voiceConfirmEl.innerHTML='';
    if(!vrPending.length){ voiceConfirmEl.classList.remove('sa-show'); return; }
    voiceConfirmEl.classList.add('sa-show');
    voiceConfirmEl.appendChild(el('div',{class:'sa-vc-title'},'Confirm before I run:'));
    vrPending.forEach(function(c){
      var row=el('div',{class:'sa-vc-row'});
      row.appendChild(el('span',{class:'sa-vc-label',title:c.tool}, c.tool));
      var acts=el('div',{class:'sa-vc-acts'});
      var yes=el('button',{type:'button',class:'sa-vc-yes'},'Yes');
      yes.onclick=function(){ confirmTool(c.tool, true); };
      var no=el('button',{type:'button',class:'sa-vc-no'},'Cancel');
      no.onclick=function(){ confirmTool(c.tool, false); };
      acts.appendChild(yes); acts.appendChild(no);
      row.appendChild(acts);
      voiceConfirmEl.appendChild(row);
    });
  }

  function confirmTool(name, accepted){
    if(!accepted){
      vrPending=vrPending.filter(function(c){ return c.tool!==name; });
      vrMessages.push({role:'assistant',content:'Cancelled '+name+'.'});
      renderMessage({role:'assistant',content:'Cancelled '+name+'.'});
      renderConfirmations();
      return;
    }
    if(!vrLastAudio) return;
    var map={}; map[name]=true;
    vrPending=vrPending.filter(function(c){ return c.tool!==name; });
    renderConfirmations();
    setVoiceStatus('Running…');
    sendTurn(vrLastAudio, map);
  }

  function updateCredits(credits, balance){
    if(!voiceCreditsEl) return;
    if(!credits){ voiceCreditsEl.classList.remove('sa-show'); voiceCreditsEl.textContent=''; return; }
    var txt='Last turn: STT '+(credits.stt||0)+' · LLM '+(credits.llm||0)+' · TTS '+(credits.tts||0)+' (= '+(credits.total||0)+' credits)';
    if(balance!=null){ txt+=' · Balance '+balance; }
    voiceCreditsEl.textContent=txt;
    voiceCreditsEl.classList.add('sa-show');
  }

  function openCaps(){
    if(!capsPaneEl) return;
    capsPaneEl.classList.add('sa-show');
    if(!capsLoaded){
      capsBodyEl.innerHTML='';
      capsBodyEl.appendChild(el('div',{class:'sa-vcaps-loading'},'Loading…'));
      window.VoiceRuntime.loadCaps(VOICE_CAP_URL).then(function(caps){ capsLoaded=true; renderCaps(caps); });
    }
  }
  function closeCaps(){ if(capsPaneEl) capsPaneEl.classList.remove('sa-show'); }

  function renderCaps(caps){
    if(!capsBodyEl) return;
    capsBodyEl.innerHTML='';
    caps=caps||{};
    var tools=caps.tools||{};
    Object.keys(tools).forEach(function(group){
      var sec=el('div');
      sec.appendChild(el('div',{class:'sa-vcaps-h'}, String(group).replace(/_/g,' ')));
      var list=el('div',{class:'sa-vcaps-list'});
      (tools[group]||[]).forEach(function(t){
        var item=el('div',{class:'sa-vcaps-item'});
        var nm=el('div',{class:'sa-vcaps-name'}, t.name || '');
        if(t.destructive){ nm.appendChild(el('span',{style:{marginLeft:'6px',color:'#fbbf24',fontSize:'10px',fontWeight:'400'}},'⚠ confirms')); }
        item.appendChild(nm);
        if(t.description){ item.appendChild(el('div',{class:'sa-vcaps-desc'}, t.description)); }
        list.appendChild(item);
      });
      sec.appendChild(list);
      capsBodyEl.appendChild(sec);
    });
    var cant=el('div');
    cant.appendChild(el('div',{class:'sa-vcaps-h sa-vcaps-cant'},"What I can't do"));
    var clist=el('div',{class:'sa-vcaps-list'});
    (caps.limitations||[]).forEach(function(lim){ clist.appendChild(el('div',{class:'sa-vcaps-desc'}, lim)); });
    cant.appendChild(clist);
    capsBodyEl.appendChild(cant);
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
      // Handoff forms get the multi-channel quick-contact UI: pick how
      // you want to be reached (Call back / WhatsApp / Email), then one
      // contextual contact field. Other forms keep the generic renderer.
      if(b.action==='handoff'){
        renderHandoffForm(b, wrap);
        return;
      }
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
        submitChoice({label:b.submit_label||'Submitted', values:values, template:b.template||null});
      };
      form.appendChild(submit);
      wrap.appendChild(form);
    }
  }

  // Channel definitions shared by the handoff form and the standalone
  // quick-contact flow. `field` is the contextual input shown after a
  // channel is chosen; callback/whatsapp collect a phone, email an
  // email. Labels run through the translator for localization.
  var QC_CHANNELS=[
    {value:'callback', label:@json(__('Call back')),    field:'phone', placeholder:@json(__('Your phone (+91, 10 digits)')), inputType:'tel'},
    {value:'whatsapp', label:@json(__('WhatsApp call')),field:'phone', placeholder:@json(__('WhatsApp number (with country code)')), inputType:'tel'},
    {value:'email',    label:@json(__('Email')),         field:'email', placeholder:@json(__('Your email')), inputType:'email'}
  ];

  function renderHandoffForm(b, wrap){
    var form=el('div',{class:'sa-form sa-qc'});
    var selected='callback';
    var tabs=el('div',{class:'sa-qc-tabs'});
    var msg=el('textarea',{placeholder:(b.fields&&b.fields.length&&b.fields[0].label) || @json(__('How can we help? (optional)')),rows:'2'});
    var contact=el('input',{type:'tel',placeholder:QC_CHANNELS[0].placeholder});
    function applyChannel(ch){
      selected=ch.value;
      contact.value='';
      contact.setAttribute('type', ch.inputType);
      contact.setAttribute('placeholder', ch.placeholder);
      contact.style.borderColor='';
      Array.prototype.forEach.call(tabs.children,function(btn){
        btn.classList.toggle('sa-qc-on', btn.getAttribute('data-ch')===ch.value);
      });
    }
    QC_CHANNELS.forEach(function(ch){
      var btn=el('button',{type:'button',class:'sa-qc-tab','data-ch':ch.value}, ch.label);
      btn.onclick=function(){ applyChannel(ch); };
      tabs.appendChild(btn);
    });
    form.appendChild(tabs);
    form.appendChild(contact);
    form.appendChild(msg);
    var submit=el('button',{type:'button'}, b.submit_label || @json(__('Send request')));
    submit.onclick=function(){
      var val=(contact.value||'').trim();
      if(!val){ contact.style.borderColor='#ef4444'; return; }
      var values={message:(msg.value||'').trim(), channel:selected};
      if(selected==='email'){ values.email=val; } else { values.phone=val; }
      submitHandoff(values);
    };
    form.appendChild(submit);
    applyChannel(QC_CHANNELS[0]);
    wrap.appendChild(form);
  }

  function scrollBottom(){ body.scrollTop=body.scrollHeight; }

  function appendTyping(){
    var t=el('div',{class:'sa-typing',id:'sa-typing'}, CHROME.typing_indicator);
    body.appendChild(t); scrollBottom();
  }
  function removeTyping(){ var t=document.getElementById('sa-typing'); if(t) t.remove(); }

  function sendMessage(){
    if(busy || AUTH_REQUIRED) return;
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
      // Session expired/revoked: re-show the login gate in place rather than
      // falling back (which would only 401 again) or showing a raw error.
      if(res.status===401){
        if(bubble && bubble.parentNode) bubble.remove();
        handleUnauthorized();
        return;
      }
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
      phone: values.phone||values.Phone||'',
      channel: values.channel||'',
      message: values.message||values.Message||'',
      page: pageMeta()
    }).then(function(res){
      removeTyping();
      if(res && res.__unauthorized) return; // login gate already shown
      if(res && res.ok){
        renderMessage(res.assistant_message);
        disableInput(true, CHROME.handoff_note);
      } else if(res && res.error){
        renderMessage({role:'assistant',content:res.error});
      }
    }).finally(function(){ busy=false; sendBtn.disabled=false; });
  }

  // ── "Contact us" view (folded-in quick-contact widget) ─────────
  // Swaps the chat surfaces for the multi-channel quick-contact form.
  // Posts to /assistant/quick-contact (anonymous-friendly, honeypot +
  // time-trap protected) so it works regardless of the login gate.
  var BACK_SVG='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>';
  var contactBuilt=false;
  function makeBack(){
    var b=el('button',{class:'sa-contact-back',type:'button',html:BACK_SVG+'<span>'+escapeHtml(@json(__('Back to chat')))+'</span>'});
    b.onclick=closeContact;
    return b;
  }
  function openContact(){
    if(!contactBuilt){ buildContactForm(); contactBuilt=true; }
    actions.style.display='none';
    suggested.style.display='none';
    body.style.display='none';
    inputRow.style.display='none';
    lowBalance.style.display='none';
    contactPane.classList.add('sa-show');
  }
  function closeContact(){
    contactPane.classList.remove('sa-show');
    actions.style.display='';
    suggested.style.display='';
    body.style.display='';
    inputRow.style.display='';
    lowBalance.style.display='';
  }
  function buildContactForm(){
    contactPane.innerHTML='';
    // Time-trap: stamp when the form opened so the server can reject a
    // submission filled+posted implausibly fast (a bot signal). A same-
    // clock delta, immune to clock skew / timezone.
    var openedAt=Date.now();
    var intro=el('div',{class:'sa-contact-intro'}, @json(__("Prefer we reach out? Pick how you'd like to be contacted and we'll get back to you.")));
    var errBox=el('div',{class:'sa-contact-err'}); errBox.style.display='none';
    var form=el('div',{class:'sa-form sa-qc'});
    var selected=QC_CHANNELS[0].value;
    var tabs=el('div',{class:'sa-qc-tabs'});
    var contact=el('input',{type:QC_CHANNELS[0].inputType,placeholder:QC_CHANNELS[0].placeholder});
    var msg=el('textarea',{rows:'2',placeholder:@json(__('How can we help? (optional)'))});
    // Honeypot: a decoy field a real visitor never fills but blind bots do.
    var trap=el('input',{type:'text',name:'website',tabindex:'-1',autocomplete:'off','aria-hidden':'true'});
    trap.style.cssText='position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;pointer-events:none';
    var send=el('button',{type:'button'}, @json(__('Send request')));
    function applyChannel(ch){
      selected=ch.value;
      contact.value='';
      contact.setAttribute('type', ch.inputType);
      contact.setAttribute('placeholder', ch.placeholder);
      contact.style.borderColor='';
      Array.prototype.forEach.call(tabs.children,function(btn){
        btn.classList.toggle('sa-qc-on', btn.getAttribute('data-ch')===ch.value);
      });
    }
    QC_CHANNELS.forEach(function(ch){
      var btn=el('button',{type:'button',class:'sa-qc-tab','data-ch':ch.value}, ch.label);
      btn.onclick=function(){ applyChannel(ch); };
      tabs.appendChild(btn);
    });
    var busyQc=false;
    send.onclick=function(){
      if(busyQc) return;
      var val=(contact.value||'').trim();
      if(!val){ contact.style.borderColor='#ef4444'; return; }
      var payload={channel:selected, message:(msg.value||'').trim(), website:(trap.value||''), elapsed_ms:(Date.now()-openedAt)};
      if(selected==='email'){ payload.email=val; } else { payload.phone=val; }
      busyQc=true; send.disabled=true; send.textContent=@json(__('Sending…')); errBox.style.display='none';
      jpost(ds.quickContactUrl, payload)
        .then(function(d){
          if(d && d.ok){
            contactPane.innerHTML='';
            contactPane.appendChild(makeBack());
            contactPane.appendChild(el('div',{class:'sa-contact-done'}, d.message || @json(__("Thanks! We've got your request and will be in touch soon."))));
          } else {
            errBox.textContent=(d && d.error) || @json(__('Something went wrong. Please try again.')); errBox.style.display='';
          }
        })
        .catch(function(){ errBox.textContent=@json(__('Network error. Please try again.')); errBox.style.display=''; })
        .finally(function(){ busyQc=false; send.disabled=false; send.textContent=@json(__('Send request')); });
    };
    form.appendChild(tabs);
    form.appendChild(contact);
    form.appendChild(trap);
    form.appendChild(msg);
    form.appendChild(send);
    contactPane.appendChild(makeBack());
    contactPane.appendChild(intro);
    contactPane.appendChild(errBox);
    contactPane.appendChild(form);
    applyChannel(QC_CHANNELS[0]);
  }

  function handleTurn(res){
    removeTyping();
    // 401 already re-showed the login gate (jpost) — don't also render an
    // error bubble.
    if(res && res.__unauthorized) return;
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

  // --- Safari iOS visible-viewport pinning --------------------------------
  // Safari iOS animates its address/tab bars in and out as you scroll, which
  // shrinks the *visible* viewport mid-session. `100dvh` (mobile stylesheet)
  // fixes the panel's height at the moment it opens, but a panel already open
  // gets clipped under the bars as they animate back in. We track
  // window.visualViewport and pin the panel's height to the true visible area
  // for as long as it's open on a small screen. On desktop, when the panel is
  // closed, or in browsers without visualViewport we remove the inline height
  // so the stylesheet's dvh/vh sizing (and desktop 560px) stays untouched.
  var SA_VV_RESERVE = 100; // keep in lockstep with the mobile 100dvh - 100px rule
  function saSyncPanelViewport(){
    var vv = window.visualViewport;
    var mobile = (window.innerWidth || document.documentElement.clientWidth || 0) <= 480;
    if (!vv || !mobile || !open) {
      if (panel.style.height) panel.style.height = '';
      return;
    }
    var h = Math.max(240, Math.round(vv.height) - SA_VV_RESERVE);
    var hpx = h + 'px';
    if (panel.style.height !== hpx) panel.style.height = hpx;
  }
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', saSyncPanelViewport, { passive: true });
    window.visualViewport.addEventListener('scroll', saSyncPanelViewport, { passive: true });
  }
  window.addEventListener('resize', saSyncPanelViewport, { passive: true });
  window.addEventListener('orientationchange', saSyncPanelViewport, { passive: true });

  scheduleTooltip(true);
})();
</script>
@endif
