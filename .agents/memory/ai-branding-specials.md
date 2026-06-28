---
name: AI Branding Specials (#2664)
description: Brand Consistency Score + On-Brand AI injection patterns atop the AI Brand Kit.
---

## Brand Consistency Score
- `BrandConsistencyService::audit(BrandKit, Collection<Link>)` is a PURE, DB-free transformer. It compares each biolink's stored `settings['biolink']` appearance against the exact values `AiBrandKitService::applyToBiolink()` would write, so a page that had the kit applied scores 100. This is why it works (and tests) with the AI engine OFF.
- Four checked dimensions mirror applyToBiolink: button_color←palette.primary, font_family←fonts.body, font_color←darkest neutral, block_theme←blockTheme key (stored as array with `_template`). A dimension the kit doesn't define is skipped (never a phantom finding).
- The one-click "apply fix" reuses the existing `user.brand-kits.apply.biolink` route — no new apply path.
- **Why:** keeping the audit a deterministic mirror of the apply logic means the only way to "fix" a finding is to apply the kit, and re-auditing returns 100 — no drift between what we flag and what we fix.

## On-Brand AI Everywhere
- `BrandKit::defaultFor(userId)` = default kit (orderByDesc is_default, then id). `BrandKit::promptDirectives(bool $includeColors)` builds the directive block; colors ON for the page builder, OFF for chat (color is moot in replies).
- Builder: `AiBiolinkBuilderService::buildMessages/estimateCredits/generate` take a TRAILING `string $brandDirectives=''` (generate's is after `$replaceBlocks`). Trailing-param so positional callers (WizardAiDraftService) stay safe.
- Companion: `PersonaRuntime::buildSystemPrompt` appends directives when `$persona->use_brand_kit !== false` (null/legacy = on) AND owner passes the gate.
- Plan gate: `AiPlanAccess::featureAllowed($user,'brand_consistency')` — added as an explicit legacy-safe default-TRUE in `legacyAvailabilityFallback`, so it unlocks for everyone until a plan carries the key.
- Opt-out: biolink builder sends `use_brand_kit` (default true) in the intake POST; persona has a `use_brand_kit` boolean column (migration default true) with a checkbox on the edit form.
