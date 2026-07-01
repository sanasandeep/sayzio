<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile (/api/v1) parity for the app-wide "Coming soon" feature-state
 * system. Lets the mobile app render "Soon" badges + branded preview screens
 * from the same single resolver, and record "Notify me" interest (deduped
 * per user) exactly like the web surface.
 */
class FeatureStateController extends Controller
{
    use ApiResponses;

    /** List every catalogue feature with its resolved state for this user. */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);

        return $this->ok([
            'features' => FeatureAvailability::overview($userId ?: null),
        ]);
    }

    /** Record a "notify me" interest for a coming-soon feature. */
    public function notify(Request $request, string $key): JsonResponse
    {
        $def = FeatureAvailability::definition($key);
        if (!$def) {
            return $this->notFound('Unknown feature.');
        }

        $userId = (int) ($request->user()?->id ?? 0);
        if (!$userId) {
            return $this->unauthorized();
        }

        $created = FeatureAvailability::recordNotifyInterest($userId, $key);

        return $this->ok([
            'feature_key' => $key,
            'notified'    => true,
            'created'     => $created,
        ]);
    }
}
