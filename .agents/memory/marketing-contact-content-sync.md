---
name: Contact details brand-default sync
description: How marketing + mobile get contact details and the THREE places the brand defaults live in lockstep.
---
Both the marketing site's Contact page AND the mobile Contact screen
(`artifacts/1inme-mobile/app/info/contact.tsx`) read contact details from the
Laravel product app at `GET /api/v1/site/contact` (served by the Api
`SiteContentController::contact()`), exactly mirroring `/site/about` and the
blog feed pattern.

**How to apply:**
- Both clients are plain `fetch()` (base resolved via a `VITE_*` override →
  LOGIN_URL origin on marketing; `getBaseUrl()` on mobile). It is NOT part of
  the OpenAPI/Orval codegen — do not add it to api-spec.
- Brand contact defaults exist in THREE places that must stay in lockstep: the
  PHP `contactExtraDefault()`, the marketing TS `DEFAULT_CONTACT_CONTENT`, and
  the mobile `DEFAULT_CONTACT_CONTENT` in `lib/api/siteContent.ts`. Keep ALL
  rendered fields identical (address, email, hours, social, map, and a
  DELIBERATELY BLANK phone — no fake number). Every phone row is guarded so a
  blank phone renders NO row.
- Mobile-specific: `fetchContactContent()` returns a non-null `ContactContent`
  always (per-field merge with defaults; whole-default on non-OK/empty/throw),
  and the screen seeds `useState(DEFAULT_CONTACT_CONTENT)` so the first paint
  and any failed fetch show real details, never a blank card. Only successful
  fetches are cached (transient failure retries next mount).

**Why:** a fetch failure or a one-sided defaults edit must never surface stale
placeholders (an old support@ address, a made-up phone, the wrong city). The
duplicate defaults are the fallback shown when the API is unreachable, so if
they drift from the PHP source a client silently misrepresents the company.
Coverage lives in a server feature test (defaults vs admin-override paths) plus
frontend tests (renders fetched values; falls back to brand defaults on fetch
failure).

**Drift guards (TWO, both source-driven — read the canonical PHP + the shipped
client at runtime, no hard-coded third copy):**
- Marketing: `contact-content.sync.test.ts` (`1inme-com/src/lib`, in the
  `test:1inme-com` vitest gate) guards email/address/phone/hours + an explicit
  blank-phone assertion. Social deliberately blank there and there is NO map
  field, so both stay out of scope.
- Mobile: `scripts/test-contact-content-sync.mjs` (`test:contact-content`,
  chained into `test:unit` → the `mobile-unit` validation gate). The mobile
  fallback FULLY mirrors PHP, so it guards address/email/blank-phone/hours/
  social(all 5 links)/map(lat/lng/zoom/label). Evals the shipped
  `DEFAULT_CONTACT_CONTENT` object literal via `new Function`.
  - COMPLEMENTARY sibling `scripts/test-contact-details.mjs`
    (`test:contact-details`, also in `test:unit`) is NOT a drift guard — it
    tests `fetchContactContent` runtime merge/offline/cache BEHAVIOR + screen
    wiring with hardcoded expectations. Keep BOTH; different risks.

Both drift guards scope the regex to the `contactExtraDefault()` body (keys like `email`
recur in the form/messages sub-arrays), decode PHP escapes (double-quoted `\n` →
newline), and throw a clear message if the method is renamed / shape changes.
Phone MUST stay blank in the canonical source AND every client (no fake number).

Not guarded: the mobile **About** screen's separate `FALLBACK_EEFIND`
(parent-company address/email in `app/info/about.tsx`) — same drift risk, no
guard yet (tracked as a follow-up).
