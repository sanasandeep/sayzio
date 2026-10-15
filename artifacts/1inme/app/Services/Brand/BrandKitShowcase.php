<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandKitAsset;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Facades\DB;

/** A public, editable block snapshot, shared by draft preview and page creation. */
class BrandKitShowcase
{
    public const SECTIONS = ['brand' => 'Brand introduction', 'visual' => 'Colors and typography', 'assets' => 'Asset previews and downloads', 'socials' => 'Website and public contact links', 'guidelines' => 'Public brand guidelines', 'downloads' => 'PDF guide and ZIP package'];

    public const DEFAULTS = ['brand', 'visual', 'assets', 'socials', 'downloads'];

    public const GUIDELINES = ['logo_rules', 'color_rules', 'typography_rules', 'imagery_rules', 'accessibility_rules', 'dos', 'donts', 'font_license'];

    public function fromForm(array $form): BrandKit
    {
        $config = $form;
        $config['palette'] = array_intersect_key($form, array_flip(['primary', 'secondary', 'accent']));
        foreach ($config['palette'] as $key => $color) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                unset($config['palette'][$key]);
            }
        }
        $config['fonts'] = ['heading' => $this->font($form['heading'] ?? 'Inter'), 'body' => $this->font($form['body'] ?? 'Inter')];
        $config['website_url'] = $form['website'] ?? '';
        $config['voice'] = ['tone' => $form['tone'] ?? ''];
        $config['taglines'] = array_filter(explode("\n", $form['taglines'] ?? ''));

        return new BrandKit(['user_id' => workspace_owner_id(), 'name' => $form['name'] ?? 'Your brand', 'config' => $config]);
    }

    private function font(string $font): string
    {
        return preg_match('/^[a-zA-Z0-9 -]{1,100}$/', $font) ? $font : 'Inter';
    }

    private function assetUrl(string $url, int $owner): ?string
    {
        if (! preg_match('~^(https?://|/(?!/))~i', $url)) {
            return null;
        }
        if (preg_match('~^/f/(\d+)/~', parse_url($url, PHP_URL_PATH) ?: '', $m)) {
            $file = UserFile::where('user_id', $owner)->find((int) $m[1]);
            if (! $file || ! $file->isScanClean() || $file->quarantined_at) {
                return null;
            }
        }

        return $url;
    }

    public function snapshot(BrandKit $kit, ?array $sections = null, ?BrandKit $assetKit = null): array
    {
        $sections = array_values(array_intersect(array_keys(self::SECTIONS), $sections ?? self::DEFAULTS));
        $source = $kit->config ?? [];
        $c = [];
        $copy = function (array $keys) use (&$c, $source) {
            foreach ($keys as $key) {
                if (isset($source[$key])) {
                    $c[$key] = $source[$key];
                }
            }
        };
        if (in_array('brand', $sections)) {
            $copy(['description', 'bio', 'short_bio', 'long_bio', 'mission', 'vision', 'values', 'taglines', 'voice']);
        }
        // Visual styles always match the kit, even when the specs section is hidden.
        $copy(['palette', 'fonts', 'block_theme', ...array_keys(BrandKitPresentation::defaults()), 'logo_light', 'logo_dark', 'favicon', 'social_banner', 'background', 'icon_package_files', 'icon_package_fingerprint']);
        if (in_array('socials', $sections)) {
            $copy(['website_url', 'instagram', 'linkedin', 'facebook', 'youtube', 'tiktok', 'x_profile', 'contact_email', 'contact_phone']);
        }
        if (in_array('guidelines', $sections)) {
            $copy(self::GUIDELINES);
        }
        $palette = is_array($c['palette'] ?? null) ? $c['palette'] : [];
        $c['palette'] = array_filter(array_intersect_key($palette, array_flip(['primary', 'secondary', 'accent'])), fn ($color) => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color));
        $c['palette']['neutrals'] = array_values(array_filter((array) ($palette['neutrals'] ?? []), fn ($color) => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)));
        $fonts = is_array($c['fonts'] ?? null) ? $c['fonts'] : [];
        $c['fonts'] = ['heading' => $this->font((string) ($fonts['heading'] ?? 'Inter')), 'body' => $this->font((string) ($fonts['body'] ?? 'Inter'))];
        if (isset($c['voice'])) {
            $c['voice'] = ['tone' => (string) ($c['voice']['tone'] ?? ''), 'descriptors' => array_values(array_filter((array) ($c['voice']['descriptors'] ?? []), 'is_string'))];
        }
        if (! is_string($c['block_theme'] ?? null) || ! isset(BiolinkBlock::BLOCK_TEMPLATES[$c['block_theme']])) {
            unset($c['block_theme']);
        }
        $assets = [];
        if (in_array('assets', $sections)) {
            foreach (BrandKitFields::MEDIA + ['logo' => 'Primary logo', 'logo_light' => 'Light logo', 'logo_dark' => 'Dark logo', 'favicon' => 'Favicon'] as $key => $label) {
                $raw = $key === 'logo' ? $kit->logo() : ($source[$key] ?? '');
                if (! is_string($raw) || ! $raw || ! ($url = $this->assetUrl($raw, (int) $kit->user_id))) {
                    continue;
                }
                $c[$key] = $url;
                $assets[$url] = ['label' => $label, 'url' => $url, 'image' => ! in_array($key, ['font_heading_file', 'font_body_file']) && ! preg_match('/\.(pdf|docx?|pptx?)(\?|$)/i', $url)];
            }
            $saved = $assetKit ?? $kit;
            if ($saved->exists) {
                foreach (BrandKitAsset::where('brand_kit_id', $saved->id)->where('user_id', $kit->user_id)->where('status', 'ready')->with('file')->get() as $asset) {
                    if ($asset->file && ($url = $this->assetUrl($asset->file->url, (int) $kit->user_id))) {
                        $assets[$url] = ['label' => BrandKitAssetService::TYPES[$asset->type]['label'] ?? ucwords(str_replace('_', ' ', $asset->type)), 'url' => $url, 'image' => str_starts_with($asset->file->mime_type, 'image/')];
                    }
                }
            }
        }
        $logo = $kit->logo();
        if ($logo && ($url = $this->assetUrl($logo, (int) $kit->user_id))) {
            $c['logo'] = $url;
        }

        foreach (['logo_light', 'logo_dark', 'favicon', 'social_banner', 'background', 'icon_dark', 'icon_apple', 'icon_android', 'light_background_image', 'dark_background_image'] as $key) {
            if (! empty($c[$key])) {
                $c[$key] = $this->assetUrl((string) $c[$key], (int) $kit->user_id) ?? '';
            }
        }

        return ['name' => $kit->name, 'config' => $c, 'assets' => array_values($assets), 'sections' => $sections];
    }

    public function blocks(array $snapshot, ?string $downloadBase = null): array
    {
        $out = [];
        $c = $snapshot['config'];
        $sections = $snapshot['sections'];
        $add = function ($type, $settings) use (&$out) {
            $out[] = ['type' => $type, 'settings' => $settings, 'sort_order' => count($out), 'is_active' => true];
        };
        $heading = fn ($text) => $add('heading', ['text' => $text, 'size' => 'h2', '_style' => ['font_family' => $this->font($c['fonts']['heading'] ?? 'Inter')]]);
        $paragraph = function ($text) use ($add) {
            if (trim((string) $text) !== '') {
                $add('paragraph', ['text' => (string) $text]);
            }
        };
        $button = fn ($label, $url) => $add('link', ['text' => $label, 'url' => $url]);
        if (! empty($c['social_banner'])) {
            $add('image', ['url' => $c['social_banner'], 'alt' => $snapshot['name'].' brand cover']);
        }
        if (! empty($c['logo'])) {
            $add('avatar', ['url' => $c['logo'], 'rounded' => false, 'size' => 100]);
        }
        $add('heading', ['text' => $snapshot['name'], 'size' => 'h1', '_style' => ['font_family' => $this->font($c['fonts']['heading'] ?? 'Inter')]]);
        if (in_array('brand', $sections)) {
            $paragraph($c['taglines'][0] ?? $c['description'] ?? '');
            $paragraph($c['bio'] ?? $c['short_bio'] ?? '');
            if (! empty($c['long_bio']) && $c['long_bio'] !== ($c['bio'] ?? '')) {
                $paragraph($c['long_bio']);
            }
            if (! empty($c['voice']['tone'])) {
                $heading('Voice & tone');
                $paragraph($c['voice']['tone']);
            }
            foreach (['mission' => 'Our mission', 'vision' => 'Our vision', 'values' => 'Our values'] as $key => $label) {
                if (! empty($c[$key])) {
                    $heading($label);
                    $paragraph($c[$key]);
                }
            }
        }
        if (in_array('visual', $sections)) {
            $heading('Colors & typography');
            foreach (['primary', 'secondary', 'accent'] as $key) {
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $c['palette'][$key] ?? '')) {
                    $add('heading', ['text' => ucfirst($key).' · '.$c['palette'][$key], 'size' => 'h3', '_style' => ['font_family' => $this->font($c['fonts']['heading'] ?? 'Inter'), 'bg_color' => $c['palette'][$key], 'text_color' => $this->ink($c['palette'][$key]), 'border_radius' => 16, 'padding' => 8, 'grid_span' => 4]]);
                }
            }
            $heading('Aa — Make your mark.');
            $paragraph('The quick brown fox jumps over the lazy dog.');
            $paragraph('Heading: '.($c['fonts']['heading'] ?? 'Inter').' · Body: '.($c['fonts']['body'] ?? 'Inter'));
        }
        if (in_array('assets', $sections) && $snapshot['assets']) {
            $heading('Brand assets');
            foreach ($snapshot['assets'] as $asset) {
                $add('link_big', ['text' => 'Download '.$asset['label'], 'description' => 'Brand asset · view and download', 'url' => $asset['url'], 'thumbnail' => $asset['image'] ? $asset['url'] : '', 'icon' => 'fa-download']);
            }
        }
        if (in_array('guidelines', $sections)) {
            $items = [];
            foreach (self::GUIDELINES as $key) {
                if (! empty($c[$key])) {
                    $items[] = ['question' => BrandKitFields::text()[$key], 'answer' => $c[$key]];
                }
            }
            if ($items) {
                $heading('Brand guidelines');
                $add('faq', ['items' => $items]);
            }
        }
        if (in_array('socials', $sections)) {
            foreach (['website_url' => 'Visit our website', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x_profile' => 'X'] as $key => $label) {
                if (preg_match('~^https?://~i', $c[$key] ?? '')) {
                    $button($label, $c[$key]);
                }
            }
            if (filter_var($c['contact_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $button('Contact us', 'mailto:'.$c['contact_email']);
            }
        }
        if (in_array('downloads', $sections)) {
            $heading('Brand resources');
            $button('Download brand guide · PDF', $downloadBase ? $downloadBase.'/pdf' : '#');
            $button('Download all brand assets · ZIP', $downloadBase ? $downloadBase.'/zip' : '#');
        }

        return $out;
    }

    private function ink(string $hex): string
    {
        $rgb = sscanf($hex, '#%02x%02x%02x');

        return (($rgb[0] * 299 + $rgb[1] * 587 + $rgb[2] * 114) / 1000) > 155 ? '#111827' : '#ffffff';
    }

    public function appearance(array $snapshot): array
    {
        $c = $snapshot['config'];
        $primary = $c['palette']['primary'] ?? '#3454ef';
        $theme = $c['block_theme'] ?? '';
        $blockTheme = BiolinkBlock::BLOCK_TEMPLATES[$theme]['style'] ?? [];
        if ($blockTheme) {
            $blockTheme = array_replace($blockTheme, ['_template' => $theme, 'font_family' => $this->font($c['fonts']['body'] ?? 'Inter'), 'text_color' => '#172033', 'apply_to_all' => true]);
        }

        return ['brand_presentation' => BrandKitPresentation::values($c), 'brand_logos' => ['light' => ($c['logo_light'] ?? '') ?: ($c['logo'] ?? ''), 'dark' => ($c['logo_dark'] ?? '') ?: ($c['logo'] ?? '')], 'block_theme' => $blockTheme, 'background_type' => 'color', 'background_color' => '#ffffff', 'font_family' => $this->font($c['fonts']['body'] ?? 'Inter'), 'font_color' => '#172033', 'button_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#3454ef', 'button_text_color' => $this->ink(preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#3454ef')];
    }

    public function previewLink(BrandKit $kit, array $snapshot): Link
    {
        $link = new Link(['user_id' => $kit->user_id, 'type' => 'biolink', 'alias' => 'brand-showcase-preview', 'title' => $snapshot['name'], 'settings' => ['biolink' => $this->appearance($snapshot)], 'is_active' => false]);
        $blocks = collect($this->blocks($snapshot))->map(function ($data, $index) {
            $block = new BiolinkBlock($data);
            $block->id = -1 - $index;

            return $block;
        });
        $link->setRelation('biolinkBlocks', $blocks);
        $link->setRelation('user', User::find($kit->user_id));
        $link->setAttribute('_abVariantBlocks', $blocks);

        return $link;
    }

    public function seed(Link $link, BrandKit $kit, array $sections): void
    {
        DB::transaction(function () use ($link, $kit, $sections) {
            $snapshot = $this->snapshot($kit, $sections);
            $token = bin2hex(random_bytes(20));
            $base = url('/brand-showcases/'.$link->id.'/'.$token);
            $settings = $link->settings ?? [];
            $settings['biolink'] = array_replace($settings['biolink'] ?? [], $this->appearance($snapshot));
            if (($kit->config['icon_package_fingerprint'] ?? '') === BrandIconPackage::fingerprint($kit)) {
                $settings['biolink']['brand_icon_base'] = $base.'/icons/';
            }
            $settings['brand_showcase'] = ['token' => $token, 'snapshot' => $snapshot];
            $link->update(['settings' => $settings, 'is_active' => false]);
            $link->biolinkBlocks()->delete();
            $link->biolinkBlocks()->createMany($this->blocks($snapshot, $base));
        });
    }
}
