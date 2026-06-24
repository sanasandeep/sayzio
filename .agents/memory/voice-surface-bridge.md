---
name: Voice surface-bridge pattern
description: How the 1INME voice assistant drives on-screen surfaces (search, wizard, create-link, companion) via client_action + surface context, on web + mobile.
---

# Voice surface-bridge

Voice tools never mutate the page directly. They return a payload the client interprets:
- `client_action` — a structured intent the focused surface acts on.
- `navigate_to` — a fallback route, applied only AFTER the TTS reply finishes so speech isn't cut off.

**Why:** keeps the voice engine (Whisper→GPT tool-loop→ElevenLabs) decoupled from page DOM; one tool result drives both web and mobile.

**Surface contract (the sharp edge):** the client tells the service which screen the user is on so the prompt/tools are targeted. Web sends `surface` as an **object** (`{name}`); mobile sends it as a **bare string**. The service must normalize string → `{name}` before reading `.name`, or every mobile turn silently loses its surface hint. Regression-guarded by surface-context prompt tests.

**Dispatch of client_action:** web uses a DOM `voice-action` CustomEvent that surfaces listen for; mobile has no DOM bus, so the assistant component exposes an `onVoiceAction`/`emitVoiceAction` subscription and the focused screen registers a ref-based handler (so it sees latest state without re-subscribing). Same action shapes on both.

**Dictation = STT-only** (no LLM/TTS): a separate transcribe endpoint, same plan-gate + metering as a turn (403 not-allowed / 402 out-of-credits). Web exposes a reusable `voiceDictation()` Alpine factory; mobile a record-one-clip hook.
- **Gotcha:** surfaces mount `x-data="voiceDictation(...)"` on always-rendered containers (header search, composer), so `voiceDictation` must be defined for EVERY authenticated user — define it outside the voice-availability gate. If it's only defined when voice is available, plan-gated users get an Alpine init error that breaks the host component's other behaviour (e.g. the header-search Enter handler). Regression-guarded by a "helper defined for plan-blocked user" view test.

**Hands-free:** auto-restart the mic after each reply ends, paused while confirmations are pending. (Mobile has no silence auto-stop — architectural limit, acceptable.)
