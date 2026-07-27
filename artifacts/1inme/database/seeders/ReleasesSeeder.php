<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Release;
use App\Modules\Admin\Support\VersionRegistry;
use Illuminate\Database\Seeder;

/**
 * Backfill the releases changelog with each surface's currently declared
 * version so the admin "Versions & Releases" hub starts populated instead of
 * empty. Idempotent (firstOrCreate by surface+version) — never clobbers
 * admin-edited notes. Reads the committed version snapshot when available and
 * falls back to the versions shipping at the time this seeder was written.
 */
class ReleasesSeeder extends Seeder
{
    /** Fallback declared versions (as of July 2026). */
    private const FALLBACK_VERSIONS = [
        'marketing'   => '0.1.0',
        'mobile'      => '1.0.0',
        'dialer'      => '1.0.0',
        'zio_browser' => '0.3.0',
        'extension'   => '0.1.0',
        'api_server'  => '0.0.0',
    ];

    public function run(): void
    {
        $snapshot = VersionRegistry::snapshot();
        $declared = is_array($snapshot['surfaces'] ?? null) ? $snapshot['surfaces'] : [];

        foreach (self::FALLBACK_VERSIONS as $surface => $fallback) {
            $version = $declared[$surface] ?? null;
            $version = is_string($version) && $version !== '' ? $version : $fallback;

            Release::firstOrCreate(
                ['surface' => $surface, 'version' => $version],
                [
                    'released_at' => now()->toDateString(),
                    'notes'       => 'Initial recorded release for ' . Release::label($surface) . '.',
                    'source'      => 'seed',
                ]
            );
        }
    }
}
