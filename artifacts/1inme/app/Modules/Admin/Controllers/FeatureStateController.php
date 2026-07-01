<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
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

        return redirect()
            ->route('admin.feature-states.index')
            ->with('status', $msg);
    }
}
