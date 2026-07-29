---
name: Browser-spec validation port race
description: Concurrent validation runs of run-validation.sh collide on the shared default port 5050
---
Every 1inme Browser spec validation runs `tests/Browser/run-validation.sh`, which boots a "dedicated" e2e server on `VALIDATION_PORT` (default 5050) and kills it on exit. Validation executes registered commands CONCURRENTLY, so two Browser specs share one server; the first finisher's teardown kills the other's server.

**Symptoms:** TimeoutError on an element, then `net::ERR_CONNECTION_REFUSED` on retry, ENOENT Playwright trace files; each spec passes when run alone.

**Fix:** give each registered Browser-spec validation command a distinct `VALIDATION_PORT` (5051/5052/5053…) via `setValidationCommand`; direct `.replit` edits are blocked.
