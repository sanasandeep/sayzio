---
name: Absolute-positioned line confined to auto grid column
description: Why a decorative connector line rendered asymmetrically around a centered element, and the general fix pattern.
---

## Symptom

A horizontal "energy line" behind a centered pill/badge (e.g. the "1IN.ME is Sayzio" brand
section on the homepage) only appeared on one side, or looked uneven, even though the
markup used `left-0 right-0` on an absolutely-positioned element.

## Root cause

The line's positioned ancestor was a narrow, `auto`-width grid/flex column (sized to fit
just the pill). Sibling cards used margin utilities (`lg:mr-12` / `lg:ml-12`) to create the
visual gap between the cards and the center column — but those margins live *inside the
cards' own grid cells*, not inside the center column. So `left-0`/`right-0` on the line only
stretched across the tiny auto column, never across the actual visual gap created by the
margins.

## Fix pattern

Don't position the line relative to the narrow center column. Instead, make it a direct
child of the full-width row container (which already spans edge-to-edge) and position it
`absolute left-0 right-0` there, placed *before* the cards in DOM order so the opaque/glass
cards paint over it in their own footprint and it only shows through in the gaps on both
sides. Keep the centered element (pill) in its own small column/wrapper with a higher
z-index (or later DOM position) so it stays on top.

**Why:** absolute positioning resolves against the nearest positioned ancestor's content
box, not against "the visual gap the eye perceives" — box model gaps created by margins on
siblings are invisible to `left/right: 0` on a differently-scoped absolute element.

**How to apply:** any time a decorative line/divider must "read as continuous" behind or
between elements that have margin-based offsets, anchor the line to the outermost shared
container, not to the smallest wrapper around the centered element.
