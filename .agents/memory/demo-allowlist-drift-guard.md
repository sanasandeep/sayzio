---
name: Demo write-guard interactive allowlist drift guard
description: How BlockReadonlyDemoWrites classifies interactive non-persisting routes and the demo:check-allowlist drift guard that keeps it in sync
---

# Read-only demo interactive allowlist + drift guard

`BlockReadonlyDemoWrites` short-circuits every state-changing method from an
`is_readonly_demo` account BEFORE the controller runs (GET/HEAD/OPTIONS always
pass). Beyond the auth allowlist it has an **interactive-but-non-persisting**
allowlist so demo visitors don't get a wrong "changes aren't saved" banner on AI
previews, `.estimate`/`.suggest`/`.think`/`.preview*`/`lookup`/`quote` and QR
image renders that save nothing.

## The safety contract for allowing an interactive route
A route may be allowlisted ONLY if it (a) writes nothing to the DB AND (b) does
NOT charge the coin wallet (no `OpenAiService` / `AiUsageCharger` /
`workspace_owner()` wallet charge). **Why:** the demo is a single shared public
account; anything that persists rows or spends coins is an abuse/cost/data hole
when hit anonymously, and the AI engine can be ENABLED via env so those calls
spend real money. ALWAYS read the controller method before allowing.

## Two-bucket classification (both lockstep with the guard)
- `ALLOWED_INTERACTIVE_ROUTE_NAMES` / `ALLOWED_INTERACTIVE_PATHS` — genuinely
  non-persisting/non-charging → demo may use them (isAllowlisted returns true).
  Verified safe: QR image renders (`user.qrcode.download`,
  `user.links.qrcode.download` — render an image, no charge/save), biolink draft
  preview (cache-key only), bulk URL/biolink preview, AI coin-cost *estimates*
  (`user.ai.cost-estimate` and the feature-specific `.estimate` routes — pure
  dry-run arithmetic + balance read).
- `ACKNOWLEDGED_NONALLOWED_ROUTE_NAMES` / `ACKNOWLEDGED_NONALLOWED_PATHS` — the
  name/URI *looks* interactive but the controller PERSISTS or CHARGES, so it
  stays blocked; listed only so the drift guard treats it as consciously
  reviewed. Current: `user.payouts.preview-complete` (writes
  creator_payment_connections), `api/v1/dialer/lookup` (writes a DialerLookup
  row), and **`user.qr-codes.generate-art` / `api/v1/qr-codes/generate-art`**
  (AI image gen — charges coins against the workspace-owner wallet, throws
  `InsufficientCoinsForAiException`, gated by `AiPlanAccess` — MUST stay blocked).

## coach.suggest / mind.think are allowlisted AI *generation* (subtle)
Unlike the `.estimate` routes (pure arithmetic), `user.ai.coach.suggest` and
`user.ai.mind.think` ARE allowlisted yet DO call `OpenAiService::chat` — which
charges the wallet AFTER a successful HTTP call. They're deemed demo-safe only
because they (a) never write a business row (their sole side effect is a
`session()->flash()` + redirect) and (b) in the demo env the AI engine is OFF, so
`ensureEnabled()` aborts 404 before the charge. **Why this matters for tests:** in
the CI persistence test you must MOCK `OpenAiService` (via
`$this->app->instance(OpenAiService::class, $mock)` returning a fixed chat array
incl. `credits_spent`/`content`/`model`), ENABLE the engine
(`AiEngineSettings::setEnabled(true)`), and give the demo user a PAID plan — else
the route 404s/gate-fails and "persists nothing" passes for the wrong reason
(never reaching the controller). The `.estimate` routes also gate on a paid plan
(marketing_strategist needs `!isOnFreePlan()`), so grant one there too; resume /
brand-kit / ai-builder estimates fall back to allowed when the plan key is absent.
See `ReadonlyDemoAllowlistPersistenceTest` (full-DB row-count snapshot per route).

## Drift guard: `demo:check-allowlist` (CheckDemoAllowlist)
`composer check:demo-allowlist` + `DemoAllowlistDriftTest`, registered as the
`demo-allowlist` validation gate. Scans the in-memory route table (no DB), so it
runs as a fast pre-merge check.
- **Drift** = any write route whose LAST name/URI segment is an interactive verb
  (`INTERACTIVE_VERB_SUFFIXES`, incl. `generate-art`) or starts with "preview"
  that isn't classified in either bucket.
- **Staleness** = a tracked allowlist entry that matches NO registered write
  route at all (renamed/deleted), validated against the FULL non-admin write
  surface — NOT only interactive routes. This decoupling matters: legitimately
  non-persisting allowlist entries can have non-verb last segments (e.g. QR
  `download`, the unified `cost-estimate`) that the interactive-verb heuristic
  never scans; tying staleness to interactivity would falsely flag them.

## Gotchas
- Detection is the **LAST segment only** — deliberate: middle-segment `preview`
  routes persist (e.g. `contacts/import/preview/{token}/confirm`) and must NOT
  be auto-allowed. `.generate` (creates content), `.apply`, `.confirm`,
  `.complete` are excluded from the verb set — they persist. `generate-art` IS a
  verb (renders) but is acknowledged-BLOCKED because it charges coins.
- `App\Models\User` is a **phantom class** in this HMVC app — canonical is
  `App\Modules\User\Models\User`. A stray `use App\Models\User;` type-hint fails
  at CALL time with `TypeError` (not class-not-found), confusing because the
  "expected" class doesn't exist; `class_exists` returns false. Hides until the
  surface is reachable (e.g. after allowlisting it for the demo).
- Admin routes (`admin.*` name, `admin/` or `api/v1/admin/` uri) are excluded —
  the demo persona is a plain web user behind a separate guard.
- Local `php artisan test` aborts on the shared RDS (test-DB wipe guard); verify
  via the artisan command / composer script instead, trust CI for the PHPUnit
  test. Live-check with `php artisan serve --port=5000` in a SINGLE bash call;
  OTP login fixed code `123456`; server-side QR PNG needs `imagick` (SVG works
  everywhere, demo QR Studio renders client-side anyway).
