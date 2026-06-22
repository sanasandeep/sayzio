---
name: Mocking Google web OAuth in mobile e2e
description: How to drive the expo-auth-session Google success path on web with the OAuth round-trip fully mocked (no external browser/network).
---

# Mocking the Google (web) OAuth round-trip in the mobile auth e2e

The mobile login uses `expo-auth-session` `useIdTokenAuthRequest` (Google) +
`expo-web-browser` on web. To exercise the success path in an e2e without any
external browser/network, mock the round-trip with Playwright routes + a popup
shim. Verified against the installed library source.

**Key facts (why the mock works):**
- Google's discovery is HARDCODED in `expo-auth-session` (authorizationEndpoint
  `https://accounts.google.com/o/oauth2/v2/auth`), responseType `id_token`
  (implicit, no PKCE) — so NO discovery network fetch; intercept that one URL.
- The success handler reads `googleResponse.params.id_token`, calls
  `socialLogin({provider:"google", id_token})`, then `router.replace("/(tabs)")`.
  `applySession` is LOCAL-ONLY (no API call), so aborting all other `/api/**`
  calls does not block landing in tabs.
- `expo-web-browser` web completion contract (the part the popup must replicate):
  redirect page does `opener.postMessage({ url, expoSender: handle }, origin)`;
  `handle` = `localStorage['ExpoWebBrowserRedirectHandle']`; opener listener
  requires `event.origin === window.location.origin` and `data.expoSender ===
  handle`, then resolves `{type:'success', url}`.

**The recipe:**
1. `context.route("**/api/v1/auth/social")` → capture + fulfill
   `{data:{token,user}}`.
2. `context.route("**/api/**")` → abort everything EXCEPT social (registered
   AFTER social; Playwright runs handlers most-recently-added-first, so this
   sees the request first and `route.fallback()`s social through).
3. `context.route(/accounts\.google\.com\/o\/oauth2\/v2\/auth/)` → capture
   client_id/response_type/redirect_uri/state, fulfill HTML that
   `location.replace(redirect_uri + "#id_token=MOCK&state=STATE")`.
4. `page.on("popup")` → `popup.route("**/*")` serves a minimal SAME-ORIGIN
   shim on the redirect_uri document load that reads the handle from
   localStorage and posts `{url:location.href, expoSender:h}` to
   `window.opener`. This BYPASSES `maybeCompleteAuthSession` and its fragile
   `redirectUrl` exact-match check.

**Gotchas:**
- The shim MUST be served on the app origin (redirect_uri), NOT google's
  origin — the opener's listener enforces same-origin.
- Echo the original `state` back in the fragment or `parseReturnUrl` rejects it.
- `getBaseUrl()` on web returns `window.location.origin`, so the social POST is
  `http://localhost:PORT/api/v1/auth/social` — match it with `**/api/v1/auth/social`.

**Why:** the alternative (letting the redirect_uri boot the full app +
real `maybeCompleteAuthSession`) is slow and fragile on the redirectUrl match;
the shim is deterministic.

**How to apply:** when extending `scripts/test-auth-flow-e2e.mjs`
`runGoogleVariant` or any web OAuth-provider success test.
