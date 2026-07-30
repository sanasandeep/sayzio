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
 * Task #6119 — per-device block widths. The editor's Block Width control
 * now has Mobile (base `grid_span`) and Desktop (`grid_span_md` override)
 * segments; an empty `grid_span_md` submission means "same as mobile" and
 * clears the override (Task #4025 clear-on-empty semantics). The public
 * page re-places overridden blocks at the 768px breakpoint via the
 * `.md-span` class + `--md-span` var on the block wrap.
 */
class BiolinkBlockPerDeviceWidthTest extends TestCase
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

    public function test_saves_mobile_span_and_desktop_override_independently(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, [
            'grid_span'    => 6,
            'grid_span_md' => 4,
        ]);

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(6, (int) ($style['grid_span'] ?? 0));
        $this->assertSame(4, (int) ($style['grid_span_md'] ?? 0));
    }

    public function test_empty_grid_span_md_clears_the_desktop_override(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, [
            'grid_span'    => 6,
            'grid_span_md' => 8,
        ]);
        $this->assertSame(8, (int) ($block->fresh()->settings['_style']['grid_span_md'] ?? 0));

        // "Same as mobile" — empty value clears the key entirely.
        $this->updateStyle($owner, $link, $block, [
            'grid_span'    => 6,
            'grid_span_md' => '',
        ]);

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame(6, (int) ($style['grid_span'] ?? 0));
        $this->assertArrayNotHasKey('grid_span_md', $style);
    }

    public function test_grid_span_md_is_clamped_to_the_sanitizer_bounds(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, ['grid_span_md' => 40]);
        $this->assertSame(12, (int) ($block->fresh()->settings['_style']['grid_span_md'] ?? 0));

        $this->updateStyle($owner, $link, $block, ['grid_span_md' => -3]);
        $this->assertSame(1, (int) ($block->fresh()->settings['_style']['grid_span_md'] ?? 0));
    }

    public function test_public_page_emits_md_span_wrap_only_when_override_set(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link);

        // No override: wrap must not carry the md-span marker for this block.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6, 'grid_span_md' => '']);
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $wrap = $this->extractWrap($html, $block->id);
        $this->assertStringContainsString('grid-column: span 6', $wrap);
        $this->assertStringNotContainsString('md-span', $wrap);
        $this->assertStringNotContainsString('--md-span', $wrap);

        // Override set: wrap carries .md-span + --md-span var while the
        // inline base span stays mobile-first.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6, 'grid_span_md' => 12]);
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $wrap = $this->extractWrap($html, $block->id);
        $this->assertStringContainsString('grid-column: span 6', $wrap);
        $this->assertStringContainsString('md-span', $wrap);
        $this->assertStringContainsString('--md-span:12', $wrap);
    }

    public function test_card_container_child_honours_desktop_override(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        $card = $this->makeBlock($owner, $link);
        $card->update(['type' => 'card', 'settings' => array_merge($card->settings ?? [], [
            'columns' => 2,
        ])]);

        $child = $this->makeBlock($owner, $link);
        $child->update(['parent_id' => $card->id]);
        $this->updateStyle($owner, $link, $child, ['grid_span' => 12, 'grid_span_md' => 6]);

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        // grid_span_md=6 of 12 maps to 1 of the container's 2 columns.
        $this->assertStringContainsString('--md-span: 1', $html);
        $this->assertStringContainsString('md-span', $html);
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
