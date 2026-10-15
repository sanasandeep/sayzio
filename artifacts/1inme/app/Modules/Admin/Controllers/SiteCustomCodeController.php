<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SiteCustomCode;
use Illuminate\Http\Request;

final class SiteCustomCodeController extends Controller
{
    public function edit()
    {
        return view('admin.site-custom-code.edit', ['settings' => SiteCustomCode::settings()]);
    }

    public function update(Request $request)
    {
        $rules = ['code' => 'required|array'];
        foreach (SiteCustomCode::AREAS as $area => $label) {
            $rules["code.$area"] = 'required|array';
            $rules["code.$area.enabled"] = 'nullable|boolean';
            foreach (SiteCustomCode::FIELDS as $field) {
                $rules["code.$area.$field"] = 'nullable|string|max:100000';
            }
        }
        $data = $request->validate($rules)['code'];
        $clean = [];
        foreach (SiteCustomCode::AREAS as $area => $label) {
            $clean[$area] = ['enabled' => $request->boolean("code.$area.enabled")];
            foreach (SiteCustomCode::FIELDS as $field) {
                $clean[$area][$field] = (string) ($data[$area][$field] ?? '');
            }
        }
        AppSetting::put(SiteCustomCode::SETTING_KEY, $clean);

        return back()->with('success', 'Custom code saved. Enabled areas use the new code on their next page load.');
    }
}
