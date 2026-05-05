<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Short-lived signed URLs for paywalled / locked post media (Task
 * #1211). Used by the paywall partial so the actual media URL is
 * never exposed in HTML when a viewer hasn't unlocked the post.
 *
 * The signature itself only proves "we minted this URL" — the
 * controller still re-checks PostAccessPolicy at fetch time, so a
 * leaked URL can't be replayed once the viewer's access is revoked.
 */
class SignedMediaController extends Controller
{
    /**
     * Generate a temporary signed URL for media index $idx of post
     * $postId. Returns null if the viewer doesn't currently have
     * access. 5 min lifetime is enough for the page to load + the
     * <img> request to fire.
     */
    public static function makeUrl(int $postId, int $idx, int $minutes = 5): ?string
    {
        $viewer = ViewerSession::user() ?? auth()->user();
        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->whereKey($postId)->whereNotNull('published_at')->first();
        if (!$p) return null;

        $accessMap = \App\Services\Monetization\PostAccessPolicy::evaluateMany(
            $viewer, collect([$p])
        );
        $access = $accessMap[$p->id] ?? null;
        if (!$access || empty($access['can_view'])) return null;

        return URL::temporarySignedRoute('signed-media.serve', now()->addMinutes($minutes), [
            'post' => $postId,
            'idx'  => $idx,
            'v'    => $viewer?->id ?? 0,
        ]);
    }

    public function serve(Request $request, int $post, int $idx)
    {
        if (!$request->hasValidSignature()) abort(403);

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->whereKey($post)->whereNotNull('published_at')->first();
        if (!$p) abort(404);

        // Re-check access at fetch time (fail-closed if revoked).
        $viewer = ViewerSession::user() ?? auth()->user();
        $accessMap = \App\Services\Monetization\PostAccessPolicy::evaluateMany($viewer, collect([$p]));
        $access = $accessMap[$p->id] ?? null;
        if (!$access || empty($access['can_view'])) abort(403);

        // Task #1211 — re-enforce per-post country gating at the media-fetch
        // boundary. Profile-level decisions catch the page render but signed
        // media URLs can be bookmarked or hot-linked, so we have to check
        // again here. Fail closed (HTTP 451) if the viewer's region is gated.
        $creator = $p->user()->withTrashed()->first();
        if ($creator) {
            $gate = app(\App\Modules\Common\Services\CountryGate::class)
                ->decide($creator, $p, $request->ip());
            if (empty($gate['allowed'])) {
                abort(451, 'Not available in your region.');
            }
        }

        $media = is_array($p->media) ? array_values($p->media) : [];
        if (!isset($media[$idx])) abort(404);
        $url = $media[$idx]['url'] ?? ($media[$idx]['src'] ?? null);
        if (!$url) abort(404);

        if (str_starts_with($url, '/storage/')) {
            $path = substr($url, strlen('/storage/'));
            $disk = Storage::disk('public');
            if (!$disk->exists($path)) abort(404);
            return response($disk->get($path), 200, [
                'Content-Type'  => $disk->mimeType($path) ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }
        return redirect()->away($url);
    }
}
