<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5939 — custom sticker overlays on image blocks.
 *
 * Stickers are stored in `_style._photo_stickers` as sanitized
 * {file_id, url, pos, size, rotate, dx, dy} entries. The sanitizer must:
 *  - only accept image files OWNED by the workspace owner (fail closed),
 *  - re-derive the public `url` server-side (never trust the client),
 *  - clamp size/rotate/offsets and enforce the entry cap,
 *  - drop flagged / non-image / missing file references silently.
 * The public page must render owned stickers and skip anything invalid.
 */
class BiolinkPhotoStickersTest extends TestCase
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

    private function makeImageBlock(User $owner, Link $link): BiolinkBlock
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image']);
        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    public function test_owned_sticker_round_trips_with_server_derived_url(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);
        $file  = $this->makeImageFile($owner);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_photo_stickers' => json_encode([
                ['file_id' => $file->id, 'url' => 'https://evil.example/x.png', 'pos' => 'bottom_left', 'size' => 90, 'rotate' => -15, 'dx' => 5, 'dy' => -5],
            ])],
        ])->assertOk();

        $saved = $block->fresh()->settings['_style']['_photo_stickers'] ?? null;
        $this->assertIsArray($saved);
        $this->assertCount(1, $saved);
        $this->assertSame($file->id, $saved[0]['file_id']);
        // The url must be re-derived from the file row, never the client's.
        $this->assertSame($file->url_path, $saved[0]['url']);
        $this->assertStringStartsWith('/f/' . $file->id . '/', $saved[0]['url']);
        $this->assertSame('bottom_left', $saved[0]['pos']);
        $this->assertSame(90, $saved[0]['size']);
        $this->assertSame(-15, $saved[0]['rotate']);
    }

    public function test_foreign_missing_flagged_and_nonimage_files_fail_closed(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);

        $foreign = $this->makeImageFile($stranger);
        $flagged = $this->makeImageFile($owner, ['scan_status' => 'flagged']);
        $doc     = $this->makeImageFile($owner, ['type' => 'document', 'mime_type' => 'application/pdf']);
        $mine    = $this->makeImageFile($owner);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_photo_stickers' => json_encode([
                ['file_id' => $foreign->id],
                ['file_id' => $flagged->id],
                ['file_id' => $doc->id],
                ['file_id' => 999999],
                ['file_id' => $mine->id],
            ])],
        ])->assertOk();

        $saved = $block->fresh()->settings['_style']['_photo_stickers'] ?? [];
        $this->assertCount(1, $saved);
        $this->assertSame($mine->id, $saved[0]['file_id']);
    }

    public function test_cap_clamps_and_defaults_are_enforced(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);

        $entries = [];
        for ($i = 0; $i < BiolinkBlock::PHOTO_STICKER_MAX + 2; $i++) {
            $f = $this->makeImageFile($owner);
            $entries[] = ['file_id' => $f->id, 'pos' => 'nonsense', 'size' => 9999, 'rotate' => 720, 'dx' => -500, 'dy' => 500];
        }

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_photo_stickers' => json_encode($entries)],
        ])->assertOk();

        $saved = $block->fresh()->settings['_style']['_photo_stickers'] ?? [];
        $this->assertCount(BiolinkBlock::PHOTO_STICKER_MAX, $saved);
        $this->assertSame('top_right', $saved[0]['pos']); // unknown pos → default
        $this->assertSame(160, $saved[0]['size']);        // clamped
        $this->assertSame(180, $saved[0]['rotate']);      // clamped
        $this->assertSame(-80, $saved[0]['dx']);          // clamped
        $this->assertSame(80, $saved[0]['dy']);           // clamped
    }

    public function test_garbage_payloads_are_dropped_without_error(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);

        foreach (['not-json', '[]', '{"a":1}', json_encode([['url' => '/f/1/x.png']])] as $garbage) {
            $this->updateBlock($owner, $link, $block, [
                'style' => ['_photo_stickers' => $garbage],
            ])->assertOk();
            $this->assertArrayNotHasKey(
                '_photo_stickers',
                $block->fresh()->settings['_style'] ?? []
            );
        }
    }

    public function test_explicit_empty_value_clears_saved_stickers(): void
    {
        $owner = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);
        $file  = $this->makeImageFile($owner);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_photo_stickers' => json_encode([['file_id' => $file->id]])],
        ])->assertOk();
        $this->assertNotEmpty($block->fresh()->settings['_style']['_photo_stickers'] ?? []);

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_photo_stickers' => ''],
        ])->assertOk();
        $this->assertArrayNotHasKey('_photo_stickers', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_public_page_renders_owned_sticker_and_skips_foreign(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $this->actAsOwner($owner);
        $link  = $this->makeBiolink($owner);
        $block = $this->makeImageBlock($owner, $link);
        $mine    = $this->makeImageFile($owner);
        $foreign = $this->makeImageFile($stranger);

        // Persist one valid entry plus a tampered foreign one directly
        // (bypassing the sanitizer) — render must still fail closed.
        $settings = $block->fresh()->settings;
        $settings['url'] = 'https://example.com/photo.jpg';
        $settings['_style']['_photo_stickers'] = [
            ['file_id' => $mine->id, 'url' => $mine->url_path, 'pos' => 'top_left', 'size' => 48, 'rotate' => 10, 'dx' => 0, 'dy' => 0],
            ['file_id' => $foreign->id, 'url' => $foreign->url_path, 'pos' => 'top_right', 'size' => 48, 'rotate' => 0, 'dx' => 0, 'dy' => 0],
        ];
        $block->update(['settings' => $settings, 'is_active' => true]);

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();

        $this->assertStringContainsString($mine->url_path, $html);
        $this->assertStringContainsString('data-photo-hero', $html);
        $this->assertStringNotContainsString($foreign->url_path, $html);
    }
}
