<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\LinkTypeCategories;
use Tests\TestCase;

/**
 * In-suite mirror of the `parity:check-mobile-docs` drift guard
 * ({@see \App\Console\Commands\CheckMobileDocsParity}).
 *
 * The mobile block editor (`artifacts/1inme-mobile/lib/api/blocks.ts`) and the
 * docs are hand-maintained against the web block/link registries. This asserts
 * — in the normal test run, not only in the standalone validation command —
 * that every current web block/link type is triaged in the committed baseline
 * (`docs/mobile-docs-parity.json`), so a newly-shipped web type can't slip in
 * without a conscious mobile/docs parity decision.
 *
 * No database needed — it reads static PHP constants plus a JSON file.
 */
class MobileDocsParityDriftTest extends TestCase
{
    public function test_parity_check_command_passes(): void
    {
        $this->artisan('parity:check-mobile-docs')->assertExitCode(0);
    }

    public function test_every_web_type_is_triaged_in_the_baseline(): void
    {
        $path = base_path('docs/mobile-docs-parity.json');
        $this->assertFileExists($path, 'Parity baseline is missing; run `php artisan parity:check-mobile-docs --accept`.');

        $baseline = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($baseline);

        $baseBlocks = array_keys($baseline['blocks'] ?? []);
        $baseLinks = array_keys($baseline['linkTypes'] ?? []);

        $webBlocks = [];
        foreach (BiolinkBlock::pickerTypes() as $slug => $meta) {
            if (! empty($meta['system']) || ($meta['category'] ?? null) === 'verified') {
                continue;
            }
            $webBlocks[] = $slug;
        }
        $webLinks = array_keys(LinkTypeCategories::types());

        $untriagedBlocks = array_diff($webBlocks, $baseBlocks);
        $untriagedLinks = array_diff($webLinks, $baseLinks);

        $this->assertSame([], array_values($untriagedBlocks),
            'New web block type(s) missing from the mobile/docs parity baseline. '
            . 'Triage them and run `php artisan parity:check-mobile-docs --accept`.');
        $this->assertSame([], array_values($untriagedLinks),
            'New web link type(s) missing from the mobile/docs parity baseline. '
            . 'Triage them and run `php artisan parity:check-mobile-docs --accept`.');

        // Baseline must not carry entries for types that no longer exist on web.
        $this->assertSame([], array_values(array_diff($baseBlocks, $webBlocks)),
            'Stale block entry in the parity baseline; run `--accept` to prune.');
        $this->assertSame([], array_values(array_diff($baseLinks, $webLinks)),
            'Stale link entry in the parity baseline; run `--accept` to prune.');
    }
}
