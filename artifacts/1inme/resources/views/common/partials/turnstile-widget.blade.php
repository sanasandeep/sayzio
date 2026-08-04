{{-- Invisible Cloudflare Turnstile widget. Renders NOTHING when the feature
     is disabled or unconfigured, so by default no Cloudflare script loads and
     the surrounding form behaves exactly as before.

     When enabled, the widget div is picked up by Cloudflare's implicit
     renderer, which injects a hidden `cf-turnstile-response` input into the
     enclosing <form> — so both classic submits AND the auth-ajax FormData
     path carry the token automatically. `data-appearance="interaction-only"`
     keeps the widget invisible unless Cloudflare decides an interactive
     check is required. The script tag is emitted once per page (@once),
     async + defer so it never blocks rendering. --}}
@if(\App\Services\Integrations\TurnstileSettings::enabled())
    <div class="cf-turnstile"
         data-sitekey="{{ \App\Services\Integrations\TurnstileSettings::siteKey() }}"
         data-appearance="interaction-only"
         data-size="flexible"></div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
