<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\StatsStorageHealth;
use Illuminate\Http\Request;

/**
 * Admin "Analytics Storage" page. Surfaces the growth of the high-volume
 * analytics tables (link_clicks / page_sessions), the effective retention
 * window the daily `stats:prune-history` sweep applies, the last sweep
 * outcome, and lets an operator set or clear the hard physical cap
 * (`stats.hard_max_days`) and the growth-alert threshold
 * (`stats.alert_row_threshold`) without reading server logs.
 *
 * Read model + retention rules live in {@see StatsStorageHealth} /
 * {@see \App\Modules\Common\Support\StatsRetentionPolicy} so this page shows
 * exactly what the next sweep will do. Gated behind `settings.manage`.
 */
class StatsStorageController extends Controller
{
    public function index()
    {
        return view('admin.stats-storage.index', [
            'health' => StatsStorageHealth::compute(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hard_max_days'             => ['nullable', 'integer', 'min:1', 'max:36500'],
            'clear_hard_max_days'       => ['nullable', 'boolean'],
            'alert_row_threshold'       => ['nullable', 'integer', 'min:1'],
            'clear_alert_row_threshold' => ['nullable', 'boolean'],
        ]);

        // Hard physical cap: an explicit "clear" wins, otherwise a provided
        // value is saved, otherwise the stored value is left untouched.
        if ($request->boolean('clear_hard_max_days')) {
            AppSetting::put('stats.hard_max_days', null);
        } elseif (($data['hard_max_days'] ?? null) !== null) {
            AppSetting::put('stats.hard_max_days', (int) $data['hard_max_days']);
        }

        // Growth-alert threshold: same set/clear/leave semantics.
        if ($request->boolean('clear_alert_row_threshold')) {
            AppSetting::put('stats.alert_row_threshold', null);
        } elseif (($data['alert_row_threshold'] ?? null) !== null) {
            AppSetting::put('stats.alert_row_threshold', (int) $data['alert_row_threshold']);
        }

        StatsStorageHealth::flush();

        return redirect()->route('admin.stats-storage.index')
            ->with('success', 'Analytics storage settings saved.');
    }
}
