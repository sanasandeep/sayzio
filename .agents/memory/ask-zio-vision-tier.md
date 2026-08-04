---
name: Ask Zio vision tier
description: How page-screenshot vision works across Zio Browser, SiteAssistantRuntime, and OpenAiService, and its lockstep surfaces.
---

# Ask Zio vision tier

- Screenshot flows client→server as a `data:image/(png|jpeg|webp);base64,...` string in the `screenshot` field of `/assistant/message` and `/assistant/stream` (web + `/api/v1` mirrors share the controller). It is NEVER persisted — only `meta.vision.snapshot=true` on the user message.
- `SiteAssistantRuntime::resolveVisionAttachment()` is the single gate: regex/mime check (malformed = silent drop), decoded-size cap 1.5MB (notice), plan gate `AiPlanAccess::featureAllowed($user,'site_assistant_vision')` (paid-only via legacyAvailabilityFallback — the default match arm is `true`, so any new paid-only feature key MUST be added there or free users get it), then `AiEngineSettings::visionChatModel()`.
- Refusals degrade to text-only and surface `vision: {used, notice}` in turn() return / stream `done` — both turn() and turnStream() must stay in lockstep.
- `AiEngineSettings` model rows carry `supports_vision` (stored flag wins; legacy rows infer by name prefix gpt-4o/gpt-4.1/gpt-5, chat kind only). Rate keys are `in_coins_per_1k`/`out_coins_per_1k` — tests using the old `*_credits_per_1k` names silently compute cost 0 (OpenAiServiceChatStreamTest fails at HEAD for exactly this).
- `OpenAiService` prices image parts at a FLAT `IMAGE_PROMPT_TOKENS` (1100) per `image_url` part — never json_encode multimodal content into the estimate (base64 would explode the prepay). `guardVision()` throws before HTTP if image parts hit a non-vision model.
- Zio Browser side: capture guard logic lives in pure `context-extractor.ts` helpers (`isCapturableUrl`, `looksVisualQuestion`, `buildMediaBlock`, SCREENSHOT_* caps) so vitest covers it; `TabManager.captureWebsitePaneForAi` captures ONLY `tab.view.webContents` (never second/dashboard panes), refuses `internalUrl`/`isNewTabPage`, downscales to 1280w JPEG with quality fallback under the byte cap.
- Live smoke (Aug 2026) PASSED: /api/v1/assistant/message with a real key + PNG data URL → correct image description, vision.used=true, sane coin charge (gpt-4o-mini bills ~8.5k prompt tokens for a small image; billed image tokens prove OpenAI received it). Quirk: with the Sayzio Assistant system prompt the model can stochastically reply "I'm unable to analyze images directly" even though the image was billed; one refusal then poisons the conversation history so retries in the same conversation keep refusing. Prompt has no "you may be given a page snapshot" line — adding one would fix it.
- Text tier: the extract-context inline script emits labeled `media` lines (Image/Figure/Video) that `trimPageContext` appends as a capped `[Visual media on this page]` block.

## Web widget snapshot affordance (Aug 2026)
- The web Ask Zio widget's camera button captures the VISIBLE viewport via vendored `public/js/vendor/html2canvas.min.js`, lazy-loaded on first click (bundle stays light); the assistant's own launcher+panel are excluded via `ignoreElements`.
- Snapshot is downscaled/re-encoded (JPEG, ≤1280px, iterate quality→scale) until decoded size fits under the server's 1.5MB cap; sent as `screenshot` on stream + non-stream fallback for ONE turn, then cleared.
- Server refusal notes come back as `vision.notice` on both the classic response and the SSE `done` frame — the widget renders them as a `.sa-vision-note`.
- e2e stubs `window.html2canvas` via addInitScript (widget skips the vendor load when it's already defined).
