<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Services\MarketingPlanDefaults;
use Illuminate\Http\Request;

/**
 * Admin-managed "If you didn't use Sayzio — what you'd need instead"
 * standalone-tool cost table for the Marketing Plan Calculator's ROI tab.
 *
 * Admins can edit each row's estimated monthly cost (₹) and optionally
 * lock the table — when locked, users see the costs read-only and the
 * server refuses client-submitted overrides on save.
 */
class MarketingPlanToolCostsController extends Controller
{
    public function index()
    {
        return view('admin.marketing-plan-tool-costs.index', [
            'tools'  => MarketingPlanDefaults::tools(),
            'locked' => MarketingPlanDefaults::toolCostsLocked(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'costs'   => 'required|array',
            'costs.*' => 'nullable|numeric|min:0|max:1000000000000',
        ]);

        // Only known tool keys are persisted — anything else is dropped.
        $known = array_column(MarketingPlanDefaults::TOOLS, 'key');
        $costs = [];
        foreach ($validated['costs'] as $key => $cost) {
            if (in_array($key, $known, true) && $cost !== null && $cost !== '') {
                $costs[$key] = round((float) $cost, 2);
            }
        }

        AppSetting::put(MarketingPlanDefaults::SETTING_TOOL_COSTS, $costs);
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOLS_LOCKED, $request->boolean('locked'));

        return redirect()
            ->route('admin.marketing-plan-tool-costs.index')
            ->with('status', 'Tool costs saved.');
    }
}
