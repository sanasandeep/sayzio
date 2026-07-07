---
name: JSON-LD in <script type=application/ld+json> needs default JSON escaping
description: Passing JSON_UNESCAPED_SLASHES to json_encode() when embedding JSON-LD (or any JSON) inside an HTML <script> tag opens a stored-XSS breakout via </script> in user-controlled fields.
---

When embedding `json_encode(...)` output inside `<script type="application/ld+json">` (or any inline `<script>`), never pass `JSON_UNESCAPED_SLASHES`. Without slash escaping, a user-controlled string field (bio, name, description, etc.) containing literal `</script><script>...` closes the JSON-LD script tag early and injects an executable script tag — a stored XSS reachable by anyone who can set that field (e.g. profile bio).

**Why:** Found during a code-review rejection of a JSON-LD structured-data addition to a public profile page; `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` was used for "nicer" URLs in the payload, but PHP's default json_encode (no UNESCAPED_SLASHES) already escapes `/` as `\/`, which is exactly what neutralizes `</script>` breakout. Verified live: a bio of `</script><script>alert(1)</script>` rendered as inert `<\/script><script>` text once the flag was dropped.

**How to apply:** When adding any inline JSON blob to a Blade/HTML template (JSON-LD, bootstrapped JS state, etc.), use `json_encode($data, JSON_UNESCAPED_UNICODE)` (no UNESCAPED_SLASHES) or an HTML-safe helper. Prettier URLs are not worth a script-injection vector — keep default slash escaping whenever the JSON is echoed inside `<script>`.
