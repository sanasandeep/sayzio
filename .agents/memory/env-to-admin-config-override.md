---
name: Bringing env-only services under admin control
description: How to make an env-only third-party credential admin-editable in the 1inme Laravel app without touching every call site.
---

# Env-only → admin-editable pattern (1inme Laravel)

To bring a service whose credentials were read from `env()`/`config()` under
admin control, follow the `MailSettings` / `PlatformServiceSettings` pattern:

1. Store values in `app_settings` (key/value); encrypt secrets with `Crypt`,
   mask to last-4, getter fallback order **admin → config → env**.
2. Add an `applyRuntimeConfig()` that pushes the *effective* values back into
   `config('services.*')` (and for storage, `config('filesystems.disks.*')` +
   `Storage::forgetDisk`) and call it best-effort from `AppServiceProvider::boot()`.

**Why:** the existing readers (review adapters, `GoogleContactsProvider`) all do
`config('...') ?: env('...')`. Overriding config at boot means they pick up the
admin value automatically — **no need to edit the readers themselves**. This is
the same reason `MailSettings` never edits individual `Mail::` call sites.

**How to apply:** only override when an admin value actually exists
(`hasAdminValue()` guard) so the pure env/config defaults stay intact when
nothing is configured. For `php artisan serve`, also add the env var names to
`ServeCommand::$passthroughVariables` in `boot()` or the child `php -S` strips
them and the env fallback breaks.

**Where:** `app/Services/Integrations/PlatformServiceSettings.php` (Google
Places, Trustpilot, Google Contacts OAuth, S3 storage). The admin Integrations
hub (`IntegrationsController` + `IntegrationCatalog`) is the consolidated UI.
