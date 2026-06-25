---
name: Site assistant has two front-ends, one backend contract
description: The "Zio Bot" site assistant is rendered by both the Laravel blade widget and a marketing-site React widget; backend contract changes must touch both.
---

The site-wide AI assistant ("Zio Bot") is consumed by TWO independent
front-ends that share one backend contract (`SiteAssistantController`
`/assistant/*` on the Laravel app):

1. Laravel in-app/marketing-layout widget — `common/partials/site-assistant.blade.php` (vanilla JS, same-origin, CSRF + cookies, SSE streaming with non-stream fallback).
2. Marketing-site widget — `artifacts/1inme-com/src/components/site-assistant.tsx` (React, **cross-origin** to `ASSISTANT_API_BASE` = 1in.me, **anonymous: no credentials/CSRF**, non-streaming `/assistant/message` only).

**Rule:** any change to the bootstrap payload fields, block types
(buttons/list/image/form), or the session/message/choice/handoff request
shapes must update BOTH front-ends in lockstep, or one silently diverges.

**Cross-origin plumbing the marketing widget depends on:**
- `artifacts/1inme/config/cors.php` exposes `assistant/*` with `Access-Control-Allow-Origin: *` (Laravel's HandleCors is in the global stack and auto-answers preflight). Without it the marketing widget is CORS-blocked.
- `assistant/*` is in the CSRF `except` list in `bootstrap/app.php` (anonymous cross-origin posts carry no CSRF cookie).
- `detectSurface()` forces anonymous callers to the `marketing` surface server-side, so the marketing widget can't be spoofed into billed `app` behavior.

**Why:** these are easy to forget because the marketing site is a separate
artifact/origin with no auth of its own; a new bootstrap field or block type
added only to the blade widget will be missing on 1inme.com.

**Default avatar/brand:** `SiteAssistantSettings::avatarUrlFor()` falls back
to the bundled mascot `public/branding/zio-bot.png` (absolute via `asset()`)
when no admin avatar is set; `brandNameFor()` falls back to "Zio Bot". Stored
`avatar_url` default stays null so admin-clearing still yields the mascot.
