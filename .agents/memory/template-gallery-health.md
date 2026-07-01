---
name: Template gallery health coverage
description: How TemplateGalleryHealth buckets onboarding template coverage by persona / plan tier and what drives the admin alert.
---
# Onboarding template gallery health

`TemplateGalleryHealth::compute()` reports both whole-catalog emptiness and per-persona coverage. It is consumed by the admin dashboard banner and the scheduled `templates:check-gallery` command (hourly), which alerts ops admins in-app + email and sends an all-clear on recovery.

- **A "hard gap" (drives `has_gaps`, the banner, alerts, and the all-clear) = a persona with ZERO active templates whose `recommended_personas` tag list contains it.** Whole-catalog-empty is just the extreme where every persona is uncovered (`empty=true`). Clears once every persona has ≥1 active recommended template.
- **`gated` is advisory only** — personas that HAVE recommended templates but none reachable at the entry-level (default) plan tier, so entry users see an all-locked recommended row. Deliberately does NOT open an episode: a paid-only persona can be an intentional product choice, so never block the all-clear on it.

**Why onboarding plan tier never makes the gallery blank:** `OnboardingController::renderTemplateGrid()` shows EVERY active template (paid ones rendered *locked*, not hidden) — it does not use `PageTemplate::scopeAvailableForPlan`. So the only real "empty" is zero-recommended per persona; the plan-tier signal is about all-locked recommendations, computed via `defaultPlan()` sort_order rank (mirrors scopeAvailableForPlan's rule).

**Command dedup:** state in `app_settings.template_gallery_health.*` (`alerting`, `last_sent_at`, `signature`). `signature` = sorted uncovered-persona slugs; a changed signature bypasses the 6h cooldown so a newly-bare persona re-alerts promptly. Recovery clears `alerting` + `signature`.

**Verifying locally:** boot a standalone bootstrap script (NOT tinker), inject a fake persona via `config(['personas.list' => ...])` to force an uncovered gap, then call compute()/run the command. It writes to the SHARED RDS — reset `AppSetting::put('template_gallery_health', [])` and delete test `UserNotification`s afterward.
