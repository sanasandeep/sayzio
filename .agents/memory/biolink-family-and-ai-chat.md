---
name: Biolink family & ai_chat link type
description: How conversational/slides/ai_chat became distinct links.type values and how the full-page AI chat reuses the AI Companion stack
---

# Biolink family

`links.type` now has a "biolink family": `biolink`, `conversational`, `slides`, `ai_chat`.
These were previously a single `biolink` row distinguished by `settings.biolink.mode`.

- Use `Link::isBiolinkFamily()` / `Link::scopeBiolinkFamily()` / `Link::BIOLINK_FAMILY`
  for guards and queries instead of `=== 'biolink'` / `where('type','biolink')`.
- `RedirectController::biolinkViewFor()` picks the public view by **type**, but keeps a
  legacy fallback: a `biolink` row still carrying `settings.biolink.mode` of
  conversational/slides is honoured. **Why:** unmigrated rows must keep rendering.
- The data migration only promotes rows still typed `biolink` (idempotent).

# ai_chat = AiCompanion(placement='page')

An `ai_chat` link binds exactly one `AiCompanion` (placement `page`, added to
`AiCompanion::PLACEMENTS`) via the `ai_companion_links` pivot (`$link->aiCompanion()`).
There is **no new AI runtime** — the public page (`common.ai-chat`) POSTs to the existing
`PublicCompanionController@message` / `CompanionRuntime`.

Key gotchas:
- The companion needs a persona (`persona_id` is NOT NULL). `AiChatController::ensureCompanion`
  auto-provisions a dedicated default `AiPersonaAgent` when the user has none, so the editor
  is never a dead end. `PersonaRuntime` reads **live** persona fields, not a version row, so
  a persona created without writing an `ai_persona_agent_versions` row still runs.
- The PublicCompanionController origin/allow-list gate only applies to `placement === embed`.
  **Why this matters:** a same-origin full-page `page` placement works with no token/CORS setup.
- `biolinkViewFor` returns `common.biolink` (not `common.ai-chat`) when no companion is bound
  or it's disabled, so the URL is never a dead end.
- Editor tabs (`editor-header.blade.php`) adapt per type; the old in-editor mode-selector
  partial was deleted (types are chosen at creation, not switched in-editor).
