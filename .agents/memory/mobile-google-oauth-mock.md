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

## Variant: the 6 WEB_BROWSER web OAuth providers (instagram/facebook/twitter/linkedin/pinterest/tiktok)

These do NOT use `expo-auth-session`; they open the backend
`/user/social-oauth/{provider}/login` via `WebBrowser.openAuthSessionAsync`
(a popup on web), and the OS deep-link returns to `1inme://oauth-callback`,
which `oauth-callback.tsx` forwards to `socialLogin({provider, id_token})`.

- Mock with a CONTEXT-level `context.route("**/user/social-oauth/**")` (popups
  are separate pages, so `page.route` wouldn't see them). The handler records
  the opened URL (proves the right provider/`source=mobile`/`return=` query) and
  stands in for the backend + deep-link by navigating the OPENER to
  `/oauth-callback?provider=...&id_token=...`.
- **Critical:** redirect the opener to the ABSOLUTE `${new URL(APP_URL).origin}/oauth-callback`,
  NOT a relative URL. `getBaseUrl()` points OAuth at the proxy domain (via
  `EXPO_PUBLIC_DOMAIN`), but the app is only served from `APP_URL`/expo domain;
  a relative redirect strands the opener on a domain without the app → hang.
- Twitter's button label is "Continue with X" (provider id stays `twitter`).
- Between providers in the loop, clear `1inme.auth.token`/`user` on the CURRENT
  page BEFORE `goto APP_URL`: navigating with the previous provider's token
  still stored boots the app signed-in → it fires authenticated calls to the
  real (un-mocked) backend and hangs ~90s.

## 6-provider loop tail-stall — REAL root cause: APP_URL vs appBaseUrl divergence

The web-provider loop (`runWebProviderSuccessPath` + the `**/user/social-oauth/**`
popup route) navigated to the imported **`APP_URL`** constant, but the main flow
actually boots and *mocks* a throwaway server tracked in module-level
**`appBaseUrl`** (reassigned in `main()`). `APP_URL` (from check-icon-fonts.mjs)
falls back to `https://${REPLIT_EXPO_DEV_DOMAIN}/` when no `APP_URL` env is set —
exactly the `e2e` validation case. So every provider `page.goto(APP_URL)` hit the
**un-mocked proxy expo domain** → 90s nav hang. It looked like an intermittent
"tail" stall historically (and the FIRST provider stalls when the divergence is
total). **Fix:** use `appBaseUrl` (the server this run actually drives), never the
imported `APP_URL`, in both the loop `page.goto` and the popup redirect `dest`.
Native `/oauth-callback` tests never hit this because `gotoOAuthCallback` already
uses `appBaseUrl`. **Lesson:** in this script `APP_URL` = the *requested* URL
(env/default); `appBaseUrl` = the *driven* URL. Drive `appBaseUrl` everywhere.

Two secondary hygiene fixes shipped alongside (good practice, not the stall):
- catch-all `**/api/**` `route.fulfill`s a fast `{data: []}` 200 instead of
  aborting, so post-login tab GETs don't trigger React Query 3×-retry churn
  (`[]` is both array-iterable and property-accessible → never crashes a screen).
- `await popup.close()` after each provider sign-in (web loop + Google variant)
  so OAuth popups don't pile up as orphan windows.
Plus `withDeadline(label, ms, fn)` (`PROVIDER_DEADLINE_MS=75s`) around each
provider so any future stall fails fast with a provider-named message.

**Why local verification is awkward:** a clean throwaway run is the only
representative config; a *full* run (2 Metro boots + flow ≈ 150s) exceeds the
120s bash cap, and pointing `APP_URL` at a pre-booted server starves CPU during
Metro's first web-bundle build and false-fails early. Trust the non-reaped `e2e`
validation workflow for the full pass.
