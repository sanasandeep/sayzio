# Overview

This project, "1INME," is a pnpm workspace monorepo for a comprehensive SaaS platform specializing in link management. It provides tools for creating, managing, tracking, and branding links, biolinks (mini-websites), and QR codes. The platform aims to serve creators, businesses, and individuals by offering a premium user experience, extensive customization options, detailed analytics, and robust tracking capabilities to enhance online presence and engagement.

# User Preferences

I prefer iterative development. I want to be asked before making major changes. I do not want changes to the folder `artifacts/1inme/resources/views/vendor/`.

# System Architecture

The project utilizes a pnpm workspace monorepo. The architecture is composed of a PHP 8.4 Laravel application (1INME) and Node.js 24 Express 5 API services. PostgreSQL serves as the primary database, integrated with Drizzle ORM for Node.js services and Laravel Eloquent for the 1INME application. TypeScript 5.9 is used across Node.js components, with Zod for data validation and Orval for API code generation from OpenAPI specifications. esbuild is used for CJS bundling.

## 1INME Laravel App (`artifacts/1inme/`)

### Core Architecture
The Laravel application follows a HMVC pattern with `Admin`, `User`, and `Common` modules. Authentication uses `admin` and `web` guards, supporting both password and OTP logins. A `super_admin` role provides access to a dedicated "Super Admin" section for plan management.

### UI/UX Design
The UI features a glassmorphism design with dark/light modes, a purple color palette, Space Grotesk typography, and animated elements. Navigation includes a 3-mode collapsible sidebar, a glassmorphic header with breadcrumbs, live search, and notifications.

### Biolink Customization
The platform offers advanced biolink customization, including:
- **Block Styling System**: Per-block styling with 11 properties, 10 templates, and global themes with overrides.
- **Image Styling System**: 10 mask/crop shapes, customizable borders, and 6 shadow types for image blocks.
- **Trackable Block Links**: Image blocks can have trackable destination URLs with analytics capture.
- **Block Display Settings**: Per-block visibility based on schedule, location, device, OS, browser, and language.
- **Card Container Block**: Allows grouping of child blocks within a customizable card.
- **Biolink Editor**: Split into "Blocks" (drag-and-drop, grid-span, device preview) and "Settings" pages (appearance, layout, block-theme, advanced settings including SEO, Open Graph, PWA, branding, custom CSS/JS).
- **Plan-Gated Features**: Custom branding, favicon, and custom CSS/JS injection based on user plans.

### Functional Systems
- **File Management**: Per-user file storage with quota management and an AJAX API for operations.
- **File Upload Dropzone**: Reusable component for drag-and-drop file uploads.
- **AJAX Block Editor**: All biolink block operations are AJAX-driven for a fluid UX.
- **Subscription System**: Collects and manages email and WhatsApp subscribers from biolinks, with CRUD for subscribers, export, settings, and message composition/sending.
- **Geographic Heatmap**: Displays click origins on link analytics pages using MapLibre GL JS and Carto vector tiles, persisting geographic coordinates for `link_clicks` and `page_sessions`.
- **Forms (1INME Forms)**: A comprehensive form builder with 21 field types, drag-and-drop interface, design customization, notification settings (email, SMS, webhooks), and biolink integration.
- **Digital Cards (VCF)**: Full vCard 3.0 editor expanding beyond basic contact info to include multiple emails, phones, URLs, addresses, and social profiles, with RFC-compliant vCard generation.
- **QR Studio**: A dedicated QR code builder supporting 16 content types with extensive design customization, live preview, and download options.
- **Social Proof System**: A standalone, embeddable notification widget engine with 7 types (e.g., recent activity, visitor count), customizable design, targeting rules, and biolink integration.
- **Contacts & Dialer**: Per-user address book with two-way Google Contacts sync (People API v1, incremental via syncToken), a number-pad Dialer with search and recent lookups, a Dialer Profile page that resolves a phone number to a 1INME biolink via `linked_identifiers`, and silent auto-attach of biolinks to contacts whose E.164 phone matches a verified user. Detached biolinks are remembered in `contacts.detached_biolink_user_ids` so subsequent syncs don't re-attach them. Scheduled `contacts:sync` runs every 30 min. Calls/email use `tel:` / `mailto:` only (no VOIP).

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