<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Middleware\MaintenanceMode;
use Illuminate\Http\Request;

/**
 * Admin UI for the per-area Maintenance Mode switch.
 *
 * Each area is a key in app_settings (`maintenance_{area}_enabled`).
 * `maintenance_message` and `maintenance_eta` are shown to visitors on the
 * 503 page / API envelope.
 */
class MaintenanceModeController extends Controller
{
    public function index()
    {
        $areas = [];
        foreach (MaintenanceMode::AREAS as $area) {
            $areas[$area] = [
                'label'   => MaintenanceMode::AREA_LABELS[$area] ?? $area,
                'enabled' => (bool) AppSetting::get('maintenance_' . $area . '_enabled', false),
            ];
        }

        return view('admin.maintenance.index', [
            'areas'   => $areas,
            'message' => (string) AppSetting::get('maintenance_message', ''),
            'eta'     => (string) AppSetting::get('maintenance_eta', ''),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'message' => ['nullable', 'string', 'max:500'],
            'eta'     => ['nullable', 'string', 'max:120'],
        ];
        foreach (MaintenanceMode::AREAS as $area) {
            $rules['areas.' . $area] = ['nullable', 'boolean'];
        }
        $data = $request->validate($rules);

        foreach (MaintenanceMode::AREAS as $area) {
            $on = (bool) ($data['areas'][$area] ?? false);
            AppSetting::put('maintenance_' . $area . '_enabled', $on);
        }

        AppSetting::put('maintenance_message', trim((string) ($data['message'] ?? '')));
        AppSetting::put('maintenance_eta', trim((string) ($data['eta'] ?? '')));

        return back()->with('success', 'Maintenance mode settings saved.');
    }
}
