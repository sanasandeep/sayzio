<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public /{alias} short-link handler must never surface a raw 500 when
 * the database is unreachable or un-migrated — it's the most-trafficked
 * public surface on the platform. RedirectController::handle wraps alias
 * resolution and degrades to a branded, self-contained 503 "temporarily
 * unavailable" page (with Retry-After) whenever the failure classifies as
 * database-unavailable via DatabaseErrors::isUnavailable.
 *
 * Same pattern as ErrorPagesResilienceTest: swap the default connection to
 * an EMPTY in-memory SQLite database (zero tables — every query fails with
 * "no such table", which the classifier recognizes as un-migrated).
 *
 * Deliberately no RefreshDatabase: the test must not depend on any schema.
 */
class ShortLinkDatabaseDownTest extends TestCase
{
    private function breakDatabase(): void
    {
        config([
            'app.debug' => false,
            'database.connections.broken_sqlite' => [
                'driver'                  => 'sqlite',
                'database'                => ':memory:',
                'prefix'                  => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'broken_sqlite',
        ]);

        DB::purge();
        DB::setDefaultConnection('broken_sqlite');
    }

    public function test_short_link_renders_branded_503_when_database_is_down(): void
    {
        $this->breakDatabase();

        $response = $this->get('/some-short-alias');

        $this->assertSame(
            503,
            $response->getStatusCode(),
            "Expected a graceful 503 but got {$response->getStatusCode()} — short-link resolution likely crashed on the broken DB."
        );

        $response->assertHeader('Retry-After');

        $content = $response->getContent();
        $this->assertStringContainsString('temporarily unavailable', $content);
        $this->assertStringContainsString('<!doctype html>', strtolower($content), 'No HTML document rendered.');
    }
}
