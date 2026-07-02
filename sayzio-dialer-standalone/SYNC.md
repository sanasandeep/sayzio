# Keeping this app in sync with the main Sayzio mobile app

This standalone dialer was **transplanted verbatim** from
`artifacts/1inme-mobile/` and then trimmed to the dialer surface. There is no
shared package between the two, so fixes to the main app's dialer, contacts,
caller-ID, search screens or their API clients do **not** flow in
automatically. This document is the repeatable procedure for re-applying
main-app changes here.

## The manifest + drift checker

`sync-manifest.json` (in this directory) is the machine-readable map of every
transplanted file:

- **`relation: "identical"`** — the file must stay **byte-identical** to its
  main-app source. Syncing = copy the main-app file over, done.
- **`relation: "adapted"`** — the file intentionally differs (each entry's
  `note` says how). The manifest records the sha256 of the **main-app source**
  at the time of the last sync; when the source changes, the checker flags it
  so you can diff and re-apply the relevant part by hand.
- **`standaloneOnly`** — files with no main-app counterpart.

Run the checker from the monorepo root:

```bash
pnpm --filter @workspace/scripts run check:dialer-sync          # detect drift
pnpm --filter @workspace/scripts run check:dialer-sync:accept   # re-baseline after syncing
```

## Sync procedure

1. Run `check:dialer-sync`. It lists every out-of-sync file.
2. For each **identical** file flagged: copy the main-app file over
   (`cp artifacts/1inme-mobile/<source> sayzio-dialer-standalone/<standalone>`).
3. For each **adapted** file flagged: diff the main-app source against the
   standalone copy, apply what's relevant while preserving the intentional
   difference described in the manifest `note`, e.g.:
   ```bash
   diff artifacts/1inme-mobile/app/contacts/index.tsx "sayzio-dialer-standalone/app/(tabs)/contacts.tsx"
   ```
4. Typecheck the standalone app: `cd sayzio-dialer-standalone && npx tsc --noEmit`
   (or `npm run typecheck`).
5. Re-baseline: `check:dialer-sync:accept` (updates the recorded source hashes;
   identical files are verified, never auto-accepted).

If a main-app file is **moved or deleted**, the checker reports the missing
source — update the `source` path (or remove the entry) in
`sync-manifest.json` accordingly.

If a new dialer/contacts-related file appears in the main app that this app
should carry, copy it in and add a manifest entry for it.

## File mapping highlights (restructured files)

Most files map 1:1 at the same relative path. The exceptions:

| Standalone | Main app | What changed |
| --- | --- | --- |
| `app/(tabs)/dialer.tsx` | `app/dialer.tsx` | Moved into the tab group; content byte-identical. |
| `app/(tabs)/contacts.tsx` | `app/contacts/index.tsx` | Moved into the tab group; card/brochure-scan button removed; device import via `lib/deviceContacts.ts`. |
| `app/(tabs)/_layout.tsx` | `app/(tabs)/_layout.tsx` | Different tab set: Dialer / Contacts / Caller ID + header search button. |
| `app/(tabs)/caller-id.tsx` | — | New: dedicated Caller ID tab (main app reaches caller-id through dialer flows). |
| `app/search.tsx` | — | New: universal-finder modal (same `/api/v1/dialer/search` backend). |
| `app/_layout.tsx` | `app/_layout.tsx` | Providers trimmed to the dialer's needs; mounts `useContactAutoSync`. |
| `app/index.tsx` | `app/index.tsx` | Launch gate simplified (no onboarding / idle-lock routing). |
| `app/oauth-callback.tsx` | `app/oauth-callback.tsx` | Connected-Apps OAuth completion branch removed. |
| `app/(auth)/index.tsx` | `app/(auth)/index.tsx` | Dev-only missing-env alert text reworded. |
| `hooks/useContactAutoSync.ts` | — | New: auto device-import + Google sync on open/foreground. |
| `lib/deviceContacts.ts` | — | New: shared device-contact import helper. |

Everything else (`components/`, `contexts/`, `constants/`, `hooks/`,
`lib/`, remaining `app/` screens) is a byte-identical copy — see
`sync-manifest.json` for the authoritative list.

## Why copies instead of a shared package?

This project is deliberately **self-contained** so it can be lifted into its
own Replit project / Expo environment without the monorepo. A shared
`@workspace/*` package would break that. The manifest + checker is the
deliberate alternative: drift is cheap to detect and the sync steps are
mechanical.
