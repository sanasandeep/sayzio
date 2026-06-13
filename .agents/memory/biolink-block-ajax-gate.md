---
name: Biolink block controller ajax gate
description: Why feature tests against biolink block add/move/apply-card must send X-Requested-With, not just postJson.
---

# Biolink block controller ajax branch is gated on `$request->ajax()`

`BiolinkBlockController::store()` / `moveBlock()` and `LinkTemplateController::applyCard()`
return their JSON (`html` / `child_html` + `parent_id` / `insert_after`) **only** inside an
`if ($request->ajax()) { ... }` branch. Otherwise they fall through to a
`redirect()->route(...)` (302 HTML page).

`$request->ajax()` checks the `X-Requested-With: XMLHttpRequest` header — it does **not**
look at `Accept: application/json`. Laravel's `postJson()` test helper sets `Accept`/`Content-Type`
but NOT `X-Requested-With`, so a plain `postJson()` to these routes gets a 302 redirect and
`->json(...)` fails with "Invalid JSON was returned from the route".

**How to apply:** when feature-testing these endpoints (or any controller using the same
`$request->ajax()` gate), send the header explicitly:
`->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson(...)`. The editor's
front-end fetch calls already set this header, so production behavior is correct.
