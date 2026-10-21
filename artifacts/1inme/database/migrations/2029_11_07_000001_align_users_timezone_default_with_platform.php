<?php

use App\Support\PlatformTimezone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `users.timezone` has carried a column default of 'UTC' since the
 * original 2024 table. Task #3480 made Asia/Kolkata the platform default
 * and set it as the model's `$attributes` default, but nothing ever
 * changed the column, so the two disagree: a row inserted through
 * Eloquent gets IST, a row inserted any other way gets UTC.
 *
 * This aligns the column with the model. It changes the DEFAULT only --
 * no existing row is touched.
 *
 * Deliberately not backfilled. A stored 'UTC' is indistinguishable from
 * a user who genuinely chose UTC, and there is no signal in the table
 * separating "never picked one" from "picked UTC". Rewriting those rows
 * would silently move real people's events by five and a half hours.
 * The fix for them is the form's browser detection, which asks the one
 * party that actually knows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setDefault(PlatformTimezone::DEFAULT);
    }

    public function down(): void
    {
        $this->setDefault('UTC');
    }

    private function setDefault(string $timezone): void
    {
        if (!Schema::hasColumn('users', 'timezone')) {
            return;
        }

        // ALTER COLUMN ... SET DEFAULT is Postgres-specific DDL; SQLite
        // cannot express it and does not need to, since it is only ever
        // used for throwaway schemas.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Postgres accepts no bind parameters in DDL -- a placeholder here
        // fails with "could not determine data type of parameter $1" --
        // so the value has to be inlined. It comes from a class constant
        // rather than from input, and is checked against the real zone
        // list before it goes anywhere near the statement.
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \RuntimeException("Refusing to set an unrecognised timezone default: {$timezone}");
        }

        // ALTER ... SET DEFAULT is a catalog-only change: no table
        // rewrite, no lock held over 375k rows.
        DB::statement("ALTER TABLE users ALTER COLUMN timezone SET DEFAULT '{$timezone}'");
    }
};
