<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Error pages must never themselves 500 when the database is broken.
 *
 * The rich error views (errors/403|404|419|429|500|503) extend the full site
 * layout, whose partials (site assistant hints, announcements, SEO settings,
 * events band, footer pricing, domain branding, …) all read from the
 * database. Those reads are exactly what fails during the outage that
 * triggered the error page in the first place — an un-migrated or unreachable
 * DB. In production, errors/_render.blade.php has a last-resort try/catch
 * fallback; in every OTHER environment there is no such net, so each
 * DB-touching partial must individually tolerate missing tables.
 *
 * This test locks that in: it swaps the default connection to an EMPTY
 * in-memory SQLite database (zero tables — every query fails with
 * "no such table", which DatabaseErrors::isMissingTable recognizes), then
 * renders each error status and asserts the response keeps its intended
 * status code (never a cascaded 500) and contains the rich page's content.
 *
 * Deliberately no RefreshDatabase: the test must not depend on any schema.
 */
class ErrorPagesResilienceTest extends TestCase
{
    /**
     * Point the default connection at an empty in-memory SQLite DB so every
     * table read fails as a missing table, and disable debug so Laravel
     * renders the real error views (debug mode short-circuits to the
     * exception debug screen instead).
     */
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

    /**
     * Expected marker text per status, from SitePage::resolveErrorPage's
     * built-in defaults (the DB row can't load — the defaults must).
     */
    private const STATUSES = [
        403 => 'No access',
        404 => 'Page not found',
        419 => '',
        429 => '',
        500 => 'Something went wrong',
        503 => '',
    ];

    public function test_error_pages_render_without_cascading_500(): void
    {
        $this->breakDatabase();

        foreach (self::STATUSES as $status => $marker) {
            Route::get("/__error-resilience-test/{$status}", function () use ($status) {
                if ($status === 500) {
                    throw new \RuntimeException('boom');
                }
                abort($status);
            })->middleware('web');

            $response = $this->get("/__error-resilience-test/{$status}");

            $this->assertSame(
                $status,
                $response->getStatusCode(),
                "Expected HTTP {$status} but got {$response->getStatusCode()} — rendering the {$status} error page likely crashed on the broken DB."
            );

            if ($marker !== '') {
                $this->assertStringContainsString(
                    $marker,
                    $response->getContent(),
                    "Rich {$status} error page content missing — the layout likely crashed and fell back to a bare response."
                );
            }

            // The response must be the branded error page (site layout), not
            // Symfony's minimal fallback that appears when the error view
            // itself throws mid-render.
            $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent(), "Status {$status}: no HTML document rendered.");
        }
    }
}
