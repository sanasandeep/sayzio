---
name: Paid Page link type
description: How the paid_page link type repackages the creator monetized feed.
---

Paid Page (`links.type='paid_page'`) is a first-class, standalone link type — NOT
in the biolink family. It repackages the creator's existing monetized feed (posts,
tiers, PPV, tipping, comments, branded reactions, PostAccessPolicy blur) behind a
bold per-link visual template. It does NOT rebuild any monetization.

**Storage:** per-link config lives in `links.settings['paid_page']['template']`
(an id from `PaidPageTemplates`). The page-level public/gated toggle reuses the
native `links.visibility` column: `public` = anyone, `registered` = must sign in
(enforced by RedirectController::enforceVisibility, which now allows paid_page).

**Public render:** RedirectController::handlePaidPage() mirrors
CreatorProfilePublicController::show() data load (AgeGate/CountryGate/ViewerSession,
but NOT profile_published since the paid page is its own link). It reuses the
extracted `buildFeedViewData()` and shared partials (creator-feed-scripts,
creator-dm-modal, creator-tip-modal, creator-post-card). Template tokens are CSS
gradients/colors/radius/font; motion layers respect prefers-reduced-motion.

**Why this shape:** posts & tiers are per-creator (shared across every paid page
that creator publishes), so the editor links out to the existing dashboards rather
than rebuilding them. Multiple paid pages can theme the same underlying feed.

**Gotcha:** the GET catch-all `/{alias}` (routes/web.php) rejects aliases starting
with reserved single-letter tokens like `p`/`u`/`c`/`m`/`f` (negative lookahead for
`/p/` post routes etc.) while the POST `/{alias}` has a looser constraint — so a
test alias starting with "p" returns 405 on GET but POST matches. Not a bug; pick a
non-reserved alias when smoke-testing.

**Mobile:** lib/linkKinds.ts adds paid_page (creatable); the API
LinkController::store allows paid_page and seeds a default template into
settings['paid_page']. CreatorPost uses `post_type` (not `type`) and has no
top-level type column.
