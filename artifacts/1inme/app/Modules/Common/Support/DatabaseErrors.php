<?php

namespace App\Modules\Common\Support;

use Illuminate\Database\QueryException;

/**
 * Small helpers for classifying database errors so callers can degrade
 * gracefully on a specific, well-understood failure (an un-migrated /
 * missing table) without accidentally swallowing unrelated DB errors.
 */
class DatabaseErrors
{
    /**
     * Whether a QueryException was caused specifically by the target table
     * not existing — i.e. an un-migrated environment.
     *
     * Detection is driven primarily by the driver SQLSTATE, which is the
     * authoritative, table-specific signal:
     *   - Postgres `42P01` (undefined_table)
     *   - MySQL    `42S02` (base table or view not found)
     *
     * A driver-message fallback is kept only for cases where the SQLSTATE is
     * not surfaced, and it is intentionally narrow so unrelated "does not
     * exist" errors (missing column / function / type, etc.) are NOT matched:
     *   - Postgres: `relation "<name>" does not exist`
     *   - SQLite:   `no such table: <name>`
     *   - MySQL:    `... table ... doesn't exist`
     */
    public static function isMissingTable(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();
        if ($sqlState === '42P01' || $sqlState === '42S02') {
            return true;
        }

        $message = strtolower($e->getMessage());

        // Postgres undefined_table is reported as `relation "x" does not exist`.
        // Requiring "relation" keeps missing-column/function errors (which also
        // say "does not exist") from being treated as a missing table.
        if (str_contains($message, 'relation') && str_contains($message, 'does not exist')) {
            return true;
        }

        // SQLite.
        if (str_contains($message, 'no such table')) {
            return true;
        }

        // MySQL: "Base table or view '...' doesn't exist".
        if (str_contains($message, 'table') && str_contains($message, "doesn't exist")) {
            return true;
        }

        return false;
    }
}
