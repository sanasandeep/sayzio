---
name: Android adaptive-icon foreground must be transparent + how to cut the mascot
description: Why the adaptive-icon foreground has to be a transparent mascot-only layer, and the reliable way to isolate the brand mascot from its busy starfield background.
---

# Android adaptive-icon foreground = transparent mascot only

Android masks adaptive icons per-launcher (circle / squircle / teardrop / rounded-square). The foreground layer (`android.adaptiveIcon.foregroundImage`) must be the subject on a TRANSPARENT background, sized inside the center ~66% safe zone, so the solid `adaptiveIcon.backgroundColor` composes cleanly under every mask. If the foreground bakes in its own rounded-square background, some masks clip that square and reveal a double border / cut corners.

**Why:** the 1inme-mobile foreground originally reused the full starfield app-store art (its own dark rounded square). Re-exported as a transparent mascot centered at ~64% of a 1024×1024 canvas; verified under circle/squircle/rounded-square + `expo prebuild --platform android --clean`.

## Cutting the brand mascot out of the starfield (tooling that failed vs worked)
The mascot and the purple/blue starfield share the same hue family, so:
- `remove_image_background_tool` FAILS — it treats the whole rounded-square icon as one "sticker" and keeps the starfield.
- Corner flood-fill (`-draw "color x,y floodfill"`) FAILS — dark corners can't cross the fuzz gap into the brighter nebula; only clears corners.
- WORKS: grayscale threshold → `-connected-components` keep-largest (drops floating stars) → flood-fill-exterior hole-fill to re-fill the enclosed dark EYES (else eyes become transparent holes) → feather → `CopyOpacity` onto the original color art → trim → resize to ~64% → center-extent to 1024.

## Verification gotcha
The `read` image tool rendered the transparent cutout as if the starfield were still there (misleading). Trust `magick ... -alpha extract -format "%[fx:mean]"` (mascot ≈ 0.2 opaque) and flatten over magenta/`#140a32` to confirm, not the inline preview.

**How to apply:** any RN/Expo Android icon work — foreground stays transparent + safe-zone sized; `android/` is gitignored (managed workflow) so prebuild output isn't committed; edit the source PNG under `artifacts/1inme-mobile/assets/images/`.
