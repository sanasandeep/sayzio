---
name: User sidebar dual nav blocks
description: The 1INME user layout renders the sidebar menu twice; both must stay in sync.
---

`artifacts/1inme/resources/views/user/layouts/app.blade.php` contains TWO
independent renderings of the user navigation that list the same items:

1. **Desktop aside** — full sidebar with `<span class="nav-label">`,
   `<span class="sidebar-tooltip">`, per-item tint styles, and groups that stay
   open when `open || sidebarMode === 'icons'` (so icon-mode collapses still show items).
2. **Mobile drawer** — compact links (no tint, no tooltip) inside `x-show="mobileMenu"`;
   groups use `x-show="open"` only.

**Why:** Any nav change (add/remove/regroup an item, change a gate) must be applied
to BOTH blocks or the desktop and mobile menus silently diverge. The mobile block also
carries a couple of mobile-only items (Creator Profile, Linked identifiers) that have no
desktop counterpart — don't "fix" that by deleting them.

**How to apply:** When editing the sidebar, grep the file for the route name; expect
~2 hits (one per block). Both nav regions should keep matching `@if`/`@endif` balance.
Keep every gate identical between the two copies: `$__can[...]`, plan gate
(`api_access`), `AiEngineSettings::isEnabled()`, and the Ask Coach gate.
