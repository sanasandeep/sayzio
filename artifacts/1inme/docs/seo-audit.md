# SEO Audit — Homepage, Pricing & Creator/Biolink Pages

**Conducted:** July 2026  
**Scope:** Marketing homepage, /pricing, public creator profiles (/@handle), and public biolink pages (/{alias}).

---

## Checklist & Findings

### 1. Homepage (`/`)

| Check | Status | Action |
|---|---|---|
| `<title>` ≤ 60 chars | **Fixed** | Was 83 chars ("One Platform. Endless Conversations. — Link in Bio, Short Links & QR Codes — Sayzio"). Shortened to "Link in Bio, Short Links & QR Codes — Sayzio" (45 chars). |
| `<meta description>` ≤ 155 chars | **Fixed** | Was 162 chars. Trimmed to 152 chars by removing "and brand" from the links description. |
| Single `<h1>` | OK | Homepage template defines one h1 in the hero section. |
| Canonical tag | OK | `PlatformHosts::canonicalUrl()` in `marketing-share-meta.blade.php`. |
| `og:image` present | OK | Falls back to `marketing_default_share_image` AppSetting. |
| `og:image:alt` | **Fixed** | Was absent. Added `<meta property="og:image:alt" content="{{ $__shareTitle }}">` in `marketing-share-meta.blade.php`. |
| JSON-LD `@graph` (Organization + WebSite) | OK | Both nodes present, well-formed. |
| JSON-LD `JSON_UNESCAPED_SLASHES` in `<script>` | **Fixed** | Removed the flag from `site.blade.php` — unescaped `/` allows `</script>` injection through `url()` values. |

---

### 2. Pricing Page (`/pricing`)

| Check | Status | Action |
|---|---|---|
| `<title>` ≤ 60 chars | OK | "Pricing Plans — Free, Pro & Business Link-in-Bio — Sayzio" (58 chars). |
| `<meta description>` ≤ 155 chars | OK | 151 chars. |
| Single `<h1>` | OK | Page template defines one h1 hero heading. |
| `og:image:alt` | **Fixed** | Inherited via `marketing-share-meta.blade.php` fix above. |
| JSON-LD Product/Offer nodes | OK | `MarketingSchema::pricingProducts()` emits one Product per public plan with monthly + annual Offer nodes. Free plan emits `price: "0.00"` which is valid. `availability: InStock` present. |
| JSON-LD `JSON_UNESCAPED_SLASHES` in `<script>` | **Fixed** | Removed from `plans.blade.php` pricing Product graph — same XSS risk as homepage. |

---

### 3. Creator Profiles (`/@handle`)

| Check | Status | Action |
|---|---|---|
| Unique `<title>` per creator | OK | Built as `{name} (@{handle}) - Sayzio` — always unique. |
| Non-empty `<meta description>` | OK | Falls back to `name . ' on Sayzio'` when both tagline and bio are absent — unique per creator. |
| `og:image` present | OK | Uses cover image then avatar. |
| `og:image:alt` | **Fixed** | Was absent. Added `content="{{ $creator->name }}"` for both cover/avatar branches. |
| `twitter:image:alt` | **Fixed** | Added alongside og:image:alt. |
| Avatar `alt` text | **Fixed** | Was `alt=""` (treats an identity image as decorative). Changed to `alt="{{ $creator->name }}"`. Cover image stays `alt=""` — it is a decorative background. |
| `ProfilePage` JSON-LD | OK | Correctly typed; `Person` mainEntity with `name`, `alternateName`, `url`, `identifier`, `sameAs` (social + biolink URL). |
| `dateModified` in ProfilePage | **Fixed** | Was absent. Added `$creator->updated_at->toIso8601String()` — recommended by schema.org for ProfilePage. |
| Gated/private profiles non-indexable | OK | Controller returns HTTP 404 for `profile_published = false` — search engines never index unpublished profiles. |

---

### 4. Biolink Pages (`/{alias}`)

| Check | Status | Action |
|---|---|---|
| Unique `<title>` (no mass duplicate) | **Fixed** | Was `'Sayzio Link in Bio'` when no user-supplied title or link title. Now derives `"{owner name} — Link in Bio"` from `$link->user->name`, eliminating duplicate metadata across thousands of biolinks. |
| Non-empty `<meta description>` | **Fixed** | Was empty when no user-supplied description. Now derives `"Visit {owner name}'s Link in Bio page on Sayzio."` as a unique fallback. |
| Default canonical tag | **Fixed** | Was emitted only when the user had explicitly set `meta.canonical_url`. Now always emits `PlatformHosts::canonicalUrl()` as the default. |
| `noindex` for gated biolinks | **Fixed** | Biolinks with `visibility` ≠ `public` (registered/followers/subscribers) now emit `noindex,nofollow`. Public biolinks continue to emit `index,follow` (or the user's custom robots setting). |
| `og:image:alt` | **Fixed** | Was absent. Added `content="{{ $ogTitle }}"` alongside `og:image`. |
| Image block `alt` attribute | OK | `blocks/image.blade.php` reads `$s['alt']` from block settings and passes it through. Empty `alt=""` is emitted when the field is blank, which is the correct decorative treatment for purely visual blocks. |
| User-set robots override | OK | When user supplies `meta.robots`, it is still respected; gated override takes precedence. |

---

## What Was Already Fine

- `MarketingSeo` / `MarketingSchema` architecture: centralized, admin-overridable, correct resolution order.
- `PlatformHosts::canonicalUrl()`: correctly normalizes brand-domain requests to the primary canonical host.
- XML sitemap entries for all audited page groups.
- Creator profile: canonical is always the `/@handle` form regardless of entry point (`/handle` vs `/@handle`).
- `Organization` + `WebSite` JSON-LD nodes: all required fields (`name`, `url`, `@id`, `logo`, `sameAs`) present when admin credentials are configured.
- Pricing `Product/Offer` nodes: `price`, `priceCurrency`, `availability`, `url` all present and valid.
- `FAQPage` and `BreadcrumbList` builder methods: correctly skip empty entries and return `null` when there is no meaningful data.
- `BlogPost` cache (plain-array hydration) already avoids `__PHP_Incomplete_Class` on the home page.

---

## Baseline for Future Audits

After these fixes, run the following checks on the next audit:

1. **Title lengths**: `{{ $__seo['title'] }} — Sayzio` — verify all `codeDrivenDefaults()` titles stay ≤ 51 chars (total ≤ 60 with suffix).
2. **Description lengths**: all `codeDrivenDefaults()` descriptions ≤ 155 chars.
3. **`og:image:alt`**: present in `marketing-share-meta.blade.php`, `creator-profile.blade.php`, and `biolink.blade.php`.
4. **JSON-LD `<script>` encoding**: never use `JSON_UNESCAPED_SLASHES` inside `<script type="application/ld+json">` (see memory: `json-ld-html-context-escaping.md`).
5. **Biolink noindex gating**: `$link->visibility !== 'public'` → `noindex,nofollow`.
6. **Canonical always present**: all three page groups emit a `<link rel="canonical">`.
