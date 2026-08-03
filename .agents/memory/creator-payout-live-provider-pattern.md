---
name: Creator-payout live provider pattern (Razorpay Route reference)
description: How to take a preview-only payout adapter live — settlement path, webhook routing, and order→token mapping.
---

# Taking a creator-payout adapter live

Reference implementation: RazorpayRouteAdapter (+ RazorpayRouteWebhookController). Follow this pattern for Cashfree/others.

**Rules:**
- `MonetizationCheckout::confirm()` is preview-only (blocked in production by design — it grants entitlements on token possession). Live webhooks must settle via `settleFromProvider(kind, reference, token)`, which skips the preview guard; only call it AFTER verifying the provider's signature. Idempotency comes free: the cache token is `pull()`ed, so re-deliveries return null.
- The checkout context (kind/reference/token) must reach the webhook. Store it in a cache entry keyed by the provider order id at order-creation time (plus provider `notes` as fallback) — provider webhooks don't echo your return URL.
- Webhook path must be its OWN route declared BEFORE the `/webhooks/{gateway}` catch-all, and must not collide with plan-billing gateway slugs (e.g. billing already owns `/webhooks/razorpay`; Route payouts use `/webhooks/razorpay-route`).
- Keep the preview fallback: live paths return null / fall through to parent when env keys or a real (`acc_*`) account id are absent, so keyless envs behave exactly as before.
- Env-key tests set `$_ENV`/`$_SERVER` directly (existing convention); Http::fake covers the provider API. `TEST_LOCAL_MODE=artisan bash scripts/test-local.sh --filter=X` runs them green in ~15s.

**Why:** the preview guard exists to stop free entitlement grants in prod; any live provider that reused `confirm()` would silently no-op in production.
