<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single Asset Vault zip-import run. The HTTP request only records the
 * source (uploaded temp zip or remote URL/S3 location); the actual download
 * + extraction happens in ProcessAdminAssetZipImportJob, which streams
 * progress back onto this row so the vault UI can poll it.
 */
class AdminAssetImport extends Model
{
    /** Cap for how many skipped entries we keep on the row (summary only). */
    public const MAX_SKIPPED_DETAILS = 200;

    /**
     * How long an "active" import may go without any row update before it is
     * considered dead (worker crashed / deploy restart mid-run). The job saves
     * progress at least every 20 entries, so a live import keeps touching
     * updated_at; matches the job's 1h timeout ceiling.
     *
     * Policy: this deliberately includes "pending" rows — an import queued
     * with no pickup for over an hour (severe backlog or a dead queue) is
     * failed open so admins are never locked out; re-running the same
     * archive is idempotent, so nothing is lost.
     */
    public const STALE_AFTER_MINUTES = 60;

    protected $fillable = [
        'admin_id', 'status', 'source_type', 'source', 'mode',
        'zip_path', 'zip_size_bytes',
        'total_entries', 'processed_entries',
        'imported_count', 'overwritten_count', 'skipped_count',
        'skipped', 'error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'skipped'           => 'array',
        'zip_size_bytes'    => 'integer',
        'total_entries'     => 'integer',
        'processed_entries' => 'integer',
        'imported_count'    => 'integer',
        'overwritten_count' => 'integer',
        'skipped_count'     => 'integer',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'downloading', 'processing'], true);
    }

    /**
     * A run stopped by an admin rather than a genuine failure — either a
     * dedicated 'cancelled' status or a legacy 'failed' row whose error
     * records the admin cancellation.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled'
            || ($this->status === 'failed' && preg_match('/cancelled by (an )?admin/i', (string) $this->error) === 1);
    }

    /**
     * Mark any active imports whose row hasn't been touched within the stale
     * window as failed, so a dead queue worker never locks out imports
     * forever. Returns the number of rows reaped.
     */
    public static function failStale(): int
    {
        $cutoff = now()->subMinutes(self::STALE_AFTER_MINUTES);

        return static::query()
            ->whereIn('status', ['pending', 'downloading', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status'       => 'failed',
                'error'        => 'Import stalled with no progress for over ' . self::STALE_AFTER_MINUTES . ' minutes and was marked failed automatically (the worker likely restarted mid-run).',
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);
    }

    /** Record a skipped entry (bounded detail list + counter). */
    public function noteSkipped(string $path, string $reason): void
    {
        $this->skipped_count++;
        $list = (array) ($this->skipped ?? []);
        if (count($list) < self::MAX_SKIPPED_DETAILS) {
            $list[] = ['path' => mb_substr($path, 0, 300), 'reason' => $reason];
            $this->skipped = $list;
        }
    }
}
