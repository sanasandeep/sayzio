---
name: Plan upgrade/downgrade billing model
description: How mid-cycle plan changes are billed in 1inme (no proration; full-price upgrade + admin credit review; scheduled downgrade applied at cycle end).
---

# Plan upgrade/downgrade billing model

**Rule:** Mid-cycle plan changes never show or charge proration math.

- **Upgrade** = full price of the target plan for a fresh full cycle, starting today
  (`current_period_end` reset to `now()+months` in `ActivateSubscription` plan_upgrade branch).
  Leftover old-plan days + add-on time are NOT auto-credited. Instead each upgrade flags a
  pending `SubscriptionCreditReview` (computed via `floatDiffInDays`, addons snapshotted) that an
  admin can approve (extend `subscription.current_period_end` + `user.plan_expires_at` by N days —
  add-on duration shares the subscription period, so it rides along) or dismiss. Surface:
  `/admin/credit-reviews` (CheckPermission:settings.manage), audited via
  `AdminActionLogger::CREDIT_REVIEW_APPROVED/DISMISSED`.
- **Downgrade** = scheduled change to a chosen LOWER PAID plan, applied at cycle end by the renewal
  job, NOT a switch to Free (moving to Free is "cancel at period end"). Stored on
  `subscriptions.scheduled_downgrade_plan_id`; cancellable before it applies. The renewal command
  (`RenewDueSubscriptions::applyScheduledDowngrades`) runs BEFORE normal renewal, calls
  `SubscriptionLifecycle::applyScheduledDowngrade` (drops add-ons the lower plan can't carry), then
  re-bills at the lower plan price via `chargeRecurring`. Normal renew/past-due steps exclude
  `whereNull('scheduled_downgrade_plan_id')` so a scheduled sub isn't double-processed.

**Why:** Product decision — proration is confusing and error-prone; full-price + optional
manual credit keeps revenue clean and gives ops a lever. Don't reintroduce ProrationCalculator-based
charging into upgrade/downgrade confirm/handoff — `resolveMinor(...)` is used only to compare plan
prices (which is higher/lower), never to compute a partial charge.

**How to apply:** Any change to upgrade/downgrade flow must keep 5 surfaces in lockstep: user
BillingController (web) + views (upgrade/upgrade_confirm/show/downgrade), Api BillingController
`subscription()` (`scheduled_downgrade` block), admin CreditReviewController + view, SubscriptionLifecycle
(schedule/cancel/apply + notify), RenewDueSubscriptions. Mobile reads the API `scheduled_downgrade` field.
