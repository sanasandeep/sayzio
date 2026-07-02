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
            'ga4_id'                   => (string) AppSetting::get('marketing_ga4_id', ''),
            'meta_pixel_id'            => (string) AppSetting::get('marketing_meta_pixel_id', ''),
            'default_share_image'      => (string) AppSetting::get('marketing_default_share_image', ''),
            'whatsapp_channel_url'     => (string) AppSetting::get('marketing_whatsapp_channel_url', ''),
            'whatsapp_number'          => (string) AppSetting::get('marketing_whatsapp_number', ''),
            'whatsapp_message'         => (string) AppSetting::get('marketing_whatsapp_message', ''),
            'play_store_url'           => (string) AppSetting::get('marketing_play_store_url', ''),
            'app_store_url'            => (string) AppSetting::get('marketing_app_store_url', ''),
            'trust_strip'              => SitePagesContent::normalizeTrustStrip(
                (array) AppSetting::get('marketing_trust_strip', [])
            ),
            'landing_testimonials'     => SitePagesContent::normalizeTestimonials(
                (array) AppSetting::get('marketing_landing_testimonials', [])
            ),
            'features_testimonials'    => SitePagesContent::normalizeTestimonials(
                (array) AppSetting::get('marketing_features_testimonials', [])
            ),
            'why_comparison'           => SitePagesContent::normalizeWhyComparison(
                (array) AppSetting::get('marketing_why_comparison', [])
            ),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ga4_id'                          => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-_]*$/'],
            'meta_pixel_id'                   => ['nullable', 'string', 'max:60', 'regex:/^[0-9]*$/'],
            'default_share_image'             => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
            // WhatsApp channel/DM settings used by the public 3-way Subscribe block.
            // Channel URL must be an http(s) link (typically https://whatsapp.com/channel/...).
            // Number must be E.164 — digits only, optional leading +, 7–15 digits.
            // Default message is the pre-filled text dropped into wa.me URLs.
            'whatsapp_channel_url'            => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'whatsapp_number'                 => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/'],
            'whatsapp_message'                => ['nullable', 'string', 'max:280'],
            // Mobile-app store links surfaced on the homepage dialer section and
            // the public footer. When empty the badge opens a "coming soon" modal.
            'play_store_url'                  => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'app_store_url'                   => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
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
            'why_comparison'                  => 'nullable|array|max:12',
            'why_comparison.*.feature'        => 'nullable|string|max:200',
            'why_comparison.*.ours'           => 'nullable|string|max:80',
            'why_comparison.*.theirs'         => 'nullable|string|max:80',
        ]);

        AppSetting::put('marketing_ga4_id', trim((string) ($data['ga4_id'] ?? '')));
        AppSetting::put('marketing_meta_pixel_id', trim((string) ($data['meta_pixel_id'] ?? '')));
        AppSetting::put('marketing_default_share_image', trim((string) ($data['default_share_image'] ?? '')));
        AppSetting::put('marketing_whatsapp_channel_url', trim((string) ($data['whatsapp_channel_url'] ?? '')));
        AppSetting::put('marketing_whatsapp_number', trim((string) ($data['whatsapp_number'] ?? '')));
        AppSetting::put('marketing_whatsapp_message', trim((string) ($data['whatsapp_message'] ?? '')));
        AppSetting::put('marketing_play_store_url', trim((string) ($data['play_store_url'] ?? '')));
        AppSetting::put('marketing_app_store_url', trim((string) ($data['app_store_url'] ?? '')));
        AppSetting::put('marketing_trust_strip',
            SitePagesContent::normalizeTrustStrip((array) ($data['trust_strip'] ?? []))
        );
        AppSetting::put('marketing_landing_testimonials',
            SitePagesContent::normalizeTestimonials((array) ($data['landing_testimonials'] ?? []))
        );
        AppSetting::put('marketing_features_testimonials',
            SitePagesContent::normalizeTestimonials((array) ($data['features_testimonials'] ?? []))
        );
        AppSetting::put('marketing_why_comparison',
            SitePagesContent::normalizeWhyComparison((array) ($data['why_comparison'] ?? []))
        );

        return back()->with('success', 'Marketing settings saved.');
    }
}
