---
name: Addon-aware effective plan features
description: How purchased addons (with quantity) flow into a user's runtime feature caps, and how multi-addon checkout bills/persists qty.
---

# Addon-aware effective plan features

`EffectivePlanFeatures` is the single merge point that turns a user's plan
features + their active subscription's addons (each with a purchased qty) into
the effective feature set. `User::getPlanFeature($key)` reads from a memoized
`User::effectivePlanFeatures()` (NOT `$this->plan->features` directly anymore).

**Merge rules** (`mergeFeatures(baseFeatures, [[Addon,qty],...])`):
- `*_extra` keys add `value × qty` to the matching base key (e.g.
  `max_biolinks_extra:5` × qty 3 → +15 to `max_biolinks`); `-1`/unlimited base is left alone.
- booleans never downgrade (OR-merge); `analytics` tier keeps the higher rank.

**Why:** before this, `EffectivePlanFeatures` was dead code (0 callers) and addons
were billed but never granted any capability — runtime caps ignored addons entirely.

**Checkout multi-addon billing convention** (`CheckoutController`):
- request shape is `addons[ADDON_ID]=QTY` (associative); legacy `addons[]=ID`
  still parsed as qty 1 (`parseAddonQuantities`, clamped 1..MAX_ADDON_QTY=99).
- eligibility = addon attached to the chosen plan via `addon_plan` pivot, enforced
  server-side (`eligibleAddons`); ineligible ids are silently dropped, never billed.
- invoice line item: `amount_minor` is PER-UNIT, `quantity` carries the count,
  and `meta['qty']` mirrors it. `ActivateSubscription` recovers `addon_id`+`qty`
  from `line_items` meta to persist `subscription_addons.qty`. `TaxCalculator`
  multiplies per-unit × quantity for the line total.

**How to apply:** adding a new addon-granted capability = put a `*_extra` (or
boolean/tier) key in the Addon's `features`; no controller change needed. Pricing
is currency-locked per user (`PricingResolver::currencyForUser`); missing INR
price row degrades to ₹0.00 (no silent USD fallback).
