<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AndroidApkRelease;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the Android APK admin surface and public download route.
 *
 * Tests:
 * - Public page renders correctly with and without a live release.
 * - Public download 404s when no live release exists.
 * - Admin index page is accessible to admins with settings.manage permission.
 * - Admin upload creates a release and optionally sets it live.
 * - Admin set-live swaps the live flag atomically.
 * - Admin delete removes a non-live release.
 * - Admin delete refuses to remove the live release.
 * - Local-disk download route streams with correct headers and honours Range.
 */
class AndroidApkTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermission(string $permSlug): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-' . $permSlug],
            ['name' => 'Staff (' . $permSlug . ')', 'guard' => 'admin']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => $permSlug],
            ['name' => $permSlug, 'group' => explode('.', $permSlug)[0] ?? 'misc']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function fakeLocalDisk(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.driver' => 'local']);
    }

    // -----------------------------------------------------------------------
    // Public page
    // -----------------------------------------------------------------------

    public function test_public_page_shows_not_available_when_no_live_release(): void
    {
        $response = $this->get('/android');
        $response->assertOk();
        $response->assertSee("isn't available", false);
    }

    public function test_public_page_shows_version_and_size_when_live_release_exists(): void
    {
        $this->fakeLocalDisk();

        AndroidApkRelease::create([
            'version_name'    => '1.2.3',
            'build_number'    => '42',
            'file_size_bytes' => 150 * 1024 * 1024,
            'disk'            => 'public',
            'path'            => 'apk/sayzio-test.apk',
            'is_live'         => true,
        ]);

        $response = $this->get('/android');
        $response->assertOk();
        $response->assertSee('1.2.3');
        $response->assertSee('150');
        $response->assertSee(route('android.download'), false);
    }

    // -----------------------------------------------------------------------
    // Public JSON info endpoint
    // -----------------------------------------------------------------------

    public function test_public_info_returns_404_when_no_live_release(): void
    {
        $this->getJson('/android/app.json')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'no_live_release');
    }

    public function test_public_info_returns_version_and_size_for_live_release(): void
    {
        $this->fakeLocalDisk();

        AndroidApkRelease::create([
            'version_name'    => '1.2.3',
            'build_number'    => '42',
            'file_size_bytes' => 150 * 1024 * 1024,
            'disk'            => 'public',
            'path'            => 'apk/sayzio-test.apk',
            'is_live'         => true,
        ]);

        $this->getJson('/android/app.json')
            ->assertOk()
            ->assertJsonPath('data.version_name', '1.2.3')
            ->assertJsonPath('data.build_number', '42')
            ->assertJsonPath('data.file_size_bytes', 150 * 1024 * 1024)
            ->assertJsonPath('data.size_human', '150 MB');
    }

    // -----------------------------------------------------------------------
    // Public download
    // -----------------------------------------------------------------------

    public function test_public_download_returns_404_when_no_live_release(): void
    {
        $response = $this->get('/android/download');
        $response->assertNotFound();
    }

    public function test_public_download_streams_apk_with_correct_headers(): void
    {
        $this->fakeLocalDisk();

        $apkContent = str_repeat('A', 1024);
        Storage::disk('public')->put('apk/sayzio-test.apk', $apkContent);

        AndroidApkRelease::create([
            'version_name'    => '1.0.0',
            'file_size_bytes' => strlen($apkContent),
            'disk'            => 'public',
            'path'            => 'apk/sayzio-test.apk',
            'is_live'         => true,
        ]);

        $response = $this->get('/android/download');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.android.package-archive',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition') ?? ''
        );
        $this->assertStringContainsString(
            'sayzio.apk',
            $response->headers->get('Content-Disposition') ?? ''
        );
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
    }

    public function test_public_download_honours_range_header(): void
    {
        $this->fakeLocalDisk();

        $apkContent = str_repeat('B', 2048);
        Storage::disk('public')->put('apk/sayzio-range.apk', $apkContent);

        AndroidApkRelease::create([
            'version_name'    => '2.0.0',
            'file_size_bytes' => strlen($apkContent),
            'disk'            => 'public',
            'path'            => 'apk/sayzio-range.apk',
            'is_live'         => true,
        ]);

        $response = $this->get('/android/download', ['Range' => 'bytes=0-511']);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('512', $response->headers->get('Content-Length'));
        $this->assertStringContainsString('bytes 0-511/2048', $response->headers->get('Content-Range') ?? '');
    }

    // -----------------------------------------------------------------------
    // Admin index
    // -----------------------------------------------------------------------

    public function test_admin_index_requires_admin_auth(): void
    {
        $this->get('/admin/android-apk')->assertRedirect();
    }

    public function test_admin_index_requires_settings_manage(): void
    {
        $admin = $this->makeAdminWithPermission('users.view');
        $this->actingAs($admin, 'admin')->get('/admin/android-apk')->assertForbidden();
    }

    public function test_admin_index_renders_for_admin(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');

        $this->actingAs($admin, 'admin')->get('/admin/android-apk')
            ->assertOk()
            ->assertSee('Android APK', false);
    }

    public function test_admin_index_shows_release_history(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdminWithPermission('settings.manage');

        AndroidApkRelease::create([
            'version_name'    => '3.1.4',
            'file_size_bytes' => 50 * 1024 * 1024,
            'disk'            => 'public',
            'path'            => 'apk/dummy.apk',
            'is_live'         => true,
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/android-apk')
            ->assertOk()
            ->assertSee('3.1.4');
    }

    // -----------------------------------------------------------------------
    // Admin upload
    // -----------------------------------------------------------------------

    public function test_admin_upload_stores_release(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdminWithPermission('settings.manage');
        $file  = UploadedFile::fake()->create('sayzio.apk', 10 * 1024, 'application/vnd.android.package-archive');

        $this->actingAs($admin, 'admin')->post('/admin/android-apk/upload', [
            'apk_file'     => $file,
            'version_name' => '1.0.0',
            'build_number' => '7',
        ])->assertRedirect(route('admin.android-apk.index'));

        $this->assertDatabaseHas('android_apk_releases', [
            'version_name' => '1.0.0',
            'build_number' => '7',
            'is_live'      => false,
        ]);
    }

    public function test_admin_upload_with_set_live_marks_release_live(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdminWithPermission('settings.manage');
        $file  = UploadedFile::fake()->create('sayzio.apk', 10 * 1024, 'application/vnd.android.package-archive');

        $this->actingAs($admin, 'admin')->post('/admin/android-apk/upload', [
            'apk_file'     => $file,
            'version_name' => '2.0.0',
            'set_live'     => '1',
        ]);

        $this->assertDatabaseHas('android_apk_releases', [
            'version_name' => '2.0.0',
            'is_live'      => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // Admin set-live
    // -----------------------------------------------------------------------

    public function test_admin_set_live_swaps_live_flag(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdminWithPermission('settings.manage');

        $old = AndroidApkRelease::create([
            'version_name' => '1.0.0', 'file_size_bytes' => 100,
            'disk' => 'public', 'path' => 'apk/old.apk', 'is_live' => true,
        ]);
        $new = AndroidApkRelease::create([
            'version_name' => '2.0.0', 'file_size_bytes' => 100,
            'disk' => 'public', 'path' => 'apk/new.apk', 'is_live' => false,
        ]);

        $this->actingAs($admin, 'admin')->put("/admin/android-apk/{$new->id}/set-live")
            ->assertRedirect(route('admin.android-apk.index'));

        $this->assertFalse($old->fresh()->is_live);
        $this->assertTrue($new->fresh()->is_live);
    }

    // -----------------------------------------------------------------------
    // Admin delete
    // -----------------------------------------------------------------------

    public function test_admin_delete_removes_non_live_release(): void
    {
        $this->fakeLocalDisk();
        Storage::disk('public')->put('apk/deleteme.apk', 'content');
        $admin = $this->makeAdminWithPermission('settings.manage');

        $release = AndroidApkRelease::create([
            'version_name' => '0.9.0', 'file_size_bytes' => 7,
            'disk' => 'public', 'path' => 'apk/deleteme.apk', 'is_live' => false,
        ]);

        $this->actingAs($admin, 'admin')->delete("/admin/android-apk/{$release->id}")
            ->assertRedirect(route('admin.android-apk.index'));

        $this->assertDatabaseMissing('android_apk_releases', ['id' => $release->id]);
    }

    public function test_admin_delete_refuses_live_release(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdminWithPermission('settings.manage');

        $release = AndroidApkRelease::create([
            'version_name' => '1.1.0', 'file_size_bytes' => 100,
            'disk' => 'public', 'path' => 'apk/live.apk', 'is_live' => true,
        ]);

        $this->actingAs($admin, 'admin')->delete("/admin/android-apk/{$release->id}")
            ->assertRedirect(route('admin.android-apk.index'));

        $this->assertDatabaseHas('android_apk_releases', ['id' => $release->id]);
    }
}
