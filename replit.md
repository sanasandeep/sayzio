# Overview

This project is a pnpm workspace monorepo integrating TypeScript and PHP/Laravel to build a comprehensive link management SaaS platform. The core offering, "1INME", aims to provide users with a powerful tool to manage, track, and brand their links, biolinks, and QR codes, effectively serving as a mini-website builder. The platform emphasizes a premium, visually engaging user experience with advanced customization options for biolinks, comprehensive analytics, and robust tracking features. The business vision is to offer a highly competitive and feature-rich solution in the link management market, catering to creators, businesses, and individuals seeking to optimize their online presence and engagement.

# User Preferences

I prefer iterative development. I want to be asked before making major changes. I do not want changes to the folder `artifacts/1inme/resources/views/vendor/`.

# System Architecture

The project is structured as a pnpm workspace monorepo. The backend utilizes PHP 8.4 with Laravel for the 1INME application, and Node.js 24 with Express 5 for API services. PostgreSQL is used for data storage, with Drizzle ORM for the API server and Laravel Eloquent for 1INME. TypeScript 5.9 is used across the Node.js parts of the monorepo, with Zod for validation and Orval for API codegen from OpenAPI specifications. esbuild is used for CJS bundling.

## 1INME Laravel App (`artifacts/1inme/`)

### Core Architecture
The Laravel application follows an HMVC (Hierarchical Model-View-Controller) pattern with modules (`Admin`, `User`, `Common`) for organizational clarity. Routes are module-specific, and authentication uses `admin` and `web` guards, supporting both password and OTP logins.

### UI/UX Design (Premium Redesign)
The UI features a premium glassmorphism design with a dark/light mode toggle. A purple color palette is consistently used. Animated elements like an aurora mesh background, floating particles, shimmer sweeps, and pulse-glow effects are integrated, with accessibility considerations for reduced motion. The typography uses Space Grotesk. Components like `.card-premium`, `.stat-card`, and various button styles reinforce the premium aesthetic.

The login page features a split layout with animated elements and social proof on one side, and a glassmorphism login form on the other. A 3-mode collapsible sidebar (Full, Icons-only, Hidden) with state persistence and smooth transitions enhances navigation. The header is glassmorphic with breadcrumb navigation, live search, notifications, and a theme toggle.

### Biolink Customization Systems

#### Block Styling System
This system allows per-block styling with 11 customizable properties (font, color, background, border, shadow, display mode, effects, padding). Ten block templates provide one-click presets. A global block theme can be applied page-wide, with per-block overrides. Styling is rendered as inline CSS and stored in JSON `settings` fields. Strict validation is applied to all style properties.

#### Image Styling System
Available for image-based blocks, this system offers 10 mask/crop shapes using CSS `clip-path`, customizable borders, 6 shadow types, and object fit controls. Inline CSS is generated for rendering, and styles are stored in JSON.

#### Trackable Block Links
Image blocks can have optional trackable destination URLs with full link attributes (target, rel, title, UTM parameters). Clicks are tracked through a dedicated redirect controller and service, capturing comprehensive analytics data (IP, browser, OS, device, referrer, country, city, UTM).

#### Block Display Settings
Per-block visibility can be controlled by schedule (dates), geographical location (continents, countries, cities), device type, operating system, browser, and browser language. Visibility rules operate on an allowlist basis.

### Super Admin Role System
Users can have a `role` column (`user` or `super_admin`). Super admins get access to a "Super Admin" section in the sidebar with Plans CRUD management. The `SuperAdmin` middleware (`App\Modules\User\Middleware\SuperAdmin`) gates routes server-side. The demo user (demo@1inme.com) is created with `super_admin` role. Plans management at `user/plans/*` allows creating, editing, and deleting subscription plans with features/limits configuration.

### File Management System
A per-user file storage system organizes files (images, videos, audio, documents) into `user-files/{user_id}/{type}s/` with UUID-based filenames. It includes quota management (`storage_limit_mb`, `max_file_size_mb`) configurable per plan. An AJAX API handles file listing, upload, deletion, and quota checks. Allowed file types are strictly defined.

### File Upload Dropzone Component
A reusable Blade partial (`file-upload-field.blade.php`) provides a drag-and-drop upload interface with progress bars, XHR uploads, and a file browser. It supports multi-file uploads for image grids/sliders.

### AJAX Block Editor
All block operations (add, edit, save, toggle, delete, reorder) are AJAX-driven, providing a fluid user experience with toast notifications and smooth animations. The preview iframe auto-refreshes on changes.

### Biolink Editor (Split Pages)
The biolink editor is split into two separate pages:
- **Blocks page** (`/user/links/{link}/blocks`): Block management with drag-and-drop reorder, grid-span width controls, add/edit/toggle/delete blocks, device preview (phone/tablet/desktop). No settings content.
- **Settings** — 4 separate URL-based pages (not JS tabs), each with shared header nav + sticky Save:
  - `/user/links/{link}/settings/appearance` — Short URL, page design (title/font/description), colors & background, button style
  - `/user/links/{link}/settings/layout` — Max width per device, page padding, block spacing
  - `/user/links/{link}/settings/block-theme` — Global block theme with templates, text, fill, border, shadow, effects sub-tabs
  - `/user/links/{link}/settings/advanced` — SEO & meta tags (title, description, keywords, robots, canonical, author, language, rating), Open Graph (title, desc, type, site name, image upload), Twitter Cards (card type, @username, title, desc), Favicon & Touch Icons (favicon + Apple Touch 180px + 512px icon with uploads), Web App Manifest/PWA (name, short name, display, orientation, theme/bg colors, start URL, categories), Badges & Branding, Custom Branding, Custom CSS/JS
  - Dynamic manifest.json: `/{alias}/manifest.json` — serves PWA manifest when enabled; checks `isAccessible()` and `manifest.enabled`
  - `/user/links/{link}/settings` redirects to `/settings/appearance`
  - Shared partials: `settings-header.blade.php` (nav tabs as `<a>` links), `settings-footer.blade.php` (sticky save)

The platform supports approximately 100+ block types across 14 categories. All HTML content is sanitized for security, and URLs are validated. Blocks can be scheduled for visibility.

#### Card Container Block
A "Card Container" block type allows grouping child blocks inside a styled card wrapper. Child blocks are stored with a `parent_id` FK referencing the card block (cascade delete). Top-level queries use `whereNull('parent_id')` to exclude children. The card container supports:
- **Layout**: configurable columns (1-4), gap, padding, border radius
- **Background**: glassmorphism (blur + opacity sliders), solid color, CSS gradient, background image, or transparent
- **Border**: color and width controls
- **Shadow**: none/sm/md/lg/xl with color
- **Child blocks**: any block type except nested cards; rendered in CSS grid inside the container. Child blocks have their own width controls (¼, ⅓, ½, ⅔, ¾, Full) that map proportionally to the card's column count.
- **Cross-container drag & drop**: Blocks can be dragged from the main block list into a card container, and from a card container back to the main list. Uses SortableJS `group` option with shared group name 'blocks'. Card containers cannot be dragged inside other cards. The `moveBlock` endpoint (`POST /user/links/{link}/blocks/{block}/move`) handles parent_id changes and re-normalizes sort_order in the source container.
- **Editor UI**: expandable card section showing nested child blocks with drag reorder (SortableJS), per-child width controls, edit/toggle/delete actions, and "Add block to card" button that opens the gallery filtered to exclude card type
- **Public render**: card wrapper with full design styles, children rendered via `@include('common.partials.biolink-block-render')` partial. Child `grid_span` (12-column) is proportionally mapped to card's column count: `round(span/12 * cols)`.
- **Data**: `BiolinkBlock` model has `children()`, `activeChildren()`, `parent()` relationships; `activeBiolinkBlocks()` on Link model excludes children

#### Plan-Gated Biolink Features
Three new plan-gated features have been added to biolink pages (controlled by `custom_branding`, `custom_favicon`, `custom_code` plan features):
- **Custom Branding**: Replace the "Powered by 1INME" footer with custom brand name, URL, and logo. Stored in `settings.biolink.custom_branding_text/url/logo`.
- **Custom Favicon**: Set a custom browser tab icon per biolink page via URL or file upload. Stored in `settings.biolink.favicon_url` and synced to `links.favicon` column.
- **Custom CSS & JS**: Inject custom CSS (in `<head>`), JS in `<head>` (before page load), and JS at end of `<body>` (after page load). Stored in `settings.biolink.custom_css/custom_js_head/custom_js_body`.
All URL fields are sanitized via `sanitizeUrl()` (http/https only). Features show PRO badge + locked upgrade prompt for plans without access.

# External Dependencies

- **Monorepo tool**: pnpm
- **API framework**: Express 5
- **Database**: PostgreSQL
- **ORM**: Drizzle ORM (API server), Laravel Eloquent (1INME)
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build tool**: esbuild
- **Frontend Frameworks**: Tailwind CSS, Alpine.js
- **Fonts**: Google Fonts CDN (Space Grotesk)
- **Tracking Pixels Integration**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora
- **Social Embeds**: Instagram, TikTok, Twitter, Pinterest, Snapchat
- **Music/Streaming Embeds**: Spotify, Apple Music, SoundCloud, Tidal, Mixcloud, Anchor FM
- **Video Platforms Embeds**: YouTube, Vimeo, Twitch, Kick
- **Integration Widgets**: Typeform, Calendly, Discord
- **Payment Gateways**: PayPal (for donation/product blocks)
- **Mapping Services**: Google Maps, Yandex Maps
- **Storage**: Local public disk, S3 (optional)