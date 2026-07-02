---
name: App-launch mailing list notifications
description: How the mobile-app "coming soon" launch list gets emailed when an admin sets a store URL — the trigger semantics and why it mirrors FeatureLaunchNotifier.
---

# App-launch mailing list notifications (1inme)

Closes the loop on `app_launch_signups` (collected by the public store "coming soon"
modal). `AppLaunchNotifier` (Common/Support) emails everyone with `notified_at IS NULL`
once the app goes live on a store, then stamps `notified_at` so it's never re-sent.
Email key `app.launched` (EmailTemplateRegistry, category `messaging`, view
`emails.app-launched`).

**Trigger = aggregate "live on any store" transition, NOT per-store.**
The launch signal is `AppLaunchNotifier::isLaunched()` = *any* of
`marketing_play_store_url` / `marketing_app_store_url` configured.
`MarketingSettingsController::update` captures `isLaunched()` BEFORE the `put()`s and
after; on `!was && now` it fires `notifyIfLaunched()` inline. So the FIRST store URL to
be set notifies everyone exactly once; adding the SECOND store link later does NOT
re-notify (already live). We deliberately email the whole list on first launch — the
signup's `store` column only picks the *primary CTA link*, it does not filter recipients.

**Why the same shape as FeatureLaunchNotifier (see feature-launch-notify.md):**
- Idempotency is the `notified_at` stamp, only `whereNull('notified_at')` rows considered.
- Stamp ONLY on confirmed delivery: `Emailer::send` swallows transport errors, so pass
  `throw_on_failure => true` and stamp only if send didn't throw. A transient SMTP blip
  leaves the row unstamped so the hourly sweep (`app-launch:notify-signups`, routes/console.php)
  retries — a launch email is never permanently dropped.
- No-email rows skipped + left unstamped; `chunkById` paginates by id while filtering.
- These are explicitly user-requested emails — no NotificationService pref gate.

**Gotcha:** verifying a *successful* send in an isolated env is impossible (admin
MailSettings forces an unreachable SMTP at boot). Prove the no-op (no store URL) and the
no-stamp/retry (launched but SMTP down) paths instead — stamp mechanics are the standard
forceFill. `Emailer::dispatch` has a pre-existing latent bug (its `$callback` closure
references `$key` without capturing it → "Undefined variable $key" warning on every
html/text send); unrelated to this feature, don't chase it here.
