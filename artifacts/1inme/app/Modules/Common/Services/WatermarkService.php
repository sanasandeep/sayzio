<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Image watermarking (Task #1211). Renders a translucent overlay
 * with the creator handle + viewer username on every image served
 * via the Watermark controller. Backed by GD (always shipped with
 * the PHP runtime here) so it has no extra-package footprint.
 *
 * Watermarking is a deterrent, not DRM. The same viewer always sees
 * the same overlay so screenshots can be traced back, but a
 * determined attacker can always re-photograph the screen — the
 * docs are explicit about this trade-off.
 */
class WatermarkService
{
    /** Default settings applied when a creator has no `watermark_settings`. */
    public const DEFAULTS = [
        'enabled'       => false,
        'opacity'       => 35,             // 10..90
        'position'      => 'br',           // tl|tr|bl|br|center
        'text_template' => '@{handle} • viewed by {viewer}',
    ];

    public function settings(User $creator): array
    {
        $s = is_array($creator->watermark_settings ?? null) ? $creator->watermark_settings : [];
        return array_merge(self::DEFAULTS, $s);
    }

    public function isEnabled(User $creator): bool
    {
        return (bool) $this->settings($creator)['enabled'];
    }

    /**
     * Build the overlay text for $creator viewed by $viewer. Uses the
     * creator's `text_template` so they can pick e.g. an email-style
     * "Property of @creator — viewed by @viewer" wording.
     */
    public function text(User $creator, ?User $viewer): string
    {
        $tpl = $this->settings($creator)['text_template'] ?: self::DEFAULTS['text_template'];
        return strtr($tpl, [
            '{handle}'  => $creator->handle ?: 'creator',
            '{viewer}'  => $viewer?->handle ?: ($viewer?->name ?: 'guest'),
            '{date}'    => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Render a watermarked PNG of the source bytes. Returns binary PNG
     * data on success, or null when GD couldn't decode the source —
     * the controller falls back to streaming the original file.
     */
    public function render(string $sourceBytes, User $creator, ?User $viewer): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            Log::warning('Watermark: GD not available, returning original');
            return null;
        }
        $im = @imagecreatefromstring($sourceBytes);
        if (!$im) return null;

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w <= 0 || $h <= 0) { imagedestroy($im); return null; }

        // Convert palette images to truecolor so blending works.
        if (!imageistruecolor($im)) {
            $tc = imagecreatetruecolor($w, $h);
            imagealphablending($tc, true);
            imagesavealpha($tc, true);
            imagecopy($tc, $im, 0, 0, 0, 0, $w, $h);
            imagedestroy($im);
            $im = $tc;
        }
        imagealphablending($im, true);
        imagesavealpha($im, true);

        $settings = $this->settings($creator);
        $text     = $this->text($creator, $viewer);
        $opacity  = max(10, min(90, (int) $settings['opacity']));
        // GD alpha goes 0 (opaque) → 127 (transparent).
        $alpha    = (int) round(127 * (1 - $opacity / 100));

        // Pick a font size proportional to the image's shorter edge so
        // the overlay reads on both 1080×1080 portraits and 320×240
        // thumbnails without extra config.
        $base   = min($w, $h);
        $size   = max(10, (int) round($base * 0.025));
        $pad    = (int) round($size * 0.8);

        // GD's built-in font 5 is monospaced and always available; we
        // approximate the bounding box manually rather than relying on
        // imagettfbbox (which needs a TTF on disk).
        $charW  = imagefontwidth(5);
        $charH  = imagefontheight(5);
        $tw     = $charW * mb_strlen($text);
        $th     = $charH;

        // Position presets.
        switch ($settings['position']) {
            case 'tl':     $x = $pad;            $y = $pad;            break;
            case 'tr':     $x = $w - $tw - $pad; $y = $pad;            break;
            case 'bl':     $x = $pad;            $y = $h - $th - $pad; break;
            case 'center': $x = (int) (($w - $tw) / 2); $y = (int) (($h - $th) / 2); break;
            case 'br':
            default:       $x = $w - $tw - $pad; $y = $h - $th - $pad; break;
        }

        // Soft black drop-shadow + white overlay text for legibility on
        // both bright and dark images.
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, $alpha);
        $white  = imagecolorallocatealpha($im, 255, 255, 255, $alpha);
        imagestring($im, 5, $x + 1, $y + 1, $text, $shadow);
        imagestring($im, 5, $x,     $y,     $text, $white);

        // Render to PNG in memory.
        ob_start();
        imagepng($im, null, 6);
        $png = ob_get_clean();
        imagedestroy($im);
        return $png ?: null;
    }
}
