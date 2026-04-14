# Workspace

## Overview

pnpm workspace monorepo using TypeScript + PHP/Laravel. Each package manages its own dependencies.

## Stack

- **Monorepo tool**: pnpm workspaces
- **Node.js version**: 24
- **Package manager**: pnpm
- **TypeScript version**: 5.9
- **API framework**: Express 5
- **Database**: PostgreSQL + Drizzle ORM (API server) + Laravel Eloquent (1INME)
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build**: esbuild (CJS bundle)

## 1INME Laravel App (`artifacts/1inme/`)

**Link management SaaS platform** built with PHP 8.4 / Laravel, PostgreSQL, Tailwind CSS + Alpine.js.

### Architecture
- **HMVC Modules**: `app/Modules/{Admin,User,Common}/` with Controllers, Models, Middleware, Services, Providers
- **Routes**: `routes/modules/admin.php` (prefix `/admin`), `routes/modules/user.php` (prefix `/user`), `routes/web.php` (catch-all redirect)
- **Auth**: `admin` guard for staff/admin (`Admin` model), `web` guard for users (`User` model)
- **OTP Login**: `otps` table + `OtpService` for email/SMS OTP verification; login page has Password/OTP tabs
- **Demo Login**: `demo@1inme.com` user + `admin@1inme.com` admin; environment-gated (disabled in production); throttled OTP routes
- **Module registration**: `ModuleServiceProvider` in `bootstrap/providers.php`

### Branding & UI Design
- **Glassmorphism throughout**: Dark background `#0f0a1a`, glass cards (`bg-white/4, backdrop-filter: blur(20px), border: 1px solid rgba(255,255,255,0.08)`), purple radial glow effects
- **Purple palette**: primary `#7c3aed`, accent `#a855f7`, hover `#6d28d9`
- **Font**: Space Grotesk (Google Fonts CDN)
- **Sidebar navigation**: Both user and admin panels use sidebar-based layouts with glass-style sidebar (`rgba(15,10,26,0.8)`)
- **Active sidebar link**: `bg-purple-500/20, text-purple-400, border: rgba(124,58,237,0.3)`
- **Form inputs**: `bg-white/5 border-white/10` with `text-white`, purple focus rings
- **Status badges**: Use `bg-{color}-500/10 text-{color}-400` pattern for dark theme
- All pages (auth, user dashboard, admin panel, common/public pages) use consistent dark glassmorphism
- Mobile responsive: Both user and admin have slide-out mobile sidebar drawers

### Homepage
- Linktree-inspired colorful design: dark navy hero with animated blobs, **purple accent**, bold solid-color sections
- Hero mockup showcases biolink as a full website builder: text blocks, 2-column video/gallery layout, audio player with animated equalizer, file download + shop buttons, embed widget, social icons
- Floating badges highlight "Multi-Column" layouts and "Unlimited" blocks
- Marquee scrolling strip features content types: Multi-Column Layouts, Videos, Audio, Images, Embeds, Files, Bio Links, URL Shortener, QR Codes, Analytics
- Use-cases section: Creators category has rich multi-block mockup; other categories have standard link mockups
- Red section: "Not just links — build entire websites" with 3x3 block-type grid (Text, Images, Videos, Audio, Files, Embeds) + full-width Multi-Column Responsive Layouts card

### Current Features (Phases 1A-1C)
- Admin panel: login, dashboard, staff CRUD, user management, role/permission CRUD, plan CRUD, impersonation, password reset
- User panel: registration, login, dashboard with stats, profile management, password reset, email verification
- **Link Engine**: URL shortener (301/302 redirect types), file sharing (download page), ICS calendar event generator, VCF contact card generator
- **QR Code Generator**: per-link QR codes with customizable colors, size (100-1000px), error correction (L/M/Q/H), download in PNG/SVG, live preview
- **Projects**: organize links into color-coded projects
- **Tracking Pixels**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora, custom — rendered on biolink + password gate + file download pages
- **Link Features**: password protection, expiration dates, SEO settings (OG image + favicon upload), UTM parameter builder, click tracking
- **Link Protection**: country restrictions (ISO codes, fail-closed GeoIP), device targeting (desktop/mobile/tablet)
- **Analytics**: per-link stats (clicks over time, browsers, OS, devices, referrers, countries)
- **Redirect**: `/r/{alias}` routes for link resolution with tracking, device/country enforcement
- **File Download Page**: branded download page with file preview (images), file type icon, size display, download button
- **Admin link management**: browse all user links, filter by type/status/user, toggle active, bulk enable/disable/delete

### Biolink Blocks System (~99 block types, 14 categories — 66biolinks feature parity)
- **Block Editor**: `/user/links/{id}/blocks` — 66biolinks-style tabbed editor (Settings/Blocks tabs), phone mockup preview, accordion page settings
- **14 Block Categories**: Basic Content (14), Media (10), Social & Profiles (12), Music & Streaming (6), Video Platforms (7), Contact & Lead Gen (5), Interactive & Engagement (8), Business & Monetization (9), Utility & Functional (9), Layout & Design (7), Integrations & Embeds (8), Files & External (3), Maps & Location (2), Digital Identity (2)
- **Key Block Types**: Link, Link (Big), Heading variants (Gradient/Logo/Morph), Paragraph (Rich Text), Lists (Bullet/Numbered/Pricing), Alert, Badge, Image Grid/Slider, Video/Audio/PDF/PPTX/Excel, Socials (Multi/Custom), social embeds (Instagram/TikTok/Twitter/Pinterest/Snapchat), Spotify/Apple Music/SoundCloud/Tidal/Mixcloud/Anchor FM, YouTube/Vimeo/Twitch/Kick, Email/Phone collectors, Contact Form, WhatsApp Widget/Item, FAQ/FAQ V2, Poll, Quiz, Testimonials, Review, Timeline/Timeline (Staged), Product, Service, Catalog, Market, Price, Donation, Coupon, One Time Offer, PayPal, Countdown, Progress bars, Pie Chart, QR Code, Share, CTA Button, Notification, Nav Menu, Ticker, Card Slider, Scroll Cards, Profile Cards (V1-V4), Custom HTML, Iframe Embed, Typeform, Calendly, Discord, Facebook/Reddit/Telegram posts, File download, External Item, Markdown, Google Maps, Yandex Maps, VCard, Avatar, Spacer
- **Public Rendering**: `/{alias}` renders all block types with full styling (glassmorphism, Font Awesome, Alpine.js for interactivity)
- **Page Settings**: Background (color/gradient/image), font family, font color, button color/style (rounded/pill/square/outline/shadow)
- **Security**: HTML sanitized (strip_tags + attribute/protocol stripping on both store and update); all URL fields validated to `http(s)://` scheme; nested URL sanitization (platforms, items, cards, groups); event handler attributes stripped
- **Scheduling**: Blocks support start_date/end_date for visibility scheduling
- **Model**: `BiolinkBlock` (link_id, type, settings JSON, sort_order, is_active, start_date, end_date)
- **Controller**: `BiolinkBlockController` handles CRUD, reorder, toggle, page settings
- **Form partials**: `block-settings-form.blade.php` (per-type edit forms), `socials-form.blade.php` (social platform picker)

### Database Tables
`roles`, `permissions`, `role_permissions`, `admins`, `plans`, `users` (has `mobile` column), `projects`, `domains`, `pixels`, `links`, `link_pixels`, `link_clicks`, `file_links`, `ics_data`, `vcf_data`, `biolink_blocks`, `otps`, `sessions`, `cache`, `jobs`

### Default Admin Credentials
- Email: `admin@1inme.com`
- Password: `password`

### Key Commands (from `artifacts/1inme/`)
- `php artisan serve --host=0.0.0.0 --port=5000` — run dev server
- `php artisan migrate` — run migrations
- `php artisan db:seed` — seed database
- `php artisan view:clear` — clear compiled views

## Key Commands (Monorepo)

- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- `pnpm --filter @workspace/api-server run dev` — run API server locally

See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details.
