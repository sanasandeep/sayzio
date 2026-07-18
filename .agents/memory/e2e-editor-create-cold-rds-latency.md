---
name: e2e editor create-POST cold RDS latency
description: Why 1inme Browser (Playwright) editor/create flows must wait on the create POST response, not just assert the resulting row/modal.
---

Every "click Save → assert the new row/modal appears" step in a 1inme Browser
spec that writes to the DB can intermittently blow the default 10s assertion
timeout. The cause is the distant AWS RDS: the FIRST write to a given controller
in a run pays cold-controller compile + a cross-region INSERT round-trip, which
together sometimes exceed 10s. Symptom in the snapshot: the submit button is
still stuck on "Sending…"/"Saving…" (disabled) when the assertion times out.

**Why it looks flaky:** with `workers:1`, whichever spec/POST runs FIRST is cold;
a warm second run passes. So the failure hops between store↔booking and between
category↔product↔service↔order run-to-run — a whack-a-mole if you only bump the
one assertion that happened to fail.

**How to apply:** wrap EVERY cold first-write per controller with
`page.waitForResponse(pred, {timeout:60_000})` created BEFORE the Save click,
awaited right after, then assert with `{timeout:15_000}`. Match on url substring
+ `method()==='POST'`, and EXCLUDE `/reorder` (e.g. `/\/products(\?|$)/`). Cover:
store editor `/categories` + `/products`, booking editor `/service-booking/services`,
public `/sm/{alias}/order`; the public `/sb/{alias}/book` is already awaited inside
`page.evaluate`. Second writes to an already-warm controller (e.g. booking rule
POST) don't need it. Also give heavy authenticated editor `page.goto`s
`{timeout:120_000}` — the config's 45s navigationTimeout cap breaks the retry.
