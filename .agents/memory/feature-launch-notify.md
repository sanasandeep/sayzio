---
name: Coming-soon feature launch notifications
description: How "Notify me" signups get emailed when a coming-soon feature goes ready — the two transition paths and why both hooks exist.
---

# Coming-soon feature launch notifications (1inme)

Closes the loop on the "Notify me" signups (`feature_notify_interests`) collected by the
coming-soon system (`FeatureAvailability`/`FeatureCatalog`). `FeatureLaunchNotifier`
(Common/Support/FeatureStates) emails everyone waiting on a feature once it resolves to
`ready`, then stamps `notified_at` so it is never re-sent. Email key `feature.launched`
(EmailTemplateRegistry, category `messaging`, view `emails.feature-launched`).

**Why TWO trigger paths (both required, neither alone is enough):**
coming_soon → ready happens two ways and only one has a synchronous code hook.
- Admin clears the forced override → `Admin\FeatureStateController::update` calls
  `notifyLaunched($key)` inline (immediate payoff).
- Integration/config finally connects (auto-detect via `configured` callable) → NO code
  hook exists, so the hourly `features:notify-launched` command sweeps all pending
  interests via `notifyAllLaunched()`.

**How to apply / gotchas:**
- Idempotency is the `notified_at` stamp, NOT deletion: only `whereNull('notified_at')`
  rows are considered.
- Stamp ONLY on confirmed delivery. `Emailer::send` swallows transport errors by
  default (see emailer-swallows-transport-errors.md), so the notifier passes
  `throw_on_failure => true` and stamps only if send did not throw. A transient SMTP
  outage leaves the row unstamped so the next hourly sweep retries — otherwise a blip
  would permanently drop a required launch email. (Code review REJECTED the first cut
  precisely because it stamped-on-failure.)
- No-email users are skipped and left unstamped (nothing to deliver); re-scanning them
  is a cheap no-op. chunkById paginates by `id` while filtering `notified_at`, so
  unstamped rows don't wedge a single run.
- `recordNotifyInterest` re-arms an already-notified row (nulls `notified_at`) so a
  feature that goes ready → coming_soon → ready can notify the same user again.
- These are explicitly user-requested emails — do NOT add a NotificationService pref gate
  that could suppress them.
- Verifying a *successful* send in an isolated 1inme env is impossible: admin MailSettings
  forces an unreachable SMTP transport at boot, so every real send fails. Prove the
  failure/no-stamp/retry path instead; the stamp mechanics are the same forceFill used
  everywhere.
