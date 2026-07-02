# Sayzio Dialer (standalone mobile app)

A standalone Expo / React Native app that is **just the Sayzio dialer** — Dialer,
Contacts, Caller ID and universal Search — signed in with the **same Sayzio
account** as the main app. It has no backend of its own: it talks to your
existing Sayzio REST API (`/api/v1/...`).

This is a self-contained project. It does **not** depend on the Sayzio monorepo
and can be opened in its own Replit project (or any Expo environment). Everything
it needs was transplanted from the main mobile app so behaviour matches
one-to-one.

## What's inside

- **Dialer tab** — T9 keypad, recents & frequent, one-tap channels
  (call / SMS / WhatsApp / …), inline universal search.
- **Contacts tab** — list, detail, create / edit, device import, and Google
  Contacts sync. Device contacts are also imported automatically on app open
  and each time the app returns to the foreground (permission-gated), and the
  account's Google Contacts sync is triggered at the same time.
- **Caller ID tab** — look up any phone number, then log the call outcome, add
  notes and set a follow-up (the CRM lives on the shared profile screen).
- **Search** (header button / modal) — the grouped universal finder
  (Contacts · People · My links · Followed · Workspaces), backed by the same
  server-side search as the web and REST dialer.
- **Auth** — shared Sayzio login: email / WhatsApp OTP + social sign-in. There is
  **no separate registration** (OTP doubles as sign-up), exactly like the main
  app. Optional biometric app lock.

## Getting started

1. **Install dependencies**

   ```bash
   npm install
   ```

2. **Point it at your Sayzio backend**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` and set `EXPO_PUBLIC_API_BASE_URL` (or `EXPO_PUBLIC_DOMAIN`) to the
   Sayzio instance whose accounts this dialer should use. The app calls
   `${BASE}/api/v1/...`.

   Google sign-in is optional — set the `EXPO_PUBLIC_GOOGLE_*_CLIENT_ID` values
   (the same OAuth clients your main Sayzio app uses) to show the
   "Continue with Google" button; leave them unset to hide it.

3. **Run it**

   ```bash
   npm run start      # then press i / a, or scan the QR with Expo Go
   npm run ios        # iOS simulator
   npm run android    # Android emulator
   ```

## Configuration reference

| Env var | Purpose |
| --- | --- |
| `EXPO_PUBLIC_API_BASE_URL` | Full base URL of the Sayzio API (recommended). |
| `EXPO_PUBLIC_DOMAIN` | Bare domain alternative (`https://` is added for you). |
| `EXPO_PUBLIC_GOOGLE_CLIENT_ID` / `_IOS_` / `_ANDROID_` / `_WEB_` | Optional Google OAuth clients (enables Google sign-in). |

## How it stays in sync with Sayzio

> Maintainers: the transplanted files are tracked in `sync-manifest.json`, and
> **`SYNC.md`** documents the repeatable procedure (plus the
> `check:dialer-sync` drift checker in the monorepo) for re-applying main-app
> dialer changes here.

- **No backend duplication.** All data comes from the existing Sayzio REST API,
  so contacts, caller-ID lookups, call logging, favourites and universal search
  return exactly what the web dialer sees.
- **Same account.** The session/token handling mirrors the main mobile app, so a
  user signs in with their normal Sayzio credentials.
- **Near-instant contacts sync.** `hooks/useContactAutoSync.ts` (mounted while
  signed in) imports the device address book and triggers the account's Google
  Contacts sync on app open and on every foreground return — an "open /
  foreground" model rather than a live OS address-book-change listener, which
  keeps contacts fresh without a background service. The manual buttons on the
  Contacts tab remain for on-demand syncs.
- The screens and API clients under `app/`, `components/`, `contexts/`, `hooks/`,
  `lib/` and `constants/` were copied verbatim from the main app and trimmed to
  the dialer feature set, so future changes there can be re-applied cleanly.

## Project layout

```
app/
  _layout.tsx            Providers + root Stack
  index.tsx              Launch auth-gate
  (auth)/                Shared Sayzio login (OTP + social)
  (tabs)/
    _layout.tsx          Dialer · Contacts · Caller ID + Search button
    dialer.tsx           T9 keypad, recents/frequent, channels, inline search
    contacts.tsx         Contacts list
    caller-id.tsx        Number lookup entry point
  contacts/              Detail, new/edit, import, Google sync
  call/                  Incoming / active call surfaces
  dialer-profile.tsx     Number/contact profile + call-outcome CRM
  search.tsx             Grouped universal finder
  info/                  About / Help / Privacy / Terms
components/  contexts/  hooks/  lib/  constants/   transplanted shared code
```

## Notes

- Requires the contacts permission for device import and caller matching, and
  (optionally) location for the map picker on a contact.
- This app intentionally covers only the dialer surface; other Sayzio features
  (biolinks, QR, forms, etc.) are managed in the main app.
