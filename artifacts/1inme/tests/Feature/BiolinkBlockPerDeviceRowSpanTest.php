<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6123 — per-device block heights (row spans). The editor's Block
 * Height control has Mobile (base `grid_row_span`, "Auto" = empty) and
 * Desktop (`grid_row_span_md` override, "Same" = empty) segments. The
 * public page stretches a base-row-span block at every width via the
 * `.row-span` class + `--row-span` var; the desktop override re-places it
 * at the 768px breakpoint via `.md-row-span` + `--md-row-span`.
 */
class BiolinkBlockPerDeviceRowSpanTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeBiolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function makeBlock(User $owner, Link $link): BiolinkBlock
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'paragraph']);

        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateStyle(User $owner, Link $link, BiolinkBlock $block, array $style): void
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", [
                'settings' => $block->fresh()->settings,
                'style'    => $style,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));
    }

    public function test_saves_mobile_row_span_and_desktop_override_independently(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, [
            'grid_row_span'    => 2,
            'grid_row_span_md' => 4,
        ]);

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(2, (int) ($style['grid_row_span'] ?? 0));
        $this->assertSame(4, (int) ($style['grid_row_span_md'] ?? 0));
    }

    public function test_empty_values_clear_both_row_span_keys(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, [
            'grid_row_span'    => 3,
            'grid_row_span_md' => 5,
        ]);
        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(3, (int) ($style['grid_row_span'] ?? 0));
        $this->assertSame(5, (int) ($style['grid_row_span_md'] ?? 0));

        // "Auto" / "Same" — empty values clear the keys entirely.
        $this->updateStyle($owner, $link, $block, [
            'grid_row_span'    => '',
            'grid_row_span_md' => '',
        ]);

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('grid_row_span', $style);
        $this->assertArrayNotHasKey('grid_row_span_md', $style);
    }

    public function test_row_spans_are_clamped_to_the_sanitizer_bounds(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, ['grid_row_span' => 40, 'grid_row_span_md' => 40]);
        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(6, (int) ($style['grid_row_span'] ?? 0));
        $this->assertSame(6, (int) ($style['grid_row_span_md'] ?? 0));

        $this->updateStyle($owner, $link, $block, ['grid_row_span' => -3, 'grid_row_span_md' => -3]);
        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(1, (int) ($style['grid_row_span'] ?? 0));
        $this->assertSame(1, (int) ($style['grid_row_span_md'] ?? 0));
    }

    public function test_public_page_emits_row_span_wrap_markers(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        // No row spans set: no markers on this block's wrap.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6]);
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $wrap = $this->extractWrap($html, $block->id);
        $this->assertStringNotContainsString('row-span', $wrap);

        // Base row span only: `.row-span` + `--row-span`, no md marker.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6, 'grid_row_span' => 2]);
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $wrap = $this->extractWrap($html, $block->id);
        $this->assertStringContainsString('row-span', $wrap);
        $this->assertStringContainsString('--row-span:2', $wrap);
        $this->assertStringNotContainsString('md-row-span', $wrap);
        $this->assertStringNotContainsString('--md-row-span', $wrap);

        // Both set: base + desktop override markers coexist on the wrap.
        $this->updateStyle($owner, $link, $block, [
            'grid_span'        => 6,
            'grid_row_span'    => 2,
            'grid_row_span_md' => 4,
        ]);
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $wrap = $this->extractWrap($html, $block->id);
        $this->assertStringContainsString('--row-span:2', $wrap);
        $this->assertStringContainsString('md-row-span', $wrap);
        $this->assertStringContainsString('--md-row-span:4', $wrap);
    }

    public function test_card_container_child_honours_row_spans(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        $card = $this->makeBlock($owner, $link);
        $card->update(['type' => 'card', 'settings' => array_merge($card->settings ?? [], [
            'columns' => 2,
        ])]);

        $child = $this->makeBlock($owner, $link);
        $child->update(['parent_id' => $card->id]);
        $this->updateStyle($owner, $link, $child, ['grid_row_span' => 2, 'grid_row_span_md' => 3]);

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('--row-span: 2', $html);
        $this->assertStringContainsString('--md-row-span: 3', $html);
        $this->assertStringContainsString('md-row-span', $html);
    }

    /** Grab the opening wrap tag for the given block id from public HTML. */
    private function extractWrap(string $html, int $blockId): string
    {
        $pos = strpos($html, 'data-block-id="' . $blockId . '"');
        $this->assertNotFalse($pos, 'block wrap not found in public HTML');
        $start = strrpos(substr($html, 0, $pos), '<div');
        $end = strpos($html, '>', $pos);

        return substr($html, $start, $end - $start + 1);
    }
}
