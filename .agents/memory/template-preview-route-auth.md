---
name: Admin template-preview route auth quirk
description: Why feature tests for admin.templates.preview must authenticate BOTH the admin and web guards.
---

The admin template-preview route (`admin.templates.preview`, TemplateController::preview)
is gated by AdminAuth + CheckPermission, which both read `Auth::guard('admin')`. But the
controller then passes `$request->user()` into `TemplateService::buildPreviewLink(... User $user ...)`,
and `$request->user()` resolves against the **default** guard (`web`), not `admin`.
`App\Modules\Admin\Models\Admin` does NOT extend `App\Modules\User\Models\User`, so a pure
admin-guard session yields a null/Admin `$request->user()` → TypeError → the controller's
catch renders the "This template can't be previewed" error fallback (still HTTP 200).

**Why:** the working previewer in production is a web User who *also* holds admin access
(dual session). A valid template only renders blocks when `$request->user()` is a real
`App\Modules\User\Models\User`.

**How to apply:** in a feature test, authenticate both guards with the web one LAST so it
owns the default resolver:
```php
$this->be($admin, 'admin');  // satisfies AdminAuth + CheckPermission
$this->be($webUser, 'web');  // makes $request->user() a real User for buildPreviewLink
```
A single `actingAs($admin, 'admin')` would set shouldUse('admin') and the preview would
always fall into the error view.

Distinctive markers: error fallback contains the literal `This template can` (apostrophe is
`&rsquo;`); a successfully rendered biolink page contains `data-block-id=` per rendered block.

See also `running-1inme-feature-tests-locally.md`: locally DB_DATABASE points at the shared
`postgres` DB (not force-overridden to `1inme_testing`), and migrate:fresh is blocked by the
CommandStarting guard — so RefreshDatabase tests like TemplatePreviewRendersTest can't be run
locally; trust the CI sharded runner.
