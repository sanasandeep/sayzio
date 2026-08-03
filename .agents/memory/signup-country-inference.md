---
name: Signup country inference
description: How new-account billing country is inferred now that sign-up forms have no country picker.
---

# Signup country inference

Sign-up UIs collect no country, but `users.country` drives billing currency
(`config/country_currency.php`: IN => INR, else USD). `App\Modules\Common\Support\SignupCountry`
infers it at account creation: phone dialling code first (longest-prefix map, +91 => IN),
then GeoIP on the request IP (best-effort, never throws).

**How to apply:** any NEW account-creation path must call `SignupCountry::infer($mobile, $ip)`
when no explicit country was submitted. Currently wired in THREE places (keep in lockstep):
web `AuthController::register`, web `AuthController::createOtpSignupUser`, and
`Api\OtpController::register`. Explicit submitted country always wins; existing users untouched.

**Test gotcha:** register feature tests must send password+password_confirmation (password
login is enabled in the ephemeral test env), and stub `GeoIpService` via container instance.
