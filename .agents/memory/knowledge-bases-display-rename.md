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

# "AI " prefix on the nine customer-facing AI tool names

A later copy pass prefixed **"AI "** onto the nine AI tool NAMES shown to customers: AI Knowledge
Bases, AI Persona Generator / AI Persona(s), AI Companion(s), AI Coach (was Ask Coach / Account
Assistant), AI Marketing Strategist, AI Voice Assistant, AI Inbox Agent, AI Brand Kit, AI Resume.
Display-only — same wire-token rules as above.

**How to apply (scope boundaries that keep it surgical):**
- Prefix the tool NAME in titles / nav (both app.blade.php menus) / headers / kickers / empty-states /
  buttons / marketing copy / named-tool prose (e.g. "your saved AI Brand Kit").
- Do NOT double-prefix. Kickers already in the form `AI · X` stay as-is (they already read as AI).
- KEEP as-is (not AI tools or not in scope): "AI Chat", "AI Agents", "Chat Widgets"; **admin/back-office
  surfaces** (admin ai-minds / ask-coach / ai-engine / site-assistant sidebar+labels still say the bare
  names — deliberately out of the customer-facing scope); count-badge text (`{{count}} Knowledge Base`);
  link-TYPE labels ("Resume / Portfolio" as a link type in SitePagesContent, "Brand / Press Kit"); the
  disabled-feature MAP KEYS (they're lookup tokens; only the values get AI); code comments.
- Mobile has no "Marketing Strategist"/"Inbox Agent" display strings (strategist surfaces as
  "Performer Specialist" — left untouched).
- Verify blades by compiling with a standalone `BladeCompiler` (no DB); the container-dependent
  `Target [Illuminate\Contracts\View\Factory] is not instantiable` throw and false `unexpected "else"`
  lints are compiler-env artifacts on component/control-flow blades, not real errors.
