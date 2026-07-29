---
name: Design-locked page templates
description: Fixed-block prefix + palette + detach/re-attach lifecycle for admin design-locked biolink templates.
---

Design-locked templates (PageTemplate.design_locked + color_palettes; blocks pinned via settings._fixed) enforce a **fixed-prefix invariant**: fixed root blocks stay a contiguous prefix in original order.

**Rule:** enforce the prefix in EVERY mutation sink, not just the obvious ones — web+API block update (strip/clamp sort_order & parent_id), delete, move, and reorder. Reorder guards must require the fixed IDs as an exact prefix of the submitted order even when the payload omits them (partial payloads renumber user blocks from 0 and slide above the pins).

**Why:** an architect review found three bypasses after the "complete" build: API update of a non-fixed block with sort_order=0, partial reorder payloads, and a stale `design_lock_released` stamp.

**Lifecycle:** detach stamps `settings.biolink.design_lock_released` {template_id, palette}; re-applying the SAME locked template routes to `TemplateService::reattachPageToLink` (non-destructive); any fresh replace-apply must clear the release stamp or a later apply of the old template mis-triggers reattach.

**How to apply:** when adding any new endpoint that writes biolink block position or applies templates, check `Link::isDesignLocked()` and the `_fixed` prefix; tests live in tests/Feature/DesignLockedTemplateTest.php.
