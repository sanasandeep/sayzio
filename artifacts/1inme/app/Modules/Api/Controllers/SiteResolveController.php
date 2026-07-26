<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public "is this site on Sayzio?" resolver.
 *
 * Given a hostname, reports whether it is a verified, active custom domain
 * owned by a Sayzio user, and if so returns a small public owner card.
 * Used by the Zio Browser "On Sayzio" indicator. No auth required; response
 * exposes only already-public profile fields (name/handle/avatar).
 */
class SiteResolveController extends Controller
{
    public function site(Request $request)
    {
        $raw = (string) $request->query('host', '');

        // Normalize: lowercase, strip port and a leading "www.".
        $host = strtolower(trim($raw));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === '' || strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
            return response()->json(['data' => ['on_sayzio' => false]]);
        }

        $payload = Cache::remember("site-resolve:{$host}", 300, function () use ($host) {
            $domain = Domain::query()
                ->withoutGlobalScopes()
                ->where('domain', $host)
                ->where('is_verified', true)
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->first();

            $owner = $domain ? User::find($domain->user_id) : null;
            if (!$domain || !$owner) {
                return ['on_sayzio' => false];
            }

            return [
                'on_sayzio' => true,
                'owner' => [
                    'name'   => $owner->name,
                    'handle' => $owner->handle,
                    'avatar' => $owner->avatar ? PublicStorageUrl::resolve($owner->avatar) : null,
                ],
            ];
        });

        return response()->json(['data' => $payload]);
    }
}
