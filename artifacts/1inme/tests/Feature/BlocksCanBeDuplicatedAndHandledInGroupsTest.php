<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Two things Sana asked for on 2026-09-23:
 *
 *   "i am not able to add duplicate blocks.. it will work good for
 *    maintaining theme structure."
 *
 *   "need options to select multiple blocks to move as a group up down as
 *    well as to delete or hide."
 *
 * The first is about styling, not content: you get one link button looking
 * exactly right and then want five more of it. So a duplicate copies the
 * whole settings payload -- variant, colours, width, schedule -- and lands
 * directly below the original.
 *
 * The second reuses what was already there rather than growing a parallel
 * API: a group move posts the whole new order to reorder() (so the template
 * fixed-prefix rule still applies), a group delete is bulkDestroy() with an
 * id list, and only show/hide needed a verb of its own.
 */
class BlocksCanBeDuplicatedAndHandledInGroupsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $this->user = User::factory()->create(['onboarded_at' => Carbon::parse('2026-01-01')]);
        $ws = app(WorkspaceContext::class)->resolve($this->user);

        $this->link = Link::create([
            'user_id' => $this->user->id, 'workspace_id' => $ws?->id,
            'type' => 'biolink', 'alias' => 'blocks'.fake()->unique()->numerify('####'),
            'title' => 'My page', 'is_active' => true,
        ]);
        // Created by the fixture, so the starter seeding is out of the way.
        $this->link->biolinkBlocks()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function block(array $attrs = []): BiolinkBlock
    {
        return BiolinkBlock::create(array_merge([
            'link_id'    => $this->link->id,
            'type'       => 'link',
            'settings'   => ['text' => 'A link', 'url' => 'https://example.org'],
            'sort_order' => $this->link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') + 1 ?? 0,
            'is_active'  => true,
        ], $attrs));
    }

    private function duplicate(BiolinkBlock $b)
    {
        return $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson('/user/links/'.$this->link->id.'/blocks/'.$b->id.'/duplicate');
    }

    private function order(): array
    {
        return $this->link->biolinkBlocks()->whereNull('parent_id')
            ->orderBy('sort_order')->pluck('id')->all();
    }

    // ===== 1. Duplicate =====

    /** The copy carries the styling, which is the whole point. */
    public function test_a_duplicate_keeps_every_design_choice(): void
    {
        $b = $this->block(['settings' => [
            'text'   => 'Book a call',
            'url'    => 'https://cal.example/sana',
            '_variant' => 'glass_card',
            '_style' => ['border_radius' => '22', 'bg_color' => '#101828', 'grid_span' => 6],
        ]]);

        $this->duplicate($b)->assertOk()->assertJson(['success' => true, 'insert_after' => $b->id]);

        $copy = $this->link->biolinkBlocks()->where('id', '!=', $b->id)->firstOrFail();

        $this->assertSame('link', $copy->type);
        $this->assertSame('Book a call', $copy->settings['text']);
        $this->assertSame('glass_card', $copy->settings['_variant'],
            'the copy must keep the chosen design, not fall back to the default');
        $this->assertSame('22', $copy->settings['_style']['border_radius']);
        $this->assertSame(6, $copy->settings['_style']['grid_span'],
            'width is part of the look and travels with the copy');
    }

    /** It lands directly below the original, not at the end of the page. */
    public function test_the_copy_lands_directly_below_the_original(): void
    {
        $a = $this->block(['sort_order' => 0, 'settings' => ['text' => 'first']]);
        $b = $this->block(['sort_order' => 1, 'settings' => ['text' => 'second']]);
        $c = $this->block(['sort_order' => 2, 'settings' => ['text' => 'third']]);

        $this->duplicate($a)->assertOk();

        $ids  = $this->order();
        $copy = $this->link->biolinkBlocks()->whereNotIn('id', [$a->id, $b->id, $c->id])->firstOrFail();

        $this->assertSame([$a->id, $copy->id, $b->id, $c->id], $ids,
            'a duplicate belongs next to what it was copied from');
    }

    /** A card is copied with everything inside it. */
    public function test_duplicating_a_card_copies_its_children(): void
    {
        $card = $this->block(['type' => 'card', 'settings' => ['title' => 'Links'], 'sort_order' => 0]);
        BiolinkBlock::create(['link_id' => $this->link->id, 'parent_id' => $card->id, 'type' => 'heading',
            'settings' => ['text' => 'Inside one'], 'sort_order' => 0, 'is_active' => true]);
        BiolinkBlock::create(['link_id' => $this->link->id, 'parent_id' => $card->id, 'type' => 'link',
            'settings' => ['text' => 'Inside two'], 'sort_order' => 1, 'is_active' => true]);

        $this->duplicate($card)->assertOk();

        $copy = $this->link->biolinkBlocks()->whereNull('parent_id')->where('id', '!=', $card->id)->firstOrFail();
        $kids = $copy->children()->orderBy('sort_order')->get();

        $this->assertCount(2, $kids, 'a card with nothing in it is not a copy of a card with two blocks in it');
        $this->assertSame(['Inside one', 'Inside two'], $kids->pluck('settings.text')->all(),
            'and the children keep their order');
    }

    /** A child block duplicates inside its own card, not out of it. */
    public function test_a_child_duplicates_inside_its_card(): void
    {
        $card  = $this->block(['type' => 'card', 'sort_order' => 0]);
        $child = BiolinkBlock::create(['link_id' => $this->link->id, 'parent_id' => $card->id,
            'type' => 'link', 'settings' => ['text' => 'Inside'], 'sort_order' => 0, 'is_active' => true]);

        $res = $this->duplicate($child)->assertOk();
        $res->assertJson(['parent_id' => $card->id]);

        $this->assertSame(2, $card->children()->count());
        $this->assertSame(0, $this->link->biolinkBlocks()->whereNull('parent_id')->where('id', '!=', $card->id)->count(),
            'the copy must not escape the card');
    }

    /** The copy has never been clicked, whatever the original has racked up. */
    public function test_the_copy_starts_on_zero_clicks(): void
    {
        $b = $this->block(['click_count' => 4210, 'max_clicks' => 5000]);

        $this->duplicate($b)->assertOk();
        $copy = $this->link->biolinkBlocks()->where('id', '!=', $b->id)->firstOrFail();

        $this->assertSame(0, (int) $copy->click_count, 'a new block has no history');
        $this->assertSame(5000, (int) $copy->max_clicks, 'but its limit is part of its configuration');
    }

    /**
     * Duplicating is the creator building, so a page still carrying only its
     * starter blocks stops being "untouched" and goes live.
     */
    public function test_duplicating_a_starter_block_publishes_the_page(): void
    {
        $b = $this->block(['settings' => ['text' => 'My Link', '_placeholder' => true, '_starter_seed' => true]]);
        $this->assertTrue($this->link->fresh()->isUntouchedStarterPage());

        $this->duplicate($b)->assertOk();

        $copy = $this->link->biolinkBlocks()->where('id', '!=', $b->id)->firstOrFail();
        $this->assertArrayNotHasKey('_starter_seed', $copy->settings,
            'a block the creator made is theirs, not ours');
        $this->assertFalse($this->link->fresh()->isUntouchedStarterPage());
    }

    /** The verified badge is one per page, so it cannot be cloned. */
    public function test_a_verified_block_cannot_be_duplicated(): void
    {
        $b = $this->block(['type' => 'verified_heading', 'settings' => ['text' => 'Sana']]);

        $this->duplicate($b)->assertStatus(403)->assertJson(['success' => false]);
        $this->assertSame(1, $this->link->biolinkBlocks()->count());
    }

    /**
     * A block on a different account's page.
     *
     * Resolving another user's workspace swaps the request-scoped workspace
     * context, and Link is workspace-scoped -- so our own link would stop
     * resolving and every assertion below would be a 404 dressed up as a
     * pass. Put ours back before returning.
     */
    private function foreignBlock(): BiolinkBlock
    {
        $other = User::factory()->create(['onboarded_at' => Carbon::parse('2026-01-01')]);
        $ws    = app(WorkspaceContext::class)->resolve($other);
        $their = Link::create(['user_id' => $other->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
            'alias' => 'theirs'.fake()->unique()->numerify('####'), 'is_active' => true]);
        $their->biolinkBlocks()->delete();
        $block = BiolinkBlock::create(['link_id' => $their->id, 'type' => 'link',
            'settings' => ['text' => 'Theirs'], 'sort_order' => 0, 'is_active' => true]);

        app(WorkspaceContext::class)->resolve($this->user);

        return $block;
    }

    /** Someone else's block is not duplicable through your own page. */
    public function test_another_users_block_cannot_be_duplicated(): void
    {
        $block = $this->foreignBlock();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/'.$block->id.'/duplicate')
            ->assertStatus(403);

        $this->assertSame(0, $this->link->biolinkBlocks()->count(),
            'and nothing lands on our page either');
    }

    // ===== 2. Group actions =====

    /** Hide several at once. */
    public function test_a_selection_can_be_hidden_and_shown_together(): void
    {
        $a = $this->block(['sort_order' => 0]);
        $b = $this->block(['sort_order' => 1]);
        $c = $this->block(['sort_order' => 2]);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/bulk-toggle',
                ['ids' => [$a->id, $c->id], 'is_active' => false])
            ->assertOk()->assertJson(['success' => true, 'changed' => 2, 'is_active' => false]);

        $this->assertFalse((bool) $a->fresh()->is_active);
        $this->assertTrue((bool) $b->fresh()->is_active, 'an unselected block is left alone');
        $this->assertFalse((bool) $c->fresh()->is_active);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/bulk-toggle',
                ['ids' => [$a->id, $c->id], 'is_active' => true])
            ->assertOk()->assertJson(['changed' => 2]);

        $this->assertTrue((bool) $a->fresh()->is_active);
    }

    /** Blocks already in the wanted state are not counted as changed. */
    public function test_hiding_something_already_hidden_changes_nothing(): void
    {
        $a = $this->block(['is_active' => false]);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/bulk-toggle',
                ['ids' => [$a->id], 'is_active' => false])
            ->assertOk()->assertJson(['changed' => 0]);
    }

    /** Ids from another page are simply not found, never touched. */
    public function test_bulk_toggle_cannot_reach_another_page(): void
    {
        $block = $this->foreignBlock();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/bulk-toggle',
                ['ids' => [$block->id], 'is_active' => false])
            ->assertOk()->assertJson(['changed' => 0]);

        $this->assertTrue((bool) $block->fresh()->is_active);
    }

    /** Delete just the selection, not the whole page. */
    public function test_a_selection_can_be_deleted_without_touching_the_rest(): void
    {
        $a = $this->block(['sort_order' => 0]);
        $b = $this->block(['sort_order' => 1]);
        $c = $this->block(['sort_order' => 2]);

        $this->actingAs($this->user)
            ->json('DELETE', '/user/links/'.$this->link->id.'/blocks', ['ids' => [$a->id, $c->id]])
            ->assertOk()->assertJson(['success' => true, 'deleted' => 2, 'remaining' => 1]);

        $this->assertSame([$b->id], $this->order());
    }

    /**
     * With no id list it is still "delete all" -- the button that was there
     * before this change must behave exactly as it did.
     */
    public function test_delete_with_no_selection_still_clears_the_page(): void
    {
        $this->block();
        $this->block();
        $this->block();

        $this->actingAs($this->user)
            ->json('DELETE', '/user/links/'.$this->link->id.'/blocks')
            ->assertOk()->assertJson(['deleted' => 3, 'remaining' => 0]);
    }

    /** A verified block survives a selected delete, as it survives delete-all. */
    public function test_a_verified_block_survives_a_selected_delete(): void
    {
        $v = $this->block(['type' => 'verified_heading', 'sort_order' => 0]);
        $b = $this->block(['sort_order' => 1]);

        $this->actingAs($this->user)
            ->json('DELETE', '/user/links/'.$this->link->id.'/blocks', ['ids' => [$v->id, $b->id]])
            ->assertOk()->assertJson(['deleted' => 1]);

        $this->assertNotNull($v->fresh(), 'the verified badge is protected whichever delete asks for it');
        $this->assertNull($b->fresh());
    }

    /** A group move is an ordinary reorder, so it obeys the same rules. */
    public function test_a_group_move_goes_through_reorder(): void
    {
        $a = $this->block(['sort_order' => 0]);
        $b = $this->block(['sort_order' => 1]);
        $c = $this->block(['sort_order' => 2]);
        $d = $this->block(['sort_order' => 3]);

        // The client moves the pair (c, d) up one place and posts the result.
        $this->actingAs($this->user)
            ->postJson('/user/links/'.$this->link->id.'/blocks/reorder',
                ['blocks' => [$a->id, $c->id, $d->id, $b->id]])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame([$a->id, $c->id, $d->id, $b->id], $this->order());
    }

    // ===== 3. The editor offers all of it =====

    /** The buttons and the bar are actually on the page. */
    public function test_the_editor_offers_duplicate_and_a_selection_bar(): void
    {
        $b = $this->block();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$this->link->id.'/blocks')->assertOk()->getContent();

        $this->assertStringContainsString('ajaxDuplicateBlock(', $html, 'every card needs a duplicate button');
        $this->assertStringContainsString('data-select-id="'.$b->id.'"', $html, 'every card needs a tick box');
        $this->assertStringContainsString('id="blockSelectBar"', $html);

        foreach (['up', 'down', 'duplicate', 'hide', 'show', 'delete', 'clear'] as $action) {
            $this->assertStringContainsString('data-sel-action="'.$action.'"', $html,
                "the selection bar must offer {$action}");
        }

        // And the bar starts out of the way.
        $this->assertMatchesRegularExpression('/id="blockSelectBar"[^>]*\shidden/', $html,
            'an empty selection bar is noise; it appears once something is ticked');
    }

    /** A verified block shows no duplicate button, matching the server. */
    public function test_a_verified_block_is_not_offered_a_duplicate_button(): void
    {
        $v = $this->block(['type' => 'verified_heading']);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$this->link->id.'/blocks')->assertOk()->getContent();

        $this->assertStringNotContainsString('/blocks/'.$v->id.'/duplicate', $html,
            'the UI must not offer what the server refuses');
    }
}
