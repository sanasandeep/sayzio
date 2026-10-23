# Marketing standards audit — 23 October 2026

## Scope and changes

Standardized the registered marketing page families using the new features directory as the design reference. The main homepage, its partials, shared header/footer source and customer-owned page templates are unchanged. Public creators now use the shared marketing layout rather than a separate permanently-light document.

The shared standard covers typography, spacing, card surfaces, action styles, theme colors, visible focus, touch targets, motion preferences, breadcrumbs and related links. SEO changes preserve authored values, add plain-text fallback descriptions and WebPage schema, honor view description sections, retain canonical pagination, protect private/search URLs from indexing, and include the Android landing page and AI dashboard in the sitemap registry. Fixed the extension page's invalid signup route, labeled search/filter controls, and marked illustrative analytics metrics as examples.

## Results

- Fresh `npm run build` completed successfully.
- Seven targeted PHPUnit tests passed, covering standards, SEO fallbacks, exclusions, canonical/robots rules, catalog preservation and related-link coverage.
- Sixty routes/page variants rendered through the actual Blade layout with fresh local Vite assets and isolated in-memory SQLite fixtures. No production database was used.
- 360 browser checks covered those 60 variants at 390, 768 and 1440px, in light and dark mode. Each had one H1, one title/description/canonical, valid JSON-LD and no document-width overflow.
- Thirty additional checks covered populated pricing, creator/discovery lists, demo cards and FAQs at the same sizes/themes. FAQ search and the creator sign-in affordance were exercised without submitting data.
- Sampled text contrast checks found no remaining failures in the checked leaf headings, paragraphs, labels and links. The sampler excludes gradients, reduced-opacity artwork and nested text; this is not a full accessibility certification. Representative pages were also visually inspected.
- PHP/Blade syntax, Alpine line-comment, Blade JSON-in-attribute, Blade PHP, whitespace and marketing-heavy-blur checks passed.
- The repository-wide comment-echo guard retains three pre-existing matches in unchanged `page-hero-appearance.blade.php` and `navigation-appearance.blade.php`.

## Limits

The full PostgreSQL suite was not run locally. Previews use synthetic content, not production account, purchase, download or submission data. Empty event/blog states and populated article/category/tag examples are included; every possible production record, signed-in state and provider integration is not covered. The committed unit tests and CI remain the regression gates for logic.

## Render inventory

| URL / variant | Template | H1 | Render |
| --- | --- | ---: | --- |
| `/pricing` | `public.pricing.plans` | 1 | PASS |
| `/creators` | `common.creators-directory` | 1 | PASS |
| `/analytics` | `public.analytics` | 1 | PASS |
| `/audience` | `public.audience` | 1 | PASS |
| `/integrations` | `public.integrations` | 1 | PASS |
| `/domains` | `public.domains` | 1 | PASS |
| `/docs/api` | `public.api-docs` | 1 | PASS |
| `/resume-builder` | `public.resume-builder` | 1 | PASS |
| `/dialer-contacts` | `public.dialer-contacts` | 1 | PASS |
| `/ai-marketing-strategist` | `public.ai-marketing-strategist` | 1 | PASS |
| `/forms` | `public.forms` | 1 | PASS |
| `/notifications` | `public.notifications` | 1 | PASS |
| `/download` | `public.download` | 1 | PASS |
| `/android` | `public.android-download` | 1 | PASS |
| `/dialer` | `public.zio-dialer` | 1 | PASS |
| `/browser` | `public.zio-browser` | 1 | PASS |
| `/extension` | `public.zio-extension` | 1 | PASS |
| `/app` | `public.mobile-app` | 1 | PASS |
| `/demos` | `public.demos` | 1 | PASS |
| `/compare` | `public.compare.index` | 1 | PASS |
| `/compare/linktree` | `public.compare.show` | 1 | PASS |
| `/compare/bitly` | `public.compare.show` | 1 | PASS |
| `/compare/beacons` | `public.compare.show` | 1 | PASS |
| `/compare/carrd` | `public.compare.show` | 1 | PASS |
| `/compare/taplink` | `public.compare.show` | 1 | PASS |
| `/compare/stan` | `public.compare.show` | 1 | PASS |
| `/features` | `public.features` | 1 | PASS |
| `/how-it-works` | `public.how-it-works` | 1 | PASS |
| `/workspace-team` | `public.workspace-team` | 1 | PASS |
| `/ai-dashboard` | `public.ai-dashboard` | 1 | PASS |
| `/buzz` | `public.buzz` | 1 | PASS |
| `/newsroom` | `public.newsroom` | 1 | PASS |
| `/services` | `public.services` | 1 | PASS |
| `/ai-chatbot` | `public.ai-product` | 1 | PASS |
| `/ai-agent` | `public.ai-product` | 1 | PASS |
| `/ai-widget` | `public.ai-product` | 1 | PASS |
| `/ai-voice-assistant` | `public.ai-product` | 1 | PASS |
| `/whatsapp-agent` | `public.ai-product` | 1 | PASS |
| `/for/creators` | `public.use-case` | 1 | PASS |
| `/for/agencies` | `public.use-case` | 1 | PASS |
| `/for/coaches` | `public.use-case` | 1 | PASS |
| `/for/musicians` | `public.use-case` | 1 | PASS |
| `/for/small-business` | `public.use-case` | 1 | PASS |
| `/discovery` | `public.discovery` | 1 | PASS |
| `/creators-feed` | `public.creators-feed` | 1 | PASS |
| `/about` | `public.about` | 1 | PASS |
| `/contact` | `public.contact` | 1 | PASS |
| `/faqs` | `public.faqs` | 1 | PASS |
| `/terms` | `public.policy` | 1 | PASS |
| `/privacy` | `public.policy` | 1 | PASS |
| `/refunds` | `public.policy` | 1 | PASS |
| `/gdpr` | `public.policy` | 1 | PASS |
| `/cookies` | `public.policy` | 1 | PASS |
| `/events` | `common.events-directory` | 1 | PASS |
| `/blogs` | `public.blogs.index` | 1 | PASS |
| `/privacy/request` | `public.privacy-request` | 1 | PASS |
| `/blogs/category/guides` | `public.blogs.category` | 1 | PASS |
| `/blogs/tag/growth` | `public.blogs.tag` | 1 | PASS |
| `/blogs/example-guide` | `public.blogs.show` | 1 | PASS |
| `/privacy/history` | `public.policy-history` | 1 | PASS |
