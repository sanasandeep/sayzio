---
name: Profile-card identity layouts on mobile
description: How the profile_card_v1..v4 identity designs render + are edited in the native mobile app
---

# Profile-card identity layouts (mobile)

The 10 web identity designs (Classic Creator, Glass, Cover Hero, Split, Floating,
Gradient, Founder, Minimal Dark, Magazine, Social Profile) render on mobile by
dispatching on the `_profile_layout` token carried in `settings._style`.

**Layout source of truth:** the token lives in `_style._profile_layout`, set when a
curated `profile_identity` design variant is applied. When absent (older blocks),
fall back by `block.type`: v2→cover_hero, v3→stats, v4→badges, default→classic_creator.
This mirrors the web blade `common/biolink-profile-card.blade.php`, which is the
canonical reference for per-layout structure + accent colours.

**Why a bespoke editor section, not generic fields:** profile_card has no `blockKind`
(returns null), so the generic field renderer shows nothing. The text fields
(name/title/bio/avatar/cover/location/website/cta_label/cta_url) ride in the generic
string `values` map and auto-save via the settings spread; but `verified` (bool) and
`socials` (array) need their own state buckets + explicit merge into `nextSettings`.

**Feather vs FontAwesome:** mobile only ships `@expo/vector-icons` Feather, which has a
much smaller brand set than the web's FontAwesome. Map only instagram/twitter(+x)/
facebook/youtube/linkedin/github; everything else (tiktok, twitch, …) falls back to the
generic `link` glyph. Don't assume a Feather brand icon exists for a platform.

**RN gradient caveat:** RN can't take a CSS `linear-gradient(...)` string as
`backgroundColor`. Gradient/social_profile/floating fallback covers and the gradient
layout background must use `expo-linear-gradient` `<LinearGradient>`, branching on
whether the design supplied a flat `bg_color`.
