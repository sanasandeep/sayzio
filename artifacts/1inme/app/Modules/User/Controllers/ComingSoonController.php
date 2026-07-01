<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use Illuminate\Http\Request;

/**
 * Renders the reusable branded "Coming soon" preview page for any catalogue
 * feature and records "Notify me" interest (deduped per user).
 */
class ComingSoonController extends Controller
{
    /** Branded preview page for a coming-soon feature. */
    public function show(Request $request, string $feature)
    {
        $def = FeatureAvailability::definition($feature);

        // Unknown key, or the feature is actually ready → send them to it.
        if (!$def) {
            return redirect()->route('user.dashboard');
        }

        if (!FeatureAvailability::isComingSoon($feature)) {
            return redirect()->route($def['landing']);
        }

        $state    = FeatureAvailability::state($feature);
        $userId   = (int) ($request->user()?->id ?? 0);
        $notified = FeatureAvailability::hasNotifyInterest($userId, $feature);

        return view('user.coming-soon', [
            'featureKey'  => $feature,
            'feature'     => $def,
            'state'       => $state,
            'notified'    => $notified,
        ]);
    }

    /** Record a "Notify me when this launches" request. */
    public function notify(Request $request, string $feature)
    {
        $def = FeatureAvailability::definition($feature);
        if (!$def) {
            return redirect()->route('user.dashboard');
        }

        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId) {
            FeatureAvailability::recordNotifyInterest($userId, $feature);
        }

        return redirect()
            ->route('user.coming-soon.show', ['feature' => $feature])
            ->with('status', "We'll email you as soon as ".$def['label']." is ready.");
    }
}
