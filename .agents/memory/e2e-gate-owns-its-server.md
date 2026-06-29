---
name: e2e gate must own a dedicated stable server
description: Why the 1inme browser-e2e gate boots its own server instead of reusing the :80 dev workflow, and how to read the collapse signature.
---

The 1inme browser-e2e gate (`tests/Browser/run-validation.sh`) must run against a
standalone `artisan serve` it starts itself, NOT against the `:80` dev-workflow
proxy.

**Why:** the dev workflow boots its PHP server under `concurrently --kill-others`
tied to a 60s-cycling `vite build --watch`. When the e2e suite runs in parallel
with the heavy Expo/Metro validation jobs on one constrained box, a vite rebuild
spikes/crashes under the load and `--kill-others` then kills the PHP server
mid-suite. Reusing that server made the whole gate non-deterministic: it passed
only when `:80` happened to be down (so the script booted a clean standalone
server) and collapsed when `:80` was up.

**Collapse signature (read it, don't chase individual specs):** one bloated
long-running test, then `(retry #1) (0ms)` worker crashes, then every following
spec — including lightweight marketing/cookie-consent pages — failing on the 30s
nav/action timeout. That is the app server vanishing, not flaky test logic. A
premature `mark_task_complete` snapshot will report these first-attempt `✘` marks
before retries land; read the actual log for the real `✘`-vs-`✓ retry` pattern.

**How to apply:** the dedicated server uses a port that is deliberately NOT 5000
(the dev workflow's own port) so the two coexist instead of colliding — default
5050, override via `VALIDATION_PORT`. An explicit `APP_URL` env still overrides
for ad-hoc local runs; only the unattended default boots its own server. Do not
"fix" this by re-adding a reuse-:80 branch — that reintroduces the --kill-others
teardown.
