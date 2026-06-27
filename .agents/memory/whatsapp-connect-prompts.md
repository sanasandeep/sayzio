---
name: WhatsApp connect prompt surfaces
description: How the "share your WhatsApp number + follow our channel" nudges fit together and why they self-skip for some users.
---

# WhatsApp connect prompts (1inme web)

Goal: grow the WhatsApp audience + give every account a reachable, OTP-loginable number. Web-only (no mobile/API parity). Three surfaces, one gate.

- **Single source of truth for "has a number":** `User::hasWhatsappNumber()` = a verified (`verified_at` not null) `phone` LinkedIdentifier. Every prompt is gated on this; never re-implement the check.
- **Self-skip trap:** registering WITH a mobile creates a *verified* phone identifier via `User::booted()` `created` hook, so those users already pass `hasWhatsappNumber()` and correctly never see any prompt. Don't add a separate "did they register by phone?" check.
- **Shared OTP endpoints:** the post-registration step (`OnboardingController::whatsappStep/whatsappSend/whatsappVerify/whatsappSkip`, routes `user.onboarding.whatsapp[.send|.verify|.skip]`) and the dashboard nudge card BOTH POST to the same `whatsapp.send`/`whatsapp.verify`. They mirror the linked-identifier add flow: `OtpService::generate/verify($value,'mobile','link','web')` + `sendWhatsApp`, and you MUST re-check LinkedIdentifier uniqueness between send and verify (someone else can claim the number) or the unique constraint 500s.

**Why:** consolidating on one gate + shared endpoints keeps the three surfaces from drifting; the auto-verify hook is the non-obvious reason "some users never get prompted" is correct, not a bug.

**How to apply:** changing the prompt logic = touch the gate (`hasWhatsappNumber`), the middleware (`RedirectToOnboarding`, alias `n`, attached ONLY to the dashboard route — recency-gated to ≤14d onboarded, stamps `whatsapp_step_shown_at` once), the dashboard card, and the weekly command in lockstep.

## Snooze / cadence state (user `settings` JSON)
- `whatsapp_step_shown_at` — one-time step has fired; never redirect again.
- `whatsapp_prompt_dismissed_at` — dashboard card "dismiss" = a **one-week snooze**, not a permanent hide; card returns after a week until a number exists. The weekly command honours the same stamp.
- `whatsapp_connect_notified_at` — per-user cooldown for the weekly command (`whatsapp:send-connect-reminders`, scheduled `weeklyOn(2,'10:45')`), so a re-run can't double-send.

The command is in-app only (catalog `whatsapp.connect_reminder`: in_app true, email/push false). It relies on `NotificationService::notify()` returning null when the user muted in_app — only stamps the cooldown on a non-null (actually delivered) result.

Channel URL: AppSetting `marketing_whatsapp_channel_url`; empty ⇒ "Follow our channel" button hidden.
