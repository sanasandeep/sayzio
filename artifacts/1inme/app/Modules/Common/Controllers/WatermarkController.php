<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\HttpFetchGuard;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\Common\Services\WatermarkService;
use App\Modules\User\Models\CreatorPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Streams a watermarked PNG of an image that belongs to a published
 * creator post (Task #1211). The URL pattern is:
 *
 *   /watermark/p/{post}/{idx}.png
 *
 * Rules:
 *  - The post must be published.
 *  - The creator must have watermarking enabled.
 *  - The viewer must be logged in (so we have a real handle to stamp).
 *    Anonymous viewers fall back to the original media URL.
 *  - The image is rendered once per (post-image, viewer) pair and
 *    cached in the application cache for 6 hours so that repeated
 *    impressions on the feed don't re-render every request.
 */
class WatermarkController extends Controller
{
    public function __construct(protected WatermarkService $service) {}

    public function serve(Request $request, int $post, int $idx)
    {
        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->whereKey($post)->whereNotNull('published_at')->first();
        if (!$p) abort(404);

        $creator = $p->user()->withTrashed()->first();
        if (!$creator || !$this->service->isEnabled($creator)) abort(404);

        // Task #1211 — enforce per-post country gating at the media boundary.
        // Profile-level CountryGate catches the page render but watermarked
        // media URLs can be hot-linked, so we re-check here. Fail closed
        // (HTTP 451) if the viewer's region is gated for this creator/post.
        $gate = app(\App\Modules\Common\Services\CountryGate::class)
            ->decide($creator, $p, $request->ip());
        if (empty($gate['allowed'])) {
            abort(451, 'Not available in your region.');
        }

        $media = is_array($p->media) ? array_values($p->media) : [];
        if (!isset($media[$idx])) abort(404);
        $url = $media[$idx]['url'] ?? ($media[$idx]['src'] ?? null);
        if (!$url) abort(404);

        $viewer = ViewerSession::user() ?? auth()->user();
        if (!$viewer) {
            // Anonymous fallback — never bake an empty viewer name into
            // the overlay; just hand them the original.
            return redirect()->away($url);
        }

        $cacheKey = "wm:{$p->id}:{$idx}:{$viewer->id}";
        $png = Cache::remember($cacheKey, 6 * 3600, function () use ($url, $creator, $viewer) {
            $bytes = $this->fetchBytes($url);
            if ($bytes === null) return null;
            return $this->service->render($bytes, $creator, $viewer);
        });
        if ($png === null) {
            return redirect()->away($url);
        }
        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'private, max-age=21600',
        ]);
    }

    /**
     * Fetch the source image. Local /storage/* paths bypass the HTTP
     * client so we don't loop back through the proxy for our own files.
     */
    protected function fetchBytes(string $url): ?string
    {
        if (str_starts_with($url, '/storage/')) {
            $path = substr($url, strlen('/storage/'));
            $disk = Storage::disk('public');
            return $disk->exists($path) ? $disk->get($path) : null;
        }

        // Task #1211 — SSRF guard. Without this, an attacker could craft a
        // post with media['url'] pointing at 127.0.0.1, the metadata IP, or
        // an internal service and have our server cache the response back
        // to them via the watermark proxy. Allow only http(s) URLs that
        // resolve to a public IP.
        if (!HttpFetchGuard::isSafeRemoteUrl($url)) {
            return null;
        }
        try {
            $resp = Http::timeout(5)->withOptions(['allow_redirects' => false])->get($url);
            return $resp->successful() ? $resp->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
