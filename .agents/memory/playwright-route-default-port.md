---
name: Playwright page.route default-port mismatch
description: page.route URLs carrying an explicit :80/:443 never match because the browser normalizes default ports away; bites the 1inme browser e2e when run against the :80 dev workflow.
---

# Playwright `page.route` and default ports

A `page.route(\`${APP_BASE}/pricing\`, ...)` interception where `APP_BASE`
includes an explicit default port (`http://localhost:80`) will **silently never
match**. Chromium normalizes default ports out of the request URL, so the actual
navigation request is `http://localhost/pricing` (no `:80`), and the `:80` glob
never matches. `route.fetch`/`route.fulfill` rewrites then no-op with no error —
the page serves unmodified and assertions on the rewritten content fail with a
confusing "got the server default" message (e.g. consent `data-layout` stays
`banner` instead of the forced `corner`/`modal`).

**Why:** the 1inme browser-e2e harness (`tests/Browser/run-validation.sh`) sets
`APP_URL=http://localhost:80` when the dev workflow is already serving on the
:80 proxy, but falls back to an ephemeral `http://localhost:5000` (non-default
port, preserved) when it has to boot its own server — which is what CI does. So
route-interception specs pass in CI (`:5000`) but fail locally against the dev
workflow (`:80`). `page.goto` is unaffected (the browser normalizes both sides);
only `page.route` URL matching breaks.

**How to apply:** match the document by PATH, not full origin — use a regex like
`/\/pricing(?:[?#]|$)/` (keep the `resourceType() !== "document"` continue guard
for sub-resources). Works on both `:80` and `:5000`. Don't try to "fix" it by
stripping the port from `APP_BASE` only for the route — a path regex is simpler
and robust. Non-`page.route` specs (plain `goto` + assertions) are immune, so a
single failing spec amid passing sibling consent specs is a strong signal of
this exact issue.
