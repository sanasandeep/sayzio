---
name: Font Awesome is the Free build, not Pro
description: A Pro-only icon name renders as blank space with no error. Check the bundled sheet before using an icon class.
---

# Font Awesome here is Free 6.5.1

`artifacts/1inme/public/css/vendor/fontawesome-free-6.5.1/css/all.min.css`
— about 2,468 glyphs. Any Pro-only name renders **nothing**: no error, no
fallback, no console warning, just an empty box where the icon should be.
It survives code review easily, because the markup looks correct and the
page renders fine.

**It has happened.** `fa-calendar-star` shipped in the sidebar's My Events
item and left a blank beside the label. Eight other Pro names were already
in the user views when that was found — `fa-badge-check`, `fa-sparkles`,
`fa-shield-check`, `fa-shield-exclamation`, `fa-shield-xmark`, `fa-grid-2`,
`fa-user-heart`, `fa-arrow-down-to-bracket` — each of them a blank space in
production right now.

**How to check before you use one:**

```
grep -c '\.fa-calendar-star:before' \
  artifacts/1inme/public/css/vendor/fontawesome-free-*/css/all.min.css
```

Zero means it does not exist here. Picking a name that is already used
elsewhere in the views is the quickest safe option.

**The guard:** `tests/Feature/EveryIconWeAskForActuallyExistsTest.php`
walks every icon class in `resources/views/user` and fails on any name the
bundled sheet cannot draw. The eight above are listed in its
`KNOWN_MISSING` baseline so the count can only go down — fix one, remove it
from the list. Do not add to that list to make a build pass.

Common Pro names and their Free equivalents: `calendar-star` →
`calendar-check`, `badge-check` → `circle-check`, `sparkles` → `wand-magic-sparkles`,
`shield-check` → `shield-halved`, `grid-2` → `table-cells-large`.
