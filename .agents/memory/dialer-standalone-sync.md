---
name: Standalone dialer sync
description: sayzio-dialer-standalone is a manifest-tracked transplant of the main mobile app; how to keep it in sync.
---

`sayzio-dialer-standalone/` is a deliberate verbatim-copy (no shared package — it must stay liftable out of the monorepo) of the dialer surface of `artifacts/1inme-mobile/`.

**The main app has since REMOVED its Dialer/Contacts/Caller ID surfaces entirely** (screens + `lib/api/dialer.ts`/`contacts.ts`); the standalone is now their sole home and those files are tracked as `standaloneOnly` in the manifest. What still syncs is the shared foundation: auth screens, components, contexts, hooks, shared `lib/` modules.

**Rule:** any change to those shared main-app files must be re-applied to the standalone copy, then re-baselined. Post-auth redirects in the standalone target `/(tabs)/dialer` (its tab group has no `index`), so `(auth)/_layout.tsx` is `adapted`, not `identical` (`(auth)/verify.tsx` became `identical` once the standalone mirrored `lib/authNext.ts` — adapted, its `redirectAfterAuth` fallback targets `/(tabs)/dialer` — so login-completion files no longer hardcode the redirect).

**How to apply:**
- `pnpm --filter @workspace/scripts run check:dialer-sync` detects drift via `sayzio-dialer-standalone/sync-manifest.json` (relations: `identical` = must be byte-identical, copy over; `adapted` = intentional diff, source-hash-tracked, diff & hand-apply; `standaloneOnly`).
- After syncing: `check:dialer-sync:accept` re-baselines the recorded source hashes (identical entries are verified, never auto-accepted).
- Procedure + restructured-file mapping (`app/dialer.tsx`→`app/(tabs)/dialer.tsx` etc.) documented in `sayzio-dialer-standalone/SYNC.md`.

**Why:** drift already happened once (main app grew Google-sync throttle/cooldown the standalone lacked); a hash-baseline manifest catches it mechanically. It IS now a registered validation gate (`dialer-sync`, ~1s): while unregistered, 7 real drifts accumulated silently. Consequence: any task touching a mirrored main-app mobile file (auth screens, shared components/lib) must sync + `check:dialer-sync:accept` before validation passes — that friction is the point.

**Typecheck gate:** the standalone lives OUTSIDE the pnpm workspace (npm-managed Expo app), so root `pnpm run typecheck` never touches it — type errors accumulated silently until gated. `dialer-typecheck` validation runs `check:dialer-typecheck` (scripts/src/check-dialer-standalone-typecheck.ts): `npm ci` only when the package-lock sha256 stamp in node_modules is missing/stale, then `tsc --noEmit`. First run from a clean env is slow (full npm ci); cached runs are ~seconds.

**Events surface:** the standalone has a full Events surface (directory tab anchored to a saved location via `lib/eventsLocation.ts`, detail/RSVP/buy screen, tickets) as a `standaloneOnly` addition, not a shared-foundation sync — the main app's directory has no location-anchoring concept. `hooks/useNearbyEventAlerts.ts` surfaces the existing server-generated `event.new_nearby` notification (see `event-new-nearby-alert-source.md`) rather than re-deriving proximity/preference logic on-device. When adding a shared field to `lib/api/events.ts` (kept byte-identical across both apps), the event detail screen's hashtags/gallery/cover/interest-toggle UI must be mirrored in both `app/events/[alias].tsx` files by hand (one uses AuthContext user fields, the other `getProfile()`).
