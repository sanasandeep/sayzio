---
name: Biolink wizard mobile parity
description: How the mobile guided Link-in-Bio wizard reuses the web wizard's services without DB drafts
---

The guided Link-in-Bio wizard exists on web (per-user/workspace `biolink_wizard_drafts` rows so a tab can resume) and on mobile. Mobile is deliberately **stateless**: the client drives all four steps (category → page type → optional industry → Q&A) in memory and POSTs every answer at once.

**Rule:** both surfaces share the same services — never duplicate the question taxonomy or page generation.
- `BiolinkWizardQuestions` owns the taxonomy/questions AND the shared contract helpers `nameKeys()` / `hasName()` / `resolveTitle()` / `sanitizeAnswers()`.
- `BiolinkWizardGenerator` (`app/Modules/User/Services/`) owns the recipe → Link → blocks core (wraps `BiolinkPageRecipes::build` + `TemplateService::applyPageToLink` in a transaction). Both the web `User\Controllers\BiolinkWizardController::finish` and the mobile `Api\Controllers\BiolinkWizardController::generate` call it.

**Why:** task asked to reuse services not duplicate logic; the only per-surface code is the controller (validation + plan caps + response shape) and the mobile UI.

**How to apply / gotchas:**
- Mobile API: `GET /links/wizard/taxonomy`, `GET /links/wizard/questions`, `POST /links/wizard/generate` (all sanctum). Non-numeric paths, so they sit safely alongside `/links/{id}` (whereNumber-guarded).
- `sanitizeAnswers` accepts image fields as a **URL string only** — the wizard `image` answer is a URL; the web editor (not the wizard) does uploads. Mobile wizard has an `uploadWizardImage` → `POST /links/wizard/image` helper that returns a URL to stamp in.
- Plan caps on the API are enforced inline as JSON 403 (`link_limit`/`biolink_limit`); do NOT reuse the web `CheckPlanLimit` middleware on API routes — it `back()`/redirects.

**Per-step validation + AI auto-draft (added later):**
- `BiolinkWizardQuestions::validateAnswers(cat,pt,industry,answers,?onlyKeys)` is the shared per-field required check. Web `save_basics`/`finish` → `back()->withErrors($errors,'wizard')->withInput()`; API `generate`/`aiGenerate` → 422 `validation_failed` with the key→message bag in `details`. Mobile maps that `details` bag into inline `fieldErrors` (TextField `error` prop / `fieldError` style).
- AI auto-draft is **optional & user-triggered**, reuses `AiBiolinkBuilderService` via shared `WizardAiDraftService::generate(...)` (grounds on AI Minds + vault files, charges credits w/ auto-refund, attaches resources to `settings['wizard_resources']`). Engine is OFF by default in dev → guarded by `AiEngineSettings::isEnabled()`; web hides the "Auto-draft with AI" button unless `$aiEnabled`, API returns 503 `ai_unavailable`.
- **There is NO general sanctum user-files list endpoint.** For the mobile vault-file picker + AI-brain multi-select, added `GET /links/wizard/resources` returning `{ai_enabled, my_minds, platform_minds, vault_files}` (mirrors the web controller index() loading). Reuse it; don't look for a `/files` API.
- Mobile AI-draft grounding selections (`selectedMinds`/`includePlatformMind`/`selectedFiles`) are in-memory only (stateless wizard); reset them on category/page-type change alongside `answers`.
