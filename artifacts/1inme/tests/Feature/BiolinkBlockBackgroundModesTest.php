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
 * Task #6044 — unified block backgrounds: color / gradient (long strings) /
 * image (http(s) + /f/ vault paths) on `_style`, plus the card container's
 * own bg_color / bg_gradient / bg_image settings sanitization.
 */
class BiolinkBlockBackgroundModesTest extends TestCase
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

    public function test_long_gradient_string_round_trips_in_bg_color(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'link');

        // A multi-stop gradient longer than the old 240-char cap but under 500.
        $stops    = [];
        for ($i = 0; $i <= 10; $i++) {
            $stops[] = sprintf('rgba(%d, %d, %d, 0.9) %d%%', 10 + $i, 20 + $i, 30 + $i, $i * 10);
        }
        $gradient = 'linear-gradient(135deg, ' . implode(', ', $stops) . ')';
        $this->assertGreaterThan(240, strlen($gradient));
        $this->assertLessThanOrEqual(500, strlen($gradient));

        $this->updateBlock($owner, $link, $block, [
            'style' => ['bg_color' => $gradient],
        ])->assertOk();

        $this->assertSame($gradient, $block->fresh()->settings['_style']['bg_color']);
    }

    public function test_conic_and_radial_gradients_accepted(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'cta_button');

        $conic = 'conic-gradient(from 90deg at center, #7c3aed 0%, #22d3ee 50%, #7c3aed 100%)';
        $this->updateBlock($owner, $link, $block, ['style' => ['bg_color' => $conic]])->assertOk();
        $this->assertSame($conic, $block->fresh()->settings['_style']['bg_color']);

        $radial = 'radial-gradient(circle at center, #111111 0%, #222222 100%)';
        $this->updateBlock($owner, $link, $block, ['style' => ['bg_color' => $radial]])->assertOk();
        $this->assertSame($radial, $block->fresh()->settings['_style']['bg_color']);
    }

    public function test_bg_image_accepts_vault_path_and_http_url_and_rejects_junk(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'link_big');

        $this->updateBlock($owner, $link, $block, [
            'style' => ['bg_image' => '/f/abc123/photo.jpg'],
        ])->assertOk();
        $this->assertSame('/f/abc123/photo.jpg', $block->fresh()->settings['_style']['bg_image']);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['bg_image' => 'https://cdn.example.com/bg.png'],
        ])->assertOk();
        $this->assertSame('https://cdn.example.com/bg.png', $block->fresh()->settings['_style']['bg_image']);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['bg_image' => 'javascript:alert(1)'],
        ])->assertOk();
        $this->assertArrayNotHasKey('bg_image', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_empty_bg_color_clears_the_key(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'link');

        $this->updateBlock($owner, $link, $block, ['style' => ['bg_color' => '#112233']])->assertOk();
        $this->assertSame('#112233', $block->fresh()->settings['_style']['bg_color']);

        $this->updateBlock($owner, $link, $block, ['style' => ['bg_color' => '']])->assertOk();
        $this->assertArrayNotHasKey('bg_color', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_card_settings_backgrounds_sanitized(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'card');

        $gradient = 'linear-gradient(135deg, #7c3aed 0%, #22d3ee 100%)';
        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'bg_type'     => 'gradient',
                'bg_gradient' => $gradient,
                'bg_image'    => '/f/def456/texture.png',
                'bg_color'    => '#0f172a',
            ],
        ])->assertOk();

        $s = $block->fresh()->settings;
        $this->assertSame($gradient, $s['bg_gradient']);
        $this->assertSame('/f/def456/texture.png', $s['bg_image']);
        $this->assertSame('#0f172a', $s['bg_color']);

        // Invalid values are stripped, not stored.
        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'bg_gradient' => 'url(javascript:alert(1))',
                'bg_image'    => 'data:text/html,<script>x</script>',
                'bg_color'    => 'expression(alert(1))',
            ],
        ])->assertOk();

        $s = $block->fresh()->settings;
        $this->assertArrayNotHasKey('bg_gradient', $s);
        $this->assertArrayNotHasKey('bg_image', $s);
        $this->assertArrayNotHasKey('bg_color', $s);
    }

    public function test_inline_style_renders_vault_bg_image(): void
    {
        $css = BiolinkBlock::buildInlineStyle(array_merge(BiolinkBlock::STYLE_DEFAULTS, [
            'display_mode' => 'card',
            'bg_image'     => '/f/abc123/photo.jpg',
        ]));

        $this->assertStringContainsString("url('/f/abc123/photo.jpg')", $css);
    }
}
