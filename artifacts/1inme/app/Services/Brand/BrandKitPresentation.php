<?php

namespace App\Services\Brand;

/** Explicitly saved public presentation options, shared by editor and exports. */
class BrandKitPresentation
{
    public static function defaults(): array
    {
        return ['showcase_mode' => 'auto', 'showcase_switch' => '1', 'showcase_preset' => 'studio', 'light_background_type' => 'solid', 'dark_background_type' => 'solid', 'light_background_end' => '#e0e7ff', 'dark_background_end' => '#312e81', 'light_background_image' => '', 'dark_background_image' => '', 'light_background_overlay' => '10', 'dark_background_overlay' => '45', 'light_background' => '#f8fafc', 'light_text' => '#172033', 'dark_background' => '#101322', 'dark_text' => '#f1f5f9', 'light_button' => '#3454ef', 'dark_button' => '#8b5cf6', 'showcase_radius' => '20', 'showcase_spacing' => '16', 'icon_source' => 'logo', 'icon_initials' => '', 'icon_background' => '#ffffff', 'icon_padding' => '12', 'icon_scale' => '100', 'icon_x' => '0', 'icon_y' => '0', 'icon_brightness' => '100', 'icon_background_mode' => 'solid', 'icon_background_end' => '#8b5cf6', 'icon_shape' => 'rounded', 'icon_app_name' => '', 'icon_short_name' => '', 'icon_theme' => '#3454ef', 'icon_path' => '/', 'icon_version' => '', 'icon_dark' => '', 'icon_apple' => '', 'icon_android' => ''];
    }

    public static function rules(): array
    {
        $r = [];
        foreach (self::defaults() as $k => $v) {
            $r[$k] = 'nullable|string|max:2048';
        }
        foreach (['light_background', 'light_text', 'dark_background', 'dark_text', 'light_button', 'dark_button', 'icon_background', 'icon_background_end', 'icon_theme', 'light_background_end', 'dark_background_end'] as $k) {
            $r[$k] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        foreach (['light', 'dark'] as $mode) {
            $r[$mode.'_background_type'] = 'nullable|in:solid,gradient,image';
            $r[$mode.'_background_image'] = 'nullable|string|max:2048';
            $r[$mode.'_background_overlay'] = 'nullable|integer|min:0|max:80';
        }
        $r['showcase_mode'] = 'nullable|in:auto,light,dark';
        $r['showcase_switch'] = 'nullable|in:0,1';
        $r['showcase_preset'] = 'nullable|in:minimal,editorial,gradient,studio';
        $r['icon_background_mode'] = 'nullable|in:solid,gradient';
        $r['icon_scale'] = 'nullable|integer|min:30|max:150';
        $r['icon_brightness'] = 'nullable|integer|min:30|max:180';
        foreach (['icon_x', 'icon_y'] as $k) {
            $r[$k] = 'nullable|integer|min:-30|max:30';
        }
        $r['icon_source'] = 'nullable|in:logo,favicon,initials';
        $r['icon_shape'] = 'nullable|in:square,rounded,circle,transparent';
        foreach (['icon_padding', 'showcase_radius', 'showcase_spacing'] as $k) {
            $r[$k] = 'nullable|integer|min:0|max:40';
        }

        return $r;
    }

    public static function values(array $config): array
    {
        $values = array_replace(self::defaults(), array_intersect_key($config, self::defaults()));
        foreach (['light', 'dark'] as $mode) {
            if (! isset($config[$mode.'_background_type']) && ($config['showcase_preset'] ?? '') === 'gradient') {
                $values[$mode.'_background_type'] = 'gradient';
            }
        }

        return $values;
    }
}
