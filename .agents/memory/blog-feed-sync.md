---
name: Marketing blog ↔ Laravel blog sync
description: How the static 1inme.com marketing blog stays in sync with the DB-driven Laravel blog
---

The standalone marketing site (`artifacts/1inme-com`) is a static Vite SPA and
must NOT hold blog content of its own. Its `/blog` + `/blog/:slug` pages read
the DB-driven Laravel blog at runtime via a public JSON feed.

**Endpoints (Laravel, `BlogController`):**
- `GET /blogs/feed.json` — list of published posts `{data:[...]}`
- `GET /blogs/feed/{slug}.json` — single post incl. `bodyHtml` `{data:{...}}`, 404 → `{error:{code:not_found}}`
- Both are CORS-open (`Access-Control-Allow-Origin: *`) because the marketing
  site is a different origin (1inme.com → 1in.me). Payload keys are camelCase to
  match the marketing `BlogPost` interface.

**Gotchas:**
- Feed routes MUST be registered before the catch-all `/{slug}` show route, or
  `feed.json` gets captured as a post slug.
- `/api/...` is shadowed by the Express api-server in the proxy, so the feed
  lives under `/blogs/...`, not `/api/...`.
- Marketing base URL: `VITE_BLOG_API_BASE` override, else origin of `LOGIN_URL`
  (`https://1in.me`). Default is absolute because prod marketing is a separate
  domain; a relative URL only works in dev where both share the dev domain.
- Detail page renders `bodyHtml` via `dangerouslySetInnerHTML` inside a `prose`
  container (`@tailwindcss/typography` is registered in `index.css`).

**Why runtime fetch, not build-time generation:** "done" required new/edited
posts to appear without code edits; runtime fetch needs no redeploy.
