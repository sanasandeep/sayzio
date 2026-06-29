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
        fd.append('audio', blob, 'voice.' + ext);
        fd.append('context', JSON.stringify({
            messages: opts.messages || [],
            confirmed_tools: opts.confirmedTools || {},
            surface: window.__voiceSurface || null
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
                    error: json.error || null,
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
            if (r.navigate_to) { nav = r.navigate_to; }
        });
        return nav;
    }

    // Capabilities catalogue ("What I can do"). Resolves to the parsed JSON, or
    // a friendly fallback shape on any failure so callers can render blindly.
    function loadCaps(url) {
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { tools: {}, limitations: ['Could not load capabilities.'] }; });
    }

    return { sendTurn: sendTurn, applyToolResults: applyToolResults, loadCaps: loadCaps };
})();
</script>
