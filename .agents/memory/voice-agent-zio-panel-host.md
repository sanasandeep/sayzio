---
name: Voice agent hosting (Zio panel vs floating widget)
description: Where the full voice agent runs per surface, and the voiceFloating decoupling param.
---

# Voice agent hosting

The full voice agent (record → turn → spoken reply → confirmation chips →
hands-free → `window` dispatch of `voice-action` + deferred `navigate_to` →
"What I can do" capabilities) runs in TWO independent implementations:

- **Admin floating widget** — Alpine `voiceAssistant()` in
  `partials/voice-assistant.blade.php` (bottom-corner mic). Still the host on
  the admin layout.
- **User dashboard** — a plain-JS port embedded inside the Zio chat panel
  composer (`common/partials/site-assistant.blade.php`), so the dashboard has
  ONE launcher (Zio), not a separate floating mic.

**Why two copies:** lowest-risk merge — the Zio panel must not depend on
`voice-assistant.blade.php` load order (it loads AFTER site-assistant on the
user layout). Accepted logic duplication; see follow-up to unify.

**How to apply:**
- A cross-cutting voice change (turn payload shape, confirmation flow, surface
  bridge, capabilities rendering) must touch BOTH implementations or they drift.
- `voice-assistant.blade.php` takes a `$voiceFloating` param (default true).
  The user layout includes it with `voiceFloating=false` to suppress the
  floating mic while keeping `window.__voice` + the reusable `voiceDictation()`
  helper (header search / companion) alive — those render in their own
  `$voiceAvailable`-gated block, independent of the floating widget.
- Zio-panel voice eligibility is computed server-side only for
  `surface==='app' && auth()->check()` and exposed as `data-voice-*` attrs;
  marketing/anonymous surfaces get no mic. Plan-gated users get a lock-badged
  mic routing to `user.ai.voice.show`.
