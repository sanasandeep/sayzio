# Threat Model

## Project Overview

Sayzio is a publicly deployed link-management and creator SaaS built primarily on a Laravel 13 application (`artifacts/1inme`) with a smaller Express service (`artifacts/api-server`) and Expo mobile client (`artifacts/1inme-mobile`). Production traffic reaches public marketing pages, creator/biolink pages, signed share links, Laravel web routes, the Laravel `/api/v1` Sanctum API, and a separately deployable Express `/api` service. Within `artifacts/api-server`, the local Laravel proxy fallback is primarily a Replit preview aid and should generally be treated as dev-only unless a production route is shown to depend on it.

## Assets

- **User accounts and sessions** — web sessions, Sanctum bearer tokens, OTP codes, viewer-session identities, social-auth identities, and admin sessions. Compromise enables impersonation and privilege escalation.
- **Private user content** — vault files, form submissions, direct messages, invoices, subscriber/contact data, reviews, analytics, and workspace data. Much of this contains PII or business-sensitive content.
- **Administrative control planes** — admin APIs, impersonation flows, system settings, mail/integration credentials, schema-repair actions, and plan/billing administration.
- **Payment and monetization state** — invoices, payment attempts, subscriptions, coins, creator payouts, ticketing, and paid-content entitlements. Tampering can grant service or money without payment.
- **Application secrets and third-party credentials** — SMTP, OAuth, OpenAI, payment-gateway, Google/Trustpilot, S3, and webhook signing material.
- **Public trust surfaces** — public creator pages, embeds, AI widgets, checkout/share URLs, webhook endpoints, and public file/media delivery routes. These are reachable without prior trust and therefore highest risk.

## Trust Boundaries

- **Browser/mobile client → Laravel application** — all user, viewer, and admin requests cross from untrusted clients into the main backend.
- **Browser/mobile client → public unauthenticated routes** — public pages, forms, widgets, webhooks, OTP send/verify, and signed links must not rely on client honesty.
- **Laravel application → PostgreSQL** — injection or broken row scoping here can expose or modify cross-user data.
- **Laravel application → external services** — payment gateways, OAuth providers, OpenAI, SMTP, Maps/Reviews providers, WhatsApp, and object storage require strict outbound request and secret handling.
- **User/authenticated → admin/super-admin** — admin routes, impersonation, and settings-management endpoints are a hard privilege boundary.
- **Public/share-token access → private records** — signed URLs, public IDs, aliases, webhook tokens, and file/media references must only expose the intended resource and scope.
- **Development/preview-only infrastructure → production** — Replit preview helpers, mockup-sandbox, and local proxy paths are out of scope unless code proves they are reachable in production.

## Scan Anchors

- **Primary production entry points:** `artifacts/1inme/routes/web.php`, `artifacts/1inme/routes/api.php`, `artifacts/1inme/routes/webhooks.php`, and `artifacts/api-server/src/routes/*` when the Express artifact is deployed.
- **Highest-risk code areas:** auth/OTP/social login flows, admin/mobile-admin APIs, payment/webhook controllers, public AI/chat and webhook ingress, file upload/import/serve logic, redirect/share-token/media-delivery code.
- **Access-control hot spots confirmed by review:** separate public delivery endpoints like `/admin-assets/{id}/{filename}` and `/{alias}/download` need the same authorization policy as the admin UI or primary link handler; duplicated delivery logic is a recurring place for authz drift.
- **Authentication hot spots confirmed by review:** public/demo login routes and mobile/API auth paths must be checked against the web login policy; production-enabled shortcuts and missing TOTP parity can silently collapse privileged accounts back to single-factor or no-factor access.
- **Embed and outbound-fetch hot spots confirmed by review:** public AI/embed bootstraps must not trust `Origin`/`Referer` headers by themselves, and any server-side fetcher must re-validate every redirect hop before following it.
- **Workspace-scoped APIs still need per-object auth:** endpoints that only prove workspace membership or library access (for example shared file-attachment helpers) remain in scope for object-level authorization review because workspace scoping alone is not a sufficient security boundary.
- **Public vs authenticated vs admin:** public routes dominate `web.php` plus portions of `api.php` under `api.optional_auth`; authenticated user/mobile APIs sit behind `auth:sanctum`; admin APIs and web admin are separate privilege boundaries and must enforce server-side permissions on every action.
- **Usually dev-only:** `artifacts/mockup-sandbox`, preview-only Express Laravel proxy behavior in `artifacts/api-server`, and local workflow helpers unless production reachability is demonstrated. The standalone Express contact/health routes are production-scope only when that artifact is actually deployed.

## Threat Categories

### Spoofing

Sayzio supports password login, OTP login, social auth, viewer sessions, signed share links, bearer-token API access, and separate admin authentication. The system must validate each credential type on every protected request, bind share/signing tokens to the exact intended resource, and prevent weaker public or viewer-facing identities from being confused with creator/admin identities.

### Tampering

Untrusted clients can submit public forms, DM content, AI prompts, webhook payloads, upload/import files, change billing settings, and invoke many public AJAX endpoints. Server-side code must own all authorization, payment state transitions, plan gating, and object ownership checks. Public routes must not let attackers mutate other users’ records or coerce external fetches or parsers into processing attacker-chosen dangerous input outside intended limits.

### Information Disclosure

The platform stores private messages, uploaded files, subscriber/contact data, invoices, verification materials, analytics, and admin-only configuration. Public routes, signed URLs, file/media servers, optional-auth endpoints, and multi-workspace queries must scope responses to the exact authorized principal. Error responses and logs must not disclose secrets, raw tokens, internal URLs, or cross-tenant data.

### Denial of Service

A large public surface can trigger OTP sends, AI/chat endpoints, public forms, import/fetch operations, media generation, parsing, and outbound requests. These endpoints must enforce meaningful rate limits, body/file-size caps, parser/fetch timeouts, and bounded background work so an unauthenticated attacker cannot exhaust compute, mail, AI credits, or queue capacity.

### Elevation of Privilege

The most severe risk areas are broken access control across workspaces and users, admin/mobile-admin endpoints, impersonation flows, signed resource delivery, and any injection or SSRF flaw that reaches internal services or privileged data. The application must enforce row ownership and workspace membership server-side, ensure admin capabilities are checked per action, and constrain all dynamic redirects, remote fetches, file paths, and command execution to safe allowlisted behavior.
