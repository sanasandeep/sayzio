<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Support\AccountFlowCounts;

/**
 * The "how your Sayzio fits together" panel on the dashboard.
 *
 * Served as its own fragment rather than rendered inline for one reason: the
 * counts behind it are nineteen COUNT(*) queries on a cold cache, and the
 * dashboard is the page every signed-in person lands on. Rendering it inline
 * would put seconds between login and a usable screen for whoever happens to
 * warm the cache. The dashboard paints first; this arrives after.
 */
class DashboardFlowController extends Controller
{
    public function show()
    {
        $user = workspace_owner();

        return response()
            ->view('user.dashboard.partials.flow', [
                'flowCounts' => AccountFlowCounts::for($user),
            ])
            ->header('Cache-Control', 'private, max-age=60');
    }
}
