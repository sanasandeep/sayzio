---
name: Gate meta-tests via subprocess + install-cache warmth
description: How to prove a CLI validation gate can't go false-green, and the env quirks hit while doing it (bash background jobs die between tool sessions).
---

# Gate meta-tests: drive the real gate as a subprocess, both directions

A validation gate (script that `process.exit`s) can silently go false-green if its
exit-code plumbing or cwd resolution regresses. The proof pattern (used by
check-factory-columns and now the dialer-typecheck gate test):

- Spawn the REAL gate script as a subprocess (`spawnSync(tsxBin, [gateScript])`),
  never import its logic — the exit-code path is exactly what's under test.
- Assert BOTH: clean fixture → exit 0, poisoned fixture → non-zero. Poison via a
  temp file removed in `finally` (+ a defensive pre-clean so a leaked poison file
  from an aborted run can't fail the clean case for the wrong reason).
- Assert `status !== null` on the failing run (exited, not killed by timeout).

**Install-cache-gated gates:** if the gate lazily runs a slow install (npm ci)
when a stamp is stale, the meta-test must `describe.skipIf(cache cold)` and also
assert the clean run took the cached path (output contains "skipping npm ci"),
so a slow install can never run mid-test. Warm the cache once via the gate's own
command.

**Why:** the dialer gate was only manually verified; nothing automated proved a
poisoned file fails it.

**Environment quirk (Replit bash tool):** background processes do NOT survive
between bash tool invocations — `nohup ... &` and even `setsid` jobs get killed
when the session ends, leaving zero-byte logs and no exit file. For long installs
use foreground `npm install` (resumable, keeps node_modules) under `timeout`,
NOT `npm ci` (wipes node_modules each attempt, can never finish across retries).
The sayzio-dialer-standalone npm install completes in ~1 min with a warm npm
cache; write the gate's sha256 lockfile stamp manually afterwards so the gate
takes its cached path.
