---
name: demo-login form not in login markup
description: Why 1inme Browser specs' `form[action$="/user/demo-login"]` login can fail in isolated/fresh envs, and the robust token-POST alternative.
---

# The `/user/demo-login` button is not part of the login page markup

`/user/login` (rendered by `AuthController::showLogin` → `user.auth.login`) does
NOT contain a `form[action$="/user/demo-login"]` element. The string
`demo-login` appears nowhere in `resources/` (verified by grep + git `-S` +
rendering the page: the live HTML has exactly two forms — password `/user/login`
and OTP `/user/send-otp` — and zero demo markup). Whether any demo-login button
renders is environment/data driven, so a fresh isolated task env shows none.

**Why this matters:** ~22 `tests/Browser/*.spec.ts` share a `loginAsDemo` helper
that does `document.querySelector('form[action$="/user/demo-login"]').submit()`.
In an env where that button isn't rendered, every one of those specs fails at
`beforeAll` with a misleading `Error: demo-login form not found` — it looks like
the feature under test broke, but it's just the login step.

**The endpoint itself works fine** with only a same-session CSRF token. That is
exactly how `tests/Browser/run-validation.sh`'s warm step authenticates: GET
`/user/login`, scrape the `_token`, then `POST /user/demo-login -d _token=...`.
`AuthController::demoLogin` 404s ONLY in production; otherwise it converges on the
`sayzioapp@gmail.com` demo user and logs in.

**How to apply:** the robust `loginAsDemo` is now a single shared helper —
`tests/Browser/login-as-demo.ts` (exports `loginAsDemo(page)`). It reads the
`_token` the login page's own forms already carry (`input[name="_token"]`),
synthesizes a form POSTing to `/user/demo-login`, and submits it — independent of
any rendered demo button, identical to run-validation.sh's warm step. ALL
browser specs that sign in as the demo user import it from `./login-as-demo`; do
NOT re-add a copy-pasted inline `loginAsDemo` (the `form[action$="/user/demo-login"]`
button-first version is what silently broke ~22 specs with "demo-login form not
found"). If a spec needs to settle before navigating (rare — see
`biolink-block-live-preview.spec.ts`), add a `waitForLoadState` at the call site,
not a new login helper.

**Admin equivalent (now migrated):** the admin demo-login (`/admin/demo-login`,
a separate endpoint) has its own shared helper `tests/Browser/login-as-demo-admin.ts`
(exports `loginAsDemoAdmin(page)`), mirroring the user pattern: it reads the
`_token` from `/admin/login` (`input[name="_token"]`, falling back to the
`csrf-token` meta) and POSTs a synthesized form, independent of any rendered
admin demo button. Both `home-showcase-editor-preview.spec.ts` and
`admin-sidebar-findbar.spec.ts` import it; do NOT re-add a button-first
`form[action$="/admin/demo-login"]` flow (that silently fails in fresh envs with
"admin demo-login form not found").
