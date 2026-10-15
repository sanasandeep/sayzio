<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\Brand\BrandKitPresentation;
use Illuminate\Http\Request;

class BrandPageThemeController extends Controller
{
    public function update(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) workspace_owner_id() && $link->type === 'biolink', 403);
        $data = $request->validate(BrandKitPresentation::rules());
        $settings = $link->settings ?? [];
        $settings['biolink']['brand_presentation'] = array_replace(BrandKitPresentation::values($settings['biolink']['brand_presentation'] ?? []), $data);
        $identity = $settings['brand_identity'] ?? null;
        if ($identity) {
            $identity['mode'] = 'custom';
            $identity['overrides']['brand_presentation'] = $settings['biolink']['brand_presentation'];
            $settings['brand_identity'] = $identity;
        }
        $link->update(['settings' => $settings]);

        return back()->with('status', 'Updated both page themes.');
    }
}
