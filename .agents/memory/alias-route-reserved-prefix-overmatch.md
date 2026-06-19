---
name: Catch-all alias route reserved-prefix over-match
description: Why short links / biolinks whose alias starts with u/p/c/m/f returned 405, and the safe fix.
---

# Catch-all alias route reserved-prefix over-match

The public GET `/{alias}` catch-all in `artifacts/1inme/routes/web.php` (and its
`/{alias}/manifest.json` sibling) reserves system paths via a negative-lookahead
regex. The reserved list contained **bare single-letter tokens** (`u|p|c|m|f`).
In a negative lookahead, a bare `m` matches any alias that merely *starts with*
`m`, so `Str::random(7)` aliases beginning with u/p/c/m/f (~1 in 12) were excluded
from the GET route. Only the POST `/{alias}` route then matched the path, so the
public short-link/biolink URL returned **405 Method Not Allowed** (not 404/302).

**Why:** the tokens were meant to reserve exact segments / `/x/...` prefixes, but
without a trailing boundary they over-match prefixes within a single segment.

**How to apply:** anchor each reserved token to a segment boundary by wrapping the
alternation and requiring `(?:/|$)` after it, e.g.
`^(?!(?:user|admin|...|u|p|c|m|f|...)(?:/|$))[^/]+$`. This still reserves exact
words and real prefixes (explicit page routes are matched first anyway) and only
stops the over-match. Diagnosis tip: **405 (not 404) on a public alias = the URL
matched a route for a different method**, i.e. the GET catch-all regex excluded
the alias. Confirm by comparing a reserved-letter alias (405) vs a non-reserved
one (routes to controller).
