<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;

/**
 * Admin screen for editing the social-media profile URLs that the public
 * footer surfaces as an icon row. Each network is stored as its own
 * AppSetting key so we can render only the ones that are filled in.
 */
class SocialLinksController extends Controller
{
    public function edit()
    {
        $networks = SitePagesContent::socialNetworks();
        $values = [];
        foreach ($networks as $key => $_meta) {
            $values[$key] = (string) AppSetting::get($key, '');
        }
        return view('admin.social-links.edit', compact('networks', 'values'));
    }

    public function update(Request $request)
    {
        $networks = SitePagesContent::socialNetworks();

        $rules = [];
        foreach ($networks as $key => $_meta) {
            $rules[$key] = ['nullable', 'string', 'max:500', 'url', 'regex:#^https?://#i'];
        }
        $messages = [];
        foreach ($networks as $key => $meta) {
            $messages[$key . '.url'] = $meta['label'] . ' must be a valid URL.';
            $messages[$key . '.regex'] = $meta['label'] . ' must start with http:// or https://.';
        }

        $data = $request->validate($rules, $messages);

        foreach ($networks as $key => $_meta) {
            $value = trim((string) ($data[$key] ?? ''));
            AppSetting::put($key, $value === '' ? null : $value);
        }

        return back()->with('success', 'Social links updated.');
    }
}
