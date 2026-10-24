# Platform pricing, Apple sign-in and account data

## Pricing

Plans and coin packages retain their authoritative web prices. Each USD/INR price row now has independent nullable Apple/iOS and Android overrides in integer cents/paise. Blank means automatic; zero is an explicit free price. Automatic price = web price ÷ 0.7, rounded half-up to the nearest whole currency unit: $4.50 → $6, ₹187 → ₹267, $10 → $14. This is a configured pricing policy, not a claim about a store's actual commission.

Admin plan/package forms expose overrides. Plan copying preserves them; CSV import undo snapshots preserve them. Automatic values follow later web-price changes; explicit overrides stay fixed.

Catalogue endpoints accept `platform=web|ios|android` (default `web`):

- `GET /api/v1/billing/plans`
- `GET /api/v1/plans`
- `GET /api/v1/wallet/packages`

Website purchase endpoints continue to charge web prices. The current mobile app opens website checkout, so its existing catalogue calls also remain web-priced. Native store billing is not implemented by this change: a future build must configure matching App Store/Play products, retrieve localized store prices, and validate purchases before using native catalogue prices for purchases. Web introductory discounts are not represented as native store offers.

## Apple sign-in

Admin → Login & OTP contains an enable switch, iOS Bundle ID and web Services ID. Disabled by default. Configure the matching identifiers in Apple Developer and register the production web domain/return URL `/user/apple/login`. The mobile binary needs the included Expo Apple Authentication plugin/entitlement and a new signed iOS build. No Apple private key is collected by these settings.

`GET /api/v1/auth/apple/nonce` issues a five-minute, single-use challenge. Pass it to Apple and to `POST /api/v1/auth/social` with `provider=apple`, `id_token` and `nonce`. Verification checks signature, issuer, configured audience, expiration and nonce; only verified Apple email can match an existing account. Inactive accounts and existing 2FA requirements remain enforced. Use a persistent shared Laravel cache in deployment; `array` cache cannot carry challenges between requests.

The web entry point uses Apple's official popup SDK and exchanges the result through the same verifier before creating a web session. Native iOS uses the official Expo button only when enabled and available. Real Apple Developer credentials/device sign-in were not available for end-to-end testing.

## Account data

Settings → Data & account deletion and the mobile drawer use the same backend controller and existing admin privacy review queue.

- `GET /api/v1/me/data-requests`: authenticated account's request history.
- `POST /api/v1/me/data-requests`: `type=export|data_deletion|deletion`, optional `reason`.
- Selected deletion requires `confirmation="DELETE DATA"` and `data_scopes` containing one or more of `links`, `contacts`, `notifications`.
- Account deletion requires `confirmation="DELETE ACCOUNT"`.
- `GET /api/v1/me/data-requests/{id}/download`: authenticated owner only, completed unexpired export only.

The web session routes under `/user/settings/account-data` delegate to that same implementation, with CSRF protection. User identity is taken from authentication, never a submitted email. Duplicate active requests of the same type are rejected.

Requests require admin approval. Both deletion types use the existing seven-day cooling-off period and protected-account checks. Selected deletion removes the chosen personal-workspace links/contacts or account notifications, retaining the account, team workspace data and billing records. Full account deletion uses the canonical account deletion path and removes owned uploaded file objects. Deleting an account does not automatically cancel an Apple/Google store subscription.

Exports extend the existing curated account archive with creator profiles, Minds and owned sources/chunks, biolink blocks, tasks, projects, calendar and billing data. Uploaded files are included where storage is available; the manifest records unavailable domains/files. Connector secrets are excluded from Mind source exports. Downloads use authentication headers, not bearer tokens in URLs; native temporary ZIPs are deleted after sharing.

## Validation

31 backend tests / 162 assertions passed across platform pricing/account data, social auth, billing intro discounts and coin FX suites. Coverage includes override persistence and automatic fallback, website isolation, invalid platform rejection, nonce replay and web Apple session exchange, request ownership/confirmation, delayed scoped deletion and generated ZIP ownership.

Main mobile TypeScript and unit tests passed. Android and iOS Hermes JavaScript exports passed; these are not signed APK/IPA builds. Local browser checks verified saved platform overrides and the shared account-data page. Apple provider login and real store purchases still require production configuration/device validation.
