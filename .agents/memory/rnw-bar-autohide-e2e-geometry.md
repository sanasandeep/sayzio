---
name: RN-web bar autohide e2e geometry
description: How to drive and assert the shared top-bar/tab-bar autohide (TabBarContext) in an expo-web Playwright harness
---

**Rule:** assert the autohide bars via bounding-rect DELTAS from a visible baseline, and drive the scroll on the FlatList/ScrollView element that OWNS the `onScroll → reportScroll` wiring.

**Why:** three traps found while building the topbar-autohide harness:
- RNW renders `accessibilityRole="tablist"` on a zero-height layout wrapper — its rect top reads 0 and never moves. Measure a real `[role="tab"]` (height > 10) instead; for the top bar measure the "Open menu" chip.
- A "tallest scrollable div" heuristic grabs an unrelated wrapper whose scrolling never reaches the screen's onScroll; walk UP from a rendered list row to its nearest scrollable ancestor instead.
- Reading the reanimated wrapper's computed transform is fragile (which ancestor carries it isn't obvious); rects reflect the translateY 1:1 and are what the user sees anyway.

**How to apply:** see `artifacts/1inme-mobile/scripts/test-topbar-autohide-e2e.mjs` — mock-API signed-in Links tab with ~40 mocked rows for scroll range; capture the visible baseline, then hidden = chip moved ≥50px up AND tab moved ≥100px down. Keep the stable-landing + guaranteed down-nudge pattern (reportScroll ignores |delta| ≤ 6; offsets < 40 always show). The virtualized list's scroll range starts small (~450px), so use a modest scroll target.
