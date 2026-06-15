<?php

namespace App\Services\ReviewProviders\Adapters;

use App\Modules\User\Models\ReviewProvider;
use App\Services\ReviewProviders\ReviewSyncAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Business Profile reviews via the Places API. Requires a
 * GOOGLE_PLACES_API_KEY and the creator's Place ID (stored on the
 * connection's external_ref). Falls back to a preview sample when the key
 * is absent.
 */
class GoogleReviewsAdapter extends ReviewSyncAdapter
{
    public function fetch(ReviewProvider $connection): array
    {
        if (!$this->credentialsConfigured() || !$connection->external_ref) {
            return $this->previewSample($connection);
        }

        $resp = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $connection->external_ref,
            'fields'   => 'reviews',
            'reviews_sort' => 'newest',
            'key'      => env('GOOGLE_PLACES_API_KEY'),
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Google Places API returned HTTP ' . $resp->status());
        }

        $reviews = $resp->json('result.reviews') ?? [];
        $out = [];
        foreach ($reviews as $r) {
            $out[] = $this->normalizeRow([
                'source_id'     => (string) ($r['time'] ?? md5(($r['author_name'] ?? '') . ($r['text'] ?? ''))),
                'author_name'   => $r['author_name'] ?? 'Google user',
                'author_avatar' => $r['profile_photo_url'] ?? null,
                'rating'        => $r['rating'] ?? null,
                'body'          => $r['text'] ?? null,
                'source_url'    => $r['author_url'] ?? null,
                'reviewed_at'   => isset($r['time']) ? Carbon::createFromTimestamp($r['time']) : null,
                'payload'       => $r,
            ]);
        }

        return $out;
    }
}
