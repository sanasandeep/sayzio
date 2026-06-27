---
name: Inbox Agent (Inbox 2.0 AI)
description: Design decisions/guardrails for the unified-inbox AI agent (triage, drafting, autopilot) in artifacts/1inme.
---

The unified inbox (`user.inbox.unified.*`) has an AI agent layered on top: LLM triage, manual AI drafting, and per-category autopilot. Feature key `inbox_agent` (registered in both AiEngineSettings::FEATURES and AiFeatureCatalog).

**Charge the workspace OWNER for all inbox-agent AI**, never the acting teammate.
**Why:** teammates without a paid plan/wallet would otherwise 402 on shared-workspace AI; the owner is the billing authority for the workspace.
**How to apply:** resolve `User::find($ws->owner_user_id)` as the charge user in every inbox-agent AI path (triage, drafter, autopilot).

**Autopilot guardrails are hard constraints, not UI niceties.**
**Why:** auto-replying spam/sensitive or low-confidence threads is a trust/safety hazard.
**How to apply:** never auto-reply `spam` (AUTOPILOT_FORBIDDEN_CATEGORIES) or threads matching the sensitive-keyword list; only auto-send when triage confidence ≥ workspace `confidence_threshold` AND category is opted-in AND no prior outbound reply exists; otherwise stage `ai_draft` + `autopilot_state='review'` for manual review. Sent autopilot replies set `sent_by_ai` + emit a FeedEvent + label "Sent by AI".

Settings live in `workspace.settings['inbox_agent']` via `InboxAgentSettings` (defaults-merged + clamped on read/write) — no dedicated table. Triage falls back to rule-based `InboxClassifier` when the AI engine is off or on parse failure (with refund).
