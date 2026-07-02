---
name: Home page section-id contract
description: Adding/removing a marketing home section requires updating the e2e structural guard in lockstep
---

# Home page section-id contract

The Laravel marketing home page (`artifacts/1inme/resources/views/home.blade.php`,
root `/`) has a **contractual list of section ids** enforced by an e2e spec:
`artifacts/1inme/tests/Browser/home-section-structure.spec.ts` (`REQUIRED_SECTION_IDS`),
documented in `artifacts/1inme/tests/Browser/README.md`.

The spec renders `/` and asserts every id in `REQUIRED_SECTION_IDS` appears
**exactly once**. So removing a section (e.g. `#everything`/`#stats`/`#trust`) or
adding one is a lockstep change across THREE surfaces:
1. `home.blade.php` (the markup)
2. `REQUIRED_SECTION_IDS` in `home-section-structure.spec.ts`
3. the id list in `README.md`

**Why:** Blade has no compile-time check on ids; the spec is the only guard that a
nav anchor / jump link / deep link target didn't silently vanish or duplicate.
Forgetting step 2 makes the e2e gate fail with "missing required section id(s)".

**How to apply:** whenever you add/remove/rename a home page section id, update the
spec's list and the README in the same change. Note some ids (`#ai-suite`,
`#ai-marketing-strategist`, `#whatsapp-agent`, `#ai-hero-h`) live in `@include`d AI
partials, not inline in home.blade.php — the spec renders the full page so they
resolve in the DOM even though they won't grep in home.blade.php itself.
