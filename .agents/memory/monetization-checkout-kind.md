---
name: Adding a monetization checkout kind
description: The lockstep surfaces required when introducing a new paid "kind" (e.g. form) into MonetizationCheckout.
---

Adding a new paid source/"kind" to the creator monetization checkout means editing
these surfaces in lockstep — miss one and the charge silently fails to start or
fails to reconcile on return:

1. `CreatorPaymentEvent` — new `SOURCE_*` + `TYPE_*` consts + `describeShort()` labels.
2. `MonetizationCheckout` — a `start<Thing>()` (caches payload keyed by
   `cacheKey('<kind>', id, token)`, calls
   `PayoutProviderRegistry::adapter($conn->provider)->createOneTimeCheckout($conn, [...])`
   with keys `kind/reference/token/amount/currency/fan_email/return_url`), a
   `confirm<Thing>()`, a `'<kind>'` arm in `confirm()`'s match, and a `'<kind>'`
   branch in `cacheKeyFromReference()`.
3. `MonetizationCheckoutController` — add `'<kind>'` to the `in:` rule of all THREE
   validate lists (preview, confirmPreview, returnHandler).
4. The caller (e.g. `FormController::publicSubmit`) — create the pending record,
   call `start<Thing>()`, then `redirect()->away($url)` for web / return
   `checkout_url` for `wantsJson()` mobile.

**Why:** the return handler (`/checkout/return`) re-validates `kind` against the
`in:` list and dispatches via `confirm()`'s match; an unlisted kind 422s on return
or returns null from confirm, stranding a paid-but-unrecorded submission.

**How to apply (reconcile idempotency):** `confirm<Thing>()` runs OUTSIDE the request
cycle (no active workspace), so load workspace-scoped models with
`::withoutGlobalScope('workspace')`. Guard against re-delivery (return early if the
record is already `paid`). Defer side effects that the submit path normally fires
(counting, owner notifications, forwarders) to confirm-time for paid records, since
the submit path only persisted a pending row. Earnings `bySource` is a generic
`groupBy('source')`, so a new SOURCE_* auto-surfaces there — only the label + icon
maps (earnings.blade `$sources` / `$iconMap`) need a manual entry.
