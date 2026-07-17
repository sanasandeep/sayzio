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

    /**
     * Whether a throwable means "the database is effectively unavailable" —
     * either the server can't be reached at all (connection-level PDO
     * failures) or the environment is un-migrated (missing table). Used by
     * high-traffic public entry points (short-link alias resolution) to
     * degrade to a branded 503 instead of a raw 500.
     *
     * Intentionally does NOT match ordinary query errors (bad SQL, missing
     * column, constraint violations) — those are application bugs and must
     * keep surfacing loudly.
     */
    public static function isUnavailable(\Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            if (self::isMissingTable($e)) {
                return true;
            }

            return self::isConnectionFailure($e->getPrevious() ?? $e);
        }

        if ($e instanceof \PDOException) {
            return self::isConnectionFailure($e);
        }

        return false;
    }

    /**
     * Connection-level failure detection: SQLSTATE class 08 (connection
     * exception) plus the common driver messages Postgres/MySQL emit when
     * the server is down, unreachable, or dropped the connection.
     */
    private static function isConnectionFailure(\Throwable $e): bool
    {
        $sqlState = null;
        if ($e instanceof \PDOException) {
            $sqlState = $e->errorInfo[0] ?? (is_string($e->getCode()) ? $e->getCode() : null);
        } elseif ($e instanceof QueryException) {
            $sqlState = $e->errorInfo[0] ?? null;
        }

        if (is_string($sqlState) && str_starts_with($sqlState, '08')) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach ([
            'connection refused',
            'could not connect',
            'connection timed out',
            'server has gone away',
            'no connection to the server',
            'server closed the connection unexpectedly',
            'name or service not known',
            'could not translate host name',
            'the database system is starting up',
            'the database system is shutting down',
            'too many connections',
            'sqlstate[08',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
