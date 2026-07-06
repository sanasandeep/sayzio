# Browser tests (Playwright)

End-to-end browser tests for the 1inme web artifact. These complement the
PHPUnit feature suite under `tests/Feature/` by validating runtime
JavaScript behavior (keyboard nav, touch gestures, network pings) that
the controller-level tests can't observe.

## Running

The Laravel app must be reachable at `APP_URL` (defaults to
`http://localhost:80`, the workspace's path-based proxy). Migrations
must be applied so the seed step in each spec can write fixtures.

```sh
# from artifacts/1inme/
pnpm install
pnpm exec playwright install chromium
pnpm test:e2e
```

Each spec is self-bootstrapping: it shells out to `php artisan tinker`
to seed the rows it needs (idempotent — re-running is a no-op once the
fixture exists), then drives a real browser against the public alias.

## Validation step (runs on every change)

So a runtime regression (a CSS/Alpine break in the home-page mobile
sign-in popup, a shape-aware card-gallery preview rendering blank tiles,
a broken block-editor drag-and-drop, or a cookie-consent layout
reserving a phantom footer band) gets caught automatically instead of
only when someone remembers to run `pnpm test:e2e`, the gated specs are
wired into a named validation step called `e2e`. It runs the wrapper
`tests/Browser/run-validation.sh` (also exposed as the `test:e2e:ci`
package script) scoped to the reliable broader subset below, so a
failing assertion in any of them blocks the change instead of passing
silently.

The registered validation command is:

```
bash artifacts/1inme/tests/Browser/run-validation.sh \
  home-auth-modal-mobile.spec.ts \
  biolink-editor-card-gallery-preview.spec.ts \
  biolink-editor-palette-dnd.spec.ts \
  cookie-consent-footer-gap.spec.ts \
  cookie-consent-layout-styles.spec.ts \
  cookie-consent-theme-match.spec.ts \
  voice-assistant-bridge.spec.ts \
  voice-assistant-panel.spec.ts \
  slides-mode.spec.ts \
  cookie-consent-footer-reserve.spec.ts \
  home-hero-orbit-popover.spec.ts \
  home-hero-claim-handle.spec.ts \
  brand-consistency-apply-fix.spec.ts \
  create-link-picker.spec.ts \
  marketing-background-seam.spec.ts \
  home-section-structure.spec.ts \
  home-ai-zone.spec.ts \
  onboarding-flow.spec.ts \
  dashboard-layout.spec.ts \
  header-account-menu.spec.ts \
  header-mobile-account-menu.spec.ts \
  header-mobile-logged-out-cta.spec.ts \
  dashboard-mobile-account.spec.ts \
  header-desktop-logged-out-cta.spec.ts \
  dialer-live-sync.spec.ts \
  store-coming-soon-modal.spec.ts \
  ai-dashboard-demo-perf.spec.ts
```

The wrapper handles its own prerequisites:

- Installs the Playwright chromium browser (idempotent — a no-op once
  cached under `.cache/ms-playwright`).
- Ensures the app is reachable: if `APP_URL` is already serving (the dev
  workflow up on `localhost:80`), it tests against that; otherwise it
  boots an ephemeral `php artisan serve` on port 5000 (probing the
  lightweight `/up` route, since the home page is slow to render), waits
  for it, runs the spec(s), and tears the server down on exit.
- **Warms the shared app server before Playwright starts** (see below).

It does **not** run database migrations — the schema must already exist
on the (distant) RDS before any per-spec seeds can write fixtures.

### Warm-server strategy (why the whole suite is now feasible)

The dominant cost is the *first* render of each route: over a distant RDS
a cold home render is ~30s and a cold biolink-editor render ~20s, while a
warm one is ~2-3s. That first hit primes the file-backed config/AppSetting
caches (shared across all php-cli workers) and the per-worker opcache; the
editor's first paint also compiles the heavy block-editor Blade view.

Booting **one** ephemeral server for the whole Playwright run (specs share
it) and then **warming it once up front** — instead of paying a cold
render inside every spec's tight navigation budget — is what turns the
full suite from "too slow/flaky to gate" into a bounded ~5-8 min run. The
`warm()` step in `run-validation.sh`:

1. GETs the public routes the specs use (`/`, `/pricing`, `/contact`,
   `/user/login`).
2. Logs in as the demo user via the real CSRF-protected `demo-login` form
   and GETs `/user/links/1/blocks`, so the heavy authenticated editor
   render is compiled here rather than mid-spec.

All warm-up steps are best-effort: a failure (e.g. no demo link, CSRF
drift, or an already-warm `localhost:80`) is logged and never fails the
run — the specs themselves are the real assertions.

Because the server is pre-warmed, `playwright.config.ts` budgets are tuned
down from the old cold-render-per-spec headroom (per-test `60s`,
`navigationTimeout 45s`) while still leaving slack for a cold php-cli
worker the warm-up loop didn't reach. `retries: 1` absorbs the rare flake
where a heavy editor spec loses a CPU race (php server + node + Chromium
all share this box) and misses a client-side wait by a hair — a real,
consistently-failing regression still fails both attempts. The
card-gallery and palette-dnd describe blocks additionally keep their own
generous per-test ceilings and explicit per-call timeouts on the slow
editor-open / store round-trips.

### Why these twenty-six specs are gated (and not the whole suite)

The gate covers the specs that run reliably as an unattended check here:

- `home-auth-modal-mobile.spec.ts` — needs no seeding; visits `/` with a
  consent cookie and drives the mobile sign-in popup.
- `biolink-editor-card-gallery-preview.spec.ts` — self-bootstrapping: it
  seeds one no-thumbnail card template per shape family via `php artisan
  tinker`, logs in as the demo user, and asserts the shape-aware gallery
  preview draws a real mock for every tile (not a blank fallback).
- `biolink-editor-palette-dnd.spec.ts` — self-bootstrapping: it seeds a
  biolink (with a Card Container child) via `php artisan tinker`, logs in
  as the demo user, and drives the real palette-drop pipeline through the
  `window.__editorTest` hook (armed only when `window.__E2E__` is set),
  asserting each drop inserts a block in place (no reload). Gating it
  catches regressions in the editor render or the drop/persist flow.
- `cookie-consent-footer-gap.spec.ts` — pins the "no phantom band below
  the footer" invariant for the default banner layout. No login/seeding.
- `cookie-consent-layout-styles.spec.ts` — extends the footer-reserve
  guard across the corner / pill / modal / takeover consent layouts. No
  login/seeding.
- `cookie-consent-theme-match.spec.ts` — pins the consent banner's `auto`
  theme to the site's own light/dark mode (not the OS): asserts the banner
  is light on a light page and dark on a dark page (the `.cc-is-dark` class
  on `.cc-host` + the card colour), that toggling the site switch flips it
  live, and that an explicit admin `light`/`dark` theme ignores the page
  mode. No login/seeding (rewrites the `/pricing` consent config blob).
- `voice-assistant-bridge.spec.ts` — self-bootstrapping: it turns on the
  Voice Assistant via `php artisan tinker` (no API key — the turn endpoint
  is mocked in the browser), logs in as the demo user, and drives the real
  floating widget's `sendTurn()` against a mocked STT/LLM/TTS response. It
  asserts the `client_action` / `voice-action` bridge reaches each surface:
  `select_link_type` picks the type and submits the Create form,
  `wizard_advance` clicks the wizard's forward submit, and a `navigate_to`
  is deferred until the spoken reply's audio ends (yet fires immediately
  when there is no audio). It also locks down the destructive-confirmation
  path — a spoken `delete_biolink` only POSTs a gating turn (no action) until
  the user taps **Yes**, which replays the clip with
  `confirmed_tools[delete_biolink]=true` — and a read-only `search_app` tool
  reaching its surface (the header search box fills with the spoken query and
  navigates to the results). All tests share one logged-in context (the
  `demo-login` route is rate-limited).
- `voice-assistant-panel.spec.ts` — self-bootstrapping: guards the NEW in-panel
  voice runtime (the plain-JS port living in the Zio chat-panel composer), not the
  standalone Alpine floating mic that `voice-assistant-bridge.spec.ts` covers. It
  turns Voice on via `php artisan tinker` (no API key — the `/user/ai/voice/*`
  endpoints and the chat bootstrap/session are mocked in the browser; getUserMedia
  + MediaRecorder are stubbed so the real mic→stop→`sendTurn` pipeline runs against
  a deterministic blob), logs in as the demo user, and asserts: the composer shows a
  mic that opens the panel and starts recording; a turn round-trips transcript +
  reply into the chat body with the per-turn credit meter; a destructive
  `delete_biolink` is queued behind a confirmation chip and only runs (replaying the
  clip with `confirmed_tools[delete_biolink]=true`) after **Yes**; "What I can do"
  loads the capabilities pane; a `navigate_to` with spoken audio is deferred until
  the reply's audio ends; and a read-only `client_action` dispatches the
  `voice-action` surface-bridge event. Separate describe blocks then assert a
  plan-gated user gets a lock-badged mic routing to the voice gate, and an anonymous
  marketing visitor sees the launcher but no mic. The available/gated describes each
  share one logged-in context (the `demo-login` route is rate-limited).
- `slides-mode.spec.ts` — self-bootstrapping: it seeds a published 2-slide
  biolink via `php artisan tinker` and asserts both slides render, advance on
  keyboard + swipe, and ping the inline tracker. Its seed used to trip a
  psysh / PHP 8.4 `ParseErrorException` because the tinker payload was written
  with `\\$var` (which yields invalid `\$var` PHP); it now uses the same plain
  `$var` convention as the other tinker-seeded specs, so the parse error is
  gone and it runs reliably.
- `cookie-consent-footer-reserve.spec.ts` — pins the footer-reserve invariant
  on `/contact` for a first-time visitor, after the in-place "Customize"
  expansion, and for a returning visitor (no banner, no reserve). The
  "Customize" test's reserve-settle wait now compares the body padding against
  the *capped* target the page actually writes (`min(ceil(h), innerHeight*0.5)`)
  instead of raw `ceil(h)`, so it converges reliably when the expanded prompt
  sits near the half-viewport cap. No login/seeding.
- `home-hero-orbit-popover.spec.ts` — pins the home hero's orbital tool-node
  popover contract. No login/seeding: it visits `/` with a consent cookie under
  `prefers-reduced-motion` (so the spinning orbit is frozen and the nodes are
  statically clickable) and asserts that clicking a node opens its popover (with
  the right title + description), clicking another node switches (the first
  closes), re-clicking the open node closes it, and Escape / an outside click
  both close it — with exactly one popover open at any time.
- `home-hero-claim-handle.spec.ts` — pins the home hero's "claim your link" →
  in-modal signup handoff. No login/seeding: it visits `/` with a consent
  cookie, types a handle into the hero pill, submits it, and asserts the
  register modal opens on the register tab with that handle in its hidden
  `desired_handle` field + the "Claiming @handle" banner. It also covers handle
  normalization (lowercase / leading-`@` strip / trim), the empty-claim path
  (modal opens, empty field, no banner), and that the handle is actually placed
  on the wire by intercepting the register POST and reading back
  `desired_handle` from the body. This is the browser-side chain (hero JS →
  `open-auth` event → header Alpine → modal binding) the controller-level
  feature test cannot reach.
- `brand-consistency-apply-fix.spec.ts` — self-bootstrapping: it seeds a default
  Brand Kit and a deliberately off-brand biolink (bringing every other biolink in
  the workspace on-brand first) via `php artisan tinker`, logs in as the demo
  user, and on `/user/brand-kits` asserts the Brand Consistency gauge reads below
  100 and a finding renders, then clicks "Apply fix" (submitting the finding's
  form via JS and waiting only for the POST so the heavy editor redirect never
  blocks) and re-audits: the gauge now reads 100 with no findings. Gating it
  catches regressions in the consistency audit or the apply-fix round-trip.
- `create-link-picker.spec.ts` — self-bootstrapping: it seeds the demo user
  (user-admin role) and a link that already owns a known alias via `php artisan
  tinker`, logs in once, and drives the redesigned Create Link manual picker on
  both a desktop and a mobile viewport. Asserts tapping a goal card selects the
  matching link type (and moving to another goal moves the selection), the
  free-text intent search selects the matching card (and a nonsense phrase
  reports no match), the sticky Custom URL field shows live availability states
  (available / already-taken / invalid), a taken alias blocks **Continue** and
  focuses the field, and a blank alias still submits (routing to step 2 with no
  `alias` param so it auto-generates). It also locks in the three fragile
  handoffs preserved through the redesign: typing a custom address then opening
  the **guided wizard** card carries the (trimmed) alias through as
  `?alias=...`; the **Build with AI** card's submit posts that same trimmed
  alias (with `start_mode=ai`); and **Continue** is a real `disabled` button
  until a link type is selected, then enables. The banned state is not asserted
  because the demo account holds `user.banned_names.bypass`, so for that
  privileged user the live checker correctly treats banned names as available;
  the taken/banned *guard* is covered via the taken alias.
- `marketing-background-seam.spec.ts` — no login/seeding. Loads every marketing
  page that `@extends('public.layouts.site')` (about, analytics, audience,
  how-it-works, integrations, forms, notifications, buzz, domains, discovery,
  ai-product via `/ai-chatbot`, use-case via `/for/creators`, workspace-team,
  contact, demos, features, pricing) with a pre-seeded consent cookie (so the
  banner never mounts and page scroll is free), in BOTH dark and light mode. For
  each it asserts no full-bleed, tall element inside `<main>` carries a
  fully-opaque background-colour — the signature of a reintroduced opaque
  hero/section band (e.g. the retired flat `#1e2330`) that would paint over the
  shared fixed `.aurora` and seam against the rest of the page — and that the
  sticky header stays pinned (its viewport-top doesn't drift) on scroll. Gating
  it stops a future page/edit from reintroducing a background seam.
- `home-section-structure.spec.ts` — no login/seeding. Renders `/` and asserts
  each contractual home section id is present EXACTLY once (missing or duplicated
  fails), and that the four AI partials (ai-hero via `#ai-hero-h`, `#ai-suite`,
  `#ai-marketing-strategist`, `#whatsapp-agent`) all render inside the single
  `#ai-zone` wrapper. Catches a reorganisation that silently drops, duplicates,
  or moves a section anchor out of `#ai-zone` and breaks nav / jump / deep links.
- `home-ai-zone.spec.ts` — no login/seeding. Loads `/` with a pre-seeded
  consent cookie and, under `reducedMotion:"reduce"` (which disables the AI
  Suite's own 5.4s scene auto-cycle so the scene only moves on click), guards
  the consolidated home AI zone: all four AI Suite tabs (`[data-aisx-tab]`) are
  present and clicking each swaps the live demo scene (`[data-aisx-scene]` /
  `[data-aisx-screen]` title + selected state), the three jump chips
  (`a.ai-zone-chip`) anchor-scroll to `#ai-suite`, `#ai-marketing-strategist`
  and `#whatsapp-agent`, the active scene rests fully assembled (every
  `.aisx-row` at opacity 1 — the reduced-motion fallback), and the page has no
  horizontal overflow at desktop and mobile widths. Pins the AI zone behaviour
  that was previously only verifiable by hand on a live instance.
- `onboarding-flow.spec.ts` — self-bootstrapping: it seeds a plan-unlocked
  starter template and resets the demo user to a FRESH, never-onboarded state
  (`onboarded_at`/`persona` cleared, an empty marker biolink recreated) via `php
  artisan tinker`, logs in as the demo user, and drives the step-by-step
  first-run wizard on `/user/onboarding` to prove it can't leave a new user
  stuck. Asserts the visible progress stepper renders the three core stage
  labels (Welcome / Pick your persona / Choose a template) and the wizard opens
  on Welcome; that advancing off Welcome and picking a persona auto-advances to
  the template stage (the grid shows, the persona picker hides); that "Use this
  template" reaches its outcome — the biolink block editor
  (`/user/links/{id}/blocks`); and that the persistent "Skip setup" escape hatch
  reaches its outcome — the dashboard (never bounced back into onboarding). A
  further test deactivates EVERY active template (restoring them in a `finally`)
  to prove the template stage survives an empty catalog — a real deployment with
  zero active templates for a persona/plan — by still rendering the "No templates
  available yet" empty-state card with an actionable "Continue to dashboard"
  escape (plus the persistent "Skip setup" header control), then reaching the
  dashboard. Its
  `afterAll` restores the demo user to an onboarded state so sibling specs
  (which assume the demo user is already onboarded) aren't disturbed. All tests
  share one logged-in context (the `demo-login` route is rate-limited).
- `dashboard-layout.spec.ts` — self-bootstrapping: it seeds the demo user
  (active + verified + `onboarded_at`) via `php artisan tinker`, logs in as the
  demo user, and lands on `/user/dashboard` to lock in the redesigned "bento"
  command center. Asserts the live-pulse hero tile renders, the three headline
  metric/action tiles (Total Clicks, Recent Links, Quick Actions) render, and
  the Overview / Traffic / Growth tabs actually swap the visible panel
  (`aria-selected` + panel show/hide). Also guards the blue-accent rebrand: it
  scans every bento element's rendered (computed) colors — including the
  `::before`/`::after` accent bars and glow orbs, across all three tabs — for
  anything in the purple hue band, and scans the dashboard markup for a retired
  purple token (hex / `purple-`/`violet-` utility class / rgb). All tests share
  one logged-in context (the `demo-login` route is rate-limited).
- `header-account-menu.spec.ts` — self-bootstrapping: it seeds the demo user
  (active + verified + `onboarded_at`) via `php artisan tinker`, logs in, and
  lands on a marketing page (`/contact`) that renders the shared public header.
  Clicks the desktop account (user) icon and asserts the logged-in account
  dropdown opens at its intended narrow width (Tailwind `w-44` ≈ 176px) — not
  squashed and not stretched — that it does NOT carry the `.mkt-navbar-bar`
  class (the exact Task #3278 regression that reused the navbar's responsive
  width and collapsed the panel), that its width stays far below the navbar
  bar's, and that "Sign out" renders on a single line (one client rect, no
  overflow). Checked in both dark and light mode (the server `light-mode` html
  class). All tests share one logged-in context (the `demo-login` route is
  rate-limited).
- `header-mobile-account-menu.spec.ts` — self-bootstrapping: it seeds the demo
  user (active + verified + `onboarded_at`) via `php artisan tinker`, logs in,
  and lands on `/contact` (the shared public header) on a MOBILE viewport
  (400px, below `lg`) where the hamburger drawer is the only nav. Opens the
  drawer and asserts the PARALLEL logged-in section (distinct from the desktop
  dropdown) exposes a working "Dashboard" link (`-> /user/dashboard`) and a
  "Sign out" logout form with the correct action (`route('user.logout')`),
  method POST, and a non-empty CSRF token, then actually submits it and proves
  the session ends (the authed drawer controls vanish and the logged-out CTAs
  return). Checked in both dark and light mode (the server `light-mode` html
  class). Guards the mobile drawer against silently drifting from the desktop
  account controls (memory user-sidebar-dual-nav). All tests share one
  logged-in context (the `demo-login` route is rate-limited); the destructive
  logout test runs last and re-establishes the session on retry.
- `header-mobile-logged-out-cta.spec.ts` — no login/seeding. Lands on a MOBILE
  viewport (400px, below `lg`) where the hamburger drawer is the only nav, with a
  pre-seeded consent cookie so the banner can't intercept taps. Guards the
  LOGGED-OUT `@else` branch of the same header `@auth` block (distinct from the
  logged-in guard above): on a non-modal page (`/contact`) it asserts the drawer
  shows Login/Register **links** pointing at the login (`/login`) and register
  (`/register`) pages (and that the auth modal is absent); on the modal home page
  (`/`, `useModal=true`) it asserts the drawer's Login/Register **buttons** open
  the two-panel auth modal on the correct tab (login → identifier field, register
  → name field). Guards the visitor sign-in entry points against silently
  drifting out of the mobile drawer (memory user-sidebar-dual-nav).
- `dashboard-mobile-account.spec.ts` — self-bootstrapping: it seeds the demo
  user (active + verified + `onboarded_at`, plus the `user-admin` web role so
  the account/settings nav group renders) via `php artisan tinker`, logs in, and
  lands on `/user/dashboard` (the IN-APP shell, distinct from the public header
  the sibling header specs cover) on a MOBILE viewport (400px, below `lg`). Guards
  the mobile account controls of the authenticated app shell — the parallel
  hamburger drawer + header ⋮ overflow menu that tend to drift from the desktop
  aside (memory user-sidebar-dual-nav). Opens the hamburger drawer, expands its
  collapsible Account group, and asserts it exposes exactly ONE consolidated
  "Settings" entry wired into the `/user/settings/{tab}` hub (Task #3220). Then
  opens the header ⋮ overflow menu (the mobile sign-out surface — the drawer
  itself carries no logout form) and asserts its Settings link points into the
  same hub and its "Sign out" logout form is correctly wired (action
  `route('user.logout')`, POST, non-empty CSRF token), before actually submitting
  it and proving the session ends (redirect to the login page, the authed logout
  form gone). Non-destructive assertions run in both dark and light mode (the
  server `light-mode` html class); the destructive logout test runs last and
  re-establishes the session on retry. All tests share one logged-in context (the
  `demo-login` route is rate-limited).
- `header-desktop-logged-out-cta.spec.ts` — no login/seeding. Lands on a DESKTOP
  viewport (1280px, above `lg`) where the desktop CTA cluster (`hidden lg:flex`)
  is the nav surface, with a pre-seeded consent cookie so the banner can't
  intercept clicks. Guards the LOGGED-OUT `@else` branch of the header `@auth`
  block on the DESKTOP surface (distinct from the mobile drawer guard above): on
  a non-modal page (`/contact`) it asserts the header shows Login/Register
  **links** pointing at the login (`/login`) and register (`/register`) pages
  (and that the auth modal is absent); on the modal home page (`/`,
  `useModal=true`) it asserts the Login/Register **buttons** open the two-panel
  auth modal on the correct tab (login → identifier field, register → name
  field). Guards the desktop sign-in entry points against silently drifting out
  of the header (memory user-sidebar-dual-nav).
- `dialer-live-sync.spec.ts` — self-bootstrapping: it seeds the demo user
  (active + verified + `onboarded_at` + the `user-admin` web role + a resolved
  active workspace so the dialer route's `workspace.can:settings.view` gate
  passes) and clears any leftover sentinel rows via `php artisan tinker`, logs
  in, and lands on `/user/dialer`. Covers the browser half of the dialer
  live-sync (the server contract is `tests/Feature/DialerLiveSyncTest.php`,
  memory dialer-everyday): that the page's client poll/render loop actually
  APPLIES another device's change to the DOM. Asserts the 12s auto-poll timer is
  wired (a `setInterval` wrapper armed via `addInitScript` records a 12000ms
  delay), that a distinctive sentinel favorite / call-log row is ABSENT on first
  load, then — after creating it out-of-band via tinker ("another device") and
  driving the page's own `window.pollLive()` — that it appears in
  `#favorites-grid` / `#recent-list` (and the once-empty card un-hides) WITHOUT a
  full page reload (a survives-across-refresh `window.__noReload` sentinel is
  still set). The change is driven on demand rather than by waiting on the
  background interval so the poll fires a bounded number of times instead of
  saturating the few PHP-CLI workers (memory editor-e2e-heartbeat-saturation).
  All tests share one logged-in context (the `demo-login` route is rate-limited).
- `store-coming-soon-modal.spec.ts` — gated, no login. Guards the store-badge
  "coming soon" popup (`public/partials/store-buttons.blade.php`) against the
  blank-modal regression: the dialog must be teleported to `<body>` (Alpine
  `x-teleport`, plus the required empty `x-data` root on the `<template>` — see
  the comment in the partial), never trapped inside a CSS-transformed /
  opacity-animated ancestor. Seeds blank `marketing_play_store_url` /
  `marketing_app_store_url` via tinker so badges render as buttons, then in BOTH
  contexts — the homepage `#dialer-contacts` section and the `/contact` footer —
  clicks a Play badge and asserts the overlay's parent is `<body>`, the card is
  visible, effectively opaque (opacity > 0.95), fully inside the viewport, and
  hit-testable at its center; the footer test also re-opens via the App Store
  badge and checks the headline switches. Close-button click must dismiss it.
- `ai-dashboard-demo-perf.spec.ts` — gated, no login/seeding. Guards the
  animated AI Dashboard demo partial
  (`resources/views/common/partials/ai-dashboard-demo.blade.php`) against
  performance / cleanup / a11y regressions over a long session. Loads BOTH the
  home page (`/`, compact variant inside `#ai-dashboard`) and the standalone
  `/ai-dashboard` page (rich variant). A `setInterval`/`clearInterval` wrapper
  armed via `addInitScript` (keyed on the partial's own 22ms typewriter and
  6200ms scene-cycle delays) tracks how many timers of each kind are live: after
  waiting through more than one full scene cycle it asserts at most ONE of each
  is active at any time (no per-cycle leak) while confirming each kind actually
  ran (guards against the demo silently going inert). Also asserts the scene
  actually advances (more than one preset index observed), that the live region
  stays `aria-live="off"` (the typewriter must not spam assistive tech), and that
  no console errors / failed same-origin requests occur. A second `describe`
  forces `reducedMotion:"reduce"` and asserts the static fallback: zero demo
  timers are created, the tiles render at full opacity, and the typewriter caret
  is hidden.

Run the full suite manually (when you can tolerate the slow renders) with
`pnpm test:e2e`, or any subset by passing args:

```sh
# from artifacts/1inme/
pnpm run test:e2e:ci                 # the twenty-six gated specs, self-bootstrapping
pnpm test:e2e                        # the whole Browser suite
bash tests/Browser/run-validation.sh cookie-consent-footer-gap.spec.ts
```

## Specs

- `home-ai-zone.spec.ts` — gated, no login/seeding. Loads `/` with a pre-seeded
  consent cookie under `reducedMotion:"reduce"` (freezing the AI Suite's own
  scene auto-cycle so it only advances on an explicit click, and disabling the
  row-reveal keyframes). Asserts the interactive AI Suite demo works — all four
  product cards/tabs (`[data-aisx-tab]`) exist and clicking each activates the
  matching scene (`[data-aisx-scene].is-active`), updates the demo screen title
  (`[data-aisx-title]`) and `aria-selected`, with exactly one scene active at a
  time. Asserts the three zone jump chips (`a.ai-zone-chip[href="#..."]`)
  scroll their target section (`#ai-suite`, `#ai-marketing-strategist`,
  `#whatsapp-agent`) to the top of the viewport (the page intercepts in-page
  anchor clicks with a global `scrollIntoView` handler, so the scroll position —
  not a URL hash — is asserted). Asserts the active scene settles into its
  assembled state (every `.aisx-row` at opacity 1, never stranded invisible),
  and that the page has no
  horizontal overflow (`documentElement.scrollWidth === clientWidth`) at a
  desktop (1280px) and mobile (390px) width. Locks in the AI zone behaviour that
  could previously only be verified manually on a live instance.
- `marketing-background-seam.spec.ts` — gated, no login/seeding. Loads every
  marketing page that `@extends('public.layouts.site')` (about, analytics,
  audience, how-it-works, integrations, forms, notifications, buzz, domains,
  discovery, ai-product via `/ai-chatbot`, use-case via `/for/creators`,
  workspace-team, contact, demos, features, pricing) in BOTH dark and light
  mode, pre-seeding the consent cookie so the scroll-locking banner never
  mounts. For each page/mode it walks `<main>` for any full-bleed (touching
  both viewport edges), tall (≥160px) element whose computed
  `background-color` is fully opaque — the fingerprint of a reintroduced
  opaque hero/section band that would paint over the shared fixed `.aurora`
  glow and seam against the rest of the page — and asserts none exists. It
  also asserts the sticky header stays pinned (viewport-top unchanged) after
  scrolling. Guards `public/layouts/site.blade.php`'s uniform-aurora contract
  against a future page reintroducing an opaque backdrop.
- `create-link-picker.spec.ts` — Gated. Seeds the demo user (user-admin
  role) plus a link that already owns a known alias, logs in once, and
  drives the redesigned Create Link manual picker on a desktop and a
  mobile viewport: goal-card → link-type selection, free-text intent
  search → card selection, the sticky Custom URL field's live
  availability states, a taken alias blocking **Continue** (focusing the
  field), a blank alias still submitting (auto-generate), the typed alias
  carrying into the **guided wizard** card as `?alias=...`, the **Build
  with AI** card posting the trimmed alias, and **Continue** staying
  `disabled` until a link type is selected. All tests share one
  logged-in context because the `demo-login` route is rate-limited.
- `slides-mode.spec.ts` — task #1059. Seeds a published 2-slide biolink
  at alias `e2e-slides-demo`, then in a real browser asserts both
  slides render, the active-slide class moves on `ArrowRight` /
  `ArrowLeft` and on a synthesized swipe-left gesture, and the inline
  `/sl/{alias}/view` tracker pings during navigation.
- `biolink-editor-palette-dnd.spec.ts` — task #1340. Gated. Seeds a
  biolink at alias `e2e-editor-dnd` (divider, spacer, and a card with one
  paragraph child) and logs in as the demo user, then drives the real
  palette-drop pipeline through production-safe `window.__editorTest`
  hooks (armed only when `window.__E2E__` is set). Asserts a palette tile
  drops at the top, between blocks, at the end, and inside a Card
  Container (verifying position and parent); that card-type tiles are
  rejected inside a Card Container; and that `prefers-reduced-motion`
  disables the drop animation. All tests share one logged-in browser
  context because the `demo-login` route is rate-limited.
- `home-auth-modal-mobile.spec.ts` — gated. On a mobile viewport, opens
  the home-page sign-in popup, asserts the correct tab is active, the
  close button clears the tabs, background scroll is locked, and the X /
  Escape keys close the popup and restore scrolling. No login/seeding.
- `home-section-structure.spec.ts` — gated, no login/seeding. Renders `/`
  and asserts the home page's contractual structure: every required
  section id (`#audience`, `#how-it-works`, `#features`, `#share`,
  `#domains`, `#create`, `#ai-zone`, `#ai-suite`,
  `#ai-marketing-strategist`, `#whatsapp-agent`, `#workspace-team`,
  `#buzz`, `#proof`, `#faq`, `#cta-final`) appears
  **exactly once** — catching a silently dropped or duplicated anchor that
  would break nav / jump / deep links; the conditionally-rendered
  `#blog-featured` is checked as "at most once". It also asserts the four
  AI partials (ai-hero via `#ai-hero-h`, `#ai-suite`,
  `#ai-marketing-strategist`, `#whatsapp-agent`) all render **inside**
  `#ai-zone`, with the AI hero present exactly once.
- `home-hero-claim-handle.spec.ts` — gated. Drives the hero "claim your
  link" pill: types a handle, submits it, and asserts the register modal
  opens with that handle in its hidden `desired_handle` field + the
  "Claiming @handle" banner. Also covers normalization (lowercase /
  leading-`@` strip / trim), the empty-claim path (no handle, no banner),
  and that the handle is actually sent in the register POST body (via a
  route-intercept). No login/seeding — pins the browser-side hero →
  open-auth event → header Alpine → modal-binding chain.
- `biolink-editor-card-gallery-preview.spec.ts` — gated. Seeds one
  no-thumbnail card template per shape family (avatar/pill/media/form/
  list_rows/heading/text_lines/dot_row) at alias `e2e-card-gallery-preview`,
  logs in as the demo user, opens the editor's Card Templates gallery, and
  asserts every tile draws its shape-aware `preview_layout` mock — never
  the empty-snapshot fallback icon — proving a blank-tile regression would
  fail the gate. All tests share one logged-in context (the `demo-login`
  route is rate-limited).
- `cookie-consent-footer-reserve.spec.ts` — guards the cookie-banner
  footer-reserve invariant. On `/contact` it asserts that a first-time
  visitor's `document.body` bottom padding equals the *visible* banner
  height (the bug once reserved ~416px for a ~154px prompt), that the
  in-place "Customize" expansion grows the reserve in lockstep, and that
  a returning visitor (consent cookie present) gets no banner, no reserve,
  and a footer flush with the bottom of the document. No login needed.
- `cookie-consent-theme-match.spec.ts` — gated. Guards that the consent
  banner's `auto` theme follows the site's own light/dark mode rather than
  the OS `prefers-color-scheme`. On `/pricing` (consent theme rewritten in
  the server-rendered config blob) it asserts the banner is light on a
  light page and dark on a dark page (the `.cc-is-dark` class on `.cc-host`
  plus the resolved card background), that flipping the site light/dark
  switch re-themes the open banner live via its MutationObserver, and that
  explicit admin `light`/`dark` themes stay fixed regardless of the page
  mode. No login/seeding.
- `dashboard-layout.spec.ts` — gated. Seeds the demo user (active +
  verified + `onboarded_at`) and logs in once, then lands on
  `/user/dashboard` to lock in the redesigned "bento" command center.
  Asserts the live-pulse hero tile renders, the three headline
  metric/action tiles (Total Clicks, Recent Links, Quick Actions)
  render, and the Overview / Traffic / Growth tabs actually swap the
  visible panel (`aria-selected` + panel show/hide). Also guards the
  blue-accent rebrand: it scans every bento element's rendered (computed)
  colors — including the `::before`/`::after` accent bars and glow orbs,
  across all three tabs — for anything in the purple hue band, and scans
  the dashboard markup for a retired purple token (hex / `purple-` /
  `violet-` utility class / rgb). All tests share one logged-in context
  because the `demo-login` route is rate-limited.
