---
name: How-to-get-this inline help pattern
description: Reusable collapsible Blade component + centralized content registry for external-credential field help across 1inme
---

For fields where creators paste externally-obtained values (Pixel IDs, DNS/CNAME, OAuth CRM, payment/SMS/email provider credentials, webhooks, payout processor IDs, developer API keys), the pattern is:

- One anonymous Blade component `<x-how-to-get-this guide-key="...">` (collapsed by default via Alpine `x-data="{open:false}"`, `x-collapse`, `x-cloak`; ARIA `aria-expanded`/`aria-controls`; glass-styled with CSS var fallbacks). Silently renders nothing (`return;` in `@php`) when the guide key has no steps — safe to sprinkle defensively.
- All copy lives in one PHP class (`App\Modules\User\Support\ExternalValueGuides::get($key)`), keyed by dotted strings like `pixel.facebook`, `connected_apps.crm.salesforce`, `integrations.payment.stripe`, `payouts.stripe`, `domain.cname`, `forms.webhook`, `api_keys.developer`. Never inline step copy in Blade — always go through the registry so content stays in one maintainable place.

**Why:** 7+ surfaces (tracking pixels, custom domains, connected apps, integration hub, form webhooks, creator payouts, developer API keys) each have different dynamic type/provider selectors; a single component parameterized by a guide-key string lets every surface reuse the same UI while keying into per-provider content without touching validation/save logic.

**How to apply:** When wiring a new surface, confirm the guide-key suffix exactly matches the underlying registry/config `key` or `slug` used elsewhere in that surface's PHP (e.g. `ConnectedAppRegistry`'s `key`, `PayoutProviderRegistry`'s slug, `IntegrationConfigRegistry`'s `kind`/provider) — mismatches silently render nothing since the component fails silent-safe rather than erroring. For dynamic per-select-value guides (e.g. pixel type dropdown), bind the `<select>` to an Alpine `x-data` var and wrap each guide in `x-show="type === '...'" x-cloak` rather than server-side branching, since the value can change without a page reload.

Verification approach when no live dev server/session is available in an isolated task env: `php -l` per file (syntax only) + `php artisan view:cache` (full Blade compile of every view, catches broken component tags/attrs) + a one-off `php -r` bootstrapping the Laravel kernel to call `ExternalValueGuides::get()` for every guide key actually referenced in views, asserting non-empty steps. This substitutes for a browser screenshot when auth-gated pages can't be easily reached headlessly.
