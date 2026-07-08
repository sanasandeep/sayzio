---
name: e2e fixture aliases on shared RDS
description: Browser e2e fixtures keyed by a fixed alias collide across parallel task environments sharing the RDS
---

Rule: browser e2e specs that seed fixtures into the shared RDS must use a
PER-RUN unique alias/handle (e.g. `e2e-foo-<ts><pid>`), never a fixed one,
and prune stale (>2h) fixtures matching the prefix in the seeder.

**Why:** isolated task environments all point at the same RDS. Two
environments running the same spec concurrently delete/recreate the same
fixture link; the preview/public render then contains the OTHER run's block
ids, so id-based locators time out. Signature: seeded N blocks but the row
has more, with interleaved ids and block types the local seed never creates.

**How to apply:** when an e2e spec seeds by a hardcoded alias/email/handle
and shows "element not found by id" flakes that survive clean re-runs,
check the DB row for foreign/extra child rows first — it's cross-environment
contention, not app code. Fix in the seeder, not with longer timeouts.
