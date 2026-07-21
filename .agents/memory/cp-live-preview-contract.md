---
name: Creator Profile live preview (cpLive) contract
description: How the owner-only density/theme live preview works across web editor and mobile, incl. signed-URL auth for WebViews.
---
Public creator page honors `{type:'cpLive', density:'small'|'medium'|'large', theme:'dark'|'light'}` postMessage ONLY when the `cp_preview=1` gate passes; listener requires `e.origin === window.location.origin`, so embedders must post FROM INSIDE the page (WebView injectJavaScript) or same-origin iframe.

Gate accepts either the session-authenticated owner (web editor iframe) or a valid RELATIVE signed URL (`hasValidSignature(false)`), minted by the owner-only API endpoint `/me/creator-profile/preview-url` (~30-min `temporarySignedRoute`).

**Why:** mobile WebViews carry no web session; a relative signature proves ownership without one and survives dev/prod host differences.

**How to apply:** any new surface embedding the preview should fetch the signed URL from the API, prepend the base URL, and post cpLive from within the page. Never loosen the gate — real visitor rendering must stay unaffected.

Gotcha: a `{{-- --}}` Blade comment inside `@php` is NOT stripped and breaks compilation — use `//` there.
