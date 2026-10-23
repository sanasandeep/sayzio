# Sayzio marketing page standard

The features directory establishes the visual reference: a quiet page ground, distinct card surfaces, purple accents, readable type, compact corners and clear next steps. `public.layouts.site` applies that standard to explicitly registered marketing URLs through `MarketingPageStandards`. The standalone main homepage and customer-owned pages are excluded.

## Layout and interaction

- Use one descriptive H1 and a logical heading hierarchy. Keep copy and feature names in the initial server-rendered HTML.
- Use the shared page width, responsive gutters, spacing and type scale. Keep useful product-specific layouts and interactions.
- Cards use the shared surface, border and radius. Primary actions use the theme's solid accent; related actions remain quieter.
- Keep a visible breadcrumb and relevant internal links. The shared layout supplies a skip link before navigation and a focusable main landmark.
- Forms need accessible names, visible focus, readable placeholders and touch-friendly controls. Inputs use at least 16px text on phones.
- Large tables, code samples and card rails scroll within their own container. Avoid a page-wide horizontal scroll.
- Support reduced motion. Marketing copy stays visible if animation initialization or JavaScript fails.
- Explicitly dark product previews retain their own background and light text in both themes. Decorative illustrations must not be presented as live account telemetry.

## Theme and SEO

- Use `--mkt-page`, `--mkt-surface`, `--mkt-ink`, `--mkt-muted`, `--mkt-border`, `--mkt-accent` and `--mkt-soft` from `marketing-page-standards.css`.
- Test both modes instead of assuming a color inversion is readable. Avoid low-opacity small text and pale inline link colors.
- Preserve authored SEO and social metadata. Blank descriptions fall back to page-specific, plain-text summaries. A view's `meta_description` section is supported.
- Emit one title, description and canonical, with consistent Open Graph metadata and valid initial-HTML JSON-LD. The site layout adds a WebPage node alongside existing organization, breadcrumb and page-specific schemas.
- Retain numeric pagination in canonical URLs; omit tracking parameters. Internal search results and privacy request/verification pages use `noindex, follow`. Verification URLs canonicalize to the request landing page.
- Register new routes in the SEO registry and standards catalog. Keep the XML sitemap coverage in step with actual public routes.

## Validation

Run the frontend build, the standards/unit tests, PHP and Blade checks, and the repository view guards. Check representative content-filled and empty states at phone, tablet and desktop widths in both modes. Verify core interactions such as search, disclosures, navigation and theme persistence. Generated previews must use synthetic data and an isolated database.

Use Google Search Central's guidance on [descriptive title links](https://developers.google.com/search/docs/appearance/title-link), [snippets](https://developers.google.com/search/docs/appearance/snippet) and [responsive mobile indexing](https://developers.google.com/search/docs/crawling-indexing/mobile/mobile-sites-mobile-first-indexing). These implementation standards support crawlability and usability; they do not promise search rankings.
