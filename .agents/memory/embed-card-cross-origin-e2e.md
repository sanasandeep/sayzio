---
name: Embed-card cross-origin e2e framing
description: How to frame the app's embed endpoints from a fake third-party page in Playwright without Chromium blocking the iframe.
---

# Embed-card cross-origin e2e framing

Rule: to e2e-test the public embed card (`/embed/link/{alias}/card`) inside a
"third-party" host page, serve the host page from a route-fulfilled **http
origin** (e.g. `http://third-party.example/...` via `page.route`) and launch
Chromium with
`--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessSendPreflights,PrivateNetworkAccessRespectPreflightResults`
(via `test.use({ launchOptions })` in the spec).

**Why:** two separate Chromium blocks otherwise kill the iframe with an empty
`chrome-error://chromewebdata/` frame and no obvious error in the test output:
1. `page.setContent()` hosts run on an opaque `about:blank` origin, and the
   card's CSP `frame-ancestors *` does NOT match opaque origins →
   `ERR_BLOCKED_BY_RESPONSE`.
2. A (fake) public-origin document framing a `localhost` app trips Local
   Network Access checks → `ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS`; even a
   route-fulfilled `127.0.0.1` host origin is treated as public, so only the
   feature flag helps. This can't happen in prod (both sides public).

**How to apply:** see `tests/Browser/embed-card-state-heights.spec.ts` for the
working pattern (routed host page + fixed-height iframe + measuring inside the
frame). The card's static snippet heights (148/164) come from
`Link::embedCardIframeHeight()` and depend only on the subtitle source, never
on gated/unavailable state.
