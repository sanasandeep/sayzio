<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;

class MarketingSettingsController extends Controller
{
    /**
     * Editable marketing-page settings: analytics IDs, default share image,
     * and admin-managed testimonials shown on the landing + Features pages.
     */
    public function index()
    {
        return view('admin.marketing-settings.index', [
            'ga4_id'                => (string) AppSetting::get('marketing_ga4_id', ''),
            'meta_pixel_id'         => (string) AppSetting::get('marketing_meta_pixel_id', ''),
            'default_share_image'   => (string) AppSetting::get('marketing_default_share_image', ''),
            'trust_strip'           => SitePagesContent::normalizeTrustStrip(
                (array) AppSetting::get('marketing_trust_strip', [])
            ),
            'landing_testimonials'  => SitePagesContent::normalizeTestimonials(
                (array) AppSetting::get('marketing_landing_testimonials', [])
            ),
            'features_testimonials' => SitePagesContent::normalizeTestimonials(
                (array) AppSetting::get('marketing_features_testimonials', [])
            ),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ga4_id'                          => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-_]*$/'],
            'meta_pixel_id'                   => ['nullable', 'string', 'max:60', 'regex:/^[0-9]*$/'],
            'default_share_image'             => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
            'trust_strip'                     => 'nullable|array|max:6',
            'trust_strip.*.value'             => 'nullable|string|max:60',
            'trust_strip.*.label'             => 'nullable|string|max:120',
            'trust_strip.*.icon'              => 'nullable|string|max:60',
            'landing_testimonials'            => 'nullable|array|max:24',
            'landing_testimonials.*.quote'    => 'nullable|string|max:1000',
            'landing_testimonials.*.name'     => 'nullable|string|max:120',
            'landing_testimonials.*.role'     => 'nullable|string|max:160',
            'landing_testimonials.*.photo'    => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
            'features_testimonials'           => 'nullable|array|max:24',
            'features_testimonials.*.quote'   => 'nullable|string|max:1000',
            'features_testimonials.*.name'    => 'nullable|string|max:120',
            'features_testimonials.*.role'    => 'nullable|string|max:160',
            'features_testimonials.*.photo'   => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
        ]);

        AppSetting::put('marketing_ga4_id', trim((string) ($data['ga4_id'] ?? '')));
        AppSetting::put('marketing_meta_pixel_id', trim((string) ($data['meta_pixel_id'] ?? '')));
        AppSetting::put('marketing_default_share_image', trim((string) ($data['default_share_image'] ?? '')));
        AppSetting::put('marketing_trust_strip',
            SitePagesContent::normalizeTrustStrip((array) ($data['trust_strip'] ?? []))
        );
        AppSetting::put('marketing_landing_testimonials',
            SitePagesContent::normalizeTestimonials((array) ($data['landing_testimonials'] ?? []))
        );
        AppSetting::put('marketing_features_testimonials',
            SitePagesContent::normalizeTestimonials((array) ($data['features_testimonials'] ?? []))
        );

        return back()->with('success', 'Marketing settings saved.');
    }
}
