<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\TestCase;

/**
 * The corner accent one card in each section carries.
 *
 * It shipped as one shape in one colour, stamped on four cards across four
 * bands, which is what made it read as a template rather than as an accent.
 * The ask was different shapes AND different colours, so this checks both and
 * checks them as a SET: any single placement is fine on its own, and the thing
 * that goes wrong is two of them matching.
 */
class HomepageCardRibbonVarietyTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;

    private function partial(): string
    {
        return (string) file_get_contents(resource_path('views/home/partials/card-ribbon.blade.php'));
    }

    /** Every call site, with the shape and variant it asks for. */
    private function placements(): array
    {
        $out = [];

        // PHP's glob() does not treat ** as recursive, so `views/home/**` finds
        // exactly nothing and every assertion below passes on an empty set.
        // That is the shape of bug this whole batch has been about, so the
        // scan walks the tree and test_the_placements_are_found guards it.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/home'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = (string) file_get_contents($path);

            preg_match_all(
                '/@include\(\s*[\'"]home\.partials\.card-ribbon[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)/',
                $source,
                $m,
                PREG_SET_ORDER
            );

            foreach ($m as $hit) {
                $args = $hit[1] ?? '';
                preg_match('/[\'"]shape[\'"]\s*=>\s*[\'"]([\w-]+)[\'"]/', $args, $s);
                preg_match('/[\'"]variant[\'"]\s*=>\s*[\'"]([\w-]+)[\'"]/', $args, $v);

                $out[] = [
                    'file' => str_replace(resource_path('views/'), '', $path),
                    'shape' => $s[1] ?? 'wedge',
                    'variant' => $v[1] ?? 'indigo',
                ];
            }
        }

        return $out;
    }

    public function test_the_placements_are_found(): void
    {
        $this->assertGreaterThanOrEqual(4, count($this->placements()), 'the call-site scan found almost nothing');
    }

    public function test_no_two_cards_wear_the_same_shape(): void
    {
        $shapes = array_column($this->placements(), 'shape');

        $this->assertSame(
            array_unique($shapes),
            $shapes,
            'two cards carry the same shape: ' . implode(', ', $shapes)
        );
    }

    public function test_no_two_cards_wear_the_same_colour(): void
    {
        $variants = array_column($this->placements(), 'variant');

        $this->assertSame(
            array_unique($variants),
            $variants,
            'two cards carry the same palette: ' . implode(', ', $variants)
        );
    }

    /**
     * A shape name with no branch behind it falls through to the default and
     * draws the wedge -- so the call site says four shapes, the page shows
     * two, and nothing complains. Every name the partial accepts has to draw
     * something of its own.
     */
    public function test_every_shape_the_partial_accepts_draws_its_own_geometry(): void
    {
        $partial = $this->partial();

        preg_match('/\$crShapes\s*=\s*\[([^\]]*)\]/', $partial, $m);
        $this->assertNotEmpty($m, 'the shape list is gone');

        preg_match_all('/[\'"]([\w-]+)[\'"]/', $m[1], $names);

        $this->assertGreaterThanOrEqual(4, count($names[1]), 'fewer shapes than call sites');

        foreach ($names[1] as $shape) {
            $isDefault = $shape === 'wedge';

            $this->assertPatternFound(
                $isDefault ? '/@default/' : '/@case\(\s*[\'"]' . preg_quote($shape, '/') . '[\'"]\s*\)/',
                $partial,
                "'{$shape}' is accepted but has no branch, so it silently renders the default shape"
            );
        }

        // And each palette has to be a real set of stops rather than a name
        // that falls back.
        preg_match('/\$crPalettes\s*=\s*\[(.*?)\n    \];/s', $partial, $p);
        $this->assertNotEmpty($p, 'the palette table is gone');

        foreach (array_column($this->placements(), 'variant') as $variant) {
            $this->assertPatternFound(
                '/[\'"]' . preg_quote($variant, '/') . '[\'"]\s*=>\s*\[[^\]]*#[0-9a-f]{6}/i',
                $p[1],
                "'{$variant}' is asked for at a call site but is not in the palette table, so it falls back to indigo"
            );
        }
    }

    /**
     * Every instance needs its own gradient id. Two on one page and the second
     * silently paints with the first one's stops -- which, now that they are
     * four different palettes, would show up as two cards in the same colour.
     */
    public function test_each_instance_gets_a_unique_gradient_id(): void
    {
        $this->assertPatternFound(
            '/\$crUid\s*=.*uniqid/s',
            $this->partial(),
            'the gradient id is not per-instance'
        );

        $html = $this->get('/home/sections')->assertOk()->getContent();

        preg_match_all('/<linearGradient id="(cr[0-9a-f]+)-a"/', $html, $ids);

        $this->assertGreaterThanOrEqual(4, count($ids[1]), 'fewer ribbons rendered than expected');
        $this->assertSame(
            array_unique($ids[1]),
            $ids[1],
            'two ribbons share a gradient id; the second will paint with the first one\'s colours'
        );
    }

    /**
     * The host has to state position, overflow and isolation. Without
     * `position` a z-index:-1 child anchors to the nearest positioned ancestor
     * -- the section -- and paints behind the whole band as a stray gradient
     * beside the heading, with nothing in the card at all.
     */
    public function test_the_host_states_what_the_negative_z_index_needs(): void
    {
        preg_match('/\.card-ribbon-host\s*\{([^}]*)\}/', $this->partial(), $m);

        $this->assertNotEmpty($m, '.card-ribbon-host has no rule');

        foreach (['position', 'overflow', 'isolation'] as $property) {
            $this->assertSubjectContains(
                $property,
                $m[1],
                "the host must state {$property}; half these cards do not carry it already"
            );
        }

        $this->assertPatternFound(
            '/z-index:\s*-1/',
            $this->partial(),
            'at z-index 0 the ribbon paints above the card copy'
        );
    }

    /** All four render, on four different bands. */
    public function test_four_ribbons_render_on_four_bands(): void
    {
        $html = $this->get('/home/sections')->assertOk()->getContent();

        foreach (array_column($this->placements(), 'shape') as $shape) {
            $this->assertSubjectContains(
                'card-ribbon--' . $shape,
                $html,
                "the {$shape} ribbon is not in the rendered page"
            );
        }

        // Counted in class attributes only: the partial's own <style> block
        // names .card-ribbon-host too, and counting raw occurrences made this
        // off by exactly one.
        $hosts = preg_match_all('/class="[^"]*(?<![\w-])card-ribbon-host(?![\w-])[^"]*"/', $html);
        $ribbons = preg_match_all('/class="card-ribbon card-ribbon--[\w-]+"/', $html);

        $this->assertSame($hosts, $ribbons, "a host without a ribbon, or a ribbon without a host ({$hosts} hosts, {$ribbons} ribbons)");
    }
}
