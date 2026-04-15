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

### Branding & UI Design (Premium Redesign)
- **Dark/Light Mode Toggle**: CSS custom properties on `:root` / `html.light-mode`, toggled via Alpine.js, persisted in `localStorage('1inme_theme')` as `'dark'`|`'light'`
  - Shared theme partial: `common/partials/theme-styles.blade.php` (CSS vars + light-mode JS overrides)
  - Toggle component: `common/partials/theme-toggle.blade.php` (pill switch with sun/moon icons)
  - Default: dark mode; pre-render script prevents flash
- **Premium Glassmorphism**: Dark bg `#06010f` / light `#f0edf6`, `backdrop-filter: blur(40px) saturate(1.4)`, animated aurora mesh background (`bg-mesh::before/::after` with CSS `aurora` keyframe 25s)
- **Animated Effects**: Aurora mesh (25s), floating particles (JS-generated, 12-20 per layout), shimmer sweep (4s), pulse-glow (3s box-shadow), gradient-shift, float-slow (20s/28s); all gated by `@media (prefers-reduced-motion: reduce)`
- **Purple palette**: primary `#8b5cf6`, accent `#a78bfa`, CTA gradient `135deg #8b5cf6 → #7c3aed → #6d28d9`
- **Font**: Space Grotesk (Google Fonts CDN), antialiased rendering
- **Component classes**: `.card-premium` (`::before` gradient mask border, translateY(-3px) hover), `.stat-card` (`::before` accent bar + `::after` glow overlay on hover, CSS vars `--stat-accent/--stat-glow`), `.btn-primary` (`::before` shimmer on hover), `.btn-ghost`, `.badge`, `.gradient-text`, `.glow-icon`, `.shimmer`, `.upgrade-card`
- **Login page**: Split layout — left: animated floating orbs + gradient "built for growth" text + feature bullets with glow icons + social proof; right: glassmorphism login form
- **Sidebar (3-mode collapsible)**: Full (260px), Icons-only (72px), Hidden (0px); 3 direct-switch buttons in sidebar header; state persisted in `localStorage('1inme_sidebar')`; smooth CSS transitions (0.35s cubic-bezier); tooltips on hover in icons-only mode; user avatar with gradient ring; upgrade card auto-hides in collapsed modes; responsive via `matchMedia` listener
- **Header**: Glassmorphism with gradient glow line at bottom; breadcrumb navigation (supports `@section('breadcrumb_parent')`); live search box (expandable on focus, Enter to search); notification bell with badge dot; theme toggle; "New Link" CTA button; hamburger to restore hidden sidebar
- **Dashboard**: Gradient-text greeting, shimmer stat cards with glow-icon accents, premium recent links + quick actions cards, animated progress bar
- **Links page**: Colored type badges, theme-consistent filter bar, premium card styling, themed action buttons
- **Biolink editor**: card-premium accordion sections with colored icon headers, themed customization form using `.theme-input`, premium phone preview with purple glow frame, glassmorphism add-block modal
- **Form inputs**: `.theme-input` class with focus ring + box-shadow, all using CSS custom properties
- All pages include theme-styles partial; mobile responsive with slide-out drawers; consistent CSS variable usage throughout

### Block Styling System
- **Per-block styling**: 11 customizable properties (font family/size/weight/style, text color, bg color/image/opacity, border style/width/color/radius, shadow type/color/blur/offsets, display mode card/content, effects glass/gradient-border, glass blur/opacity, padding)
- **10 Block Templates**: Minimal, Clean Card, Glassmorphism, Neon Glow, Gradient Border, Bold Solid, Frosted, Outlined, Neumorphic, Pill — one-click apply presets
- **Global Block Theme**: Page-level default styling in Settings > Global Block Theme, with "Apply to all blocks" checkbox; saved to `settings['biolink']['block_theme']`
- **Style merging**: Global theme (if apply_to_all) → per-block `_style` overrides; computed via `BiolinkBlock::getBlockStyle()` → `buildInlineStyle()`
- **UI**: 5-tab styling panel (Templates / Text / Fill / Border / FX) in both per-block edit drawer and page settings
- **Security**: Strict per-property validation (enums, numeric bounds, color regex, URL protocol allowlist, font name sanitization) in `sanitizeBlockStyle()`
- **Rendering**: `biolink.blade.php` wraps styled blocks in `<div class="block-styled" style="...">` with computed inline styles
- **Data storage**: Per-block in `settings['_style']`, global in `settings['biolink']['block_theme']`

### Image Styling System
- **Per-image styling**: Available for `image`, `image_grid`, `image_slider`, `image_slider_v2` blocks
- **Mask/Crop shapes**: 10 options — None, Rounded, Circle, Square, Diamond, Hexagon, Octagon, Star, Blob, Arch (using CSS clip-path)
- **Border**: style (solid/dashed/dotted/double), width, color, custom radius
- **Shadow**: 6 types — Soft, Hard, Glow, Neon, Drop Shadow (CSS filter), None — with color/blur/offset/spread controls
- **Object fit**: cover, contain, fill, none
- **UI**: Collapsible "Image Styling" section in block editor via `image-style-settings.blade.php` partial
- **Data storage**: `settings['_image_style']` JSON on block; sanitized via `sanitizeImageStyle()`
- **Rendering**: `BiolinkBlock::buildImageInlineStyle()` generates inline CSS; `MASK_CLIP_PATHS` constant for shape definitions

### Trackable Block Links
- **Optional links**: Any image block can have a destination URL with full link attributes
- **Attributes**: URL, target (_blank/_self), rel (noopener/nofollow/noreferrer/sponsored/ugc), title/tooltip
- **UTM parameters**: utm_source, utm_medium, utm_campaign, utm_term, utm_content — appended to destination URL
- **Click tracking**: Clicks route through `/{alias}/b/{blockId}` → `RedirectController::handleBlockClick()` → `LinkTrackingService::trackBlockClick()`
- **Tracking data**: Same as page clicks (IP, browser, OS, device, referrer, country, city, UTM) + block_id, block_type, destination_url in `link_clicks` table
- **UI**: Collapsible "Trackable Link" section in block editor via `block-link-settings.blade.php` partial
- **Data storage**: `settings['_link']` JSON on block; sanitized via `sanitizeLinkSettings()`

### Block Display Settings
- Per-block visibility controls in `user/links/partials/block-display-settings.blade.php`
- Collapsible section with schedule (start/end dates), continents, countries, cities, devices, OS, browsers, and browser languages
- Uses allowlist pattern: empty array = show to all; populated = only show to those values
- Data stored in `settings['_visibility']` JSON on `BiolinkBlock` model

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
