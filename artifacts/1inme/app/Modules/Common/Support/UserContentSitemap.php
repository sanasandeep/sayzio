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
                        'loc' => url('/@' . $user->handle),
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
                        'loc' => url('/' . $handle . '/resume'),
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
        return Link::query()
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

        self::indexableLinksQuery()
            ->orderBy('id')
            ->select(['id', 'alias', 'updated_at'])
            ->chunk(1000, function ($links) use (&$urls) {
                foreach ($links as $link) {
                    if (count($urls) >= self::MAX_URLS) {
                        return false;
                    }
                    $urls[] = [
                        'loc' => url('/' . $link->alias),
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
