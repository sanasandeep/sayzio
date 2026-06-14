---
name: 1INME S3/CloudFront user-content storage
description: Gotchas wiring 1inme's public/user_files/admin_assets disks to S3 + CloudFront behind one toggle.
---

# 1INME user-content on S3 + CloudFront

The `public`, `user_files`, and `admin_assets` disks flip to S3 via a single
`USER_CONTENT_DISK=s3` env toggle (default `local`) inside `config/filesystems.php`.
The disk **names** stay the same so existing DB `disk` values and every
`Storage::disk('public'|'user_files'|'admin_assets')` call site keep resolving —
only the backing driver changes. The literal `s3` disk still exists for S3Service.

## Rule: branch on disk DRIVER, never the disk name
Several serve paths historically did `if ($file->disk === 's3') { signedUrl } else { ->path() }`.
Once a named disk is S3-backed that breaks (S3 has no local `->path()`). Resolve the
disk name, then decide by `config("filesystems.disks.{$name}.driver") === 's3'`.
**Why:** legacy rows are stamped `disk='user_files'`/`'public'`, not `'s3'`.

## Rule: the `1in.me` bucket has ACLs disabled — set NO object visibility
Object Ownership = bucket-owner-enforced. Any PutObject carrying an `x-amz-acl`
header (which Laravel adds when a disk/config has `'visibility' => 'public'|'private'`)
fails with `AccessControlListNotSupported` and `put()` silently returns `false`
(throw=false). Omit `visibility` entirely on the S3 disks and in any
`Storage::build(...)`/`writeStream` used by the migration command. Access is
governed by the bucket policy + CloudFront (public reads) and pre-signed
temporary URLs (private reads). **Symptom:** `put()` → false, but `url()`/`temporaryUrl()`
still generate fine (they only sign locally), so it looks like config is right.

## Rule: dotted bucket name needs path-style endpoint
`AWS_USE_PATH_STYLE_ENDPOINT=true` is required because `1in.me` contains dots,
which break virtual-hosted-style HTTPS (wildcard cert mismatch).

## Gotcha: Laravel auto-serve route shadows a custom /storage/{path}
Legacy public content was stored as plain `/storage/...` URLs (avatars, covers,
post images, `asset('storage/..')`, verification logos). A custom catch-all
`Route::get('/storage/{path}')` that redirects to CloudFront when the file is
absent locally is the low-touch bridge (no data migration, no touching dozens of
write sites). BUT Laravel 11's `local` disk default `'serve' => true`
auto-registers its own `storage/{path}` route (`storage.local`) pointing at the
**private** disk, registered before web.php routes, so it wins and returns 403.
Fix: set `'serve' => false` on the `local` disk. Verify with `route:list | grep storage/`
— only `storage.cdn.fallback` should remain. The fallback guards on
`config('filesystems.disks.public.driver') === 's3'` so it never loops in local mode.

## Migration command
`php artisan storage:migrate-to-s3` (`--only=`, `--force`, `--limit=`) is idempotent
(skips same-size objects) and resumable for the high-latency ap-south-2 bucket.
It builds its own local source + S3 target disks from env, so it works regardless
of the toggle, and never deletes local copies.

## Passthrough
`php artisan serve` strips env from its child `php -S`; AWS_* + USER_CONTENT_DISK
must be added to `ServeCommand::$passthroughVariables` in AppServiceProvider::boot()
or the served app falls back to local with no S3 creds (see artisan-serve-env-passthrough.md).
