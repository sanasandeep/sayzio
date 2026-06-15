<?php

namespace App\Services\ReviewProviders\Adapters;

use App\Modules\User\Models\ReviewProvider;
use App\Services\ReviewProviders\ReviewSyncAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Business Profile reviews via the Places Details API. Requires a
 * GOOGLE_PLACES_API_KEY and the creator's Place ID (stored on the
 * connection's external_ref). Falls back to a preview sample when the key
 * is absent.
 *
 * Note: the Places Details API returns the (up to) 5 most relevant reviews
 * for a place and signals errors with an HTTP 200 + a `status` field rather
 * than an HTTP error code, so we must inspect `status` to detect failures.
 */
class GoogleReviewsAdapter extends ReviewSyncAdapter
{
    /** Resolve the API key via config (cache-safe) with an env fallback. */
    protected function apiKey(): ?string
    {
        $key = config('services.google_places.api_key') ?: env('GOOGLE_PLACES_API_KEY');
        return $key !== null && $key !== '' ? (string) $key : null;
    }

    public function credentialsConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function fetch(ReviewProvider $connection): array
    {
        if (!$this->credentialsConfigured() || !$connection->external_ref) {
            return $this->previewSample($connection);
        }

        $resp = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id'     => $connection->external_ref,
            'fields'       => 'reviews',
            'reviews_sort' => 'newest',
            'key'          => $this->apiKey(),
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Google Places API returned HTTP ' . $resp->status());
        }

        // The Places API reports logical errors via the `status` field while
        // still returning HTTP 200. OK and ZERO_RESULTS are non-error states.
        $status = $resp->json('status');
        if ($status !== null && !in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            $message = $resp->json('error_message') ?: $status;
            throw new \RuntimeException('Google Places API error: ' . $message);
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
