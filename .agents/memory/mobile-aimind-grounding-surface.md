---
name: Mobile AiMind grounding picker surface
description: Which /api/v1 AI endpoints actually accept a user-supplied Mind picker and ground through resolveMindsForUser.
---

On the 1inme mobile Sanctum API, the ONLY endpoint that accepts a user-supplied
Mind picker and grounds through `AiMindQueryService::resolveMindsForUser` is the
guided Link-in-Bio wizard's AI auto-draft:
`POST /api/v1/links/wizard/ai-generate` (params `ai_mind_ids` /
`include_platform_mind`) → `WizardAiDraftService::resolveGrounding()`.

NOT mobile Mind-picker surfaces (unlike their web twins):
- `AskCoachController` (mobile) — never resolves KB grounding at all.
- `AiCompanionController` (mobile) — companion runtime, no Mind picker.
- `BrandKitController::generate` (mobile) — accepts only prompt/website_url/logo_url.
- `AiBiolinkBuilderController` (mobile "Build with AI") — only description/links/images/files/use_brand_kit, no mind_ids.
- `AiMindPickerController` — saves defaults, does NOT ground.

**Why:** task briefs sometimes assume mobile parity with web grounding that
doesn't exist yet. `resolveMindsForUser` is owner-only (returns only Minds the
caller owns + opt-in platform default), so a merely-SHARED Mind (team seat /
badge group) can never reach `retrieveContext`, regardless of live USE access —
a guarantee strictly stronger than `canUseMind`.

**How to apply:** when asked to test/extend "shared KB can't leak via mobile AI
grounding", target the wizard ai-generate surface. Regression test mirroring the
web pattern lives at
`artifacts/1inme/tests/Feature/ApiAiGroundingSharedMindRevocationTest.php`
(partial-mock AiMindQueryService capturing retrieveContext, stub OpenAiService +
AiBiolinkBuilderService, real Sanctum bearer token). If a Mind picker is later
added to the other mobile endpoints, add matching coverage there.
