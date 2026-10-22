<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\NavigationAppearance;
use App\Modules\Common\Support\PageHeroStyle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Manage workspace branding (logos + icon) shown across the auth pages,
 * sidebars, and marketing surfaces. Files are written to public/branding so
 * they can be served directly without going through a private storage disk.
 */
class BrandingController extends Controller
{
    /**
     * Map of setting key -> [field name, default public path, allowed mimes].
     * Each entry corresponds to one upload slot on the form.
     */
    private const SLOTS = [
        'brand_logo_light_url' => ['logo_light', 'branding/logo-light.png', ['png', 'jpg', 'jpeg', 'webp'], 4096],
        'brand_logo_dark_url'  => ['logo_dark',  'branding/logo-dark.png',  ['png', 'jpg', 'jpeg', 'webp'], 4096],
        'brand_icon_url'       => ['icon',       'branding/icon.png',       ['png', 'jpg', 'jpeg', 'webp', 'ico'], 1024],
    ];

    public function edit()
    {
        $current = [];
        foreach (self::SLOTS as $key => [$field, $default, $mimes, $max]) {
            $current[$field] = AppSetting::get($key, '/' . ltrim($default, '/'));
        }

        return view('admin.branding.edit', ['logos' => $current, 'navigationAppearance' => NavigationAppearance::defaults(), 'heroStyles' => PageHeroStyle::options(), 'heroStyle' => PageHeroStyle::current()]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::SLOTS as $key => [$field, , $mimes, $max]) {
            $rules[$field] = ['nullable', 'file', 'mimes:' . implode(',', $mimes), 'max:' . $max];
        }
        // One rule per area, drawn from the catalogue so validation cannot
        // reject a preset the form offers. The legacy single field is still
        // accepted: a form cached before the split posts navigation_sidebar
        // and nothing else, and refusing it would save nothing at all.
        foreach ([...array_keys(NavigationAppearance::AREAS), NavigationAppearance::LEGACY_AREA] as $area) {
            $rules['navigation_' . $area] = ['sometimes', 'required', NavigationAppearance::rule()];
        }
        $rules['navigation_logo'] = ['sometimes', 'required', 'boolean'];
        $rules['page_hero_style'] = ['sometimes', 'required', 'in:' . implode(',', array_keys(PageHeroStyle::options()))];
        $validated = $request->validate($rules);
        // A navigation save is anything that posts the top bar, which every
        // version of this form has. The columns may arrive as `panel`/`rail`
        // or, from a form cached before the split, as the single `sidebar`
        // field - which then seeds both, exactly as the stored legacy value
        // does on read.
        if ($request->has('navigation_topbar')) {
            $current = NavigationAppearance::defaults();
            $legacy = NavigationAppearance::normalise($validated['navigation_' . NavigationAppearance::LEGACY_AREA] ?? null);
            $stored = ['logo' => $request->boolean('navigation_logo')];

            foreach (array_keys(NavigationAppearance::AREAS) as $area) {
                $seed = $area === 'topbar' ? null : $legacy;
                $stored[$area] = $validated['navigation_' . $area] ?? $seed ?? $current[$area];
            }

            AppSetting::put(NavigationAppearance::SETTING, $stored);
        }
        if ($request->has('page_hero_style')) {
            PageHeroStyle::store($validated['page_hero_style'] ?? null);
        }

        $publicDir = public_path('branding');
        if (!File::isDirectory($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        foreach (self::SLOTS as $key => [$field, , , ]) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $ext = strtolower($file->getClientOriginalExtension());
                $name = $field . '-' . time() . '.' . $ext;
                $file->move($publicDir, $name);
                AppSetting::put($key, '/branding/' . $name);
            }
        }

        return back()->with('success', 'Branding updated successfully.');
    }

    public function reset(Request $request)
    {
        foreach (self::SLOTS as $key => [, $default, , ]) {
            AppSetting::put($key, '/' . ltrim($default, '/'));
        }
        return back()->with('success', 'Branding reset to defaults.');
    }
}
