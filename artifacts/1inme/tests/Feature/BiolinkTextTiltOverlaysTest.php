<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\FontCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5954 — tilted text, text-on-photo overlays, and free-floating
 * page-level text overlays.
 *
 *  - `_style._tilt` on heading/paragraph blocks is clamped to ±30° and a
 *    plain `0` is never stamped (range-input default noise).
 *  - `_style._photo_text_stickers` on image blocks is sanitized: text
 *    required (tags stripped), fonts/colors allowlisted, size/rotate/offsets
 *    clamped, capped at PHOTO_TEXT_STICKER_MAX.
 *  - `settings.biolink.text_overlays` saved via the page-settings endpoint
 *    is sanitized (percent x/y clamps, cap PAGE_TEXT_OVERLAY_MAX) and
 *    ignored entirely on design-locked pages.
 *  - The public API resolve payload exposes the overlays for mobile parity.
 *  - FontCatalog carries the distressed/poster display faces.
 */
class BiolinkTextTiltOverlaysTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function actAsOwner(User $user): void
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);
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

    private function makeBlock(User $owner, Link $link, string $type): BiolinkBlock
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

    private function savePageSettings(User $owner, Link $link, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/page-settings", $payload);
    }

    // ── _tilt ────────────────────────────────────────────────────────────

    public function test_tilt_is_saved_and_clamped_on_heading(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Hello'],
            'style'    => ['_tilt' => 95],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame((float) BiolinkBlock::TILT_MAX, (float) $style['_tilt']);

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Hello'],
            'style'    => ['_tilt' => -12],
        ])->assertOk();
        $this->assertSame(-12.0, (float) ($block->fresh()->settings['_style']['_tilt'] ?? null));
    }

    public function test_tilt_zero_is_not_stamped(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link, 'paragraph');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Body copy'],
            'style'    => ['_tilt' => 0],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_tilt', $style);
    }

    public function test_tilted_heading_renders_rotation_on_public_page(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Tilted Headline'],
            'style'    => ['_tilt' => -8],
        ])->assertOk();

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('data-tilt-wrap', $html);
        $this->assertStringContainsString('rotate(-8', $html);
    }

    // ── _photo_text_stickers ─────────────────────────────────────────────

    public function test_photo_text_stickers_are_sanitized_and_capped(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link, 'image');

        $entries = [
            ['text' => '  <b>Summer</b>  Sale ', 'font' => 'Rubik Dirt<script>', 'color' => 'nothex', 'pos' => 'nonsense', 'size' => 999, 'rotate' => 720, 'dx' => -500, 'dy' => 500],
            ['text' => '', 'font' => 'Anton'], // dropped: empty text
            ['no_text' => true],                // dropped: not text
        ];
        for ($i = 0; $i < BiolinkBlock::PHOTO_TEXT_STICKER_MAX + 2; $i++) {
            $entries[] = ['text' => "Cap {$i}"];
        }

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['url' => 'https://example.com/x.png'],
            'style'    => ['_photo_text_stickers' => json_encode($entries)],
        ])->assertOk();

        $saved = $block->fresh()->settings['_style']['_photo_text_stickers'] ?? null;
        $this->assertIsArray($saved);
        $this->assertCount(BiolinkBlock::PHOTO_TEXT_STICKER_MAX, $saved);

        $first = $saved[0];
        $this->assertSame('Summer Sale', $first['text']);        // tags stripped, whitespace collapsed
        $this->assertSame('Rubik Dirtscript', $first['font']);   // unsafe chars removed
        $this->assertSame('#ffffff', $first['color']);           // bad hex → default
        $this->assertSame('top_right', $first['pos']);           // bad pos → default
        $this->assertSame(64, $first['size']);
        $this->assertSame(180, $first['rotate']);
        $this->assertSame(-80, $first['dx']);
        $this->assertSame(80, $first['dy']);
    }

    public function test_photo_text_stickers_render_on_public_page(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($owner, $link, 'image');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['url' => 'https://example.com/x.png'],
            'style'    => ['_photo_text_stickers' => json_encode([
                ['text' => 'Fresh Drop', 'font' => 'Bebas Neue', 'color' => '#ff2244', 'pos' => 'bottom_left', 'size' => 28, 'rotate' => -10],
            ])],
        ])->assertOk();

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('data-photo-text-sticker', $html);
        $this->assertStringContainsString('Fresh Drop', $html);
    }

    // ── page-level text_overlays ─────────────────────────────────────────

    public function test_page_overlays_save_clamp_and_cap(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link = $this->makeBiolink($owner);

        $entries = [
            ['text' => ' <i>Hi</i> there ', 'font' => 'Permanent Marker', 'color' => '#123abc', 'size' => 500, 'x' => 120.456, 'y' => -3, 'rotate' => -400],
        ];
        for ($i = 0; $i < BiolinkBlock::PAGE_TEXT_OVERLAY_MAX + 3; $i++) {
            $entries[] = ['text' => "Cap {$i}", 'x' => 50, 'y' => 50];
        }

        $this->savePageSettings($owner, $link, [
            'text_overlays' => json_encode($entries),
        ]);

        $saved = $link->fresh()->settings['biolink']['text_overlays'] ?? null;
        $this->assertIsArray($saved);
        $this->assertCount(BiolinkBlock::PAGE_TEXT_OVERLAY_MAX, $saved);

        $first = $saved[0];
        $this->assertSame('Hi there', $first['text']);
        $this->assertSame('Permanent Marker', $first['font']);
        $this->assertSame('#123abc', $first['color']);
        $this->assertSame(72, $first['size']);
        $this->assertSame(100.0, (float) $first['x']);
        $this->assertSame(0.0, (float) $first['y']);
        $this->assertSame(-180, $first['rotate']);
    }

    public function test_page_overlays_can_be_cleared(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link = $this->makeBiolink($owner);

        $this->savePageSettings($owner, $link, [
            'text_overlays' => json_encode([['text' => 'Keep me', 'x' => 40, 'y' => 20]]),
        ]);
        $this->assertNotEmpty($link->fresh()->settings['biolink']['text_overlays']);

        $this->savePageSettings($owner, $link, ['text_overlays' => '']);
        $this->assertSame([], $link->fresh()->settings['biolink']['text_overlays'] ?? []);
    }

    public function test_page_overlays_ignored_on_design_locked_pages(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link = $this->makeBiolink($owner);

        $settings = $link->settings ?? [];
        $settings['biolink']['design_locked'] = ['template_id' => 1, 'fixed_blocks' => []];
        $link->update(['settings' => $settings]);

        $this->savePageSettings($owner, $link, [
            'text_overlays' => json_encode([['text' => 'Sneaky', 'x' => 10, 'y' => 10]]),
        ]);

        $this->assertArrayNotHasKey(
            'text_overlays',
            $link->fresh()->settings['biolink'] ?? []
        );
    }

    public function test_page_overlays_render_on_public_page(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link = $this->makeBiolink($owner);
        $this->makeBlock($owner, $link, 'heading');

        $this->savePageSettings($owner, $link, [
            'text_overlays' => json_encode([
                ['text' => 'Floating Caption', 'color' => '#ffee00', 'size' => 24, 'x' => 60, 'y' => 8, 'rotate' => 6],
            ]),
        ]);

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('biolink-text-overlays', $html);
        $this->assertStringContainsString('Floating Caption', $html);
    }

    // ── API parity ───────────────────────────────────────────────────────

    public function test_api_resolve_exposes_text_overlays(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link = $this->makeBiolink($owner);

        $this->savePageSettings($owner, $link, [
            'text_overlays' => json_encode([
                ['text' => 'Mobile Caption', 'x' => 33.5, 'y' => 12, 'rotate' => -6, 'size' => 20, 'color' => '#ffffff'],
            ]),
        ]);

        $resp = $this->getJson('/api/v1/biolinks/' . $link->alias);
        $resp->assertOk();
        $overlays = $resp->json('data.biolink.text_overlays');
        $this->assertIsArray($overlays);
        $this->assertCount(1, $overlays);
        $this->assertSame('Mobile Caption', $overlays[0]['text']);
        $this->assertSame(33.5, (float) $overlays[0]['x']);
        $this->assertSame(-6.0, (float) $overlays[0]['rotate']);
    }

    // ── FontCatalog ──────────────────────────────────────────────────────

    public function test_font_catalog_includes_distressed_poster_faces(): void
    {
        $families = array_column(FontCatalog::all(), 'category', 'family');
        foreach (['Rubik Dirt', 'Rye', 'Bungee Shade', 'Rock Salt'] as $f) {
            $this->assertArrayHasKey($f, $families, "Missing display font {$f}");
            $this->assertSame('display', $families[$f]);
        }
    }
}
