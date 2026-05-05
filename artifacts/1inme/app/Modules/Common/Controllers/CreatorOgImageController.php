<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\HttpFetchGuard;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Generated OG/Twitter share image for /@handle pages (Task #1211).
 * Renders a 1200×630 PNG with the creator avatar, name, handle, and
 * tagline so any time the profile is shared on social media the
 * preview shows the creator instead of a generic 1INME card.
 *
 * Cached for 1 hour because the underlying creator profile rarely
 * changes faster than that, and re-rendering on every crawler hit
 * would be wasteful.
 */
class CreatorOgImageController extends Controller
{
    public function show(string $handle)
    {
        $creator = User::where('handle', strtolower(trim($handle)))->first();
        if (!$creator || !$creator->profile_published) abort(404);

        $key = "og:creator:{$creator->id}:" . md5(($creator->name ?? '') . ($creator->tagline ?? '') . ($creator->avatar ?? ''));
        $png = Cache::remember($key, 3600, fn() => $this->render($creator));

        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    protected function render(User $creator): string
    {
        $W = 1200; $H = 630;
        $im = imagecreatetruecolor($W, $H);

        // Diagonal violet → fuchsia gradient — same brand colours as
        // the directory header.
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $r = (int) round(124 + (219 - 124) * $t);
            $g = (int) round( 58 + ( 39 -  58) * $t);
            $b = (int) round(237 + (119 - 237) * $t);
            $line = imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, $W, $y, $line);
        }

        $white  = imagecolorallocate($im, 255, 255, 255);
        $soft   = imagecolorallocatealpha($im, 255, 255, 255, 50);

        // Avatar disc (left side, ~360px).
        $avatarBytes = $creator->avatar ? $this->fetchBytes($creator->avatar) : null;
        $cx = 250; $cy = 315; $rad = 160;
        if ($avatarBytes) {
            $av = @imagecreatefromstring($avatarBytes);
            if ($av) {
                $sw = imagesx($av); $sh = imagesy($av);
                $size = min($sw, $sh);
                $disc = imagecreatetruecolor($rad * 2, $rad * 2);
                imagecopyresampled($disc, $av, 0, 0, ($sw-$size)/2, ($sh-$size)/2, $rad*2, $rad*2, $size, $size);
                imagecopy($im, $disc, $cx-$rad, $cy-$rad, 0, 0, $rad*2, $rad*2);
                imagedestroy($av); imagedestroy($disc);
            }
        } else {
            imagefilledellipse($im, $cx, $cy, $rad*2, $rad*2, $white);
            $initials = mb_strtoupper(mb_substr($creator->name ?: 'U', 0, 1));
            imagestring($im, 5, $cx - 8, $cy - 8, $initials, imagecolorallocate($im, 124, 58, 237));
        }
        // Avatar ring.
        for ($i = 0; $i < 6; $i++) {
            imageellipse($im, $cx, $cy, ($rad+$i)*2, ($rad+$i)*2, $white);
        }

        $name    = mb_substr($creator->name ?: '@' . $creator->handle, 0, 36);
        $hand    = '@' . $creator->handle;
        $tag     = mb_substr((string) ($creator->tagline ?: $creator->bio ?: ''), 0, 96);
        $brand   = '1INME';

        // Right column text (built-in font 5; legible at 1200×630).
        $tx = 470;
        imagestring($im, 5, $tx, 200, $name,  $white);
        imagestring($im, 5, $tx, 240, $hand,  $soft);
        if ($tag !== '') {
            imagestring($im, 5, $tx, 320, $tag, $white);
        }
        imagestring($im, 5, $tx, 460, $brand, $soft);

        ob_start();
        imagepng($im, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($im);
        return $bytes;
    }

    protected function fetchBytes(string $url): ?string
    {
        if (str_starts_with($url, '/storage/')) {
            $path = public_path(ltrim($url, '/'));
            return file_exists($path) ? file_get_contents($path) : null;
        }
        // Task #1211 — SSRF guard. Avatar URLs come from user input on
        // signup, so without this an attacker could set their avatar to
        // e.g. http://169.254.169.254/ and have our OG renderer fetch
        // cloud metadata on every share-card render.
        if (!HttpFetchGuard::isSafeRemoteUrl($url)) {
            return null;
        }
        try {
            $resp = Http::timeout(3)->withOptions(['allow_redirects' => false])->get($url);
            return $resp->successful() ? $resp->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
