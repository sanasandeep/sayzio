<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin manager for the curated platform galleries (upload / rename /
 * delete of the owner-managed S3 asset folders + immediate catalog
 * cache invalidation).
 */
class AdminPlatformGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Cache::flush();
    }

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_guest_cannot_access_gallery_manager(): void
    {
        $this->get('/admin/platform-gallery')->assertRedirect();
    }

    public function test_admin_without_settings_permission_is_denied(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'viewer-only'],
            ['name' => 'Viewer Only', 'guard' => 'admin']
        );
        $admin = Admin::create([
            'name'     => 'Limited Admin',
            'email'    => 'limited' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
        $this->be($admin, 'admin');

        $this->get('/admin/platform-gallery')->assertForbidden();
        $this->post('/admin/platform-gallery/grid-images/upload', [
            'files' => [UploadedFile::fake()->image('a.png')],
        ])->assertForbidden();
    }

    public function test_index_does_not_bust_the_catalog_cache(): void
    {
        // Warm the cache with an empty listing, then add a file directly
        // on S3: a read-only page view must keep serving the cached
        // (stale) listing — only mutations invalidate it.
        $this->assertSame([], PlatformAssetCatalog::list('grid-images'));
        Storage::disk('s3')->put('assets/grid-images/late-arrival.png', 'x');

        $this->be($this->makeAdmin(), 'admin');
        $this->get('/admin/platform-gallery?folder=grid-images')->assertOk();

        $this->assertSame([], PlatformAssetCatalog::list('grid-images'));
    }

    public function test_index_lists_folder_assets(): void
    {
        Storage::disk('s3')->put('assets/grid-images/city-lights.png', 'x');

        $this->be($this->makeAdmin(), 'admin');

        $resp = $this->get('/admin/platform-gallery?folder=grid-images');
        $resp->assertOk();
        $resp->assertSee('city-lights.png');
        $resp->assertSee('City Lights');
    }

    public function test_upload_stores_files_and_busts_cache(): void
    {
        $this->be($this->makeAdmin(), 'admin');

        // Warm the cache with an empty listing first.
        $this->assertSame([], PlatformAssetCatalog::list('biolink-backgrounds'));

        $resp = $this->post('/admin/platform-gallery/biolink-backgrounds/upload', [
            'files' => [UploadedFile::fake()->image('Sunset Beach.jpg', 100, 100)],
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        Storage::disk('s3')->assertExists('assets/biolink-backgrounds/Sunset Beach.jpg');

        // Cache was busted: the fresh listing sees the new file.
        $assets = PlatformAssetCatalog::list('biolink-backgrounds');
        $this->assertCount(1, $assets);
        $this->assertSame('assets/biolink-backgrounds/Sunset Beach.jpg', $assets[0]['key']);
    }

    public function test_upload_duplicate_name_gets_suffix(): void
    {
        Storage::disk('s3')->put('assets/grid-images/photo.png', 'x');
        $this->be($this->makeAdmin(), 'admin');

        $this->post('/admin/platform-gallery/grid-images/upload', [
            'files' => [UploadedFile::fake()->image('photo.png', 10, 10)],
        ])->assertSessionHas('success');

        Storage::disk('s3')->assertExists('assets/grid-images/photo-2.png');
    }

    public function test_upload_rejects_non_image(): void
    {
        $this->be($this->makeAdmin(), 'admin');

        $this->post('/admin/platform-gallery/grid-images/upload', [
            'files' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors();
    }

    public function test_rename_moves_object_and_pair(): void
    {
        Storage::disk('s3')->put('assets/hand-drawn/doodle.png', 'x');
        Storage::disk('s3')->put('assets/hand-drawn/doodle.svg', 'y');
        $this->be($this->makeAdmin(), 'admin');

        $this->post('/admin/platform-gallery/hand-drawn/rename', [
            'key'      => 'assets/hand-drawn/doodle.png',
            'new_name' => 'star-doodle',
        ])->assertSessionHas('success');

        Storage::disk('s3')->assertExists('assets/hand-drawn/star-doodle.png');
        Storage::disk('s3')->assertExists('assets/hand-drawn/star-doodle.svg');
        Storage::disk('s3')->assertMissing('assets/hand-drawn/doodle.png');
        Storage::disk('s3')->assertMissing('assets/hand-drawn/doodle.svg');
    }

    public function test_delete_removes_object_and_pair_and_busts_cache(): void
    {
        Storage::disk('s3')->put('assets/hand-drawn/doodle.png', 'x');
        Storage::disk('s3')->put('assets/hand-drawn/doodle.svg', 'y');
        $this->assertCount(1, PlatformAssetCatalog::list('hand-drawn')); // warm cache

        $this->be($this->makeAdmin(), 'admin');

        $this->delete('/admin/platform-gallery/hand-drawn', [
            'key' => 'assets/hand-drawn/doodle.png',
        ])->assertSessionHas('success');

        Storage::disk('s3')->assertMissing('assets/hand-drawn/doodle.png');
        Storage::disk('s3')->assertMissing('assets/hand-drawn/doodle.svg');
        $this->assertSame([], PlatformAssetCatalog::list('hand-drawn'));
    }

    public function test_delete_rejects_foreign_or_traversal_keys(): void
    {
        Storage::disk('s3')->put('assets/grid-images/keep.png', 'x');
        $this->be($this->makeAdmin(), 'admin');

        $this->delete('/admin/platform-gallery/grid-images', [
            'key' => 'assets/other-folder/secret.png',
        ])->assertSessionHas('error');

        $this->delete('/admin/platform-gallery/grid-images', [
            'key' => 'assets/grid-images/../secret.png',
        ])->assertSessionHas('error');

        Storage::disk('s3')->assertExists('assets/grid-images/keep.png');
    }

    public function test_unknown_folder_404s(): void
    {
        $this->be($this->makeAdmin(), 'admin');

        $this->post('/admin/platform-gallery/not-a-folder/upload', [
            'files' => [UploadedFile::fake()->image('a.png')],
        ])->assertNotFound();
    }
}
