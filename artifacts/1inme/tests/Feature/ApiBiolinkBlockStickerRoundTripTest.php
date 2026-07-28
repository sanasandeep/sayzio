<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5957 — sticker positions saved on mobile must survive a
 * save-and-reload round trip. The mobile editor merges
 * `_style._photo_stickers` back into settings on save because the
 * /api/v1 block PATCH replaces settings wholesale; the API path must run
 * the same sanitizer the web editor uses so:
 *  - sticker entries round-trip (pos/dx/dy/size/rotate persisted),
 *  - dx/dy are clamped to ±80, size to 24–160, rotate to ±180,
 *  - pos is limited to the 6 anchor presets (unknown → top_right),
 *  - foreign/missing files fail closed and the url is server-derived,
 *  - all OTHER `_style` keys are preserved untouched.
 */
class ApiBiolinkBlockStickerRoundTripTest extends TestCase
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

    private function makeImageFile(User $owner, array $overrides = []): UserFile
    {
        return UserFile::create(array_merge([
            'user_id'       => $owner->id,
            'original_name' => 'sticker.png',
            'filename'      => Str::random(10) . '.png',
            'mime_type'     => 'image/png',
            'size_bytes'    => 1234,
            'type'          => 'image',
            'disk'          => 'public',
            'path'          => 'user-files/' . $owner->id . '/sticker.png',
            'scan_status'   => 'clean',
        ], $overrides));
    }

    private function makeImageBlock(Link $link): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'image',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['url' => 'https://example.com/photo.jpg'],
        ]);
    }

    private function authed(User $user): static
    {
        return $this->withHeader(
            'Authorization',
            'Bearer ' . $user->createToken('test')->plainTextToken
        );
    }

    public function test_sticker_entries_round_trip_through_api_patch_and_reload(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($link);
        $file  = $this->makeImageFile($owner);

        // Mirrors what the mobile editor sends: the whole settings object
        // with the drag-positioned stickers merged into `_style`.
        $style = [
            'block_bg'        => '#112233',
            'text_color'      => '#ffffff',
            'mask_shape'      => 'circle',
            '_photo_stickers' => [
                ['file_id' => $file->id, 'url' => 'https://evil.example/x.png', 'pos' => 'bottom_left', 'size' => 90, 'rotate' => -15, 'dx' => 41, 'dy' => -27],
            ],
        ];

        $this->authed($owner)->patchJson("/api/v1/links/{$link->id}/blocks/{$block->id}", [
            'settings' => ['url' => 'https://example.com/photo.jpg', '_style' => $style],
        ])->assertOk();

        // "Reload": re-fetch through the API index like the mobile editor does.
        $resp = $this->authed($owner)->getJson("/api/v1/links/{$link->id}/blocks")->assertOk();
        $items = $resp->json('data.items');
        $saved = collect($items)->firstWhere('id', $block->id)['settings']['_style'] ?? [];

        $stickers = $saved['_photo_stickers'] ?? null;
        $this->assertIsArray($stickers);
        $this->assertCount(1, $stickers);
        $s = $stickers[0];
        $this->assertSame($file->id, $s['file_id']);
        // url is re-derived server-side, never the client's value.
        $this->assertSame($file->url_path, $s['url']);
        $this->assertStringStartsWith('/f/' . $file->id . '/', $s['url']);
        $this->assertSame('bottom_left', $s['pos']);
        $this->assertSame(90, $s['size']);
        $this->assertSame(-15, $s['rotate']);
        $this->assertSame(41, $s['dx']);
        $this->assertSame(-27, $s['dy']);

        // Other _style keys survive untouched.
        $this->assertSame('#112233', $saved['block_bg'] ?? null);
        $this->assertSame('#ffffff', $saved['text_color'] ?? null);
        $this->assertSame('circle', $saved['mask_shape'] ?? null);

        // Second round trip: PATCH the stored settings back verbatim (what a
        // subsequent mobile save of an unrelated field does) — nothing drifts.
        $stored = $block->fresh()->settings;
        $this->authed($owner)->patchJson("/api/v1/links/{$link->id}/blocks/{$block->id}", [
            'settings' => $stored,
        ])->assertOk();
        $this->assertSame($stored['_style']['_photo_stickers'], $block->fresh()->settings['_style']['_photo_stickers']);
    }

    public function test_out_of_bounds_values_are_clamped_and_pos_limited_to_presets(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($link);

        $entries = [];
        for ($i = 0; $i < BiolinkBlock::PHOTO_STICKER_MAX + 2; $i++) {
            $f = $this->makeImageFile($owner);
            $entries[] = ['file_id' => $f->id, 'pos' => 'outer_space', 'size' => 9999, 'rotate' => 720, 'dx' => -500, 'dy' => 500];
        }

        $this->authed($owner)->patchJson("/api/v1/links/{$link->id}/blocks/{$block->id}", [
            'settings' => ['url' => 'x', '_style' => ['_photo_stickers' => $entries]],
        ])->assertOk();

        $saved = $block->fresh()->settings['_style']['_photo_stickers'] ?? [];
        $this->assertCount(BiolinkBlock::PHOTO_STICKER_MAX, $saved);
        foreach ($saved as $s) {
            $this->assertContains($s['pos'], BiolinkBlock::PHOTO_STICKER_POSITIONS);
            $this->assertSame('top_right', $s['pos']); // unknown pos → default preset
            $this->assertSame(160, $s['size']);        // clamped
            $this->assertSame(180, $s['rotate']);      // clamped
            $this->assertSame(-80, $s['dx']);          // clamped
            $this->assertSame(80, $s['dy']);           // clamped
        }
    }

    public function test_each_of_the_six_presets_is_accepted(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($link);
        $file  = $this->makeImageFile($owner);

        foreach (BiolinkBlock::PHOTO_STICKER_POSITIONS as $pos) {
            $this->authed($owner)->patchJson("/api/v1/links/{$link->id}/blocks/{$block->id}", [
                'settings' => ['url' => 'x', '_style' => ['_photo_stickers' => [
                    ['file_id' => $file->id, 'pos' => $pos, 'size' => 48, 'rotate' => 0, 'dx' => 0, 'dy' => 0],
                ]]],
            ])->assertOk();
            $this->assertSame($pos, $block->fresh()->settings['_style']['_photo_stickers'][0]['pos'] ?? null);
        }
    }

    public function test_foreign_and_garbage_entries_fail_closed_but_style_keys_remain(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($link);
        $foreign = $this->makeImageFile($stranger);

        $this->authed($owner)->patchJson("/api/v1/links/{$link->id}/blocks/{$block->id}", [
            'settings' => ['url' => 'x', '_style' => [
                'block_bg'        => '#445566',
                '_photo_stickers' => [
                    ['file_id' => $foreign->id],
                    ['file_id' => 999999],
                    'not-an-entry',
                ],
            ]],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_photo_stickers', $style);
        $this->assertSame('#445566', $style['block_bg'] ?? null);
    }

    public function test_api_create_also_sanitizes_sticker_entries(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $file  = $this->makeImageFile($owner);

        $resp = $this->authed($owner)->postJson("/api/v1/links/{$link->id}/blocks", [
            'type'     => 'image',
            'settings' => ['url' => 'x', '_style' => ['_photo_stickers' => [
                ['file_id' => $file->id, 'url' => 'https://evil.example/x.png', 'pos' => 'top_left', 'size' => 5, 'rotate' => -999, 'dx' => 200, 'dy' => -200],
            ]]],
        ])->assertCreated();

        $blockId = $resp->json('data.block.id');
        $s = BiolinkBlock::findOrFail($blockId)->settings['_style']['_photo_stickers'][0] ?? null;
        $this->assertNotNull($s);
        $this->assertSame($file->url_path, $s['url']);
        $this->assertSame('top_left', $s['pos']);
        $this->assertSame(24, $s['size']);     // clamped up to min
        $this->assertSame(-180, $s['rotate']); // clamped
        $this->assertSame(80, $s['dx']);       // clamped
        $this->assertSame(-80, $s['dy']);      // clamped
    }
}
