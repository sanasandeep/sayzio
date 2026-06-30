<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon endpoint hit by the auto-pixel redirect interstitial after the
 * configured pixel scripts have been loaded. Records one row in
 * `link_pixel_fires` per click so the dashboard can show retargeting
 * impact (count + which providers fired).
 *
 * Public endpoint — interstitial visitors are anonymous. Heavily
 * throttled by the route definition; the visitor "identity" is a
 * per-day SHA-256 hash so we never store the raw IP/UA fingerprint.
 */
class LinkPixelFireController extends Controller
{
    use ApiResponses;

    public function store(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost(), withoutWorkspaceScope: true);
        if (!$link) return $this->notFound('Link not found');

        // Trust only the providers the workspace actually has configured —
        // a malicious caller can't inflate counts for providers that aren't
        // set up on the workspace. The interstitial submits the providers
        // it actually loaded scripts for.
        $claimed = (array) $request->input('providers', []);
        $claimed = array_values(array_filter(array_map('strval', $claimed)));

        $configured = $this->configuredProviders($link);
        $providers  = array_values(array_intersect($claimed, $configured));
        if (empty($providers)) {
            return $this->ok(['recorded' => false, 'reason' => 'no_configured_providers']);
        }

        if (!Schema::hasTable('link_pixel_fires')) {
            return $this->ok(['recorded' => false, 'reason' => 'table_missing']);
        }

        $hash = hash('sha256', ($request->ip() ?? '') . '|'
            . ($request->userAgent() ?? '') . '|' . now()->toDateString());

        DB::table('link_pixel_fires')->insert([
            'link_id'      => $link->id,
            'workspace_id' => $link->workspace_id ?? null,
            'providers'    => implode(',', $providers),
            'visitor_hash' => $hash,
            'fired_at'     => now(),
        ]);

        return $this->ok(['recorded' => true, 'providers' => $providers]);
    }

    /** Providers configured for this link's workspace owner. */
    protected function configuredProviders(Link $link): array
    {
        $ws = $link->workspace_id
            ? \App\Modules\User\Models\Workspace::query()->find($link->workspace_id)
            : null;
        if (!$ws) return [];
        $p = (array) (data_get($ws->settings, 'pixels', []) ?? []);
        $out = [];
        if (!empty($p['meta_id']))   $out[] = 'meta';
        if (!empty($p['tiktok_id'])) $out[] = 'tiktok';
        if (!empty($p['google_id'])) $out[] = 'google';
        return $out;
    }
}
