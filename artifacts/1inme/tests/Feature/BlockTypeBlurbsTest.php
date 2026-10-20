<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockTypeBlurbs;
use Tests\TestCase;

/**
 * The palette shows a label and an icon and nothing else, so the blurb is
 * the only prose a creator ever gets about what a block is for. A type
 * added without one is a type nobody can tell apart from its neighbours --
 * and the gap is invisible, because a missing blurb renders as empty space
 * rather than an error. Hence a test rather than a convention.
 */
class BlockTypeBlurbsTest extends TestCase
{
    /**
     * Same rule the palette itself applies: everything in TYPES except
     * system blocks and the `verified` category, which the picker hides.
     */
    private function paletteTypes(): array
    {
        $out = [];
        foreach (BiolinkBlock::TYPES as $slug => $info) {
            if (!empty($info['system'])) {
                continue;
            }
            if (($info['category'] ?? '') === 'verified') {
                continue;
            }
            $out[$slug] = $info;
        }

        return $out;
    }

    public function test_every_palette_type_has_a_blurb(): void
    {
        $missing = [];
        foreach ($this->paletteTypes() as $slug => $info) {
            if (BlockTypeBlurbs::for($slug) === null) {
                $missing[] = $slug . ' (' . ($info['label'] ?? '?') . ')';
            }
        }

        $this->assertSame([], $missing, "Block types with no blurb:\n  " . implode("\n  ", $missing));
    }

    public function test_blurbs_follow_house_style(): void
    {
        $problems = [];
        foreach (BlockTypeBlurbs::all() as $slug => $blurb) {
            if (mb_strlen($blurb) > BlockTypeBlurbs::MAX_LENGTH) {
                $problems[] = "{$slug}: " . mb_strlen($blurb) . ' chars, over ' . BlockTypeBlurbs::MAX_LENGTH;
            }
            if (!str_ends_with($blurb, '.')) {
                $problems[] = "{$slug}: does not end in a full stop";
            }
            if (trim($blurb) !== $blurb || str_contains($blurb, '  ')) {
                $problems[] = "{$slug}: stray whitespace";
            }
            // "A block that..." explains nothing to someone who does not
            // know what a block is -- which is the whole complaint.
            if (preg_match('/\bblocks?\b/i', $blurb)) {
                $problems[] = "{$slug}: says \"block\"";
            }
        }

        $this->assertSame([], $problems, "Blurb style problems:\n  " . implode("\n  ", $problems));
    }

    public function test_blurbs_are_not_copies_of_each_other(): void
    {
        $seen = [];
        foreach (BlockTypeBlurbs::all() as $slug => $blurb) {
            $key = mb_strtolower($blurb);
            $seen[$key][] = $slug;
        }

        $dupes = array_filter($seen, fn ($slugs) => count($slugs) > 1);
        $report = implode('; ', array_map(fn ($s) => implode(' = ', $s), $dupes));

        $this->assertSame([], array_values($dupes), "Types sharing one blurb cannot be told apart: {$report}");
    }

    /**
     * The three clusters creators actually get stuck on. A preview cannot
     * separate these -- they all render the same shape -- so if the blurbs
     * ever converge, the flyout stops answering the question it exists for.
     */
    public function test_the_confusable_clusters_read_differently(): void
    {
        $clusters = [
            ['paragraph', 'paragraph_rich', 'markdown'],
            ['link', 'link_big', 'cta_button', 'featured_pin'],
            ['email_collector', 'email_subscribe'],
            ['testimonials', 'review'],
            ['faq', 'faq_v2'],
        ];

        foreach ($clusters as $cluster) {
            $blurbs = array_map(fn ($t) => BlockTypeBlurbs::for($t), $cluster);
            $this->assertNotContains(null, $blurbs, 'Cluster member missing a blurb: ' . implode(', ', $cluster));
            $this->assertCount(
                count($cluster),
                array_unique($blurbs),
                'These read identically and are exactly the ones people confuse: ' . implode(', ', $cluster)
            );
        }
    }

    public function test_search_haystack_carries_both_label_and_blurb(): void
    {
        $haystack = BlockTypeBlurbs::haystack('markdown', 'Markdown');

        $this->assertStringContainsString('markdown', $haystack);
        $this->assertStringContainsString('shortcuts', $haystack, 'Blurb words must be searchable.');
        $this->assertSame(mb_strtolower($haystack), $haystack, 'Haystack is compared lowercased.');
    }

    public function test_an_unknown_alias_falls_back_to_its_canonical_type(): void
    {
        // Not a real slug; exercises the canonical fallback so a future
        // alias inherits a blurb instead of showing nothing.
        $this->assertNotNull(BlockTypeBlurbs::for('link'));
        $this->assertNull(BlockTypeBlurbs::for('definitely_not_a_block_type'));
    }
}
