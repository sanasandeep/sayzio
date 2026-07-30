<?php

namespace App\Modules\Common\Services;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;

/**
 * Fuzzy-matches a mistyped public path against known biolink aliases,
 * short-link aliases, and public site-page slugs. Used to power the
 * "Did you mean..?" hints on the 404 page so visitors with a typo can
 * recover without bouncing.
 */
class PathSuggester
{
    public const SETTING_KEY = 'error_404_suggestions_enabled';

    public function isEnabled(): bool
    {
        return (bool) AppSetting::get(self::SETTING_KEY, true);
    }

    /**
     * Returns up to $limit suggestions shaped as
     * [['label' => string, 'url' => string, 'kind' => string], ...].
     * Returns an empty array when nothing is close enough — the view
     * is expected to silently render nothing in that case.
     */
    public function suggest(string $rawPath, int $limit = 3, ?string $host = null): array
    {
        $needle = $this->normalise($rawPath);
        if ($needle === '' || strlen($needle) < 2) {
            return [];
        }

        $maxDistance = max(2, (int) floor(strlen($needle) / 3));
        $lenMin = max(1, strlen($needle) - $maxDistance);
        $lenMax = strlen($needle) + $maxDistance;

        // Resolve which domain (if any) the visitor is on. Aliases are
        // bound to a specific domain (or to the platform host when
        // domain_id is null), so suggesting a slug from a different
        // domain would just produce another 404.
        $host = $host ?? request()->getHost();
        $domainId = null;
        $isPlatformHost = false;
        $platformDomainId = null;
        $hostUnknown = false;
        $normalizedHost = \App\Modules\Common\Support\PlatformHosts::normalize($host);
        if ($normalizedHost !== null && in_array($normalizedHost, \App\Modules\Common\Support\PlatformHosts::platformDomains(), true)) {
            // Platform host: suggest only aliases in this host's own domain
            // namespace (per-domain aliasing — matching Link::resolveByAlias).
            $isPlatformHost = true;
            $platformDomainId = Domain::platformDomainIdForHost($normalizedHost);
        } elseif ($host) {
            $domain = Domain::where('domain', strtolower($host))
                ->where('is_active', true)
                ->where('is_verified', true)
                ->first();
            if ($domain) {
                $domainId = $domain->id;
            } else {
                // Unknown / unverified host — don't leak platform aliases here.
                $hostUnknown = true;
            }
        }
        $domainScope = function ($q) use ($isPlatformHost, $platformDomainId, $domainId) {
            if ($isPlatformHost) {
                \App\Modules\User\Support\AliasNamespace::scope($q, $platformDomainId);
                return;
            }
            $domainId === null ? $q->whereNull('domain_id') : $q->where('domain_id', $domainId);
        };

        $candidates = [];

        // Site pages — only public ones (skip the error pages themselves
        // and the home page which already has its own CTA).
        $skipSlugs = ['error-403', 'error-404', 'home'];
        foreach (SitePage::query()->whereNotIn('slug', $skipSlugs)->pluck('slug', 'id') as $slug) {
            if ($slug === null) continue;
            $candidates[] = [
                'value' => strtolower($slug),
                'label' => '/' . $slug,
                'url'   => '/' . $slug,
                'kind'  => 'page',
            ];
        }

        // Primary link aliases — only reachable ones. For biolinks that
        // means visibility=public; for everything else just being active
        // is enough (private alias suggestions would be misleading).
        // Also drop links that are past their expiry — `isAccessible()`
        // would refuse them, so suggesting them would just bounce the
        // visitor to another error page.
        if ($hostUnknown) {
            $linkRows = collect();
        } else {
            $linkQ = Link::query()
                ->select(['alias', 'title', 'type', 'visibility', 'is_active', 'expires_at', 'domain_id'])
                ->where('is_active', true)
                ->whereNotNull('alias')
                ->whereRaw('char_length(alias) between ? and ?', [$lenMin, $lenMax])
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            $linkQ->where($domainScope);
            $linkRows = $linkQ->limit(500)->get();
        }
        foreach ($linkRows as $row) {
            if ($row->type === 'biolink' && ($row->visibility ?? 'public') !== 'public') continue;
            $candidates[] = [
                'value' => strtolower($row->alias),
                'label' => '/' . $row->alias,
                'url'   => '/' . $row->alias,
                'kind'  => $row->type === 'biolink' ? 'biolink' : 'short_link',
            ];
        }

        // Extra biolink aliases (the link_aliases table). Mirror the
        // reachability/visibility filter applied to primary aliases so we
        // don't leak slugs of private or disabled biolinks via "Did you
        // mean…?" suggestions.
        if ($hostUnknown) {
            $extraRows = collect();
        } else {
            $extraRows = LinkAlias::query()
                ->whereRaw('char_length(alias) between ? and ?', [$lenMin, $lenMax])
                // Extra aliases carry their OWN domain binding — scope on the
                // link_aliases row, not the parent link's domain_id (matching
                // Link::resolveByAlias).
                ->where($domainScope)
                ->whereHas('link', function ($q) {
                    $q->where('is_active', true)
                      ->where(function ($w) {
                          $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                      })
                      ->where(function ($w) {
                          $w->where('type', '!=', 'biolink')
                            ->orWhereNull('visibility')
                            ->orWhere('visibility', 'public');
                      });
                })
                ->limit(500)
                ->get(['alias']);
        }
        foreach ($extraRows as $row) {
            $candidates[] = [
                'value' => strtolower($row->alias),
                'label' => '/' . $row->alias,
                'url'   => '/' . $row->alias,
                'kind'  => 'biolink',
            ];
        }

        // Score by Levenshtein, keep only close-enough matches, dedupe by URL.
        $scored = [];
        $seen = [];
        foreach ($candidates as $c) {
            if (isset($seen[$c['url']])) continue;
            $d = levenshtein($needle, $c['value']);
            if ($d > $maxDistance) continue;
            if ($d === 0) continue; // exact match means it's not actually a 404 — skip.
            $seen[$c['url']] = true;
            $scored[] = ['distance' => $d, 'item' => $c];
        }

        usort($scored, function ($a, $b) {
            if ($a['distance'] !== $b['distance']) return $a['distance'] <=> $b['distance'];
            return strcmp($a['item']['value'], $b['item']['value']);
        });

        return array_map(fn($s) => $s['item'], array_slice($scored, 0, $limit));
    }

    private function normalise(string $path): string
    {
        // Strip query string, leading slash, trailing slash, and lowercase.
        // Multi-segment paths are matched on their first segment — the part
        // after the host is by far the most likely typo target.
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = trim($path, "/ \t\n\r\0\x0B");
        if ($path === '') return '';
        $first = explode('/', $path)[0];
        return strtolower(rawurldecode($first));
    }
}
