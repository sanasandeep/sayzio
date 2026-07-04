---
name: Dashboard widget catalog mobile parity
description: How "Customize dashboard" (presets + AI designer) parity was handled on mobile when the mobile home tab renders different widgets than the web bento dashboard.
---

The web `/user/dashboard` bento grid (11 widgets, curated presets, AI designer)
has no 1:1 mobile equivalent — the mobile `(tabs)/index.tsx` home screen is a
simplified fixed layout (stat tiles + top link + recent links) built long
before the widget catalog existed.

**Decision:** mobile parity = a dedicated settings-hub screen
(`app/dashboard-customize.tsx`, linked from the Settings list in
`(tabs)/profile.tsx`) that lets the user manage the same server-side
preference (apply a preset / run the AI designer) via the shared REST
endpoints, without reshaping the mobile home tab itself to render the widget
catalog.

**Why:** the task's mobile requirement was "REST parity + mobile UI updates,"
not "mobile home must visually reflect the widget layout." Retrofitting the
mobile home tab to the same widget catalog would have been a much larger,
out-of-scope UI rewrite, and the preference itself is still fully manageable
and effective (it drives the web dashboard) from the new mobile screen.

**How to apply:** when a feature's data model is web-first and the mobile
home surface predates it, prefer adding a settings-surface control screen
over reshaping mobile home rendering, unless the task explicitly asks for
visual mobile-home parity.
