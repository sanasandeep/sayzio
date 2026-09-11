<?php

namespace Tests\Feature;

use App\Modules\Common\Controllers\SitePageController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * /demos, rendered the way production renders it: with demo cards on it.
 *
 * The page has two branches. With no seeded demo links it shows a single
 * "Demos are on their way" panel, which carries its own h2. With demo links it
 * shows a grid of cards whose titles are h3s -- and until this test, nothing
 * above them, so the outline went h1 straight to h3.
 *
 * That is the same skip already fixed on /pricing, /coins and /download, and
 * it outlived that fix for a reason worth writing down: a development database
 * has no `demo-type-*` links, so the page renders the empty branch, and a
 * full audit of all 42 marketing pages reported it clean while production --
 * which has the cards -- was broken. The audit was right about what it
 * measured and measured the wrong branch.
 *
 * So this seeds a card. A page whose shape depends on data has to be tested
 * with the data.
 */
class DemosPageHeadingOutlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The controller caches the demo-link lookup for five minutes.
        Cache::forget(SitePageController::DEMOS_CACHE_KEY);
    }

    private function seedDemoCard(string $slug = 'short-link', string $title = 'Short Link'): Link
    {
        return Link::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'biolink',
            'alias' => 'demo-type-' . $slug,
            'title' => $title,
            'is_active' => true,
            'visibility' => 'public',
        ]);
    }

    /** @return list<int> heading levels in document order */
    private function headingLevels(string $html): array
    {
        preg_match_all('/<h([1-6])\b/i', $html, $m);

        return array_map('intval', $m[1]);
    }

    private function skips(array $levels): array
    {
        $skips = [];
        $prev = 0;

        foreach ($levels as $i => $level) {
            if ($prev && $level > $prev + 1) {
                $skips[] = "h{$prev} -> h{$level} (heading #" . ($i + 1) . ')';
            }

            $prev = $level;
        }

        return $skips;
    }

    public function test_the_card_grid_does_not_skip_a_heading_level(): void
    {
        $this->seedDemoCard();
        $this->seedDemoCard('qr-code', 'QR Code');

        $html = (string) $this->get('/demos')->assertOk()->getContent();

        // The branch under test really is the one that rendered.
        $this->assertStringContainsString('Short Link', $html, '/demos did not render the seeded demo cards');
        $this->assertStringNotContainsString('Demos are on their way', $html, '/demos fell back to the empty state; the seeding did not take');

        $skips = $this->skips($this->headingLevels($html));

        $this->assertSame([], $skips, sprintf(
            "/demos skips a heading level with cards on it.\n\n"
            . "Each card's title is an h3. Something has to name the grid above them --\n"
            . "a visually hidden h2 is enough, and is what /pricing and /download use.\n\n%s",
            implode("\n", $skips)
        ));
    }

    /** The empty state was already sound, and should stay that way. */
    public function test_the_empty_state_does_not_skip_a_heading_level(): void
    {
        $html = (string) $this->get('/demos')->assertOk()->getContent();

        $this->assertStringContainsString('Demos are on their way', $html, '/demos is not showing its empty state with no demo links seeded');

        $this->assertSame([], $this->skips($this->headingLevels($html)));
    }

    /**
     * And exactly one h1, in both branches -- the other half of an outline
     * being sound.
     */
    public function test_the_page_has_one_h1_either_way(): void
    {
        $empty = $this->headingLevels((string) $this->get('/demos')->getContent());

        $this->seedDemoCard();
        Cache::forget(SitePageController::DEMOS_CACHE_KEY);

        $withCards = $this->headingLevels((string) $this->get('/demos')->getContent());

        $this->assertSame(1, count(array_filter($empty, fn ($l) => $l === 1)), 'the empty state does not have exactly one h1');
        $this->assertSame(1, count(array_filter($withCards, fn ($l) => $l === 1)), 'the card grid does not have exactly one h1');
    }
}
