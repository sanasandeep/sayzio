---
name: S3-only storage default disk env override
description: Forcing filesystems.php disks to hardcode S3 isn't enough — check FILESYSTEM_DISK in .env, it silently overrides the config default.
---

Making `public`/`user_files`/`admin_assets` disk arrays unconditionally S3 in
`config/filesystems.php` does NOT make `filesystems.default` resolve to S3 if
`.env` has `FILESYSTEM_DISK=local` set explicitly — Laravel's `env()` call
picks the .env value over the config file's fallback default every time.

**Why:** `'default' => env('FILESYSTEM_DISK', 's3')` looks S3-first at a
glance, but an explicit `FILESYSTEM_DISK=local` in `.env`/`.env.example`
silently wins. `.env.example` (which CI copies) now says `s3`, while the dev
`.env` still says `local` — so a test that passes in dev can attempt real S3
in CI. A few code paths DO write to the default disk (vault attachments,
task-board attachments via `config('filesystems.default')`), so any test
that exercises those uploads must pin `config(['filesystems.default' =>
'local'])` **and** `Storage::fake('local')` — faking a *named* disk like
'public' does not intercept default-disk writes. Note Flysystem S3 `put()`
failures return false (no exception), so a mis-isolated test fails later
with a weird "path [0]"/false path, not a network error.

**How to apply:** When a task requires a specific disk to be the *default*
filesystem (not just backing a named disk), grep `.env`/`.env.example` for
the relevant env var and update it too — don't trust the config file's
fallback argument alone. To prove a test suite makes no S3 network calls,
run it with `FILESYSTEM_DISK=s3 AWS_ENDPOINT=http://127.0.0.1:9` (poisoned
endpoint) — green means offline; sweep default-disk reliance by grepping
`config('filesystems.default')` and disk-less `->store(...)`/`Storage::` calls.
