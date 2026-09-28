<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-27: "Blocks are placed at bottom... how to insert them top
 * below or inbetween any wehere".
 *
 * Three separate things were wrong, and only the first is the one he could
 * see.
 *
 * ---- 1. The top of the page could not be reached at all ----------------
 *
 * Positions were expressed as `insert_after: <block id>`. Every gap in the
 * list is "after the card above it" -- except the first, which has no card
 * above it and therefore no id to name. So there was no request that placed
 * a block at the top of a page. You added it at the bottom and dragged it up
 * past everything else, and on a page of twenty blocks that is a long drag.
 *
 * `insert_at_top` is that position, and the server resolves it to an
 * ordinary insert_after wherever one exists -- on a design-locked page the
 * top means "after the fixed prefix", which is the same clamp insert_after
 * already applied, kept in one place rather than restated.
 *
 * ---- 2. The control was invisible --------------------------------------
 *
 * Between-card inserts did exist, as a 20px circle at `opacity: 0`,
 * positioned 14px outside the card's right edge and revealed on hover. A
 * touch screen has no hover, so on a phone or tablet it could not be reached
 * at all. That is why the feature reads as missing: it may as well have been.
 *
 * ---- 3. Clicking it changed nothing on screen --------------------------
 *
 * The pending target was tracked in two places and rendered in none. The
 * code comment said Alpine mirrored it "to drive the banner"; there was no
 * banner, and nothing in the template read the value. So you clicked the
 * circle, nothing happened, and the next palette click -- possibly minutes
 * later, after you had forgotten -- dropped a block at a position nothing
 * had ever shown you.
 */
class ABlockCanGoAtTheTopOfThePageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function link(): Link
    {
        return Link::create([
            'user_id' => $this->user->id,
            'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Bio',
            'is_active' => true,
        ]);
    }

    /** A page with the given headings, top to bottom. */
    private function pageOf(array $names, bool $fixedPrefix = false): Link
    {
        $link = $this->link();
        $link->biolinkBlocks()->delete();

        foreach (array_values($names) as $i => $name) {
            $settings = ['text' => $name];
            if ($fixedPrefix && $i === 0) {
                $settings['_fixed'] = true;
            }
            BiolinkBlock::create([
                'link_id' => $link->id,
                'type' => 'heading',
                'settings' => $settings,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        return $link->fresh();
    }

    /** The page's headings, in the order a visitor reads them. */
    private function order(Link $link): array
    {
        return $link->biolinkBlocks()
            ->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($b) => $b->settings['text'] ?? '?')
            ->all();
    }

    private function add(Link $link, array $payload)
    {
        return $this->actingAs($this->user)->post(
            route('user.links.blocks.store', $link),
            array_merge(['type' => 'heading'], $payload),
            ['X-Requested-With' => 'XMLHttpRequest']
        );
    }

    private function editor(Link $link): string
    {
        return $this->actingAs($this->user)
            ->get(route('user.links.blocks.editor', $link))
            ->assertOk()->getContent();
    }

    // ===== 1. The position that did not exist ==============================

    public function test_a_block_can_be_added_at_the_top_of_a_page(): void
    {
        $link = $this->pageOf(['First', 'Second', 'Third']);

        $this->add($link, ['settings' => ['text' => 'Newest'], 'insert_at_top' => 1])->assertOk();

        $this->assertSame(['Newest', 'First', 'Second', 'Third'], $this->order($link->fresh()));
    }

    public function test_adding_at_the_top_twice_keeps_the_newest_on_top(): void
    {
        $link = $this->pageOf(['First', 'Second']);

        $this->add($link, ['settings' => ['text' => 'A'], 'insert_at_top' => 1])->assertOk();
        $this->add($link, ['settings' => ['text' => 'B'], 'insert_at_top' => 1])->assertOk();

        $this->assertSame(['B', 'A', 'First', 'Second'], $this->order($link->fresh()));
    }

    public function test_the_top_of_an_empty_page_is_just_the_page(): void
    {
        $link = $this->link();
        $link->biolinkBlocks()->delete();

        $this->add($link, ['settings' => ['text' => 'Only'], 'insert_at_top' => 1])->assertOk();

        $this->assertSame(['Only'], $this->order($link->fresh()));
    }

    public function test_the_response_says_where_the_block_actually_went(): void
    {
        $link = $this->pageOf(['First', 'Second']);

        $resp = $this->add($link, ['settings' => ['text' => 'Newest'], 'insert_at_top' => 1])->assertOk();

        // The editor draws the new card from this, so it has to describe the
        // position the SERVER chose, not the one that was asked for.
        $resp->assertJsonPath('insert_at_top', true);
        $resp->assertJsonPath('insert_after', null);
    }

    // ===== 2. Everything that already worked, still works ==================

    public function test_adding_with_no_position_still_lands_at_the_bottom(): void
    {
        $link = $this->pageOf(['First', 'Second']);

        $this->add($link, ['settings' => ['text' => 'Last']])->assertOk();

        $this->assertSame(['First', 'Second', 'Last'], $this->order($link->fresh()));
    }

    public function test_inserting_after_a_block_still_lands_right_under_it(): void
    {
        $link = $this->pageOf(['First', 'Second', 'Third']);
        $first = $link->biolinkBlocks()->orderBy('sort_order')->first();

        $this->add($link, [
            'settings' => ['text' => 'Wedged'],
            'insert_after' => $first->id,
        ])->assertOk();

        $this->assertSame(['First', 'Wedged', 'Second', 'Third'], $this->order($link->fresh()));
    }

    public function test_insert_after_wins_when_both_are_somehow_sent(): void
    {
        $link = $this->pageOf(['First', 'Second']);
        $first = $link->biolinkBlocks()->orderBy('sort_order')->first();

        $this->add($link, [
            'settings' => ['text' => 'Wedged'],
            'insert_after' => $first->id,
            'insert_at_top' => 1,
        ])->assertOk();

        // A named block beats a named position: the client only ever sends
        // one, and this pins which way an accident resolves.
        $this->assertSame(['First', 'Wedged', 'Second'], $this->order($link->fresh()));
    }

    // ===== 3. A pinned block stays pinned =================================

    public function test_the_top_of_a_design_locked_page_means_under_the_pinned_block(): void
    {
        $link = $this->pageOf(['Pinned', 'First', 'Second'], fixedPrefix: true);
        $settings = $link->settings ?? [];
        $settings['biolink']['design_locked'] = ['template' => 'x', 'block_styles' => []];
        $link->settings = $settings;
        $link->save();
        $link = $link->fresh();

        $this->assertTrue($link->isDesignLocked(), 'This case needs a design-locked page.');

        $this->add($link, ['settings' => ['text' => 'Newest'], 'insert_at_top' => 1])->assertOk();

        // Not above the pinned block. Fixed template blocks are a contiguous
        // prefix, and "the top" resolves to just after it -- the same clamp
        // insert_after has always applied.
        $this->assertSame(['Pinned', 'Newest', 'First', 'Second'], $this->order($link->fresh()));
    }

    // ===== 4. The control is reachable ====================================

    public function test_every_gap_in_the_list_offers_a_place_to_insert(): void
    {
        $link = $this->pageOf(['First', 'Second', 'Third']);
        $html = $this->editor($link);

        // One rail per card, plus the top one.
        $this->assertSame(
            3,
            substr_count($html, 'data-insert-after="'),
            'Each block should offer the gap below it.'
        );
        $this->assertStringContainsString('id="insertRailTop"', $html);
        $this->assertStringContainsString('openInsertGalleryAtTop()', $html);
    }

    public function test_the_insert_control_is_visible_without_hovering(): void
    {
        $html = $this->editor($this->pageOf(['First']));

        // The old control was opacity:0 until :hover, which on a touch screen
        // meant it could not be reached at all.
        $this->assertStringNotContainsString('.insert-block-btn', $html);
        $this->assertMatchesRegularExpression(
            '/\.insert-rail\s*\{[^}]*opacity:\s*0\.[1-9]/',
            $html,
            'An insert rail should be visible at rest, not only on hover.'
        );
        // Reachable by keyboard, and it says what it does.
        $this->assertStringContainsString('aria-label="Add a block below this one"', $html);
        $this->assertStringContainsString('aria-label="Add a block at the top of the page"', $html);
        $this->assertStringContainsString('.insert-rail:focus-visible', $html);
    }

    public function test_pressing_a_rail_does_not_start_dragging_the_card_around_it(): void
    {
        $html = $this->editor($this->pageOf(['First']));

        // A rail lives inside .block-card-wrapper, which is what the canvas
        // drags. Without the filter, pressing the rail picks the whole card
        // up instead of arming the gap.
        $this->assertMatchesRegularExpression(
            "/filter:\s*'[^']*\.insert-rail[^']*'/",
            $html,
            'The drag filter should exclude the insert rails.'
        );
    }

    public function test_arming_a_rail_shows_which_gap_is_armed(): void
    {
        $html = $this->editor($this->pageOf(['First']));

        // The pending target used to be tracked in two places and rendered in
        // neither, so a click changed nothing on screen. The load-bearing
        // line is the one that puts the class ON the rail -- a stylesheet
        // describing an armed rail nothing ever arms is the same bug again.
        $this->assertStringContainsString('function _syncInsertRails()', $html);
        $this->assertStringContainsString("rail.classList.toggle('is-armed', armed)", $html);
        $this->assertStringContainsString("rail.setAttribute('aria-pressed'", $html);
        $this->assertMatchesRegularExpression(
            '/\.insert-rail\.is-armed\s*\{/',
            $html,
            'An armed rail should look different from an idle one.'
        );
        $this->assertStringContainsString("content: 'Pick a block'", $html);

        // And every path that changes the pending target repaints. Arming,
        // switching rails, cancelling, and clearing after the block lands.
        foreach (['beginInsert(afterId)', 'beginInsertAtTop()', 'cancelInsert()'] as $method) {
            $this->assertStringContainsString($method, $html);
        }
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($html, '_syncInsertRails()'),
            'Every change to the pending insert target should repaint the rails.'
        );
    }

    public function test_every_add_path_sends_the_position_the_same_way(): void
    {
        $blade = file_get_contents(resource_path('views/user/links/biolink-editor.blade.php'));

        // Three add paths each appended the position themselves, which is how
        // a fourth would have shipped knowing about insert_after and not
        // insert_at_top.
        $this->assertStringContainsString('function _appendInsertPosition(fd)', $blade);
        $this->assertSame(
            1,
            substr_count($blade, "fd.append('insert_at_top'"),
            'The top position should be sent from exactly one place.'
        );
        $this->assertSame(
            1,
            substr_count($blade, "fd.append('insert_after', _insertAfterId)"),
            'The after position should be sent from exactly one place.'
        );
    }
}
