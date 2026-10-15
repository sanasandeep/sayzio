<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Facades\Storage;

class BrandIconPackage
{
    public static function fingerprint(BrandKit $kit): string
    {
        $c = $kit->config ?? [];

        return hash('sha256', json_encode([$kit->name, array_filter(BrandKitPresentation::values($c), fn ($key) => str_starts_with($key, 'icon_'), ARRAY_FILTER_USE_KEY), array_intersect_key($c, array_flip(['logo', 'favicon', 'logo_light', 'logo_dark']))]));
    }

    public function files(BrandKit $kit): array
    {
        $c = $kit->config ?? [];
        $p = BrandKitPresentation::values($c);
        $files = [];
        $notes = [];
        $valid = ($c['icon_package_fingerprint'] ?? '') === self::fingerprint($kit);
        foreach (['main' => [16, 32, 48, 96, 192, 512], 'dark' => [32], 'apple' => [180], 'android' => [192, 512]] as $variant => $sizes) {
            $id = $valid ? ($c['icon_package_files'][$variant] ?? null) : null;
            $file = $id ? UserFile::where('user_id', $kit->user_id)->find($id) : null;
            if (! $file || ! $file->isScanClean() || $file->quarantined_at) {
                $notes[] = "Generate icons in the saved kit to include {$variant} PNG files.";

                continue;
            }
            abort_unless(function_exists('imagecreatefromstring'), 503, 'PNG icon processing is unavailable on this server.');
            $bytes = Storage::disk($file->disk ?: 'user_files')->get($file->path);
            $image = @imagecreatefromstring($bytes);
            if (! $image) {
                continue;
            }
            foreach ($sizes as $size) {
                $out = imagecreatetruecolor($size, $size);
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagecopyresampled($out, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
                ob_start();
                imagepng($out);
                $png = ob_get_clean();
                imagedestroy($out);
                $name = match ($variant) {
                    'main' => "favicon-{$size}.png",'dark' => 'favicon-dark.png','apple' => 'apple-touch-icon.png',default => "android-{$size}.png"
                };
                $files[$name] = $png;
            }imagedestroy($image);
        }
        if (isset($files['favicon-16.png'])) {
            $icons = array_map(fn ($size) => $files["favicon-{$size}.png"], [16, 32, 48]);
            $ico = pack('vvv', 0, 1, 3);
            $offset = 6 + 16 * 3;
            foreach ([16, 32, 48] as $i => $size) {
                $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($icons[$i]), $offset);
                $offset += strlen($icons[$i]);
            }
            $files['favicon.ico'] = $ico.implode('', $icons);
        }
        // Preserve source SVG only; never claim a raster conversion is a vector.
        $source = $p['icon_source'] === 'favicon' ? ($c['favicon'] ?? '') : ($c['logo'] ?? '');
        $path = parse_url($source, PHP_URL_PATH) ?: '';
        if (preg_match('~^/brand-demo/([a-z0-9-]+\.svg)$~', $path, $m) && is_file(public_path('brand-demo/'.$m[1]))) {
            $files['favicon.svg'] = file_get_contents(public_path('brand-demo/'.$m[1]));
        }
        if (preg_match('~^/f/(\d+)/~', $path, $m)) {
            $f = UserFile::where('user_id', $kit->user_id)->find($m[1]);
            if ($f && $f->mime_type === 'image/svg+xml' && $f->isScanClean() && ! $f->quarantined_at && $f->size_bytes < 1024 * 1024) {
                $files['favicon.svg'] = Storage::disk($f->disk ?: 'user_files')->get($f->path);
            }
        }
        $base = '/'.trim(preg_replace('~[^a-zA-Z0-9/_-]~', '', $p['icon_path']), '/');
        $base = rtrim($base, '/').'/';
        $version = $p['icon_version'] !== '' ? '?v='.rawurlencode($p['icon_version']) : '';
        $manifest = ['name' => $p['icon_app_name'] ?: $kit->name, 'short_name' => $p['icon_short_name'] ?: mb_substr($kit->name, 0, 12), 'start_url' => '/', 'display' => 'standalone', 'background_color' => $p['icon_background'], 'theme_color' => $p['icon_theme'], 'icons' => []];
        foreach ([192, 512] as $size) {
            if (isset($files["android-{$size}.png"])) {
                $manifest['icons'][] = ['src' => $base."android-{$size}.png".$version, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
            }
        }
        // Dedicated safe-area maskable raster is generated separately in the browser.
        if ($valid && ! empty($c['icon_package_files']['maskable'])) {
            $f = UserFile::where('user_id', $kit->user_id)->find($c['icon_package_files']['maskable']);
            if ($f && $f->isScanClean() && ! $f->quarantined_at) {
                $files['maskable-512.png'] = Storage::disk($f->disk ?: 'user_files')->get($f->path);
                $manifest['icons'][] = ['src' => $base.'maskable-512.png'.$version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
            }
        }
        $files['site.webmanifest'] = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $html = [];
        foreach (['favicon.ico' => 'rel="icon" sizes="any"', 'favicon.svg' => 'rel="icon" type="image/svg+xml"', 'favicon-32.png' => 'rel="icon" type="image/png" sizes="32x32"', 'favicon-dark.png' => 'rel="icon" type="image/png" media="(prefers-color-scheme: dark)"', 'apple-touch-icon.png' => 'rel="apple-touch-icon" sizes="180x180"'] as $file => $attrs) {
            if (isset($files[$file])) {
                $html[] = '<link '.$attrs.' href="'.$base.$file.$version.'">';
            }
        }
        $html[] = '<link rel="manifest" href="'.$base.'site.webmanifest'.$version.'">';
        $files['head.html'] = implode("\n", $html);
        $files['README.txt'] = "Icon installation\nUpload files to {$base} and paste head.html inside your page <head>.\nGenerated raster files use your saved icon settings. Original SVG is included where available.\nA manifest alone does not provide offline support or guarantee app installation. Search previews are illustrative.\n".implode("\n", array_unique($notes));

        return $files;
    }
}
