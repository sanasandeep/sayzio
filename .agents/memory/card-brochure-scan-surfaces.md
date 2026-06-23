---
name: Card/brochure scan surfaces
description: How the web and mobile "scan a business card/brochure" features share one extraction service and hand off to the biolink wizard.
---

# Card/brochure scan surfaces

The "scan a business card/brochure → AI extract → save contact and/or seed
biolink" feature exists on both web (User module controller) and mobile (a
Sanctum REST surface under `/api/v1/card-scans`). **Both controllers delegate
the heavy lifting to `App\Services\AI\CardBrochureExtractionService`** — do not
reimplement extraction, limits, or the credit flow in a controller.

**Why:** the service is the single source of truth for the upload limits
(`MAX_UPLOADS=6`, `MAX_UPLOAD_MB=10`, `MAX_PDF_PAGES=4`), the AI-credit
affordability gate + auto-refund on parse failure, and the "AI scanning
disabled" state. Splitting that logic across web/mobile guarantees drift.

**How to apply:**
- New scan behavior (new fields, new limits, credit changes) goes in the
  service, not in a controller, so both surfaces inherit it.
- The mobile API path is Sanctum and does NOT run `SetActiveWorkspace`, so the
  controller must stamp `workspace_id` itself on the saved `Contact` /
  `BiolinkWizardDraft` (see the `api-workspace-scope` memory).
- Error mapping on the mobile API: `ai_unavailable` → 503,
  `insufficient_credits` → 402 (include required + balance),
  `invalid_upload` → 422, contacts cap → 403 plan gate.
- **Biolink handoff is param-based, not draft-based on mobile.** The mobile
  wizard is stateless (no DB drafts), so the review screen passes
  `prefillCategory` + `prefillAnswers` (JSON) as route params to
  `/links/wizard`, which seeds category + answers once. Web instead persists a
  `BiolinkWizardDraft`.
