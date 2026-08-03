---
name: Homepage deferred below-the-fold sections
description: How the marketing homepage lazy-loads everything after the hero via /home/sections, and the lockstep pieces that keep it working.
---

# Homepage deferred sections

The marketing `/` initial response is header + hero + CTA only. Everything after
the hero renders via `GET /home/sections` (`home.deferred-sections` view) and is
injected into `#home-deferred` by an inline loader (fires on window load +50ms,
first interaction, or a 3s failsafe — whichever first).

**Why:** initial HTML dropped ~1.4MB→580KB and index() needs zero plan/link-type
queries; logged-in visitors share the anon per-currency cached payload (only a
per-user tax overlay, `HomePageCache::applyTaxOverlay`, is computed on top).

**How to apply / gotchas:**
- Injected `<script>` tags must be RE-CREATED (innerHTML never executes them);
  the loader does this, then calls `window.homeEnhance(box)` and
  `window.marketingAnimScan(box)` — both idempotent via dataset stamps. New
  section JS must tolerate running post-load (no DOMContentLoaded waits).
- `marketing-anim.js` exposes `window.marketingAnimScan(root)`; keep the
  auto-run + the 2200ms reveal failsafe when editing it.
- Feature tests asserting section content must GET `route('home.sections')`,
  not `/`.
- Home e2e specs pass because injection happens right after load; specs that
  need a section immediately should still auto-wait via expect polling.
- Mascot WebM src lives in `data-src` (attached lazily); hero zio-node PNGs and
  the mascot poster were resized in place (128px / 440px) keeping filenames.
- Chromium treats ports 5060/5061 as unsafe (SIP) — never use them as
  VALIDATION_PORT for Playwright runs (net::ERR_UNSAFE_PORT).
- The auth modal renders TWO register forms (email + WhatsApp) each with
  `desired_handle`/`Claiming` banner — scope e2e locators to
  `form[action*="register"]` .first(), and fill password fields when password
  signup is enabled or HTML5 validation silently blocks the submit.
