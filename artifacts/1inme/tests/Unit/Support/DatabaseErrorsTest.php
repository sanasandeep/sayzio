<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\DatabaseErrors;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class DatabaseErrorsTest extends TestCase
{
    /**
     * Build a QueryException wrapping a PDOException with the given SQLSTATE
     * and driver message, mirroring how Laravel constructs them at runtime.
     */
    private function queryException(?string $sqlState, string $message): QueryException
    {
        $pdo = new PDOException($message);
        if ($sqlState !== null) {
            // QueryException reads the SQLSTATE from the wrapped PDOException's
            // public errorInfo array (errorInfo[0]).
            $pdo->errorInfo = [$sqlState, null, $message];
        }

        return new QueryException('pgsql', 'select 1', [], $pdo);
    }

    public function test_postgres_undefined_table_sqlstate_is_missing_table(): void
    {
        $e = $this->queryException('42P01', 'SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "app_settings" does not exist');
        $this->assertTrue(DatabaseErrors::isMissingTable($e));
    }

    public function test_mysql_undefined_table_sqlstate_is_missing_table(): void
    {
        $e = $this->queryException('42S02', "Base table or view 'app_settings' doesn't exist");
        $this->assertTrue(DatabaseErrors::isMissingTable($e));
    }

    public function test_postgres_relation_message_without_sqlstate_is_missing_table(): void
    {
        $e = $this->queryException(null, 'ERROR: relation "app_settings" does not exist');
        $this->assertTrue(DatabaseErrors::isMissingTable($e));
    }

    public function test_sqlite_no_such_table_message_is_missing_table(): void
    {
        $e = $this->queryException(null, 'SQLSTATE[HY000]: General error: 1 no such table: app_settings');
        $this->assertTrue(DatabaseErrors::isMissingTable($e));
    }

    public function test_missing_column_is_not_missing_table(): void
    {
        // Postgres undefined_column is 42703, message also contains "does not
        // exist" — this must NOT be treated as a missing table.
        $e = $this->queryException('42703', 'SQLSTATE[42703]: Undefined column: 7 ERROR: column "foo" does not exist');
        $this->assertFalse(DatabaseErrors::isMissingTable($e));
    }

    public function test_missing_function_is_not_missing_table(): void
    {
        $e = $this->queryException('42883', 'SQLSTATE[42883]: Undefined function: 7 ERROR: function foo() does not exist');
        $this->assertFalse(DatabaseErrors::isMissingTable($e));
    }

    public function test_generic_constraint_violation_is_not_missing_table(): void
    {
        $e = $this->queryException('23505', 'SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint');
        $this->assertFalse(DatabaseErrors::isMissingTable($e));
    }
}
