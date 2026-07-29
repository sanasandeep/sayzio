<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task #6015 — curated platform asset galleries (owner-managed S3
 * folders surfaced through PlatformAssetCatalog + web/API endpoints,
 * available on every plan).
 */
class PlatformAssetCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Cache::flush();
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Asset Tester',
            'email'    => 'asset-tester-' . uniqid() . '@example.com',
            'password' => bcrypt('secret1234'),
        ]);
    }

    public function test_catalog_lists_folder_with_labels_and_urls(): void
    {
        Storage::disk('s3')->put('assets/biolink-backgrounds/sunset-beach_02.jpg', 'x');
        Storage::disk('s3')->put('assets/biolink-backgrounds/aurora.png', 'x');
        Storage::disk('s3')->put('assets/biolink-backgrounds/notes.txt', 'x');

        $assets = PlatformAssetCatalog::list('biolink-backgrounds');

        $this->assertCount(2, $assets);
        $byKey = collect($assets)->keyBy('key');
        $this->assertTrue($byKey->has('assets/biolink-backgrounds/sunset-beach_02.jpg'));
        $entry = $byKey['assets/biolink-backgrounds/sunset-beach_02.jpg'];
        $this->assertSame('Sunset Beach 02', $entry['label']);
        $this->assertNotEmpty($entry['url']);
        $this->assertStringContainsString('assets/biolink-backgrounds/sunset-beach_02.jpg', $entry['url']);
    }

    public function test_hand_drawn_png_svg_pairs_collapse_into_one_entry(): void
    {
        Storage::disk('s3')->put('assets/hand-drawn/doodle-star.png', 'x');
        Storage::disk('s3')->put('assets/hand-drawn/doodle-star.svg', 'x');
        Storage::disk('s3')->put('assets/hand-drawn/solo-vector.svg', 'x');

        $assets = PlatformAssetCatalog::list('hand-drawn');

        $this->assertCount(2, $assets);
        $byLabel = collect($assets)->keyBy('label');
        $pair = $byLabel['Doodle Star'];
        $this->assertStringEndsWith('.png', $pair['key']);
        $this->assertArrayHasKey('svg_url', $pair);
        $this->assertStringContainsString('doodle-star.svg', $pair['svg_url']);
        // SVG-only files still surface.
        $this->assertStringEndsWith('.svg', $byLabel['Solo Vector']['key']);
    }

    public function test_empty_or_unknown_folder_behaviour(): void
    {
        $this->assertSame([], PlatformAssetCatalog::list('biolink-backgrounds'));
        $this->assertSame([], PlatformAssetCatalog::list('not-a-folder'));
        $this->assertFalse(PlatformAssetCatalog::isFolder('not-a-folder'));
    }

    public function test_key_validation_rejects_traversal_and_foreign_prefixes(): void
    {
        $this->assertTrue(PlatformAssetCatalog::isValidKey('grid-images', 'assets/grid-images/photo (1).jpg'));
        $this->assertFalse(PlatformAssetCatalog::isValidKey('grid-images', 'assets/grid-images/../secret.jpg'));
        $this->assertFalse(PlatformAssetCatalog::isValidKey('grid-images', 'assets/grid-images/nested/a.jpg'));
        $this->assertFalse(PlatformAssetCatalog::isValidKey('grid-images', 'assets/hand-drawn/a.png'));
        $this->assertFalse(PlatformAssetCatalog::isValidKey('grid-images', 'assets/grid-images/a.php'));
        $this->assertSame(
            'stock-avatars',
            PlatformAssetCatalog::folderForKey('assets/stock-avatars/bot.png', PlatformAssetCatalog::AVATAR_FOLDERS)
        );
        $this->assertNull(
            PlatformAssetCatalog::folderForKey('assets/biolink-backgrounds/a.jpg', PlatformAssetCatalog::AVATAR_FOLDERS)
        );
    }

    public function test_listing_is_cached(): void
    {
        Storage::disk('s3')->put('assets/grid-images/one.jpg', 'x');
        $this->assertCount(1, PlatformAssetCatalog::list('grid-images'));

        Storage::disk('s3')->put('assets/grid-images/two.jpg', 'x');
        // Still the cached listing until the TTL lapses.
        $this->assertCount(1, PlatformAssetCatalog::list('grid-images'));

        Cache::flush();
        $this->assertCount(2, PlatformAssetCatalog::list('grid-images'));
    }

    public function test_web_endpoint_returns_assets_for_any_plan(): void
    {
        Storage::disk('s3')->put('assets/people-avatars/ana.jpg', 'x');
        $user = $this->makeUser(); // free/no plan

        $res = $this->actingAs($user)->getJson(route('user.platform-assets.index', 'people-avatars'));
        $res->assertOk()->assertJson(['success' => true, 'folder' => 'people-avatars']);
        $this->assertCount(1, $res->json('assets'));

        $this->actingAs($user)
            ->getJson(route('user.platform-assets.index', 'nope'))
            ->assertNotFound();
    }

    public function test_api_endpoint_lists_and_404s_unknown_folder(): void
    {
        Storage::disk('s3')->put('assets/stock-avatars/bot.png', 'x');
        $user = $this->makeUser();
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/v1/platform-assets/stock-avatars');
        $res->assertOk();
        $this->assertSame('stock-avatars', $res->json('data.folder'));
        $this->assertCount(1, $res->json('data.assets'));

        $this->withToken($token)
            ->getJson('/api/v1/platform-assets/wrong')
            ->assertNotFound();
    }

    public function test_profile_avatar_asset_save_resolves_to_public_url(): void
    {
        Storage::disk('s3')->put('assets/people-avatars/ana.jpg', 'x');
        $user = $this->makeUser();

        $this->actingAs($user)->put(route('user.profile.update'), [
            'name'         => $user->name,
            'email'        => $user->email,
            'timezone'     => 'UTC',
            'language'     => 'en',
            'avatar_asset' => 'assets/people-avatars/ana.jpg',
        ]);

        $user->refresh();
        $this->assertStringContainsString('assets/people-avatars/ana.jpg', (string) $user->avatar);
    }

    public function test_profile_avatar_asset_rejects_invalid_key(): void
    {
        $user = $this->makeUser();
        $before = $user->avatar;

        $this->actingAs($user)->put(route('user.profile.update'), [
            'name'         => $user->name,
            'email'        => $user->email,
            'timezone'     => 'UTC',
            'language'     => 'en',
            'avatar_asset' => 'assets/people-avatars/../../etc/passwd',
        ]);

        $this->assertSame($before, $user->refresh()->avatar);
    }
}
