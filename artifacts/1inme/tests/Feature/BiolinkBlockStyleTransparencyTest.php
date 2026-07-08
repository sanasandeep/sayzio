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
 * Task #4025 — block backgrounds must stay transparent unless the user
 * explicitly picks one. The old Look tab submitted a browser-normalized
 * input[type=color] value (seeded with an invalid 8-digit hex) on every
 * save, stamping a solid bg_color onto text-only edits. The text input is
 * now the named source of truth and an explicitly-submitted empty value
 * clears the key from _style.
 */
class BiolinkBlockStyleTransparencyTest extends TestCase
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

    private function createBlock(User $owner, Link $link, string $type): BiolinkBlock
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => $type]);

        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    /**
     * The style fields the Edit Block form echoes on every save when the
     * user never touched the Look tab: empty color text inputs / hidden
     * inputs, mirroring the fixed blade partial for a fresh heading block.
     */
    private function untouchedFormStyle(): array
    {
        return [
            'text_color'   => '',
            'bg_color'     => '',
            'border_color' => '',
            'shadow_color' => '',
        ];
    }

    public function test_new_heading_block_has_no_background_color(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $style = $block->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_color', $style);
        $this->assertSame('content', $style['display_mode'] ?? null);
    }

    public function test_text_only_edit_does_not_add_background_color(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $resp = $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'My new heading'],
            'style'    => $this->untouchedFormStyle(),
        ]);
        $resp->assertOk();

        $fresh = $block->fresh()->settings;
        $this->assertSame('My new heading', $fresh['text'] ?? null);
        $style = $fresh['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_color', $style);
        $this->assertArrayNotHasKey('text_color', $style);
        $this->assertArrayNotHasKey('border_color', $style);
    }

    public function test_explicit_background_choice_round_trips(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        // Explicit solid color.
        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Hi'],
            'style'    => array_merge($this->untouchedFormStyle(), ['bg_color' => '#ff0000']),
        ])->assertOk();
        $this->assertSame('#ff0000', $block->fresh()->settings['_style']['bg_color'] ?? null);

        // A later text-only save must not disturb the chosen color.
        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Hello again'],
            'style'    => array_merge($this->untouchedFormStyle(), ['bg_color' => '#ff0000']),
        ])->assertOk();
        $this->assertSame('#ff0000', $block->fresh()->settings['_style']['bg_color'] ?? null);

        // Gradient round-trips.
        $this->updateBlock($owner, $link, $block, [
            'style' => array_merge($this->untouchedFormStyle(), [
                'bg_color' => 'linear-gradient(90deg, #ff0000, #0000ff)',
            ]),
        ])->assertOk();
        $this->assertSame(
            'linear-gradient(90deg, #ff0000, #0000ff)',
            $block->fresh()->settings['_style']['bg_color'] ?? null
        );

        // The transparent keyword round-trips too.
        $this->updateBlock($owner, $link, $block, [
            'style' => array_merge($this->untouchedFormStyle(), ['bg_color' => 'transparent']),
        ])->assertOk();
        $this->assertSame('transparent', $block->fresh()->settings['_style']['bg_color'] ?? null);
    }

    public function test_clearing_the_field_removes_a_saved_background(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        // Simulate a block that got an unwanted solid bg from the old bug.
        $settings = $block->settings;
        $settings['_style']['bg_color'] = '#ffffff';
        $block->update(['settings' => $settings]);
        $this->assertSame('#ffffff', $block->fresh()->settings['_style']['bg_color'] ?? null);

        // Clearing the text field back to empty ("Transparent") removes it.
        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Still my heading'],
            'style'    => $this->untouchedFormStyle(),
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_color', $style);
    }

    public function test_empty_values_clear_sibling_color_fields_too(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'style' => array_merge($this->untouchedFormStyle(), [
                'text_color'   => '#00ff00',
                'border_color' => '#123456',
            ]),
        ])->assertOk();
        $style = $block->fresh()->settings['_style'];
        $this->assertSame('#00ff00', $style['text_color'] ?? null);
        $this->assertSame('#123456', $style['border_color'] ?? null);

        $this->updateBlock($owner, $link, $block, [
            'style' => $this->untouchedFormStyle(),
        ])->assertOk();
        $style = $block->fresh()->settings['_style'];
        $this->assertArrayNotHasKey('text_color', $style);
        $this->assertArrayNotHasKey('border_color', $style);
    }

    public function test_unrelated_saves_keep_intentional_style_untouched(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'link');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Shop', 'url' => 'https://shop.example.com'],
            'style'    => array_merge($this->untouchedFormStyle(), ['bg_color' => '#112233']),
        ])->assertOk();

        // A save that omits the style array entirely (e.g. a non-Look-tab
        // endpoint) must not drop the chosen background.
        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Shop now', 'url' => 'https://shop.example.com'],
        ])->assertOk();

        $this->assertSame('#112233', $block->fresh()->settings['_style']['bg_color'] ?? null);
    }
}
