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

**How to apply:** make `loginAsDemo` independent of the DOM button — read the
`_token` the login page's own forms already carry (`input[name="_token"]`),
synthesize a form POSTing to `/user/demo-login`, and submit it. Identical result
to the shared helper, but works whether or not any demo button is rendered.
`form-builder-panel-scroll-reset.spec.ts` uses this pattern. If you touch the
shared `loginAsDemo`, prefer migrating it to the token-POST form for the same
reason.
