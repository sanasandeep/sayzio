---
name: Production APP_URL & blog-feed base resolution
description: How the live domain reaches Laravel APP_URL at deploy time and how the marketing prerender finds the blog feed.
---

**Rule:** The committed `artifacts/1inme/.env` keeps `APP_URL=http://localhost:5000` forever. In production, the artifact.toml run command derives `APP_URL` at boot: an explicit non-localhost `APP_URL` deployment secret always wins; otherwise it prefers a brand domain (sayzio.app, then 1in.me) present in `REPLIT_DOMAINS`, then the first `REPLIT_DOMAINS` entry, last resort sayzio.app.

**Why:** Absolute URLs (sitemap, OG tags, emails, `/blogs/feed.json` URLs) generate from APP_URL; Laravel's Dotenv is immutable, so an exported process env var beats the .env value (same mechanism as the existing `APP_ENV=production` run-env override). The brand list must stay in lockstep with `PlatformHosts::PLATFORM_DOMAINS`.

**How to apply:** Never "fix" prod URLs by editing the committed .env; adjust the derivation in the 1inme artifact.toml production run (and remember direct artifact.toml edits are blocked — write a sibling temp file and use `verifyAndReplaceArtifactToml`).

**Prerender side:** `artifacts/1inme-com/scripts/prerender.mjs` tries blog-feed bases in order: `VITE_BLOG_API_BASE`, sayzio.app, 1in.me, then `REPLIT_DOMAINS`/`REPLIT_DEV_DOMAIN` hosts (previous deployment serves during a re-deploy build; the dev workspace domain serves the same shared-RDS feed in dev builds). Feed unreachable ⇒ blog routes skipped with a warning, never a failed build.
