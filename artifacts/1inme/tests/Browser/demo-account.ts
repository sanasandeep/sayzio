/**
 * The account the non-prod demo-login route authenticates as
 * (AuthController::demoLogin -> this email). Every browser spec that seeds an
 * owner-scoped fixture MUST own it as this account, or the controller owner
 * guard (`$link->user_id !== workspace_owner_id()`) 403s the seeded page and
 * the asserted element is simply absent — a silent, misleading failure.
 *
 * Keep this the single source of truth: import it and interpolate it into the
 * tinker seed strings instead of hardcoding the email per file.
 */
export const DEMO_LOGIN_EMAIL = "sayzioapp@gmail.com";
