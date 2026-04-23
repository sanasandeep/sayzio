<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\CookieConsentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

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
        $layouts   = implode(',', CookieConsentConfig::LAYOUTS);
        $positions = implode(',', CookieConsentConfig::POSITIONS);
        $sizes     = implode(',', CookieConsentConfig::SIZES);
        $btnStyles = implode(',', CookieConsentConfig::BTN_STYLES);
        $anims     = implode(',', CookieConsentConfig::ANIMATIONS);

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
            'layout'              => 'required|in:' . $layouts,
            'position'            => 'required|in:' . $positions,
            'size'                => 'required|in:' . $sizes,
            'max_width'           => 'required|integer|min:280|max:960',
            'radius'              => 'required|integer|min:0|max:40',
            'theme'               => 'required|in:auto,light,dark',
            'accent'              => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'animation'           => 'required|in:' . $anims,
            'entrance_delay'      => 'required|integer|min:0|max:30',
            'header_logo_enabled' => 'nullable|boolean',
            'header_logo_url'     => ['nullable', 'string', 'max:2000', 'regex:#^(/|https?://|data:image/)#i'],
            'header_logo_file'    => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
            'remove_header_logo'  => 'nullable|boolean',
            'show_policy_link'    => 'nullable|boolean',
            'show_reopen_button'  => 'nullable|boolean',

            'buttons'                       => 'array',
            'buttons.*.bg'                  => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'buttons.*.text'                => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'buttons.*.style'               => 'nullable|in:' . $btnStyles,

            'backdrop'        => 'array',
            'backdrop.show'   => 'nullable|boolean',
            'backdrop.dim'    => 'nullable|integer|min:0|max:100',
            'backdrop.blur'   => 'nullable|boolean',

            'surface_overrides'                  => 'array',
            'surface_overrides.site.layout'      => 'nullable|in:,' . $layouts,
            'surface_overrides.site.position'    => 'nullable|in:,' . $positions,
            'surface_overrides.biolink.layout'   => 'nullable|in:,' . $layouts,
            'surface_overrides.biolink.position' => 'nullable|in:,' . $positions,

            'copy'                   => 'array',
            'copy.title'             => 'nullable|string|max:200',
            'copy.body'              => 'nullable|string|max:2000',
            'copy.accept_all'        => 'nullable|string|max:60',
            'copy.reject_all'        => 'nullable|string|max:60',
            'copy.customize'         => 'nullable|string|max:60',
            'copy.save'              => 'nullable|string|max:60',
            'copy.policy_link_label' => 'nullable|string|max:80',
            'copy.policy_link_url'   => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'copy.reopen_link_label' => 'nullable|string|max:80',

            'categories'               => 'array',
            'categories.*.id'          => 'required|in:analytics,marketing,functional',
            'categories.*.name'        => 'nullable|string|max:80',
            'categories.*.description' => 'nullable|string|max:1000',
            'categories.*.cookies'     => 'nullable|string|max:2000',
            'categories.*.default_on'  => 'nullable|boolean',

            'bump_version' => 'nullable|boolean',
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

        // Explicit removal via the "Remove" button next to the preview.
        // Clears the stored URL and, if the previous logo was an uploaded
        // file in public/branding, deletes it from disk too. A new upload
        // in the same submit takes precedence over the removal.
        if (!$request->hasFile('header_logo_file') && $request->boolean('remove_header_logo')) {
            $oldUrl = $current['header_logo_url'] ?? '';
            if (is_string($oldUrl) && str_starts_with($oldUrl, '/branding/')) {
                $oldPath = public_path(ltrim($oldUrl, '/'));
                $realBase = realpath(public_path('branding'));
                $realFile = realpath($oldPath);
                if ($realBase && $realFile && str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR) && File::isFile($realFile)) {
                    File::delete($realFile);
                }
            }
            $payload['header_logo_url'] = '';
        }
        unset($payload['remove_header_logo']);

        // If an admin uploaded an image, store it in the same public/branding
        // bucket the brand logo / favicon uploader uses, and use its public
        // path as the header_logo_url. Falls back to whatever is in the URL
        // text field when no file is attached, so existing pasted URLs keep
        // working unchanged.
        if ($request->hasFile('header_logo_file')) {
            $file = $request->file('header_logo_file');
            $publicDir = public_path('branding');
            if (!File::isDirectory($publicDir)) {
                File::makeDirectory($publicDir, 0755, true);
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $name = 'cookie-consent-logo-' . time() . '.' . $ext;
            $file->move($publicDir, $name);
            $payload['header_logo_url'] = '/branding/' . $name;
        }
        unset($payload['header_logo_file']);

        $payload['policy_version'] = $current['policy_version'] + ($bump ? 1 : 0);

        CookieConsentConfig::put($payload);

        return back()->with('success', 'Cookie consent settings saved.');
    }
}
