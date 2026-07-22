---
name: Admin settings light-mode ak-* classes
description: Shared light-mode legibility helpers for admin settings blades and sweep gotchas
---

The `ak-*` override block in the admin layout (`admin/layouts/app.blade.php`) is the SHARED light-mode legibility helper set for all admin settings pages (originally API Keys, now Mail/SMTP, Integrations, AI Engine, Payment Gateways, Auth/Marketing/Wallet/Social-OAuth/Stats-Storage). Add `ak-*` classes alongside the dark-only Tailwind classes; never replace them.

**How to apply:**
- Tiers: `text-white` → ak-strong; `/45-65` → ak-muted; `/20-40` → ak-note; amber/emerald/red/blue tints → ak-amber/green/red/blue; inputs with `bg-white/5` → ak-input; status chips → ak-tone-green/amber/red/neutral.
- Do NOT put ak-strong on solid-colored buttons or gradient icon tiles (`bg-blue-600 text-white`, `bg-gradient…`) — their white text must stay white in light mode.

**Gotchas from a mechanical sweep:**
- Class attrs containing Blade ternaries (`{{ $x ? 'text-emerald-300' : 'text-white/50' }}`) get BOTH ak classes from a regex sweep — move the ak class inside each ternary branch instead.
- Dark-only classes also hide in PHP `match` arm strings, `'inputClass' =>` partial args, and JS-built `className` strings — grep those separately.
- Verify with the `light-mode-pairing` guard workflow + `php artisan view:cache`.
