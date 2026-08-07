---
name: App-layout select chevron vs background shorthand
description: Why in-app selects can render a criss-cross/zigzag tiled chevron and the guard preventing it.
---

# App-layout select chevron vs `background:` shorthand

The in-app layouts (`[data-app-layout]`) inject a chevron `background-image` into every `<select>` (appearance reset in `resources/css/app.css`).

**Rule:** component CSS must style select backgrounds with `background-color`, never the `background:` shorthand.

**Why:** a same/higher-specificity shorthand (e.g. a light-mode component rule) resets `background-repeat/position/size` while the higher-specificity chevron `background-image` declaration still wins — the chevron then TILES across the whole select as a criss-cross/zigzag artifact (seen on the Marketing Plan Calculator light mode).

**How to apply:** app.css now guards `[data-app-layout] select` with `background-repeat/position/size !important` (safe — no app select uses a custom background image), and the light-mode chevron rule re-asserts geometry. Still prefer `background-color` in component styles; if a select ever needs a custom background image, the `!important` guard must be revisited.
