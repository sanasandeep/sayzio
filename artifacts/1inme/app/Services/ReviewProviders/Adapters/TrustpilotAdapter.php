<?php

namespace App\Services\ReviewProviders\Adapters;

use App\Modules\User\Models\ReviewProvider;
use App\Services\ReviewProviders\ReviewSyncAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Trustpilot business reviews via the public Business Unit reviews endpoint.
 * Requires a TRUSTPILOT_API_KEY and the creator's Business Unit ID (stored
 * on the connection's external_ref). Falls back to a preview sample when the
 * key is absent.
 *
 * Public (read-only) Trustpilot endpoints authenticate with the API key passed
 * as the `apikey` query parameter.
 */
class TrustpilotAdapter extends ReviewSyncAdapter
{
    /** Resolve the API key via config (cache-safe) with an env fallback. */
    protected function apiKey(): ?string
    {
        $key = config('services.trustpilot.api_key') ?: env('TRUSTPILOT_API_KEY');
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

        $unit = rawurlencode($connection->external_ref);
        $resp = Http::timeout(15)
            ->get("https://api.trustpilot.com/v1/business-units/{$unit}/reviews", [
                'apikey'  => $this->apiKey(),
                'orderBy' => 'createdat.desc',
                'perPage' => 50,
            ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Trustpilot API returned HTTP ' . $resp->status());
        }

        $reviews = $resp->json('reviews') ?? [];
        $out = [];
        foreach ($reviews as $r) {
            $out[] = $this->normalizeRow([
                'source_id'     => (string) ($r['id'] ?? md5(json_encode($r))),
                'author_name'   => $r['consumer']['displayName'] ?? 'Trustpilot user',
                'rating'        => $r['stars'] ?? null,
                'body'          => $r['text'] ?? ($r['title'] ?? null),
                'source_url'    => $this->reviewUrl($r),
                'reviewed_at'   => isset($r['createdAt']) ? Carbon::parse($r['createdAt']) : null,
                'payload'       => $r,
            ]);
        }

        return $out;
    }

    /** Best-effort public link to the review on Trustpilot, when provided. */
    private function reviewUrl(array $review): ?string
    {
        return $review['links']['profileUrl']
            ?? ($review['businessUnit']['profileUrl'] ?? null);
    }
}
