---
name: 1inme Laravel Vite/Tailwind compiled assets
description: How the 1inme Laravel app serves Tailwind v4 — compiled via Vite (@vite), not the CDN; the dev-env manifest gotcha and the deploy build requirement.
---

# 1inme Laravel Tailwind = compiled Vite v4 (no CDN)

The 1inme Laravel Blade views use `@vite(['resources/css/app.css','resources/js/app.js'])`,
NOT the old `https://cdn.tailwindcss.com` Play CDN. Tailwind v4 config lives in
`resources/css/app.css` `@theme {}` (font `--font-sans: 'Space Grotesk'`, primary
palette `--color-primary-50..900`). `@source '../**/*.blade.php'` scans every view, so
no purge/safelist worry. The Google-Fonts `<link>` for Space Grotesk and the Font Awesome
CDN are separate and stay; only the Tailwind CDN `<script>` + inline `tailwind.config` were
removed.

## Dev-env manifest gotcha (important)
`public/build` and `public/hot` are **gitignored**, and the dev workflow runs
`php artisan serve` — there is **no Vite dev server**. So a fresh/isolated task env starts
with NO `public/build/manifest.json`, and every page 500s with
"Unable to locate file in Vite manifest" until you run a one-off build:
`cd artifacts/1inme && npx vite build` (or `pnpm --dir artifacts/1inme run build`).
**How to apply:** if 1inme pages 500 right after env setup or pulling, build Vite assets first.

## Deploy build must compile assets
`.replit-artifact/artifact.toml` `[services.production.build]` runs
`pnpm install --frozen-lockfile && pnpm --dir artifacts/1inme run build` before the composer/
artisan steps so the manifest exists in production. The 1inme `package.json` has **no `name`**,
so filter by directory (`pnpm --dir artifacts/1inme`), not `--filter @workspace/...`.
**Why:** without this the deployed app would 500 on the missing Vite manifest.

**Browser e2e runs the COMPILED bundle:** run-validation.sh only builds when
`public/build/manifest.json` is missing — after editing anything under
`resources/js/`, run `pnpm run build` first or the spec exercises the stale
bundle (symptom: page behaves like your JS change never happened).
