---
name: Coming-soon feature-state system
description: How the app-wide "Coming soon" resolver decides ready vs coming_soon, and which features are integration-backed vs config-independent.
---

# Coming-soon feature-state system (1inme)

Single resolver `FeatureAvailability` + catalogue `FeatureCatalog` (Common/Support/FeatureStates). Every surface (sidebar "Soon" badge, `EnsureFeatureAvailable` route guard, branded preview page `user/coming-soon.blade.php`, admin override screen, mobile `GET /api/v1/feature-states`) reads state from here so behaviour/look stay identical.

State model: `forced (admin AppSetting override) > !configured (auto) > ready`. Override key `feature_states.forced_coming_soon` in AppSetting (array of feature keys).

**Why the enabled/configured split:** "enabled" is NOT re-checked in the resolver — it's already handled by the app's per-plan capability gating ($__can / PremiumFeatures) that decides whether a nav item/route is exposed at all. The resolver layers on top, distinguishing configured (ready) vs enabled-but-not-connected (coming_soon:auto), plus the admin forced override. Adding a hollow always-true `enabled` flag was deliberately avoided.

**Integration-backed features (real `configured` callable, auto-detect coming_soon when platform creds absent):**
- connected_apps + integrations → `connectedAppsConfigured()` (any `ConnectedAppRegistry::isPlatformConfigured`)
- dialer → `dialerConfigured()` (`PlatformServiceSettings::googleContactsConfigured()`)
- payouts + monetization → `paymentProviderConfigured()` (any `PayoutProviderRegistry::adapter($slug)->credentialsConfigured()`)

**Config-independent features (`configured => null`, only coming_soon via admin force):** social_proofs (Buzz), pixels (user enters own IDs), domains (users add/verify own). This is correct-by-design, not a gap — do NOT fabricate fake signals for them.

**How to apply:** adding a feature = one catalogue entry (label/icon/tint/blurb/capabilities/landing/routes/admin_hint/configured). If it depends on a platform integration, point `configured` at a real readiness signal; otherwise leave `null`.

**Mobile parity:** the same `feature-states` list/notify API drives the Expo app's "Soon" badges + coming-soon preview, so the system is now cross-surface — a catalogue change lands on web AND mobile with no extra endpoint. Two non-obvious impedance mismatches any non-web consumer hits: (1) `overview().icon` is a FontAwesome class (web-oriented) and must be mapped to the native icon set; (2) the menu can't gate by route name because mobile routes differ from web route names, so it needs its own href→feature-key map. `overview()` returns `tint` purely so non-web surfaces can brand the preview accent (web reads the icon instead).
