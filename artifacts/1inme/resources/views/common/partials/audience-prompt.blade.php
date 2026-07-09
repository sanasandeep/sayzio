{{-- Visitor self-identification prompt.
     Rendered on public biolinks when the owner has enabled
     settings.biolink.audience_prompt.enabled.
     Respects the same consent gate as engagement tracking.
     The visitor's tapped answer is stored in page_sessions.visitor_type
     via POST /{alias}/track/identify. --}}
@php
    $apCfg      = $link->settings['biolink']['audience_prompt'] ?? [];
    $apEnabled  = !empty($apCfg['enabled']);
    if (!$apEnabled) return;

    $apQuestion = trim($apCfg['question'] ?? '') ?: 'What best describes you?';
    $apChoices  = !empty($apCfg['choices']) && is_array($apCfg['choices'])
        ? $apCfg['choices']
        : [
            ['key' => 'student',      'label' => 'Student',              'icon' => '🎓'],
            ['key' => 'professional', 'label' => 'Professional',         'icon' => '💼'],
            ['key' => 'business',     'label' => 'Business Owner',       'icon' => '🏢'],
            ['key' => 'creator',      'label' => 'Creator',              'icon' => '🎨'],
            ['key' => 'other',        'label' => 'Other',                'icon' => '👋'],
        ];
    $apChoices = array_slice($apChoices, 0, 6);
    $apAlias   = $link->_used_alias ?? $link->alias;
    $apUrl     = '/' . $apAlias . '/track/identify';
    $apConsent = !empty($link->settings['biolink']['privacy']['consent_banner_enabled'] ?? false);
    $apCookieKey = 'ap_type_' . $link->id;
@endphp
<div id="audience-prompt-wrap"
     style="width:100%;max-width:480px;margin:0 auto 16px;padding:0 12px;"
     {{-- @js (Js::from) escapes for HTML-attribute context; a raw @json here
          would emit literal double quotes that truncate this double-quoted
          attribute, leaving Alpine uninitialised and the prompt invisible. --}}
     x-data="audiencePrompt(@js($apUrl), @js($apConsent), @js($link->id))">

    <template x-if="!dismissed">
        <div class="audience-prompt-card" x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             style="border-radius:16px;padding:16px 14px 14px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.13);backdrop-filter:blur(12px);">

            <p style="font-size:13px;font-weight:600;color:rgba(255,255,255,0.88);margin:0 0 10px;text-align:center;">
                {{ $apQuestion }}
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                @foreach($apChoices as $choice)
                <button type="button"
                        @click="pick(@js($choice['key']))"
                        style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.85);transition:all .18s;"
                        :style="chosen === @js($choice['key']) ? 'background:rgba(92,131,255,0.35);border-color:rgba(92,131,255,0.6);' : ''"
                        onmouseover="this.style.background='rgba(255,255,255,0.13)'"
                        onmouseout="if(this.getAttribute('data-chosen')!=='1') this.style.background='rgba(255,255,255,0.08)'">
                    <span>{{ $choice['icon'] ?? '' }}</span>
                    <span>{{ $choice['label'] }}</span>
                </button>
                @endforeach
            </div>

            <button type="button" @click="dismissed = true"
                    style="display:block;margin:10px auto 0;font-size:11px;color:rgba(255,255,255,0.35);background:none;border:none;cursor:pointer;">
                Skip
            </button>
        </div>
    </template>

    <template x-if="dismissed && chosen">
        <p x-transition style="text-align:center;font-size:12px;color:rgba(255,255,255,0.45);margin:0 0 8px;">
            Thanks for letting us know!
        </p>
    </template>
</div>

<script>
function audiencePrompt(url, consentRequired, linkId) {
    return {
        dismissed: false,
        chosen: null,
        init() {
            var stored = this.readStorage();
            if (stored) { this.dismissed = true; this.chosen = stored; }
        },
        readStorage() {
            try { return localStorage.getItem('ap_type_' + linkId); } catch(e) { return null; }
        },
        pick(key) {
            this.chosen = key;
            this.dismissed = true;
            // Persist NOTHING (no localStorage, no cookie, no network call)
            // unless the visitor has granted consent when consent is required.
            if (consentRequired && !this.consentGranted()) return;
            try { localStorage.setItem('ap_type_' + linkId, key); } catch(e) {}
            // also set a cookie so server can read it for display rules on next page load
            try { document.cookie = 'ap_type_' + linkId + '=' + encodeURIComponent(key) + '; path=/; max-age=31536000; SameSite=Lax'; } catch(e) {}
            this.send(key);
        },
        send(key, attempt) {
            attempt = attempt || 0;
            var sid = window.__SESSION_ID__;
            if (!sid) {
                // Session ID populated asynchronously after startSession() completes — retry
                if (attempt < 8) {
                    var self = this;
                    setTimeout(function() { self.send(key, attempt + 1); }, 700);
                }
                return;
            }
            if (consentRequired && !this.consentGranted()) return;
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
                body: JSON.stringify({ session_id: sid, visitor_type: key }),
                keepalive: true
            }).catch(function(){});
        },
        consentGranted() {
            var ck = document.cookie.match(/1inme_link_consent_(\d+)=([^;]*)/);
            if (ck && ck[1] == linkId) return ck[2] === 'accept';
            var ws = document.cookie.match(/1inme_cookie_consent=([^;]*)/);
            if (ws) { try { var p = JSON.parse(decodeURIComponent(ws[1])); return !!(p && p.c && p.c.analytics); } catch(e){} }
            return false;
        }
    };
}
</script>
