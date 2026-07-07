---
name: Marketing SPA static prerender for SEO
description: How artifacts/1inme-com (Vite React SPA, no SSR) gets route-specific <head> tags and a sitemap without SSR.
---

The marketing site is a plain client-rendered Vite SPA (`artifacts/1inme-com`), deployed as static files with an SPA fallback rewrite (`/*` → `/index.html`). To get real per-route `<title>`/meta/canonical/OG tags in the FIRST HTML response (crawlers that don't run JS), we do NOT add SSR. Instead a `postbuild` npm script (`scripts/prerender.mjs`) runs after `vite build` and writes a separate `<route>/index.html` per route into `dist/public/`, cloned from the built root `index.html` with just the `<head>` tags swapped. Since the deploy's static file server checks for an exact file match before falling back to the SPA rewrite, these per-route files get served directly.

**Why this works / what to preserve:**
- The route registry (`src/content/seo-routes.ts`) is generated from the SAME content files each page component reads (`use-cases.ts`, `ai-products.ts`, `compare.ts`) so titles never drift from what the page actually renders — new use cases/AI products/competitors auto-register.
- Blog posts are fetched live from the DB-backed blog feed at build time; if unreachable, the script skips blog prerendering and logs a warning rather than failing the build.
- `scripts/prerender.mjs` also generates `sitemap.xml` and patches `dist/public/robots.txt`'s `Sitemap:` line to match the build's actual `BASE_PATH` (this workspace's shared multi-artifact preview mounts the site at `/1inme-com/`, not root, so a hardcoded root sitemap URL in `public/robots.txt` would be wrong there — it's only correct once deployed at its own custom domain root).
- Relative `href="./..."` asset links (favicons/manifest, written by hand in `index.html`, not rewritten by Vite's `base` config) must be rewritten to absolute paths in the prerendered head, or nested route files (e.g. `/blog/my-post/index.html`) load them from the wrong directory depth.
- Client-side `src/components/seo.tsx` only needs to keep tags in sync on further SPA navigation after hydration — the crawler-visible correctness comes from the prerender step, not this component.
