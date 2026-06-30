---
name: Mascot WebM alpha vs Safari
description: Why the home mascot shows an opaque box in Safari and how the runtime fallback works
---

The Sayzio home mascot is a VP9-alpha WebM (`public/branding/sayzio-mascot.webm`, transparent still `sayzio-mascot-still.png`). It has REAL alpha — verify by compositing over a solid fill with `ffmpeg -c:v libvpx-vp9` (corner pixel resolves to the fill color = transparent). The container pix_fmt reads `yuv420p`, which is expected for VP9-alpha; ffprobe/native decoder cannot see the separate alpha layer.

**The bug:** Safari / iOS WebKit DECODE VP9 but IGNORE its alpha channel, so the keyed-out off-white background renders as an opaque box around the mascot. Chrome/Firefox honor alpha and render it transparent. There is no Safari-renderable transparent video without an HEVC-with-alpha MP4, which can only be produced with Apple videotoolbox (a Mac) — ffmpeg/libx265 in this Linux env cannot make it. Prior attempts to "animate for Safari" were cancelled waiting on that Mac-made file.

**The fix (runtime, no UA sniffing):** in `home/partials/hero.blade.php` a `<script>` draws a small top-left corner region (background) of the same-origin playing `<video>` to a canvas and reads the sampled pixel's alpha. If opaque (`alpha > 24`) the browser isn't honoring alpha → hide the `<video>`, show the matching transparent `*-fallback` still PNG. Same-origin ⇒ canvas not tainted; any canvas exception falls back to the still safely.

**Why these details matter / how to apply:**
- Cover BOTH home mascot clips: `.zio-mascot-video` (hero) and `.bs-mascot-video` (brand-sayzio section, included LOWER on the page). The guard must run after `DOMContentLoaded`, otherwise the later-included brand-sayzio video doesn't exist yet at the hero's parse time.
- Each video's still is its sibling with `-video` → `-fallback` class swap.
- `prefers-reduced-motion` already swaps video→still via CSS independently; keep that path.
