---
name: Billing email CC list
description: How the admin-managed CC on platform billing emails is gated and why client_invoice is excluded.
---

# Billing notification CC

Admin-managed CC list (`BillingNotificationSettings`, app_settings key
`billing.cc_recipients`) is applied centrally in `Emailer::applyBillingCc()`
(called from `dispatch()` + non-override `sendMailable`). Gating is by
EmailTemplateRegistry **category == 'billing'**, not a hardcoded key list.

**Why exclude `billing.client_invoice`:** it is a creator invoicing their own
client (creator-economy), not platform billing. CC'ing the platform finance
addresses on it would leak the creator's client relationship. It lives in the
`billing` category but sits in `BillingNotificationSettings::EXCLUDED_KEYS`.

**How to apply:** any NEW billing-category email is auto-CC'd. If a new billing
key is actually a creator/third-party flow, add it to EXCLUDED_KEYS. Unset list
returns the two seeded DEFAULTS (env-first, no seeder); saving an empty list
disables CC.
