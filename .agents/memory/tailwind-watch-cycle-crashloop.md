---
name: Tailwind watch-cycle crash-loop guard
description: Why the 1inme dev Tailwind watcher must self-detect crash loops and bail, not just rely on concurrently --kill-others.
---

# Tailwind watch-cycle crash-loop guard

The 1inme dev command runs `concurrently --kill-others` over two children: the
`build:watch:cycle` npm script (a `while true; do timeout -s TERM 60 vite build --watch; done`
loop) and `php artisan serve`.

**The rule:** the watch loop must self-detect a crash loop and exit non-zero. Do
NOT assume `--kill-others` alone keeps the pair healthy.

**Why:** `concurrently --kill-others` only tears the group down when a tracked
CHILD PROCESS actually EXITS. A `while true` loop never exits on its own. In the
healthy case vite runs the full ~60s window, `timeout` TERMs it, and the loop
restarts — fine. But if vite starts crashing IMMEDIATELY every cycle (bad config,
missing bin, port held), the loop busy-spins forever: the tailwind process stays
alive but rebuilds nothing, concurrently never fires `--kill-others`, and the
preview silently serves STALE CSS behind a live PHP server. Symptom: live server,
frozen styles, no error, no restart.

**How to apply:** `build:watch:cycle` times each cycle. A cycle that ran the full
window (elapsed ≥ 10s) is a normal timeout cycle → reset the fail counter, keep
looping (resilient to transient non-zero exits like 124/143). A cycle that
returned in <10s is a crash, not a timeout → count it; after 5 consecutive fast
exits, `exit 1`. That non-zero exit lets `--kill-others` tear down PHP too and
triggers a clean Replit workflow auto-restart with a fresh watcher, instead of
stranding a style-less orphan. Distinguish "process alive" from "process doing
useful work" — liveness is not health for a supervised watch loop.
