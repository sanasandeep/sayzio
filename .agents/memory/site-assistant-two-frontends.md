---
name: Site assistant Zio Bot widget (marketing twin removed)
description: The "Zio Bot" site assistant is the Laravel blade widget on the /assistant/* contract; the marketing-site React twin was removed in July 2026.
---

The site-wide AI assistant ("Zio Bot") is rendered by the Laravel
in-app/marketing-layout widget — `common/partials/site-assistant.blade.php`
(vanilla JS, same-origin, CSRF + cookies, SSE streaming with non-stream
fallback) — against the backend contract `SiteAssistantController`
`/assistant/*`.

**History:** until July 2026 a second, cross-origin React widget lived in the
standalone marketing site (`artifacts/1inme-com`, since deleted). Leftovers of
that era that are still in place and intentionally kept:
- `artifacts/1inme/config/cors.php` exposes `assistant/*` with `Access-Control-Allow-Origin: *`.
- `assistant/*` is in the CSRF `except` list in `bootstrap/app.php`.
- `detectSurface()` forces anonymous callers to the `marketing` surface server-side.

**Rule:** login is gated SERVER-SIDE (bootstrap payload says whether the
visitor is authed); in-chat OTP login==signup; the blade widget uses session
auth. If a new external consumer of `/assistant/*` is ever added, changes to
the bootstrap payload fields, block types (buttons/list/image/form), or the
session/message/choice/handoff request shapes must update all consumers in
lockstep.
