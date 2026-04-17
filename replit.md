# Overview

This project is a pnpm workspace monorepo for "1INME," a comprehensive SaaS platform for link management. It integrates TypeScript and PHP/Laravel to offer robust tools for managing, tracking, and branding links, biolinks (mini-websites), and QR codes. The platform targets creators, businesses, and individuals, providing a premium user experience, advanced biolink customization, detailed analytics, and extensive tracking features to optimize online presence and engagement.

# User Preferences

I prefer iterative development. I want to be asked before making major changes. I do not want changes to the folder `artifacts/1inme/resources/views/vendor/`.

# System Architecture

The project employs a pnpm workspace monorepo structure. The backend consists of a PHP 8.4 Laravel application (1INME) and Node.js 24 Express 5 API services. PostgreSQL is the primary data store, utilizing Drizzle ORM for Node.js services and Laravel Eloquent for 1INME. TypeScript 5.9 is used throughout the Node.js components, with Zod for validation and Orval for API codegen from OpenAPI specifications. esbuild handles CJS bundling.

## 1INME Laravel App (`artifacts/1inme/`)

### Core Architecture
The Laravel application follows an HMVC pattern with `Admin`, `User`, and `Common` modules. Authentication uses `admin` and `web` guards, supporting password and OTP logins.

### UI/UX Design
The UI features a premium glassmorphism design with dark/light mode, a purple color palette, Space Grotesk typography, and animated elements like an aurora mesh background and pulse-glow effects. Navigation includes a 3-mode collapsible sidebar, a glassmorphic header with breadcrumbs, live search, and notifications.

### Biolink Customization Systems
- **Block Styling System**: Allows per-block styling with 11 properties, 10 templates, and global themes with overrides. Styles are rendered as inline CSS and stored in JSON.
- **Image Styling System**: Offers 10 mask/crop shapes, customizable borders, 6 shadow types, and object fit controls for image blocks.
- **Trackable Block Links**: Image blocks can have trackable destination URLs with comprehensive analytics capture (IP, browser, OS, device, referrer, UTM).
- **Block Display Settings**: Controls per-block visibility based on schedule, geographical location, device type, operating system, browser, and language.

### Super Admin Role System
Introduces a `super_admin` role with access to a "Super Admin" section for Plans CRUD management, gated by `SuperAdmin` middleware.

### File Management System
A per-user file storage system organizes files into `user-files/{user_id}/{type}s/` with UUIDs, including quota management configurable per plan. An AJAX API handles file operations.

### File Upload Dropzone Component
A reusable Blade partial for drag-and-drop file uploads with progress bars, XHR, and a file browser.

### AJAX Block Editor
All biolink block operations (add, edit, save, toggle, delete, reorder) are AJAX-driven for a fluid user experience. The preview iframe auto-refreshes.

### Biolink Editor (Split Pages)
The biolink editor is split into:
- **Blocks page**: Block management with drag-and-drop reorder, grid-span width controls, and device preview.
- **Settings pages**: Four distinct URL-based pages for `appearance`, `layout`, `block-theme`, and `advanced` settings. Features include SEO/meta tags, Open Graph, PWA, share button/QR code configuration, navigation menu, auto page translation, badges, branding, and custom CSS/JS injection.

#### Card Container Block
A "Card Container" block type allows grouping child blocks inside a styled, customizable card. It supports configurable layouts, backgrounds, borders, and shadows. Child blocks have independent width controls and can be drag-and-dropped between the main block list and card containers using SortableJS.

### Subscription System
A comprehensive system for collecting and managing email and WhatsApp subscribers from biolink pages.
- **Subscribe Block Types**: Includes `email_subscribe`, `whatsapp_channel_subscribe`, and `whatsapp_number_subscribe` blocks.
- **Database**: `subscribers` and `subscriber_messages` tables manage subscriber data and messaging history.
- **Functionality**: Routes and controllers provide full CRUD for subscribers, export, settings configuration (SMTP, WhatsApp API, welcome email), message composition, and sending.

### Plan-Gated Biolink Features
- **Custom Branding**: Replace the "Powered by 1INME" footer with custom text, URL, and logo.
- **Custom Favicon**: Set a custom browser tab icon.
- **Custom CSS & JS**: Inject custom CSS and JavaScript into biolink pages.

### Geographic Heatmap (Link Analytics)
A Snap-Map style geographic heatmap on link analytics pages displays click origins using **MapLibre GL JS** and Carto vector tiles. Geographic coordinates (`latitude`, `longitude`) are persisted from `link_clicks` and `page_sessions` via `GeoIpService` and `CityLookupService`, which utilizes a seeded `cities` reference table derived from public domain and CC-BY 4.0 datasets. The heatmap aggregates clicks server-side and renders points as GeoJSON. A backfill command `php artisan analytics:backfill-coords` is available. The map basemap automatically switches between light/dark themes.

### Forms (1INME Forms)
A complete form-builder feature in the 1INME artifact (Google Forms / Jotform competitor). Lives under `app/Modules/User/{Controllers/FormController.php, Models/Form.php, Models/FormSubmission.php}`. Migration `2026_04_17_100000_create_forms_tables` creates `forms` + `form_submissions` with JSONB columns (`fields`, `design`, `settings`, `notifications`, submission `data`). Slugs auto-generate via a `booted()` creating hook. Public routes use `/f/{slug}` (regex-constrained `[a-z0-9-]+` to coexist with the existing `/f/{id}/{filename}` numeric file-serve route) plus `/iframe`, `/embed.js` and POST throttled at 10/min.

Features: 21 field types (text, email, phone, url, number, textarea, select, radio, checkbox, rating, scale, file, consent, hidden, date, time, heading, paragraph, divider, page_break, **section** group container). Drag-and-drop builder uses Sortable.js + Alpine. Sections group multiple fields under one card surface — fields carry an optional `parent` pointing to a section id; controller `updateBuilder` sanitizes orphan/nested-section parents. Sortable.js `onEnd` reorders by `data-id` (not DOM index) so hidden child wrappers can't desync the array. Live design tab with theme/colors/font/radius/buttons/branding plus a separate **Card Surface** card (page background vs card color, optional card background image with cover/contain/tile + opacity scrim that lets the card color bleed through for legibility). Notifications tab with email-to-owner, auto-responder, SMS (Twilio/MSG91 stub), and webhooks (with SSRF guard rejecting private/loopback IPs). Embed tab with iframe HTML / script tag (auto-resizing via `postMessage`) / direct link / biolink-block guidance. Submissions inbox with star, read/unread, CSV export (with formula-injection mitigation). Biolink integration: new `form` block type in `BiolinkBlock::TYPES` rendered via iframe in `common/partials/biolink-block-render.blade.php` with auto-resize listener. Custom CSS in form design is sanitized to prevent `</style>`/`<script>` breakouts. File uploads restricted by mime type. Email reply-to and webhook headers stripped of newlines to prevent header injection.

### Digital Cards (VCF) — Full vCard 3.0 Editor
Expanded VCF link type from a single-name/single-email/single-phone schema to a full vCard 3.0 editor. Migration `2026_04_17_160000_extend_vcf_data_table` adds prefix/middle_name/suffix/nickname/photo_path/department/role/birthday/anniversary scalars plus JSONB columns `emails`, `phones`, `urls`, `addresses`, `social_profiles`. Legacy single-value columns (email, phone, website, street/city/state/zip/country) are kept; on save, the controller mirrors the first JSON entry of each multi-value field back into them so old code paths and seeded rows keep working without a data migration.

`VcfData` model exposes `emailList()`, `phoneList()`, `urlList()`, `addressList()`, `socialList()`, `fullName()`, `photoUrl()` — list helpers prefer the JSON arrays and fall back to legacy columns when arrays are empty (transparent backwards compat). `toVcf()` emits an RFC-compliant vCard 3.0 with N/FN/NICKNAME/ORG (with `;Department`)/TITLE/ROLE; one EMAIL/TEL/URL/ADR/X-SOCIALPROFILE line per entry with mapped TYPE tokens (Mobile→CELL, Work→WORK, etc.); BDAY/ANNIVERSARY; PHOTO base64-embedded with RFC 2425 line folding so the .vcf is fully self-contained offline; NOTE; REV.

`VcfLinkController` has create/store/edit/update; `compactRows()` strips empty rows; photo upload writes to `vcf-photos/` on the public disk with safe replace + remove. Routes: `GET /links-vcf/{link}/edit` (`user.links.vcf.edit`) and `PUT /links-vcf/{link}` (`user.links.vcf.update`). `LinkController::edit` redirects vcf-type links to the dedicated editor (parallel to the biolink redirect).

UI: shared partial `resources/views/user/links/partials/vcf-form.blade.php` powers both create and edit. Alpine.js `vcfForm({...})` provides reactive arrays per multi-value section with Add/Remove buttons, plus avatar preview that handles both an existing-photo URL and a freshly chosen file via `URL.createObjectURL`. Forms are `enctype="multipart/form-data"`. The interstitial preview page (`common/preview-page.blade.php`) renders all multi-value rows so visitors see emails, phones, websites, addresses, social profiles, birthday and anniversary before downloading.

### QR Studio
A full QR-code resource (separate from the legacy per-link QR generator under `user.links.qrcode`). Lives under `app/Modules/User/{Models/QrCode.php, Controllers/QrCodeController.php, Support/QrCodeTypeRegistry.php}`. Migration `2026_04_17_130000_create_qr_codes_table` creates `qr_codes` with FKs to `users`, `projects`, optional `links`, plus JSONB `payload` + `design`. Routes namespaced as `user.qr-codes.*` (index/create/store/edit/update/destroy/duplicate/resolve). Legacy `user.qrcode` and `user.links.qrcode` routes are preserved.

Supports 16 content types: text, url, phone, sms, email, whatsapp, facetime, location, wifi, event, vcard, crypto (bitcoin/ethereum/litecoin/dogecoin), paypal, upi, epc (SEPA EPC069-12 v2), pix (Brazilian EMVCo BR Code with proper TLV + CRC-16/CCITT-FALSE checksum). `QrCodeTypeRegistry::buildPayloadString()` is the single source of truth for converting payload data into the encoded string.

Builder UI (`resources/views/user/qr-codes/builder.blade.php`) is a 3-column Alpine layout: type picker + per-type form (left), live preview (center), design panel (right). Live rendering uses `qr-code-styling@1.6.0-rc.1` from jsDelivr, supporting dot styles (square/rounded/dots/classy/classy-rounded/extra-rounded), separate inner+outer eye styles+colors, transparent background toggle, logo embed with size, error correction L/M/Q/H, and 8 frame templates (none/scan-me/classic/rounded/ribbon/bubble/minimal/arrow) with custom font + text + colors. "Use existing link" mode swaps the type form for a link-picker so the QR encodes the user's short URL (scans tracked via the link's clicks). PNG/SVG download via the JS library; payload encoding validated server-side via `resolvePayload` endpoint for live preview accuracy. Per-type forms in `_type-forms.blade.php`; sidebar entry in `user/layouts/app.blade.php` points to `user.qr-codes.index`.

# External Dependencies

- **Monorepo Tool**: pnpm
- **API Framework**: Express 5
- **Database**: PostgreSQL
- **ORM**: Drizzle ORM, Laravel Eloquent
- **Validation**: Zod
- **API Codegen**: Orval
- **Build Tool**: esbuild
- **Frontend Frameworks**: Tailwind CSS, Alpine.js
- **Fonts**: Google Fonts CDN
- **Tracking Pixels**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora
- **Social Embeds**: Instagram, TikTok, Twitter, Pinterest, Snapchat
- **Music/Streaming Embeds**: Spotify, Apple Music, SoundCloud, Tidal, Mixcloud, Anchor FM
- **Video Platforms Embeds**: YouTube, Vimeo, Twitch, Kick
- **Integration Widgets**: Typeform, Calendly, Discord
- **Payment Gateways**: PayPal
- **Mapping Services**: MapLibre GL JS, Carto (vector tiles), Google Maps, Yandex Maps
- **Storage**: Local public disk, S3 (optional)