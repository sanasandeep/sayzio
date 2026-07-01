---
name: Onboarding — two mobile gates + single shared step model
description: The staged persona onboarding — its ONE step model and the two DISTINCT mobile onboarding gates that must not be conflated.
---

The first-run persona onboarding is staged (Welcome → Pick persona → Choose
template → Connect WhatsApp optional → Done) with a visible progress stepper.

- **One step model, many surfaces.** `OnboardingSteps` is the single ordered
  step list shared by the web onboarding page, the web WhatsApp step, and the
  mobile setup screen. It only includes the WhatsApp stage when it is actually
  pending, so "Step X of Y" never promises a stage the user will skip.
  **Why:** if any surface hardcodes its own order/count the progress indicator
  lies. **How to apply:** change the stage set in `OnboardingSteps`, never in an
  individual view.

- **Mobile has TWO separate onboarding gates that look alike — do not conflate.**
  1. Pre-auth **intro slides**, gated by a LOCAL secure flag. Independent of the
     server; untouched by the staged-onboarding work.
  2. Post-sign-in **staged setup** (`app/setup.tsx`), gated by the SERVER
     onboarding status (`onboarded_at === null`) in the launch gate, checked only
     when a user+token exist, and fail-open on error so a transient API failure
     never traps the user.
  **How to apply:** a request about "mobile onboarding" almost always means only
  ONE of these. Confirm which gate before editing.
