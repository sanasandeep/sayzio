---
name: AI builder automatic web-photo tier
description: Sourcing pipeline order, quota/consent rules, and test gotchas for the auto Google-image tier in the AI biolink builder.
---

Pipeline in BuilderImageSourcer::source(): uploads → OG extraction from links → automatic Google CSE web search from the description → paid generation fallback.

Rules that must hold:
- The auto search tier runs ONLY when `keptExtracted === null` (creator never opened the preview review). A reviewed-but-empty kept list is authoritative — no search.
- **Why:** the manual image-search contract is "suggestions, never auto-placed"; preview review is the consent step. Skipping preview is the only path where web photos are used directly.
- The auto tier must check `GoogleCseUsage::capReached()` BEFORE calling search — `search()` records usage but never blocks; only the manual endpoints enforced the cap originally. Forgetting this is a quota-exhaustion vector.
- preview() merges searched candidates into `extracted` when link extraction yields nothing, so the existing keep/drop UI covers them with no UI change.

Test gotchas:
- The auto tier filters images under MIN_SEARCH_DIMENSION (200px); the suite's stock 1x1 PNG silently yields zero results — build a ≥200px PNG with GD.
- source-preview routes 404 unless `AiEngineSettings::setEnabled(true)`.
- Stale `/tmp/1inme-testpg.*` dirs make the local test pg fail to start; `rm -rf` them first.
