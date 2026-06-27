---
name: Registration pause switch
description: The admin "pause new registrations" gate and every account-creation surface it must touch.
---

# Registration pause switch

A single admin toggle (`AuthMethods::SETTING_REGISTRATION_PAUSED` in `app_settings`, read via `AuthMethods::registrationPaused()`, default OFF) blocks NEW account creation while existing users keep signing in. Shared copy/code via `AuthMethods::registrationPausedMessage()` + `AuthMethods::ERROR_REGISTRATION_PAUSED` ('registration_paused').

**Why:** "no new sign-ups" is not one branch — account creation happens on ~6 separate surfaces. Gating only `/register` leaves OTP/social/mobile wide open.

**How to apply:** any change to who-can-create-an-account must touch ALL of these in lockstep:
- Web register: `User\Controllers\AuthController::showRegister` + `register` (paused → render `user.auth.registration-paused` view).
- Web OTP-as-signup: `AuthController::sendOtp` `!$user` branch (paused → upgrade view; existing users still get codes). `resendOtp` needs NO gate — it never creates and only sends for a real user.
- API: `Api\Controllers\AuthController::register`, `OtpController::send` (`!$user` branch) + `OtpController::register` → `fail(msg,403,code)`.
- Social: `Api\Controllers\SocialAuthController::exchange` `!$user` block; web `User\Controllers\SocialOAuthController::callback` login-mode `!$user && $email` block (web → redirect `user.register` which shows upgrade page; mobile → deep-link `?error=registration_paused`).
- Admin UI/persist: `Admin\Controllers\AuthSettingsController` index()+update() and `resources/views/admin/auth-settings/index.blade.php`.

Branded upgrade page = `resources/views/user/auth/registration-paused.blade.php` (standalone @vite page, Space Grotesk, theme-styles vars). Enumeration tradeoff (unknown identifier sees upgrade page, existing sees code) is accepted per spec.
