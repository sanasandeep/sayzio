<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Controllers\ZioBrowserAdblockPolicyController as AdminPolicy;
use Illuminate\Http\Request;

/**
 * Public, versioned Zio Browser ad-block policy (Task #6453).
 *
 * GET /api/v1/zio-browser/adblock-policy
 *
 * Returns the admin-mandated allow/block domain lists with an ETag derived
 * from the policy version ("v{n}"). Clients send If-None-Match and get a 304
 * when unchanged, so the browser's 6-hour refresh is a cheap no-op most of
 * the time. No auth — the policy applies to every Zio Browser install.
 */
class ZioBrowserAdblockPolicyController extends Controller
{
    public function show(Request $request)
    {
        $policy = AdminPolicy::policy();
        $etag = '"v' . $policy['version'] . '"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json([
            'data' => [
                'version' => $policy['version'],
                'allow'   => $policy['allow'],
                'block'   => $policy['block'],
            ],
        ])->header('ETag', $etag);
    }
}
