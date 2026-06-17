---
name: Paid Page mobile render
description: How the mobile app renders a standalone Paid Page (links.type=paid_page) with its themed design.
---

Paid Pages are a standalone `links.type='paid_page'` and are deliberately NOT in `Link::BIOLINK_FAMILY`, so the biolink resolve API (`BiolinkController::show`, filtered to BIOLINK_FAMILY) will never return them. Mobile therefore needs its own alias-keyed surface.

**Backend:** `CreatorProfileApiController::paidPageShow($alias)` + `paidPageFeed($alias)` resolve the link via `Link::resolveByAlias`, assert `type===TYPE_PAID_PAGE`, and enforce the page-level visibility column (public/registered/followers/subscribers) via `enforcePaidPageVisibility`. The show response carries the creator `handle`; the feed reuses the existing handle-keyed `react`/`comment`/`comments` endpoints (those don't require profile_published). `PaidPageTemplates::mobileTokens()` decomposes the web template's CSS gradients into ordered colour stops (for expo-linear-gradient) and converts `rem` radius to px — the app can't parse CSS gradients/rem.

**Note:** `links` has no `description` column (it lives on `file_links`); use `seo_description` for a paid-page subtitle.

**Frontend:** shared feed UI lives in `components/CreatorFeed.tsx` (PostCard/PostBody/CommentsThread/CommentRow) with an optional `FeedTheme` prop and a required `feedQueryKey` prop (the optimistic react update + comment invalidation key the screen owns). The standard profile screen passes `["creator-profile-feed", handle]`; the paid-page screen (`app/paid-page/[alias].tsx`) passes `["paid-page-feed", alias]` and a theme built from the template tokens. Owner can open it from the link edit screen's "View page" tile.

**Why:** keeps one source of truth for the post card across both surfaces and lets the bold per-link template colours drive every surface (page/hero gradients, cards, reactions) to match `public/paid-page.blade.php`.
