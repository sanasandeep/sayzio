---
name: Tailwind scans Laravel compiled-view cache
description: Why purple/old arbitrary classes leak into 1inme's compiled CSS after a blade color sweep
---

After a blade-wide color/class sweep in `artifacts/1inme`, the Vite/Tailwind v4
build can STILL emit the old arbitrary classes (e.g. `bg-[#7c3aed]`,
`hover:bg-[#6d28d9]`) even though every source blade is clean.

**Why:** `@tailwindcss/vite` auto-detects content and also scans Laravel's
compiled-blade cache in `storage/framework/views/*.php`. Those cached files
still contain the pre-sweep class tokens, so Tailwind regenerates the stale
utilities into `public/build/assets/app-*.css`.

**How to apply:** after editing class names/colors in blade, run
`php artisan view:clear` (or `rm -f storage/framework/views/*.php`) BEFORE
`pnpm run build`, then grep the compiled CSS to confirm. `public/build` is
gitignored and uses `@vite` (no CDN), so an un-rebuilt or stale-cache build is
what users actually see.
