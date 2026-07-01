---
name: "AI Minds" → "Knowledge Bases" display rename
description: The user-facing rename is display-only; the AiMind wire tokens stay untouched.
---

# "Knowledge Bases" is the display label for the AiMind feature

The user-facing term across web (Laravel blades) + mobile (Expo) is **"Knowledge Bases"**, but the
underlying feature is still **AiMind** in code. The rename is **display-only**.

**Why:** a product copy change shouldn't churn stored data, routes, or plan/feature keys (same
principle as the brand-sweep protected-tokens rule).

**How to apply — NEVER rename these wire tokens when touching this feature:**
- Model `AiMind`, table `ai_minds`
- Route names `admin.ai-minds.*`
- Marketing-strategist source key `minds` (and `SELECTABLE_SOURCES` entry)
- Plan key `max_minds`
- AI feature keys (grounding etc.)

Only swap visible strings ("AI Minds"/"AI Mind") → "Knowledge Bases"/"Knowledge Base" in blade text,
mobile labels, and the upgrade-page `max_minds` label map. Code comments referencing the model
concept may stay. Grep guard: `rg "AI Mind"` then exclude `route(|routeIs(|admin.ai-minds`.
