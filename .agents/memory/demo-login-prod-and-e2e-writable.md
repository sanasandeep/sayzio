---
name: Demo login in production & the shared writable demo account
description: How the one-click demo logins work, why demo@1inme.com must stay writable, and the safe way to build a public read-only demo.
---

# Demo login & the shared demo account

**Demo logins are public, passwordless POSTs.** `POST /user/demo-login` (route `user.demo.login`) and `POST /admin/demo-login` (route `admin.demo.login`) call `Auth::login()` / `Auth::guard('admin')->login()` directly — no password is checked. The buttons live in `public/partials/auth-modal.blade.php` and `user/auth/login.blade.php`. They were historically gated `if (app()->environment('production')) abort(404);` (dev-only). The 1inme owner explicitly asked to enable BOTH in production (informed, warned, overrode the recommendation), so that guard was removed from both controllers. Reversing = re-add the env guard (or hide the blade buttons in prod).

**Security reality of enabling them in prod:** the user demo logs into `demo@1inme.com` (which carries the `user-admin` role); the admin demo logs into `official1inme@gmail.com` **super-admin**. Enabling the admin one publicly = anyone on the internet gets full platform admin. The `BlockReadonlyDemoWrites` guard does NOT cover admin surfaces by design.

**Non-obvious constraint — do NOT flag demo@1inme.com read-only.** `demo@1inme.com` (id 3, `is_readonly_demo=false`) is the SAME account the browser e2e gate logs into (fast-login via the demo-login POST), and several gated specs perform WRITES as it (e.g. palette-dnd asserts an in-place block insert). `BlockReadonlyDemoWrites` keys purely on the `is_readonly_demo` flag and is env-independent, so setting the flag true on this account would block those e2e writes and break the gate. A genuinely safe public read-only demo therefore needs a SEPARATE `is_readonly_demo=true` account with the demo button pointed at it — not a flip of this shared one. (The middleware docblock even references a distinct `demo@sayzio.app` example account.)

**Deploy note:** these are code changes — they only reach the live site after a Republish. `dev==prod` share one RDS, so any DB-level account change (e.g. password, role) is immediate on live, but controller/blade changes are not.
