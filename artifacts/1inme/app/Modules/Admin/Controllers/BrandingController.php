<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
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
        'brand_icon_url'       => ['icon',       'branding/icon.jpg',       ['png', 'jpg', 'jpeg', 'webp', 'ico'], 1024],
    ];

    public function edit()
    {
        $current = [];
        foreach (self::SLOTS as $key => [$field, $default, $mimes, $max]) {
            $current[$field] = AppSetting::get($key, asset($default));
        }

        return view('admin.branding.edit', ['logos' => $current]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::SLOTS as $key => [$field, , $mimes, $max]) {
            $rules[$field] = ['nullable', 'file', 'mimes:' . implode(',', $mimes), 'max:' . $max];
        }
        $request->validate($rules);

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
                AppSetting::put($key, asset('branding/' . $name));
            }
        }

        return back()->with('success', 'Branding updated successfully.');
    }

    public function reset(Request $request)
    {
        foreach (self::SLOTS as $key => [, $default, , ]) {
            AppSetting::put($key, asset($default));
        }
        return back()->with('success', 'Branding reset to defaults.');
    }
}
