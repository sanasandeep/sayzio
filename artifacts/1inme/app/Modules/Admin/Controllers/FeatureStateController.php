<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use App\Modules\Common\Support\FeatureStates\FeatureLaunchNotifier;
use Illuminate\Http\Request;

/**
 * Admin surface for the app-wide "Coming soon" feature-state system. Lists
 * every catalogue feature with its auto-detected readiness and lets an admin
 * force any feature to "coming soon" (or clear that override) via AppSetting.
 */
class FeatureStateController extends Controller
{
    public function index()
    {
        return view('admin.feature-states.index', [
            'features' => FeatureAvailability::overview(),
        ]);
    }

    public function update(Request $request, string $key)
    {
        if (!FeatureAvailability::definition($key)) {
            return redirect()->route('admin.feature-states.index');
        }

        $forced = $request->boolean('forced');
        FeatureAvailability::setForced($key, $forced);

        $label = FeatureAvailability::definition($key)['label'] ?? $key;
        $msg = $forced
            ? $label.' is now marked "Coming soon" for everyone.'
            : $label.' override cleared — it now follows its integration status.';

        // Clearing the override may transition the feature coming_soon → ready.
        // If so, email everyone who asked to be notified (once each). No-op
        // when the feature is still coming soon (e.g. its integration/config
        // isn't connected yet) or when nobody is waiting.
        if (!$forced) {
            $notified = FeatureLaunchNotifier::notifyLaunched($key);
            if ($notified > 0) {
                $msg .= ' '.$notified.' waiting '.($notified === 1 ? 'user was' : 'users were').' emailed that it\'s now live.';
            }
        }

        return redirect()
            ->route('admin.feature-states.index')
            ->with('status', $msg);
    }
}
