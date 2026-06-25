<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;
use App\Services\PricingResolver;
use Illuminate\Support\Collection;

/**
 * Builds JSON-LD structured data for the public marketing pages so Google can
 * surface rich results (sitelinks search box, breadcrumbs, FAQ snippets,
 * product/offer pricing). It is the schema-side companion to
 * {@see MarketingSeo}: breadcrumb labels reuse the same seeded page registry,
 * and breadcrumb names reuse the resolved SEO title, so the structured data
 * always stays consistent with the visible title/description.
 *
 * Every node is wrapped in a single `@graph` per page:
 *   - Organization      (always)
 *   - WebSite + SearchAction (always — drives the sitelinks search box)
 *   - BreadcrumbList    (every page except the home page)
 * Page-specific nodes (FAQPage, Product/Offer) are emitted separately by the
 * relevant views via {@see self::graph()} so they can be pushed into the head
 * from a child template.
 */
class MarketingSchema
{
    /** Organization node identifying the brand. */
    public static function organization(): array
    {
        $appName = config('app.name', 'Sayzio');
        $home = self::homeUrl();

        $node = [
            '@type' => 'Organization',
            '@id'   => $home . '#organization',
            'name'  => $appName,
            'url'   => $home,
        ];

        $logo = self::logoUrl();
        if ($logo !== '') {
            $node['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
            $node['image'] = $logo;
        }

        $sameAs = self::socialLinks();
        if (!empty($sameAs)) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /** WebSite node with a SearchAction that powers the sitelinks search box. */
    public static function website(): array
    {
        $appName = config('app.name', 'Sayzio');
        $home = self::homeUrl();

        return [
            '@type'     => 'WebSite',
            '@id'       => $home . '#website',
            'name'      => $appName,
            'url'       => $home,
            'publisher' => ['@id' => $home . '#organization'],
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => rtrim(url('/creators'), '/') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * BreadcrumbList from an ordered list of [name, url] crumbs. Returns null
     * unless there's a real trail (>= 2 levels), so the home page stays clean.
     */
    public static function breadcrumbList(array $crumbs): ?array
    {
        $items = [];
        $pos = 1;
        foreach ($crumbs as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $entry = ['@type' => 'ListItem', 'position' => $pos, 'name' => $name];
            $url = trim((string) ($c['url'] ?? ''));
            if ($url !== '') {
                $entry['item'] = $url;
            }
            $items[] = $entry;
            $pos++;
        }

        if (count($items) < 2) {
            return null;
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** FAQPage node from [['q'=>..,'a'=>..], ...]; null when empty. */
    public static function faqPage(array $qas): ?array
    {
        $entities = [];
        foreach ($qas as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }

        if (empty($entities)) {
            return null;
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $entities];
    }

    /**
     * Product/Offer nodes built from the seeded plans. Each plan becomes a
     * Product with a monthly + annual Offer in the given currency.
     */
    public static function pricingProducts(Collection $plans, string $currency = 'USD'): array
    {
        $appName = config('app.name', 'Sayzio');
        $currency = in_array($currency, ['USD', 'INR'], true) ? $currency : 'USD';
        $pricingUrl = rtrim(url('/pricing'), '/');

        $nodes = [];
        foreach ($plans as $plan) {
            $name = trim((string) ($plan->name ?? ''));
            if ($name === '') {
                continue;
            }

            $offers = [];
            foreach (['monthly', 'annual'] as $cycle) {
                $price = PricingResolver::priceForCurrency($plan, $currency, $cycle);
                $minor = (int) ($price['amount_minor'] ?? 0);
                $offers[] = [
                    '@type'         => 'Offer',
                    'name'          => ucfirst($cycle),
                    'price'         => number_format($minor / 100, 2, '.', ''),
                    'priceCurrency' => $currency,
                    'url'           => $pricingUrl,
                    'availability'  => 'https://schema.org/InStock',
                ];
            }

            $node = [
                '@type'  => 'Product',
                'name'   => $appName . ' ' . $name,
                'brand'  => ['@type' => 'Brand', 'name' => $appName],
                'url'    => $pricingUrl,
                'offers' => $offers,
            ];

            $desc = trim((string) ($plan->description ?? ''));
            if ($desc !== '') {
                $node['description'] = $desc;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Base @graph for a marketing page: Organization + WebSite, plus a
     * BreadcrumbList for every page except home. Pass any page-specific nodes
     * (FAQPage, Product) via $ctx['extra'].
     *
     * Recognised $ctx keys: 'seoKey', 'page' (SitePage), 'title', 'url', 'extra'.
     */
    public static function forView(array $ctx): array
    {
        $nodes = [self::organization(), self::website()];

        $bc = self::breadcrumbList(self::breadcrumbsFor($ctx));
        if ($bc !== null) {
            $nodes[] = $bc;
        }

        foreach (($ctx['extra'] ?? []) as $extra) {
            if (is_array($extra) && !empty($extra)) {
                $nodes[] = $extra;
            }
        }

        return self::graph($nodes);
    }

    /** Wrap one or more nodes in a schema.org @graph document. */
    public static function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph'   => array_values(array_filter($nodes)),
        ];
    }

    /** Home → [] (no trail); other pages → Home > <short page label>. */
    private static function breadcrumbsFor(array $ctx): array
    {
        $home = self::homeUrl();
        $url = trim((string) ($ctx['url'] ?? request()->url()));

        if (rtrim($url, '/') === rtrim($home, '/')) {
            return [];
        }

        $label = self::breadcrumbLabel($ctx);
        if ($label === '') {
            return [];
        }

        return [
            ['name' => 'Home', 'url' => $home],
            ['name' => $label, 'url' => $url],
        ];
    }

    /**
     * Short, human breadcrumb label. Reuses the MarketingSeo page registry
     * (code-driven labels, site-page labels) and falls back to the resolved
     * SEO title.
     */
    private static function breadcrumbLabel(array $ctx): string
    {
        $seoKey = $ctx['seoKey'] ?? null;
        if (is_string($seoKey) && $seoKey !== '') {
            $def = MarketingSeo::codeDrivenDefaults()[$seoKey] ?? null;
            $label = is_array($def) ? trim((string) ($def['label'] ?? '')) : '';
            if ($label !== '') {
                return $label;
            }
        }

        $page = $ctx['page'] ?? null;
        if ($page instanceof SitePage) {
            $label = trim((string) (MarketingSeo::sitePageLabels()[$page->slug]['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
            $title = trim((string) $page->title);
            if ($title !== '') {
                return $title;
            }
        }

        return trim((string) ($ctx['title'] ?? ''));
    }

    /** Canonical home URL with a trailing slash (stable @id base). */
    private static function homeUrl(): string
    {
        return rtrim(url('/'), '/') . '/';
    }

    /** Organization logo: the admin's default share image when present. */
    private static function logoUrl(): string
    {
        return trim((string) AppSetting::get('marketing_default_share_image', ''));
    }

    /** Configured public social profile URLs, for Organization `sameAs`. */
    private static function socialLinks(): array
    {
        $links = [];
        foreach (array_keys(SitePagesContent::socialNetworks()) as $key) {
            $url = trim((string) AppSetting::get($key, ''));
            if ($url !== '') {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }
}
