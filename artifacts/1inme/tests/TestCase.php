<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The Feature suite runs every test in one long-lived PHP process, and
     * each test boots a fresh Laravel app (~1.4k lines of routes). That builds
     * up large cyclic object graphs (container bindings, the HTTP request /
     * response, Eloquent models). PHP only runs its cycle collector once the
     * root buffer fills, so the peak climbs between automatic collections and
     * eventually exhausts the limit as the app grows.
     *
     * Forcing a collection after each test reclaims that garbage immediately,
     * which lowers both the resident baseline and the per-test growth slope
     * (measured ~50% reduction in peak growth on the boot+request lifecycle),
     * keeping the single-process run comfortably under the configured ceiling.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }
}
