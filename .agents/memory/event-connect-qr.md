---
name: Event Connect QR flow
description: Shared connect service across web/API, imagick-dependent QR PNG, and mobile routing for ?src=connect_qr event links.
---

# Event Connect QR (scan → OTP sign-in → RSVP yes → follow)

- The RSVP+follow+attribution logic lives in `App\Services\Events\EventConnectService` and is shared by the web OTP controller (`EventConnectQrController`) and the authed API endpoint (`POST /api/v1/events/{alias}/connect`). Extend the service, not the controllers, and guard session writes with `$request->hasSession()` (API callers have none).
- **QR PNG needs the imagick PHP extension** — absent in local/test envs, so SimpleSoftwareIO `QrCode::format('png')` throws. The API returns `qr_png_base64: null` best-effort; mobile falls back to writing/sharing the SVG. Never assert PNG presence in tests; SVG is the guaranteed payload.
- Mobile routing: the mobile biolink API (`GET /biolinks/{alias}`) 404s for non-biolink-family types (incl. `ics`), so `app/biolink/[handle].tsx` cannot type-branch on events. It instead falls back on a 404 by trying `getEvent(alias)` and replacing to `/events/{alias}` preserving `?src=` — new "scan lands in app" link types need the same 404-fallback pattern.
- **Why:** three surfaces (web OTP flow, mobile one-tap connect, host QR/stat views) must stay behaviorally identical; drift showed up immediately as idempotency/count mismatches in `EventConnectQrTest`.
- **How to apply:** any change to connect semantics goes into EventConnectService; run `TEST_LOCAL_MODE=artisan composer test:local -- --filter EventConnectQrTest` (covers web + API + stats parity).
