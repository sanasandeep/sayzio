<?php

namespace App\Services\ReviewProviders;

use App\Modules\User\Models\ExternalReview;
use App\Modules\User\Models\ReviewProvider;
use Illuminate\Support\Facades\DB;

/**
 * Per-provider adapter for pulling 3rd-party reviews into Sayzio.
 *
 * Contract:
 *   - fetch($connection): array
 *       Return a list of normalized review rows (see normalizeRow()) from
 *       the provider's API. When credentials are missing the adapter returns
 *       a small, clearly-labelled preview sample so the UX flows end-to-end.
 *
 *   - sync($connection): array
 *       Fetch + upsert into external_reviews with a stable dedup key, then
 *       stamp the connection's status + last_synced_at. Returns a summary
 *       ['imported' => int, 'preview' => bool].
 */
abstract class ReviewSyncAdapter
{
    public function __construct(protected array $provider) {}

    public function slug(): string { return $this->provider['slug']; }
    public function name(): string { return $this->provider['name']; }
    public function descriptor(): array { return $this->provider; }

    /** True iff every env key the provider declared is set. */
    public function credentialsConfigured(): bool
    {
        foreach (($this->provider['env_keys'] ?? []) as $key) {
            if (!env($key)) return false;
        }
        return true;
    }

    /**
     * Fetch reviews for the connection. Concrete adapters issue the live API
     * call when credentials are present; otherwise they fall back to
     * previewSample().
     *
     * @return array<int,array<string,mixed>>
     */
    abstract public function fetch(ReviewProvider $connection): array;

    /**
     * A clearly-labelled preview sample shown when the provider's API
     * credentials aren't configured, so the connect/sync flow is testable.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function previewSample(ReviewProvider $connection): array
    {
        $name = $this->name();
        return [
            $this->normalizeRow([
                'source_id'     => 'preview-1',
                'author_name'   => 'Preview Reviewer',
                'rating'        => 5,
                'body'          => "This is a preview {$name} review. Connect {$name} with API credentials to import your real reviews.",
                'reviewed_at'   => now()->subDays(2),
            ]),
            $this->normalizeRow([
                'source_id'     => 'preview-2',
                'author_name'   => 'Sample Customer',
                'rating'        => 4,
                'body'          => "Another sample {$name} review for layout preview.",
                'reviewed_at'   => now()->subDays(9),
            ]),
        ];
    }

    /** Normalize a raw row into the shape external_reviews expects. */
    protected function normalizeRow(array $row): array
    {
        return [
            'source_id'     => $row['source_id'] ?? null,
            'author_name'   => $row['author_name'] ?? 'Anonymous',
            'author_avatar' => $row['author_avatar'] ?? null,
            'rating'        => isset($row['rating']) ? max(1, min(5, (int) $row['rating'])) : null,
            'body'          => $row['body'] ?? null,
            'source_url'    => $row['source_url'] ?? null,
            'reviewed_at'   => $row['reviewed_at'] ?? null,
            'payload'       => $row['payload'] ?? null,
        ];
    }

    /**
     * Fetch + upsert into external_reviews with dedup, then stamp status.
     *
     * @return array{imported:int,preview:bool}
     */
    public function sync(ReviewProvider $connection): array
    {
        $preview = !$this->credentialsConfigured();

        try {
            $rows = $this->fetch($connection);
        } catch (\Throwable $e) {
            $connection->status = ReviewProvider::STATUS_ERROR;
            $connection->status_reason = 'Sync failed: ' . $e->getMessage();
            $connection->last_synced_at = now();
            $connection->save();
            return ['imported' => 0, 'preview' => $preview];
        }

        $imported = 0;
        foreach ($rows as $row) {
            $dedup = $this->dedupKey($connection, $row);
            $existing = ExternalReview::where('dedup_key', $dedup)->first();
            $attrs = [
                'user_id'       => $connection->user_id,
                'provider_id'   => $connection->id,
                'provider'      => $connection->provider,
                'source_id'     => $row['source_id'] ?? null,
                'dedup_key'     => $dedup,
                'author_name'   => $row['author_name'] ?? null,
                'author_avatar' => $row['author_avatar'] ?? null,
                'rating'        => $row['rating'] ?? null,
                'body'          => $row['body'] ?? null,
                'source_url'    => $row['source_url'] ?? null,
                'payload'       => $row['payload'] ?? null,
                'reviewed_at'   => $row['reviewed_at'] ?? null,
            ];
            if ($existing) {
                $existing->update($attrs);
            } else {
                ExternalReview::create($attrs);
                $imported++;
            }
        }

        $connection->status = $preview ? ReviewProvider::STATUS_PREVIEW : ReviewProvider::STATUS_CONNECTED;
        $connection->status_reason = $preview
            ? 'API credentials not configured — showing a preview sample.'
            : null;
        $connection->last_synced_at = now();
        $connection->save();

        return ['imported' => $imported, 'preview' => $preview];
    }

    /** Stable dedup key per (provider connection, source review). */
    protected function dedupKey(ReviewProvider $connection, array $row): string
    {
        $basis = $row['source_id'] ?? md5(($row['author_name'] ?? '') . '|' . ($row['body'] ?? ''));
        return substr($connection->user_id . ':' . $connection->provider . ':' . $basis, 0, 191);
    }
}
