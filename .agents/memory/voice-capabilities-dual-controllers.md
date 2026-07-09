---
name: Voice capabilities lives in TWO controllers
description: Web and API voice-assistant capabilities endpoints are separate controllers that must stay in lockstep.
---

# Voice capabilities dual controllers

The voice assistant "capabilities" payload is served by TWO separate controllers:
- Web: `App\Modules\User\Controllers\AI\VoiceAssistantController::capabilities` (route `user.ai.voice.capabilities`)
- API/mobile: `App\Modules\Api\Controllers\VoiceAssistantController::capabilities` (`GET /api/v1/ai/voice/capabilities`)

**Why:** adding a field (e.g. `coin_cost`/`dictation_coin_cost`/`coin_balance`) to only one silently breaks parity; the feature test (`VoiceAssistantCapabilitiesTest`) hits the WEB route, while the mobile app reads the API route — a green test does not prove the mobile contract.

**How to apply:** any change to the capabilities payload must be made in both controllers. Note the estimator returns 0 coins when `AiEngineSettings::isEnabled()` is false, so tests asserting non-zero costs must `setEnabled(true)`, not just `setVoiceEnabled(true)`.
