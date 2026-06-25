# Sayzio Mobile App

The Sayzio mobile app is the native (iOS / Android / web) companion to the
[Sayzio Laravel platform](../../1inme/). It is an [Expo](https://expo.dev) /
React Native app that talks to the same `/api/v1` REST API the web app exposes,
so a creator can manage links, biolinks, QR codes, payouts, contacts, AI, and
monetization from their phone.

> **Related docs:** [REST API reference](../../1inme/docs/api.md) · [Browser extension](../../1inme-extension/README.md)

## Stack

- **Expo SDK 54** (`expo` ~54.0.27), **React Native** 0.81.5, React 19 (New
  Architecture enabled in `app.json`).
- **expo-router** ~6 — file-based routing; every file under `app/` is a route.
- **@tanstack/react-query** — server-state/data fetching.
- **expo-secure-store** — encrypted token storage; `@react-native-async-storage`
  for non-secret cache.
- **react-native-purchases** (RevenueCat) for in-app subscriptions;
  **expo-web-browser** / **expo-auth-session** for hosted-onboarding handoffs
  (e.g. payouts).
- Native modules: `expo-contacts`, `expo-location`, `expo-audio` (voice
  assistant), `expo-notifications` (push), `react-native-nfc-manager` (NFC),
  `expo-local-authentication` (biometric app lock), `react-native-qrcode-svg`,
  `react-native-webview` (web-only feature fallbacks).
- UI: `react-native-reanimated`, `react-native-gesture-handler`, `expo-blur`,
  `expo-linear-gradient`, `expo-glass-effect`, `react-native-svg`, Space Grotesk
  + Inter fonts — matching the web glassmorphism look.

## How it talks to the API

Base URL resolution lives in `lib/api.ts` (`getBaseUrl()`), checked in order:

1. `EXPO_PUBLIC_API_BASE_URL` — explicit full API base URL.
2. `EXPO_PUBLIC_DOMAIN` — a bare domain, `https://` is prepended.
3. On web, `window.location.origin`.
4. Default: `https://1inme.com`.

`apiFetch` (in `lib/api.ts`) is the single fetch wrapper: it injects
`Authorization: Bearer <token>`, a `User-Agent`, and an `X-1INME-Client` header
identifying the app version, then unwraps the unified `{data}` / `{error}`
envelope. Per-domain typed clients live under `lib/api/*` (e.g. `lib/api/qr.ts`,
`lib/api/payouts.ts`, `lib/api/contacts.ts`).

### Auth handshake

- The token is stored under the key `1inme.auth.token` via `expo-secure-store`
  (`lib/secure.ts`). `getToken()` reads it; `apiFetch` attaches it.
- Sign-in paths (see `app/(auth)/`): email/password (`POST /auth/login`),
  passwordless OTP (`/auth/otp/*`, gated by `GET /auth/config`), and native
  social sign-in (`POST /auth/social`). A non-prod demo login (`POST /auth/demo`)
  mirrors the web "Try as Demo" button.
- A `401` clears the stored token and bounces the user back to the auth stack.
- After login the app registers an Expo push token (`POST /me/push-tokens`,
  `lib/push.ts`) and can require a biometric/PIN unlock on cold start
  (`app/lock.tsx`, `lib/biometrics.ts`).

## Environment & running in Replit

The Expo dev server runs as the `artifacts/1inme-mobile: expo` workflow. To
restart it, use the workflow tooling (do **not** run `pnpm dev` at the repo
root). The `dev` script wires up the Replit-specific env so Expo Go / a dev
client can reach the container:

```bash
# package.json "dev" (run via the workflow, not by hand):
EXPO_PACKAGER_PROXY_URL=https://$REPLIT_EXPO_DEV_DOMAIN \
EXPO_PUBLIC_DOMAIN=$REPLIT_DEV_DOMAIN \
EXPO_PUBLIC_REPL_ID=$REPL_ID \
REACT_NATIVE_PACKAGER_HOSTNAME=$REPLIT_DEV_DOMAIN \
pnpm exec expo start --localhost --port $PORT
```

Key env vars:

| Var                        | Purpose                                                       |
| -------------------------- | ----------------------------------------------------------- |
| `EXPO_PUBLIC_API_BASE_URL` | Explicit API base (overrides everything).                   |
| `EXPO_PUBLIC_DOMAIN`       | Bare domain the app derives its API/web base from.          |
| `EXPO_PUBLIC_REPL_ID`      | Replit repl id (passed through for the dev client).         |
| `REPLIT_DEV_DOMAIN` / `REPLIT_EXPO_DEV_DOMAIN` | Replit-assigned dev domains used by the packager proxy. |
| `PORT`                     | Port the Expo packager binds (assigned by the workflow).    |

Because `EXPO_PUBLIC_DOMAIN` defaults to the Replit dev domain, the app talks to
the **same repl's** Sayzio backend in development. Point `EXPO_PUBLIC_API_BASE_URL`
at production (`https://1inme.com/api/v1`) to test against live data.

Type-check the app with `pnpm --filter @workspace/1inme-mobile run typecheck`.

## Screen / route map

Generated from `app/` (expo-router). `(auth)` and `(tabs)` are route groups; `[param]` segments are dynamic; `_layout` files configure their stack/tabs; `+not-found` / `+native-intent` are expo-router specials.

### Entry, auth & app shell

| Route file | Purpose |
| ---------- | ------- |
| `app/index.tsx` | Entry — redirects to tabs or onboarding. |
| `app/_layout.tsx` | Root providers (React Query, theme, auth gate). |
| `app/onboarding.tsx`, `app/splash.tsx` | First-run onboarding slides + splash. |
| `app/(auth)/index.tsx`, `_layout.tsx`, `verify.tsx`, `cancel-change.tsx` | Sign-in / OTP verify / cancel email-change. |
| `app/lock.tsx` | Biometric / PIN app lock. |
| `app/oauth-callback.tsx`, `app/+native-intent.ts` | External auth + deep-link handling. |
| `app/+not-found.tsx` | 404 fallback. |

### Tabs (primary navigation)

| Route file | Purpose |
| ---------- | ------- |
| `app/(tabs)/_layout.tsx` | Tab bar. |
| `app/(tabs)/index.tsx` | Home / dashboard. |
| `app/(tabs)/links.tsx` | Your links & biolinks. |
| `app/(tabs)/create.tsx` | Quick-create menu. |
| `app/(tabs)/inbox.tsx` | Unified inbox. |
| `app/(tabs)/profile.tsx` | Your profile. |

### Links & biolink editing

| Route file | Purpose |
| ---------- | ------- |
| `app/links/create/[kind].tsx` | Create a link of a given kind. |
| `app/links/wizard.tsx` | Guided Link-in-bio wizard. |
| `app/links/conversational.tsx` | Conversational link setup. |
| `app/links/[id]/edit.tsx` | Link editor. |
| `app/links/[id]/analytics.tsx` | Link analytics. |
| `app/links/[id]/ai-chat.tsx` | AI-chat link config. |
| `app/links/[id]/conversational.tsx` | Conversational flow editor. |
| `app/links/[id]/restaurant-menu.tsx` | Restaurant menu builder (categories, items, tables). |
| `app/links/[id]/restaurant-orders.tsx` | Restaurant orders dashboard. |
| `app/links/[id]/blocks/index.tsx`, `[blockId].tsx` | Block list + block editor. |
| `app/links/[id]/settings/{appearance,layout,block-theme,themes,advanced}.tsx` | Biolink settings pages. |
| `app/biolink/[handle].tsx` | Public biolink viewer. |

### Social, posts & messaging

| Route file | Purpose |
| ---------- | ------- |
| `app/posts/index.tsx`, `new.tsx`, `[id].tsx` | Creator posts. |
| `app/followers/index.tsx`, `app/subscribers.tsx` | Followers & subscribers. |
| `app/inbox/[id].tsx` | Conversation view. |
| `app/dm/[handle].tsx`, `app/dm/tip.tsx` | Paid DMs + tipping. |
| `app/social/index.tsx` | Connected social accounts / proofs. |
| `app/profile/[handle].tsx`, `app/profile-edit.tsx` | Public creator profile + edit. |

### Contacts, dialer & calls

| Route file | Purpose |
| ---------- | ------- |
| `app/contacts/index.tsx`, `new.tsx`, `[id].tsx`, `_form.tsx` | Address book CRUD. |
| `app/dialer.tsx` | Number-pad dialer + lookup. |
| `app/dialer-profile.tsx` | Caller-ID profile resolved from a phone number. |
| `app/call/active.tsx`, `app/call/incoming.tsx` | Call UI (`tel:`-based). |

### Monetization, wallet & billing

| Route file | Purpose |
| ---------- | ------- |
| `app/wallet.tsx`, `app/coin-packages.tsx` | Coin balance, ledger, packages. |
| `app/payouts.tsx` | Creator payouts + inline 18+ adult-content consent. |
| `app/monetization/{manage,subscribe,tip,unlock}.tsx` | Creator monetization. |
| `app/paid-page/[alias].tsx` | Standalone paid-page viewer. |
| `app/orders.tsx`, `app/store/order/[id].tsx` | Product storefront orders + order detail. |
| `app/plans.tsx`, `app/upgrade.tsx`, `app/premium-features.tsx` | Plans & upsell (RevenueCat). |
| `app/invoices.tsx`, `app/invoices/[id].tsx` | Invoicing. |

### AI

| Route file | Purpose |
| ---------- | ------- |
| `app/ai-coach.tsx`, `app/ask-coach.tsx` | AI coach / Ask Coach chat. |
| `app/ai-persona.tsx` | AI personas. |
| `app/ai-credits.tsx` | AI-credit balance & packs. |

### Tools & business

| Route file | Purpose |
| ---------- | ------- |
| `app/qr.tsx` | Native QR generator. |
| `app/qr-studio.tsx` | Advanced QR Studio (web fallback — see below). |
| `app/forms/index.tsx`, `[id].tsx` | Forms list + submissions. |
| `app/resume.tsx` | Resume / portfolio. |
| `app/projects.tsx`, `app/domains.tsx`, `app/splash.tsx` | Projects, custom domains, splash pages. |
| `app/client-portals.tsx`, `app/client-portals/[id].tsx` | Client portals. |
| `app/vault.tsx`, `app/vault-audit.tsx` | Vault (read-only on mobile). |
| `app/workspaces.tsx`, `app/workspace-members.tsx`, `app/team.tsx` | Workspace & team. |
| `app/integrations.tsx`, `app/calendar.tsx`, `app/verification.tsx` | Integrations, calendar, verification. |
| `app/backlinks.tsx` | Backlink radar matches. |
| `app/reviews/[alias].tsx`, `app/reviews/manage.tsx` | Reviews viewer + owner moderation. |
| `app/restaurant/[alias].tsx` | Public restaurant menu / ordering. |

### Analytics & community

| Route file | Purpose |
| ---------- | ------- |
| `app/stats.tsx`, `app/visitors.tsx` | Stats & live visitors. |
| `app/leaderboard.tsx`, `app/insider.tsx`, `app/carbon.tsx` | Leaderboard, insider, carbon. |
| `app/moderation.tsx` | Moderation (where applicable). |

### Account, security & info

| Route file | Purpose |
| ---------- | ------- |
| `app/security.tsx`, `app/security/{two-factor,backup-codes,trusted-contacts,cool-off}.tsx` | Security center. |
| `app/account-sessions.tsx`, `app/security-logins.tsx` | Sessions + recent logins. |
| `app/notifications.tsx`, `app/notification-preferences.tsx` | Notifications. |
| `app/api-usage.tsx` | Developer API-usage meter. |
| `app/info/{about,help,privacy,terms,nfc}.tsx`, `_layout.tsx` | Info pages. |

### Admin (back-office)

Reachable only by an operator whose Sanctum token is email-linked to a back-office Admin record — switching is navigation, not a re-login. Each screen is gated by the same admin-guard permissions as the web routes.

| Route file | Purpose |
| ---------- | ------- |
| `app/admin/index.tsx` | Admin dashboard / entry. |
| `app/admin/users.tsx`, `app/admin/users/[id].tsx` | User list + roles / admin-access / impersonation. |
| `app/mail-settings.tsx` | Mail / SMTP settings (status, edit, send test). |

## Feature parity with the web app

| Feature | Status | Notes |
| ------- | ------ | ----- |
| **Contacts & Dialer** | Full | `expo-contacts` import, CRUD, dialer lookup (`tel:`-based). |
| **Creator Payouts** | Full | `app/payouts.tsx`; hosted onboarding (Stripe/PayPal/Razorpay/CCBill/Segpay) opened via `expo-web-browser`. |
| **18+ Adult Content** | Full | Inline three-checkbox consent in `app/payouts.tsx`; `/api/v1/adult-content`. |
| **Forms** | Full | List, view, and submissions (`app/forms/`). |
| **AI** | Full | Coach, Ask Coach, personas, credits; native voice via `expo-audio` (`lib/api/voice.ts`). |
| **Wallet & coins** | Full | Balance, ledger, packages, purchase. |
| **Payments** | Full | RevenueCat (`react-native-purchases`) with `/billing/revenuecat/activate`. |
| **Restaurant Menu** | Full | Native builder (settings, categories, items, device-photo upload, tables/QR) + orders dashboard (`app/links/[id]/restaurant-{menu,orders}.tsx`). |
| **Reviews** | Full | Public viewer + owner approve/hide/pin/reply/delete (`app/reviews/`). |
| **Product storefront** | Full | Native checkout + owner orders/fulfillment (`app/orders.tsx`, `app/store/order/[id].tsx`). |
| **Paid pages** | Full | Standalone paid-page viewer (`app/paid-page/[alias].tsx`). |
| **Link-in-bio wizard** | Full | Stateless guided builder (`app/links/wizard.tsx`). |
| **Admin back-office** | Full | Users / roles / admin-access / impersonation + mail settings (`app/admin/`, `app/mail-settings.tsx`). |
| **QR Studio** | Partial | Simple QR is native (`app/qr.tsx`); advanced styling redirects to web (`app/qr-studio.tsx`). |
