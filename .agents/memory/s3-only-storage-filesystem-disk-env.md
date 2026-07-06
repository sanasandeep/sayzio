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
silently wins. This is easy to miss because no code path in this app calls
`Storage::` without an explicit disk name, so the wrong default was
functionally inert here — but it's still a footgun for any future
disk-less `Storage::` call and it violates a "default filesystem = S3"
requirement literally.

**How to apply:** When a task requires a specific disk to be the *default*
filesystem (not just backing a named disk), grep `.env`/`.env.example` for
the relevant env var and update it too — don't trust the config file's
fallback argument alone.
