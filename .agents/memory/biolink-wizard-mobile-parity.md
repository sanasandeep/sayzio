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
- `sanitizeAnswers` accepts image fields as a **URL string only** — mobile has no upload step in the wizard (uses the recipe's themed placeholder); uploads stay web-only.
- Plan caps on the API are enforced inline as JSON 403 (`link_limit`/`biolink_limit`); do NOT reuse the web `CheckPlanLimit` middleware on API routes — it `back()`/redirects.
