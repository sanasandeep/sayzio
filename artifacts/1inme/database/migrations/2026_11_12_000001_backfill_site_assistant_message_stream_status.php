<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill an explicit `meta.stream.status = "unknown"` marker on every
 * pre-existing assistant message that was written before the runtime
 * started stamping `meta.stream` on each turn.
 *
 * Without this, the admin transcript view falls back to "classic" for
 * any assistant message missing `meta.stream`, which is misleading for
 * historical conversations: those replies might actually have been
 * streamed — we just didn't record it. After this migration runs, the
 * UI can distinguish three states cleanly:
 *   - meta.stream.status = "streamed" | "partial" | "failed" | "classic"
 *     → the runtime explicitly recorded what happened
 *   - meta.stream.status = "unknown"
 *     → historical row, delivery mode is genuinely not known
 *   - meta.stream missing
 *     → should not occur once this migration runs and going-forward
 *       writes always stamp the marker
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('site_assistant_messages')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE site_assistant_messages
            SET meta = jsonb_set(
                COALESCE(meta, '{}'::jsonb),
                '{stream}',
                '{"status":"unknown"}'::jsonb,
                true
            )
            WHERE role = 'assistant'
              AND (meta IS NULL OR NOT (meta ? 'stream'))
        SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('site_assistant_messages')) {
            return;
        }

        // Only strip the marker we added — leave any real stream metadata
        // (streamed/partial/failed/classic) untouched.
        DB::statement(<<<'SQL'
            UPDATE site_assistant_messages
            SET meta = CASE
                WHEN (meta - 'stream') = '{}'::jsonb THEN NULL
                ELSE meta - 'stream'
            END
            WHERE role = 'assistant'
              AND meta IS NOT NULL
              AND meta #>> '{stream,status}' = 'unknown'
        SQL);
    }
};
