---
name: Mascot WebM alpha vs Safari
description: Why the home mascot shows an opaque box in Safari and how the runtime fallback works
---

The Sayzio home mascot is a VP9-alpha WebM (`public/branding/sayzio-mascot.webm`, transparent still `sayzio-mascot-still.png`). It has REAL alpha — verify by compositing over a solid fill with `ffmpeg -c:v libvpx-vp9` (corner pixel resolves to the fill color = transparent). The container pix_fmt reads `yuv420p`, which is expected for VP9-alpha; ffprobe/native decoder cannot see the separate alpha layer.

**The bug:** Safari / iOS WebKit DECODE VP9 but IGNORE its alpha channel, so the keyed-out off-white background renders as an opaque box around the mascot. Chrome/Firefox honor alpha and render it transparent. There is no Safari-renderable transparent video without an HEVC-with-alpha MP4, which can only be produced with Apple videotoolbox (a Mac) — ffmpeg/libx265 in this Linux env cannot make it. Prior attempts to "animate for Safari" were cancelled waiting on that Mac-made file.

**The fix (runtime, no UA sniffing):** in `home/partials/hero.blade.php` a `<script>` draws a small top-left corner region (background) of the same-origin playing `<video>` to a canvas and reads the sampled pixel's alpha. If opaque (`alpha > 24`) the browser isn't honoring alpha → hide the `<video>`, show the matching transparent `*-fallback` still PNG. Same-origin ⇒ canvas not tainted; any canvas exception falls back to the still safely.

**The Safari motion fallback (animated transparent WebP):** the no-alpha branch now shows an animated transparent WebP (`public/branding/sayzio-mascot.webp`) instead of the static still, so Safari/iOS visitors see motion with no opaque box. Build it from the genuine-alpha WebM by decoding the alpha layer with the VP9 decoder and feeding frames to img2webp: `ffmpeg -c:v libvpx-vp9 -i sayzio-mascot.webm -vf "fps=12,scale=440:440:flags=lanczos" -an f_%03d.png` then `img2webp -loop 0 -d 83 -lossy -q 50 -m 4 -min_size f_*.png -o out.webp`. Gotchas: you MUST pass `-c:v libvpx-vp9` or ffmpeg's default decoder drops the alpha (opaque frames); `-min_size` + lower fps are the real size levers (quality barely moves it — alpha+motion dominate); `img2webp -m 6` is far too slow for the 120s tool cap (use -m 4); verify transparency survived by compositing a frame over a solid fill (`magick "out.webp[0]" -background '#0b1020' -flatten`) — corner pixel must equal the fill.

**Why these details matter / how to apply:**
- Cover BOTH home mascot clips: `.zio-mascot-video` (hero) and `.bs-mascot-video` (brand-sayzio section, included LOWER on the page). The guard must run after `DOMContentLoaded`, otherwise the later-included brand-sayzio video doesn't exist yet at the hero's parse time.
- Three sibling layers per mascot, swapped by class-prefix: `-video` (WebM), `-fallback` (static PNG, reduced-motion path), `-anim` (animated WebP, no-alpha path). The single shared guard lives in hero.blade.php and serves both mascots.
- The animated WebP `<img>` uses `data-src` (not `src`); the guard assigns `src` only when it actually shows it, so Chrome/Firefox (alpha honored) and reduced-motion users never download the ~1.4MB asset.
- `showStill()` branches on `prefers-reduced-motion`: reduced → static PNG, otherwise → animated WebP. `prefers-reduced-motion` still swaps video→static-PNG via CSS independently; keep that path.
