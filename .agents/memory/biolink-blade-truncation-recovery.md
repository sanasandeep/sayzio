---
name: Recovering a clean-truncated working file from git objects (read-only)
description: How to restore a file an interrupted platform write/merge cut mid-stream, without running git, and the biolink top-level→partial delegation invariant.
---

When a core file (e.g. `common/biolink.blade.php`) shows up byte-truncated mid-write (unbalanced `@forelse`/`@endforelse`, ends mid-tag), and a platform AUTO_MERGE happened (`.git/AUTO_MERGE` + `.git/ORIG_HEAD` present at the same mtime), the complete content usually still lives in git objects. Recover **read-only** without running git:

- Decompress `.git/objects/xx/rest` with python `zlib.decompress`; strip the `b"blob <len>\0"` header.
- Resolve the right blob by parsing commit→tree→path entries yourself (tree entry = `mode SP name \0 20-byte-sha`). Repo root path is monorepo-relative (`artifacts/1inme/...`), not artifact-relative.
- Confirm the truncated blob is a **clean prefix** of a complete candidate (`trunc == full[:len(trunc)]`). If so, the complete blob is verbatim-correct — write it back, zero guessing.

**Why:** an interrupted write can corrupt the *committed* HEAD blob too (not just the working tree), so "restore from HEAD" is wrong — the last complete copy may only be in `ORIG_HEAD`/an older loose blob.

**Caveat:** the last complete copy can be *stale* — `ORIG_HEAD`'s pre-merge version may predate newer block types and fail `templates:check-designs`. The intended post-refactor shape is the inline `@if/@elseif` chain ending in a **catch-all `@else` that `@include`s `common/partials/biolink-block-render.blade.php`** (the shared dispatch table covering all types). Mirror the existing card-child include's params but pass `$block` (not `$childBlock`); Blade `@include` shares the full outer scope so `$link` etc. are available.

**How to apply:** never run `git`; use zlib reads. After restoring, re-run `php artisan templates:check-designs` (must exit 0) — it is the authoritative verification that every block type has a top-level renderer.
