# SEO Strategy

## In scope
- Public marketing pages on the Laravel app (`artifacts/1inme`)
- Public content pages on the Laravel app, including blogs, creator profiles, public resumes, and public biolink/link surfaces when indexable
- Public marketing pages on the standalone marketing site (`artifacts/1inme-com`)

## Out of scope
- Authenticated dashboard and account areas (`/user/**`)
- Admin/back-office areas (`/admin/**`)
- API endpoints except where they directly affect crawlability assets such as `robots.txt`, `sitemap.xml`, or public content feeds
- Mobile app (`artifacts/1inme-mobile`)

## Target audience
- Creators, businesses, and individuals who want link management, biolinks, QR codes, branded short links, and public profile/discovery features.

## Primary keywords
- Link in bio
- Biolink builder
- QR code generator
- Branded short links
- Creator profile page
- Digital business card

## Durable notes
- `artifacts/1inme` is primarily SSR for public pages.
- `artifacts/1inme-com` is a Vite SPA with client-side route metadata, so route-level SEO depends on prerendering or SSR if deep pages need unique crawlable metadata.
- `sayzio.app` is treated in code as the primary brand domain, while `1in.me` is also live on some public surfaces.

## Dismissed categories
- (None yet)
