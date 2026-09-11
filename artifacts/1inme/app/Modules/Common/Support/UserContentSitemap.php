<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds, caches and invalidates the three public "user content" sitemaps
 * that sit alongside the marketing sitemap in /sitemap_index.xml:
 *
 *  - /sitemap-creators.xml — published Creator Profiles at /@handle
 *  - /sitemap-resumes.xml  — public (non-password) default resumes at /{handle}/resume
 *  - /sitemap-links.xml    — public, active biolink-family pages at /{alias}
 *
 * Each list is intentionally conservative: only pages that are guaranteed to
 * resolve for an anonymous crawler (no age gate, no password, no
 * registered/subscriber-only visibility tier) are included, mirroring the
 * same gates their public controllers enforce. Rendered XML is cached for 10
 * minutes (matching {@see MarketingSitemap}) since these lists can be large
 * and change constantly; {@see self::flush()} drops all three caches for
 * callers that want a faster refresh after a bulk change.
 */
class UserContentSitemap
{
    private const CREATORS_CACHE_KEY = 'sitemap.creators.xml';
    private const RESUMES_CACHE_KEY  = 'sitemap.resumes.xml';
    private const LINKS_CACHE_KEY    = 'sitemap.links.xml';

    private const CACHE_TTL = 600;

    /** Hard cap per sitemap file, per the sitemaps.org protocol limit. */
    private const MAX_URLS = 50000;

    /**
     * The host a link's alias actually resolves on, from its domain_id.
     *
     * A link's alias is NOT global. Every admin-global brand domain is its
     * own namespace, and every custom user domain is another
     * (Link::resolveByAlias). sayzio.app/sana and 1in.me/sana are different
     * pages, owned by possibly different people, and an alias bound to one
     * host 404s on the other.
     *
     * The links sitemap ignored that entirely: it selected only id, alias and
     * updated_at, and emitted every row on the platform host. So a link
     * living in the 1in.me namespace, or on a customer's own domain, was
     * advertised to Google at a sayzio.app URL that either 404s or belongs to
     * a different person. 180 URLs, all claimed for one host.
     *
     * domain_id IS NULL is the default namespace (the primary brand domain),
     * which is why the null branch is the common, correct one rather than a
     * fallback.
     *
     * @return string|null the host, or null when the id names no live domain
     */
    private static function hostForDomainId(?int $domainId, array $hosts): ?string
    {
        if ($domainId === null) {
            return PlatformHosts::primaryBrandDomain();
        }

        return $hosts[$domainId] ?? null;
    }

    /**
     * domain_id => host, for every domain row.
     *
     * Built once per sitemap render rather than per row: these files run to
     * tens of thousands of URLs and a query each would be the slowest thing
     * on the box. Deliberately NOT memoised in a static -- the rendered XML
     * already has its own ten-minute cache, and a static would outlive it in
     * any long-running process (queue worker, Octane), pinning a domain map
     * from whenever that process booted.
     *
     * @return array<int,string>
     */
    private static function domainHosts(): array
    {
        try {
            return \App\Modules\User\Models\Domain::query()
                ->pluck('domain', 'id')
                ->map(fn ($d) => (string) PlatformHosts::normalize((string) $d))
                ->filter()
                ->all();
        } catch (\Throwable $e) {
            // A broken domains table must not take the sitemap down; the
            // null-domain rows still render.
            return [];
        }
    }

    /**
     * An absolute URL for a path on the host that actually serves it.
     *
     * Returns null when the row's domain no longer exists, so the caller can
     * drop it rather than guess a host -- a wrong URL in a sitemap is worse
     * than a missing one.
     */
    private static function urlOnDomain(?int $domainId, string $path, array $hosts): ?string
    {
        $host = self::hostForDomainId($domainId, $hosts);

        return $host === null ? null : 'https://' . $host . $path;
    }

    public static function renderCreators(): string
    {
        return Cache::remember(self::CREATORS_CACHE_KEY, self::CACHE_TTL, fn () => self::buildCreators());
    }

    public static function renderResumes(): string
    {
        return Cache::remember(self::RESUMES_CACHE_KEY, self::CACHE_TTL, fn () => self::buildResumes());
    }

    public static function renderLinks(): string
    {
        return Cache::remember(self::LINKS_CACHE_KEY, self::CACHE_TTL, fn () => self::buildLinks());
    }

    /**
     * Most recent update among indexable creator profiles — used for the
     * sitemap index's per-entry <lastmod>.
     */
    public static function creatorsLastmod()
    {
        return self::indexableCreatorsQuery()->max('updated_at');
    }

    public static function resumesLastmod()
    {
        return self::indexableResumesQuery()->max('updated_at');
    }

    public static function linksLastmod()
    {
        return self::indexableLinksQuery()->max('updated_at');
    }

    /**
     * Drop all three cached bodies so the next request rebuilds them. Safe to
     * call opportunistically (e.g. from a scheduled job) — never throws.
     */
    public static function flush(): void
    {
        foreach ([self::CREATORS_CACHE_KEY, self::RESUMES_CACHE_KEY, self::LINKS_CACHE_KEY] as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable $e) {
                // Cache flushing must never break the write path.
            }
        }
    }

    /**
     * Published creator profiles, excluding anything the public controller
     * would gate before a crawler could ever see content: unpublished
     * profiles and profiles flagged 18+ (adult content is kept out of the
     * sitemap regardless of the age-gate cookie a crawler will never have).
     */
    private static function indexableCreatorsQuery()
    {
        return User::query()
            ->whereNotNull('handle')
            ->where('handle', '!=', '')
            ->where('profile_published', true)
            ->where(function ($q) {
                $q->where('adult_content_enabled', false)
                    ->orWhereNull('adult_content_enabled')
                    ->orWhereNotNull('adult_flag_suspended_at');
            });
    }

    private static function buildCreators(): string
    {
        $urls = [];

        self::indexableCreatorsQuery()
            ->orderBy('id')
            ->select(['id', 'handle', 'updated_at'])
            ->chunk(1000, function ($users) use (&$urls) {
                foreach ($users as $user) {
                    if (count($urls) >= self::MAX_URLS) {
                        return false;
                    }
                    $urls[] = [
                        // brandUrl() is correct HERE and not for links, which
                        // is worth stating because the two look alike. A
                        // handle lives on the users table with no domain
                        // scoping, so /@sana is the same person on every
                        // brand host -- one canonical URL, on the primary.
                        // An alias is scoped per domain and is not.
                        'loc' => PlatformHosts::brandUrl('/@' . $user->handle),
                        'lastmod' => self::formatLastmod($user->updated_at),
                    ];
                }
            });

        return view('public.sitemap', ['urls' => $urls])->render();
    }

    /**
     * Public, unlocked, default-version resumes. Only the default version is
     * indexed (named/alternate versions stay reachable but out of the
     * sitemap) so crawlers don't spend budget on near-duplicate content.
     */
    private static function indexableResumesQuery()
    {
        return Resume::query()
            ->where('is_public', true)
            ->where('is_default', true)
            ->where('visibility', 'public')
            // Mirrors resume-public.blade.php's own $allowIndex gate: a
            // resume the owner marked noindex (or that renders a
            // <meta robots noindex> tag) must never be advertised in the
            // sitemap that's supposed to only list indexable pages.
            ->where(function ($q) {
                $q->where('allow_indexing', true)->orWhereNull('allow_indexing');
            })
            ->whereHas('user', function ($q) {
                $q->whereNotNull('handle')->where('handle', '!=', '');
            });
    }

    private static function buildResumes(): string
    {
        $urls = [];

        self::indexableResumesQuery()
            ->with(['user:id,handle'])
            ->orderBy('id')
            ->select(['id', 'user_id', 'updated_at'])
            ->chunk(1000, function ($resumes) use (&$urls) {
                foreach ($resumes as $resume) {
                    if (count($urls) >= self::MAX_URLS) {
                        return false;
                    }
                    $handle = $resume->user->handle ?? null;
                    if (empty($handle)) {
                        continue;
                    }
                    $urls[] = [
                        // Handle-based, like /@handle above: global, so the
                        // primary brand host is the one right answer.
                        'loc' => PlatformHosts::brandUrl('/' . $handle . '/resume'),
                        'lastmod' => self::formatLastmod($resume->updated_at),
                    ];
                }
            });

        return view('public.sitemap', ['urls' => $urls])->render();
    }

    /**
     * Public, active biolink-family pages (classic biolink + conversational /
     * slides / ai_chat / restaurant_menu / store_menu / service_booking),
     * excluding demo links and pages owned by an 18+-flagged creator.
     */
    private static function indexableLinksQuery()
    {
        // withoutGlobalScope: the sitemap is platform-global; the
        // BelongsToWorkspace scope would silently filter it to the requesting
        // user's active workspace and poison the shared cache.
        return Link::query()
            ->withoutGlobalScope('workspace')
            ->biolinkFamily()
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->where('is_demo', false)
            ->whereNotNull('alias')
            ->where('alias', '!=', '')
            // Password-locked biolinks show no content to an anonymous
            // crawler — never advertise them as indexable.
            ->where(function ($q) {
                $q->where('is_password_protected', false)->orWhereNull('is_password_protected');
            })
            // Mirrors common/biolink.blade.php's own robots meta tag: an
            // owner-set noindex must keep the page out of the sitemap too.
            ->where(function ($q) {
                $q->whereNull('settings->biolink->meta->robots')
                    ->orWhereRaw("settings->'biolink'->'meta'->>'robots' NOT LIKE ?", ['%noindex%']);
            })
            ->whereHas('user', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('adult_content_enabled', false)
                        ->orWhereNull('adult_content_enabled')
                        ->orWhereNotNull('adult_flag_suspended_at');
                });
            });
    }

    private static function buildLinks(): string
    {
        $urls = [];
        // One query for the whole render, read fresh each time.
        $hosts = self::domainHosts();

        self::indexableLinksQuery()
            ->orderBy('id')
            // domain_id is what says WHICH host this alias resolves on. It was
            // missing from this select, and the sitemap claimed every link for
            // the platform host as a result.
            ->select(['id', 'alias', 'domain_id', 'updated_at'])
            ->chunk(1000, function ($links) use (&$urls, $hosts) {
                foreach ($links as $link) {
                    if (count($urls) >= self::MAX_URLS) {
                        return false;
                    }
                    $loc = self::urlOnDomain($link->domain_id, '/' . $link->alias, $hosts);
                    if ($loc === null) {
                        // Domain row is gone; the alias resolves nowhere we
                        // can name. Skip rather than invent a host.
                        continue;
                    }
                    $urls[] = [
                        'loc' => $loc,
                        'lastmod' => self::formatLastmod($link->updated_at),
                    ];
                }
            });

        return view('public.sitemap', ['urls' => $urls])->render();
    }

    private static function formatLastmod($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toAtomString();
        }

        try {
            return Carbon::parse((string) $value)->toAtomString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
