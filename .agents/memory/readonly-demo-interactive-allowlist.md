---
name: Read-only demo interactive allowlist
description: What the is_readonly_demo account may POST to, why AI-generation stays blocked, and a User-model type-hint gotcha found while wiring it.
---

# Read-only demo interactive allowlist

`BlockReadonlyDemoWrites` is a scoped middleware that short-circuits every
state-changing method from an `is_readonly_demo` account BEFORE the controller
runs. GET/HEAD/OPTIONS always pass. Beyond the 3 auth routes, it also allows a
curated set of *interactive-but-non-persisting* POSTs so a public demo visitor
can actually TRY showcase features.

## The safety contract for adding an interactive route
A route may be allowlisted ONLY if it (a) writes nothing to the DB and (b) does
NOT charge the coin wallet (no `OpenAiService` / `AiUsageCharger` call). Verified
safe today: QR generation (renders an image), biolink draft preview (cache-key
only), bulk URL/biolink preview, and the AI coin-cost *estimates* (pure dry-run
arithmetic). Dialer universal search is safe because it is a GET.

**Why:** the demo is a single shared public account. Anything that persists rows
or spends coins is an abuse/cost/data-integrity hole when hit anonymously — and
the AI engine can be ENABLED in an env, so those calls spend real money.

**How to apply:** AI-*generation* surfaces (companion send, ask-a-mind,
persona/coach test, AI persona generate, artistic QR `qr-codes.generate-art`) and
the dialer live `lookup` are deliberately kept OUT of the allowlist — they persist
and/or charge. Letting a demo "actually run" AI generation needs a separate
non-persisting/non-charging demo AI mode, not an allowlist entry.

## Gotcha: `App\Models\User` does not exist in this app
The canonical user model is `App\Modules\User\Models\User` (HMVC). A stray
`use App\Models\User;` type-hint (found in `AiCostEstimator`) makes any call fail
with `TypeError: Argument #1 ($user) must be of type App\Models\User,
App\Modules\User\Models\User given` at CALL time — not a class-not-found — which
is confusing because the "expected" class is a phantom. `class_exists` returns
false for it. Fix = import the module User. Such bugs hide until the surface is
reachable (e.g. after allowlisting it for the demo).

## Verifying live in an isolated env
No workflow; `php artisan serve --port=5000` in a SINGLE bash call (a backgrounded
server is reaped between separate bash tool calls). OTP login: fixed code
`123456`; grab a fresh `_token` from each form and a `csrf-token` meta from a
FULLY-RENDERED authed page (follow redirects with `-L`; the dashboard 302s to
onboarding, so a redirect body yields an empty token → 419 on every POST).
Server-side QR PNG needs the `imagick` extension (only `gd` may be present) — SVG
works everywhere; the demo QR Studio renders client-side anyway.
