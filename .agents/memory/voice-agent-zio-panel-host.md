---
name: Voice agent hosting (Zio panel vs floating widget)
description: Where the full voice agent runs per surface, the shared voice-runtime core, and the voiceFloating decoupling param.
---

# Voice agent hosting

The full voice agent (record → turn → spoken reply → confirmation chips →
hands-free → `window` dispatch of `voice-action` + deferred `navigate_to` →
"What I can do" capabilities) runs on TWO surfaces with DIFFERENT UI shells but
ONE shared runtime core:

- **Admin floating widget** — Alpine `voiceAssistant()` in
  `partials/voice-assistant.blade.php` (bottom-corner mic). Host on the admin
  layout.
- **User dashboard** — a plain-JS mic embedded inside the Zio chat panel
  composer (`common/partials/site-assistant.blade.php`), so the dashboard has
  ONE launcher (Zio), not a separate floating mic.

**Shared core:** `common/partials/voice-runtime.blade.php` defines
`window.VoiceRuntime = window.VoiceRuntime || (...)` (idempotent) exposing:
- `sendTurn({url,csrf,blob,messages,confirmedTools})` — builds the audio+context
  FormData (`context.surface = window.__voiceSurface`, ext webm/ogg), POSTs to
  `/user/ai/voice/turn`, returns a normalized `{ok,status,error,transcript,
  reply,pending,credits,balance,toolResults,audioBase64}`; rejects ONLY on a
  network error.
- `applyToolResults(results)` — the surface bridge: dispatches a `voice-action`
  CustomEvent per `client_action`, returns `navigate_to` (caller defers it until
  the spoken reply ends).
- `loadCaps(url)` — fetch capabilities, fallback `{tools:{},limitations:[…]}`.

Both shells `@include('common.partials.voice-runtime')` (the include is
idempotent, so a page hosting both surfaces registers the runtime once). Each
shell still owns its own MediaRecorder capture, state, and DOM/Alpine rendering
— only the turn payload, surface bridge, and caps fetch are shared.

**Why:** the two shells previously duplicated this logic and could silently
drift (turn payload shape, bridge, caps). Centralizing the contract-bearing
parts keeps them in lockstep; recording/rendering stay per-surface because
they're shell-specific.

**How to apply:**
- A cross-cutting change to the turn payload, surface bridge, or caps shape goes
  in `voice-runtime.blade.php` ONCE — do not re-edit the two shells.
- Changes to capture, message rendering, confirmation chips, or hands-free still
  touch each shell independently.
- The Alpine shell keeps test-constrained internals intact: `comp.sendTurn`,
  `pendingNav`, `lastAudio`, `panelOpen`, and the in-widget `$refs.player`
  `<audio>` (see `tests/Browser/voice-assistant-bridge.spec.ts`, which drives
  Alpine internals directly and dispatches `ended`).
- The 1inme-com source-driven blade test runs site-assistant's FIRST `<script>`
  with voice UNAVAILABLE, so `VoiceRuntime` is never referenced there — the
  `@include` must stay a directive OUTSIDE that first `<script>`.
- `voice-assistant.blade.php` takes a `$voiceFloating` param (default true). The
  user layout includes it with `voiceFloating=false` to suppress the floating
  mic while keeping `window.__voice` + the reusable `voiceDictation()` helper
  alive — those render in their own `$voiceAvailable`-gated block, independent of
  the floating widget. `window.voiceAssistant` and the runtime include both live
  inside `@if($voiceFloating && $voiceAvailable)`.
- Zio-panel voice eligibility is computed server-side only for
  `surface==='app' && auth()->check()` and exposed as `data-voice-*` attrs;
  marketing/anonymous surfaces get no mic. Plan-gated users get a lock-badged
  mic routing to `user.ai.voice.show`.
