<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * "Known properties" feed for the browser extension's backlink radar.
 *
 * The extension's content script never sends raw page content over the
 * wire — it just collects outbound hrefs in the page DOM and asks the
 * background worker to compare them against the data this endpoint
 * returns:
 *
 *   - short_link_hosts:   exact-match hostnames that point at the
 *                          creator's short links (platform hosts + the
 *                          user's verified+active custom domains).
 *   - biolink_username_path: '/{handle}' on the platform hosts (the
 *                          creator's public bio-link).
 *   - custom_domain_hosts: any verified+active custom domains (also
 *                          counted as a backlink target on its own).
 *   - slug_hashes:        a set of short hex prefixes of SHA-256(slug)
 *                          for every short alias the creator owns. The
 *                          extension hashes candidate path segments and
 *                          looks them up in this set, so the full slug
 *                          list never has to live in the extension's
 *                          memory and the page's outbound hrefs never
 *                          have to leave the browser.
 */
class PropertyController extends Controller
{
    use ApiResponses;

    protected const SLUG_HASH_PREFIX_LEN = 12;

    public function show(Request $request)
    {
        $user = $request->user();

        $platformHosts = PlatformHosts::configured();

        $customDomainHosts = Domain::where('user_id', $user->id)
            ->where('is_verified', true)
            ->where('is_active', true)
            ->orderBy('domain')
            ->pluck('domain')
            ->map(fn ($d) => strtolower($d))
            ->all();

        // Short-link hosts include both the platform hosts and the
        // user's own custom domains, since either may be where their
        // shortened URLs resolve from.
        $shortLinkHosts = array_values(array_unique(array_merge($platformHosts, $customDomainHosts)));

        // Hash every alias the user owns so the extension can probe
        // path-segment candidates without holding the full slug list.
        // Capped at 25k to keep the payload reasonable; super-prolific
        // creators get a high false-positive ceiling but still privacy.
        $aliases = Link::where('user_id', $user->id)
            ->where('type', 'short')
            ->whereNotNull('alias')
            ->orderByDesc('id')
            ->limit(25000)
            ->pluck('alias')
            ->all();

        $slugHashes = [];
        foreach ($aliases as $alias) {
            $slugHashes[] = $this->hashSlug($alias);
        }
        $slugHashes = array_values(array_unique($slugHashes));
        sort($slugHashes);

        // Bio-link username path uses the user's handle (or 'user{id}'
        // fallback baked into User::publicHandle()). The extension
        // checks for path equality / prefix on platform hosts.
        $handle = $user->handle ?: ('user' . $user->id);
        $biolinkUsernamePath = '/' . $handle;

        return $this->ok([
            'properties' => [
                'short_link_hosts'      => array_values($shortLinkHosts),
                'biolink_username_path' => $biolinkUsernamePath,
                'biolink_hosts'         => array_values($platformHosts),
                'custom_domain_hosts'   => $customDomainHosts,
                'slug_hash_prefix_len'  => self::SLUG_HASH_PREFIX_LEN,
                'slug_hash_algo'        => 'sha256',
                'slug_hashes'           => $slugHashes,
                'cached_at'             => now()->toIso8601String(),
                // Suggest the extension's cache TTL (seconds). The
                // extension still enforces its own cap.
                'cache_ttl_seconds'     => 3600,
            ],
        ]);
    }

    protected function hashSlug(string $slug): string
    {
        $h = hash('sha256', strtolower($slug));
        return substr($h, 0, self::SLUG_HASH_PREFIX_LEN);
    }
}
