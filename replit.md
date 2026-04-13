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
- **Module registration**: `ModuleServiceProvider` in `bootstrap/providers.php`

### Current Features (Phases 1A-1C)
- Admin panel: login, dashboard, staff CRUD, user management, role/permission CRUD, plan CRUD, impersonation, password reset
- User panel: registration, login, dashboard with stats, profile management, password reset, email verification
- **Link Engine**: URL shortener, file sharing, ICS calendar event generator, VCF contact card generator
- **Projects**: organize links into color-coded projects
- **Tracking Pixels**: Facebook, Google Analytics, GTM, LinkedIn, Twitter, Pinterest, TikTok, Snapchat, Quora, custom — rendered on biolink + password gate pages
- **Link Features**: password protection, expiration dates, SEO settings (OG image upload), UTM parameter builder, click tracking
- **Link Protection**: country restrictions (ISO codes), device targeting (desktop/mobile/tablet)
- **Analytics**: per-link stats (clicks over time, browsers, OS, devices, referrers, countries)
- **Redirect**: `/r/{alias}` routes for link resolution with tracking, device/country enforcement
- **Admin link management**: browse all user links, filter by type/status/user, toggle active, delete

### Database Tables
`roles`, `permissions`, `role_permissions`, `admins`, `plans`, `users`, `projects`, `domains`, `pixels`, `links`, `link_pixels`, `link_clicks`, `file_links`, `ics_data`, `vcf_data`, `sessions`, `cache`, `jobs`

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
