<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * A database error never reaches the user as its own message.
 *
 * This exists because it already happened on production. Running a competitor
 * teardown against a table missing a column printed this onto the page:
 *
 *   SQLSTATE[42703]: Undefined column ... (Connection: pgsql,
 *   Host: <rds host>, Port: 5432, Database: postgres, SQL: insert into
 *   "competitor_teardowns" (...) values (...)
 *
 * plus the entire scraped page payload. The controller looked careful -- it
 * had a generic `catch (\Throwable)` arm -- but QueryException extends
 * PDOException extends RuntimeException, so the DB error was caught by the
 * `catch (\RuntimeException)` arm above it, whose job is to pass a service's
 * human-written message through. 92 call sites across 46 files had that same
 * shape. None of it is gated by APP_DEBUG.
 */
class SafeErrorMessageTest extends TestCase
{
    private function queryException(): QueryException
    {
        // Shaped like the real one, including the connection details that are
        // the actual leak.
        return new QueryException(
            'pgsql',
            'insert into "competitor_teardowns" ("user_id", "workspace_id") values (?, ?)',
            [229, 316],
            new \PDOException('SQLSTATE[42703]: Undefined column: 7 ERROR: column "workspace_id" does not exist'),
            ['host' => 'oneinme-prod.example.ap-south-2.rds.amazonaws.com', 'port' => '5432', 'database' => 'postgres'],
        );
    }

    public function test_an_ordinary_runtime_exception_still_speaks_for_itself(): void
    {
        // The whole point of those catch arms: services throw RuntimeException
        // with copy meant for a person, and that must keep reaching them.
        $this->assertSame(
            "This teardown isn't ready yet.",
            safe_error_message(new \RuntimeException("This teardown isn't ready yet."))
        );

        $this->assertSame('Plain failure', safe_error_message(new \Exception('Plain failure')));
    }

    public function test_a_query_exception_is_replaced_wholesale(): void
    {
        $out = safe_error_message($this->queryException());

        $this->assertSame('Something went wrong. Please try again.', $out);
    }

    public function test_the_fallback_is_overridable_per_call_site(): void
    {
        $this->assertSame(
            "We couldn't save that.",
            safe_error_message($this->queryException(), "We couldn't save that.")
        );
    }

    public function test_nothing_about_the_database_survives_into_the_message(): void
    {
        $out = safe_error_message($this->queryException());

        foreach ([
            'SQLSTATE',
            '42703',
            'rds.amazonaws.com',
            '5432',
            'postgres',
            'insert into',
            'competitor_teardowns',
            'workspace_id',
        ] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $out, "'{$leak}' reached the user-facing message");
        }
    }

    public function test_a_pdo_exception_is_covered_not_just_the_laravel_wrapper(): void
    {
        // QueryException is the common case, but a bare PDOException thrown by
        // a raw PDO call carries the same kind of detail.
        $this->assertSame(
            'Something went wrong. Please try again.',
            safe_error_message(new \PDOException('SQLSTATE[08006] could not connect to server at 10.0.0.4'))
        );
    }
}
