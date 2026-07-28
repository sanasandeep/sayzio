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
 * Task #5938 — decorative shape accents behind heading blocks. The
 * `_heading_*` style keys must round-trip through the editor save
 * (sanitized: unknown shape tokens dropped, strict placement/size
 * enums, color validated) and the public page must layer the accent
 * SVGs behind the heading text.
 */
class HeadingAccentDecorationsTest extends TestCase
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

    private function createHeading(User $owner, Link $link): BiolinkBlock
    {
        $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'heading'])
            ->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $style)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", [
                'settings' => ['text' => 'Hello world'],
                'style'    => $style,
            ]);
    }

    public function test_heading_accent_keys_round_trip_sanitized(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createHeading($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            '_heading_accents'          => 'starburst, unicorn, DOTS, dots',
            '_heading_accent_color'     => '#ff2f92',
            '_heading_accent_placement' => 'top_right',
            '_heading_accent_size'      => 'lg',
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame('starburst,dots', $style['_heading_accents'] ?? null);
        $this->assertSame('#ff2f92', $style['_heading_accent_color'] ?? null);
        $this->assertSame('top_right', $style['_heading_accent_placement'] ?? null);
        $this->assertSame('lg', $style['_heading_accent_size'] ?? null);
    }

    public function test_invalid_enum_and_color_values_are_stripped(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createHeading($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            '_heading_accents'          => 'dragon,phoenix',
            '_heading_accent_color'     => 'url(javascript:alert(1))',
            '_heading_accent_placement' => 'under_the_sea',
            '_heading_accent_size'      => 'xxl',
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_heading_accents', $style);
        $this->assertArrayNotHasKey('_heading_accent_color', $style);
        $this->assertArrayNotHasKey('_heading_accent_placement', $style);
        $this->assertArrayNotHasKey('_heading_accent_size', $style);
    }

    public function test_clearing_the_accents_field_removes_saved_accents(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createHeading($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            '_heading_accents'      => 'ring,blob',
            '_heading_accent_color' => '#123456',
        ])->assertOk();
        $this->assertSame('ring,blob', $block->fresh()->settings['_style']['_heading_accents'] ?? null);

        // Unchecking every shape submits an empty hidden input — a "clear
        // this key" instruction (Task #4025 semantics).
        $this->updateBlock($owner, $link, $block, [
            '_heading_accents' => '',
        ])->assertOk();
        $this->assertArrayNotHasKey('_heading_accents', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_public_page_renders_accents_behind_heading(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createHeading($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            '_heading_accents'          => 'starburst,squiggle',
            '_heading_accent_color'     => '#ff2f92',
            '_heading_accent_placement' => 'behind_left',
            '_heading_accent_size'      => 'sm',
        ])->assertOk();

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $html = $resp->getContent();

        $this->assertStringContainsString('data-heading-accents="starburst,squiggle"', $html);
        // Starburst path from AccentShapeCatalog, tinted with the chosen color.
        $this->assertStringContainsString('M50 0 L56 33 L75 7', $html);
        $this->assertStringContainsString('#ff2f92', $html);
        $this->assertStringContainsString('Hello world', $html);
    }

    public function test_public_page_without_accents_has_no_accent_markup(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createHeading($owner, $link);

        $this->updateBlock($owner, $link, $block, [])->assertOk();

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $this->assertStringNotContainsString('data-heading-accents', $resp->getContent());
    }
}
