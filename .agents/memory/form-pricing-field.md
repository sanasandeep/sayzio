---
name: Forms pricing/package field
description: The "pricing" form field stores a structured breakdown array under its field id, not a scalar — every submission->data iterator must special-case it.
---

# Forms "pricing / package" field type

A `pricing` form field (1INME Laravel Forms builder) lets the creator define
radio price options + checkbox add-ons. On public submit the controller
computes a per-field breakdown and stores it **as a structured array** under the
field's own id in `form_submissions.data`, shaped:

```
{ _pricing: true, label, option: {label, price_cents}|null,
  addons: [{label, price_cents}], currency, total_cents }
```

**Why it matters:** every place that iterates `$submission->data` and assumes
scalar (or flat-array) values will break or render "Array" on a pricing field.
The `_pricing` flag is the discriminator.

**How to apply:** when adding any new surface that walks `$submission->data`
(exports, owner emails, notifications, owner UI), branch on
`is_array($v) && !empty($v['_pricing'])` first and flatten to a line item, then
fall back to the existing scalar/flat-array handling. JSON webhook payloads are
already safe (they encode the nested array as-is). Known special-cased sites:
CSV export, submission-show view, owner-email notification body.

**Charge routing:** pricing forms reuse the EXISTING paid-forms gateway. The
charge total is the sum of all pricing fields' selections; it's passed to
`MonetizationCheckout::startFormPayment()` via the `$amountOverride` /
`$currencyOverride` params (fixed-price forms pass nulls and fall back to the
form-level amount). A zero computed total ⇒ free submit even on a paid-capable
form. Prices are authored as dollar floats in the field config and converted to
cents via `Form::priceToCents()`.
