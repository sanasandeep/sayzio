<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\CookieConsentConfig;
use Illuminate\Http\Request;

/**
 * Admin screen for the workspace-wide cookie-consent banner shown on the
 * marketing site and public biolinks. The whole config lives behind a
 * single AppSetting key — see CookieConsentConfig for the shape and
 * normalizer rules.
 */
class CookieConsentController extends Controller
{
    public function edit()
    {
        return view('admin.cookie-consent.edit', [
            'config'       => CookieConsentConfig::get(),
            'eu_countries' => CookieConsentConfig::EU_COUNTRIES,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'             => 'nullable|boolean',
            'scope_marketing'     => 'nullable|boolean',
            'scope_biolink'       => 'nullable|boolean',
            'remember_days'       => 'required|integer|min:1|max:730',
            'reprompt_on_change'  => 'nullable|boolean',
            'geo_scope'           => 'required|in:all,eu,custom',
            'geo_countries'       => 'nullable|string|max:1000',
            'scroll_acceptance'   => 'nullable|boolean',
            'block_until_consent' => 'nullable|boolean',
            'layout'              => 'required|in:modal,banner,corner',
            'position'            => 'required|in:bottom-center,bottom-left,bottom-right,top-center',
            'theme'               => 'required|in:auto,light,dark',
            'accent'              => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'show_reopen_button'  => 'nullable|boolean',
            'copy'                => 'array',
            'copy.title'             => 'nullable|string|max:200',
            'copy.body'              => 'nullable|string|max:2000',
            'copy.accept_all'        => 'nullable|string|max:60',
            'copy.reject_all'        => 'nullable|string|max:60',
            'copy.customize'         => 'nullable|string|max:60',
            'copy.save'              => 'nullable|string|max:60',
            'copy.policy_link_label' => 'nullable|string|max:80',
            'copy.policy_link_url'   => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'categories'             => 'array',
            'categories.*.id'          => 'required|in:analytics,marketing,functional',
            'categories.*.name'        => 'nullable|string|max:80',
            'categories.*.description' => 'nullable|string|max:1000',
            'categories.*.cookies'     => 'nullable|string|max:2000',
            'categories.*.default_on'  => 'nullable|boolean',
            'bump_version'         => 'nullable|boolean',
        ]);

        $current = CookieConsentConfig::get();
        $bump    = (bool) $request->input('bump_version', false);

        // Auto-bump policy_version whenever the categories block changes
        // in any meaningful way (added/removed/renamed/description edited
        // /cookie list edited/default toggle flipped). Visitors must be
        // re-prompted because the choices on offer or what each one
        // covers has changed. We compare the normalized form so cosmetic
        // whitespace/order differences don't trigger spurious bumps.
        $normalizedNewCats = CookieConsentConfig::normalize(['categories' => $data['categories'] ?? []])['categories'];
        if ($current['categories'] !== $normalizedNewCats) {
            $bump = true;
        }

        $payload = $data;
        $payload['policy_version'] = $current['policy_version'] + ($bump ? 1 : 0);

        CookieConsentConfig::put($payload);

        return back()->with('success', 'Cookie consent settings saved.');
    }
}
