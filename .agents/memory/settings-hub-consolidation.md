---
name: Consolidated user Settings hub
description: How the /user/settings/{tab} hub is wired and how to add/move a settings surface without breaking it
---

The Sayzio user account/settings surfaces are consolidated into ONE tabbed,
deep-linkable hub at `/user/settings/{tab}` (Profile, Creator, Security,
Connected Accounts & Apps, Integrations, Domains, Notifications, Billing &
Identity, Developer/API, Verification & Badges).

**Wiring**
- Tab/sub-tab metadata + route-derived active state: `App\Modules\User\Support\SettingsTabs` (`tabs()`, `visibleTabs()`, `matches()`, `activeKey()` — all parameterless/static, safe to call from blade with no DB).
- Shell layout: `resources/views/user/layouts/settings.blade.php` (extends `user.layouts.app`, self-contained inline `<style>`, blue `--color-primary-*`). Child views use `@extends('user.layouts.settings')` + `@section('settings-content')`.
- Routes: `routes/modules/user.php` — real routes were REPOINTED in place under `settings/*` keeping their route NAMES/controllers/middleware; a "Settings hub" block at the END maps `settings`→profile and adds ~15 any-verb `Route::redirect` for legacy GET paths.

**Rules when adding or moving a settings surface**
- Keep the existing route NAME and controller; only change the URL path to live under `settings/*`. Callers use `route()` by name so they don't break.
- Register the surface in `SettingsTabs` (a tab or a sub-tab) or it won't appear/highlight.
- `Route::redirect` for legacy paths MUST stay placed LAST in the file (any-verb) so real POST/PUT/DELETE routes win; redirect destinations are literal `user/settings/...` (source auto-gets the `user` prefix, dest does not).
- Sidebar lives in `user.layouts.app.blade.php` and has TWO parallel navs (desktop aside + mobile drawer) — edit in lockstep (see user-sidebar-dual-nav). Both show a single "Settings" entry (fa-sliders) whose active state = `SettingsTabs::activeKey() !== null`.

**Mobile parity**: `artifacts/1inme-mobile/app/(tabs)/profile.tsx` `SETTINGS_PAGES` is the mobile "hub" (a grouped list on the Profile tab), ordered to mirror the web tab order. It is NOT a tabbed screen — parity was done by list ordering, not a new hub screen.

**Kept out of the hub on purpose**: coin Wallet (top-level), Email history, and (mobile) Linked identifiers stay as standalone entries; billing wallet/invoice cards not added to the Billing tab (scope).
