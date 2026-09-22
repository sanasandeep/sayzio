<?php

namespace Tests\Feature;

use App\Modules\User\Support\BgPresetCatalog;
use App\Modules\User\Support\GradientCatalog;
use App\Modules\User\Support\MeshGradientCatalog;
use App\Modules\User\Support\PatternCatalog;
use App\Modules\User\Support\TilesBgCatalog;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The net, recorded BEFORE the catalogs learned to read the database.
 *
 * 485 looks are compiled into seven PHP constants. Making them
 * admin-editable means every one of those constants stops being the
 * answer and becomes the DEFAULT answer, merged with whatever rows an
 * admin has since created. That is a change to the one code path that
 * paints the background of every published page on the platform, and the
 * failure mode is not a crash -- it is 375,000 pages quietly rendering a
 * slightly different gradient.
 *
 * So this file records what every shipped look resolves to today, at the
 * point each catalog is actually consumed: the CSS string for a preset,
 * the composed gradient for a gradient, the 24 positioned tiles for a
 * palette, the clip polygons for a tear. It is recorded on first run and
 * compared on every run after.
 *
 * It says nothing about whether the admin screens work -- that is
 * BackgroundCatalogEntriesAreAdminEditableTest. This one says only that
 * with no rows in the table, the platform renders exactly what it
 * rendered before the table existed.
 */
class TheShippedBackgroundCatalogsStillResolveTest extends TestCase
{
    use RefreshDatabase;

    private const GOLDEN = __DIR__.'/../Fixtures/background-catalog-goldens.txt';

    /**
     * Everything the renderer can ask a catalog, for every shipped key.
     *
     * @return array<string, string>
     */
    private function resolveEverything(): array
    {
        $out = [];

        foreach (BgPresetCatalog::all() as $key => $preset) {
            $out["preset/{$key}"] = implode('|', [
                $preset['group'],
                $preset['label'],
                (string) BgPresetCatalog::css($key),
                BgPresetCatalog::isTorn($key) ? 'torn' : '-',
                (string) BgPresetCatalog::tornPaper($key),
                (string) BgPresetCatalog::tornBackdrop($key),
                implode(',', BgPresetCatalog::swatchStops((string) BgPresetCatalog::css($key))),
            ]);
        }

        foreach (GradientCatalog::all() as $g) {
            $out["gradient/{$g['id']}"] = implode('|', [
                $g['name'], $g['category'], (string) $g['angle'], $g['type'],
                GradientCatalog::toCss($g),
            ]);
        }

        foreach (MeshGradientCatalog::all() as $key => $mesh) {
            $out["mesh/{$key}"] = implode('|', [
                $mesh['label'],
                (string) MeshGradientCatalog::css($key),
                implode(',', MeshGradientCatalog::colors($key)),
            ]);
        }

        foreach (PatternCatalog::all() as $key => $pattern) {
            $out["pattern/{$key}"] = implode('|', [
                $pattern['label'],
                (string) PatternCatalog::css($key),
                implode(',', PatternCatalog::colors($key)),
            ]);
        }

        foreach (TilesBgCatalog::palettes() as $key => $palette) {
            // Every layout, because the tile list is resolved per layout and
            // a merge that dropped `tiles` would still return 24 entries.
            $rendered = [];
            foreach (array_keys(TilesBgCatalog::LAYOUTS) as $layout) {
                foreach (TilesBgCatalog::tiles($key, $layout) as $t) {
                    $rendered[] = "{$t['col']}x{$t['row']}:{$t['css']}";
                }
            }
            $out["tiles/{$key}"] = implode('|', [
                $palette['label'],
                implode(',', TilesBgCatalog::colors($key)),
                implode(';', $rendered),
            ]);
        }

        foreach (TornStyleCatalog::PRESETS as $key => $preset) {
            $sheets = array_map(
                fn ($s) => $s['clip'].'@'.$s['shade'],
                TornStyleCatalog::sheets($preset['style'])
            );
            $out["torn/{$key}"] = implode('|', [
                $preset['label'], $preset['style'], $preset['paper'],
                implode(',', (array) $preset['backdrop']),
                implode(';', $sheets),
            ]);
        }

        foreach (TornStyleCatalog::styles() as $key => $label) {
            $sheets = array_map(fn ($s) => $s['clip'].'@'.$s['shade'], TornStyleCatalog::sheets($key));
            $out["torn_style/{$key}"] = $label.'|'.implode(';', $sheets);
        }

        ksort($out);

        return $out;
    }

    /**
     * With nothing in the table, every shipped look is byte-for-byte what
     * it was before the table existed.
     */
    public function test_every_shipped_look_resolves_exactly_as_recorded(): void
    {
        $now = $this->resolveEverything();

        if (! file_exists(self::GOLDEN)) {
            @mkdir(dirname(self::GOLDEN), 0777, true);
            $lines = [];
            foreach ($now as $key => $value) {
                $lines[] = $key."\t".md5($value);
            }
            file_put_contents(self::GOLDEN, implode("\n", $lines)."\n");
            $this->markTestSkipped(
                'Recorded '.count($now).' catalog goldens. Re-run to compare against them.'
            );
        }

        $golden = [];
        foreach (file(self::GOLDEN, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$key, $hash] = explode("\t", $line, 2);
            $golden[$key] = $hash;
        }

        $this->assertSame(
            array_keys($golden), array_keys($now),
            'the set of shipped looks changed; a look that disappears takes every page using it with it'
        );

        $drifted = [];
        foreach ($now as $key => $value) {
            if (md5($value) !== $golden[$key]) {
                $drifted[] = $key;
            }
        }

        $this->assertSame([], $drifted,
            'these shipped looks resolve differently than they did before the catalogs read the database: '
            .implode(', ', array_slice($drifted, 0, 20))
        );
    }

    /** The recorded set is the whole library, not a sample of it. */
    public function test_the_recording_covers_every_catalog(): void
    {
        $now    = $this->resolveEverything();
        $counts = [];
        foreach (array_keys($now) as $key) {
            $counts[explode('/', $key)[0]] = ($counts[explode('/', $key)[0]] ?? 0) + 1;
        }

        $this->assertSame([
            'gradient'   => 166,
            'mesh'       => 10,
            'pattern'    => 12,
            'preset'     => 179,
            'tiles'      => 52,
            'torn'       => 60,
            'torn_style' => 6,
        ], $counts, 'the shipped library changed size; update the goldens deliberately, not by accident');
    }
}
