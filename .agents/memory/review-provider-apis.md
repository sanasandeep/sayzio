---
name: Review provider live-API quirks
description: Real-world auth/error-envelope quirks for the Google & Trustpilot review adapters in 1inme.
---

# Google & Trustpilot review adapters (live wiring)

Adapters: `app/Services/ReviewProviders/Adapters/{GoogleReviewsAdapter,TrustpilotAdapter}.php`.
Keys resolved via `config('services.google_places.api_key')` / `config('services.trustpilot.api_key')` with an `env()` fallback — read through config (not raw `env()`) so a cached config still sees the key.

## Google Places Details API
- Endpoint: `maps.googleapis.com/maps/api/place/details/json` with `place_id`, `fields=reviews`, `key`.
- **Reports logical errors as HTTP 200 + a `status` field** (`REQUEST_DENIED`, `INVALID_REQUEST`, `OVER_QUERY_LIMIT`, …). `$resp->successful()` alone is NOT enough — must inspect `status`; treat only `OK`/`ZERO_RESULTS` as non-error, else throw with `error_message`.
- Returns at most ~5 "most relevant" reviews per place; full history is not available on this legacy endpoint.

## Trustpilot public Business Unit reviews
- Endpoint: `api.trustpilot.com/v1/business-units/{unit}/reviews`.
- **Public read endpoints authenticate via the `apikey` QUERY parameter**, not a header. (An `apikey` header is unreliable for the public API tier.)

**Why:** these are external-API behaviors not derivable from the codebase; getting either wrong silently breaks live sync while preview mode masks it.
**How to apply:** when extending or debugging review sync, keep the status-envelope check (Google) and query-param auth (Trustpilot); absent keys fall back to `previewSample()` and stamp `STATUS_PREVIEW`.
