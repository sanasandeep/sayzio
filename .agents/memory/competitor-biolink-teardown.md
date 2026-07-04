---
name: Competitor Biolink Teardown
description: How the "paste a competitor URL → AI teardown → build a better version" feature is wired (fetch, charge/refund, builder handoff).
---

Fetch-then-analyze order matters for cost safety: `CompetitorPageFetcher::fetchAndExtract()` (plain HTTP GET + DOMDocument, with an SSRF guard re-checked post-redirect) runs and can throw BEFORE any AI credit charge, so a bad/unreachable URL never costs the user coins. Only after a successful fetch does `CompetitorTeardownService::analyze()` create the DB row and call `OpenAiService::chat(feature: 'competitor_teardown')`.

**Why:** mirrors the `CardBrochureExtractionService` chokepoint pattern — persist the credit spend immediately after the chat call succeeds (before parsing the JSON), so any downstream parse failure can still refund the exact amount via `AiUsageCharger::refund` with an idempotency key.

**How to apply:** "Build me a better version" does NOT add its own charge/gate — it creates a fresh biolink shell (`Link::create` + `Link::generateAlias()`) and hands off to the existing `AiBiolinkBuilderService::generate()`, which owns its own credit charge/refund and safe block-subset constraints. The builder's description is synthesized from the teardown's strengths/weaknesses/missing-elements/recommendations. On builder failure the shell link is deleted (same pattern as `WhatsAppAgentTools`).

Verifying this feature in an environment with no OpenAI key configured: the controller's `store()`/`build()` explicitly guard on `AiEngineSettings::isEnabled() && AiEngineSettings::openAiKey()` before calling the service, so you can smoke-test routing/views/plan-gating end-to-end (expect a clean flashed error + zero DB rows) without ever reaching a live OpenAI call.
