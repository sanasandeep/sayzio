<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminAsset;
use App\Modules\Admin\Models\AdminAssetFolder;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Image dimensions on Asset Vault assets and the multi-select bulk-edit
 * endpoint (POST /admin/assets/bulk-update).
 */
class AdminAssetDimensionsBulkTest extends TestCase
{
    use RefreshDatabase;

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

    private function fakeLocalDisk(): void
    {
        $disk = AdminAsset::diskName();
        Storage::fake($disk);
        config(["filesystems.disks.{$disk}.driver" => 'local']);
    }

    private function makeAsset(array $attrs = []): AdminAsset
    {
        $filename = uniqid('asset_') . '.png';
        return AdminAsset::create(array_merge([
            'original_name' => 'pic.png',
            'filename'      => $filename,
            'mime_type'     => 'image/png',
            'size_bytes'    => 100,
            'type'          => 'image',
            'disk'          => AdminAsset::diskName(),
            'path'          => 'assets/' . $filename,
            'is_public'     => true,
        ], $attrs));
    }

    public function test_probe_reads_raster_and_svg_dimensions(): void
    {
        $img = imagecreatetruecolor(240, 135);
        $tmp = tempnam(sys_get_temp_dir(), 't_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);
        $this->assertSame([240, 135], AdminAsset::probeImageDimensions($tmp));
        @unlink($tmp);

        $svg = tempnam(sys_get_temp_dir(), 't_') . '.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"></svg>');
        $this->assertSame([300, 150], AdminAsset::probeImageDimensions($svg));
        @unlink($svg);
    }

    public function test_upload_records_image_dimensions(): void
    {
        $this->fakeLocalDisk();
        $admin = $this->makeAdmin();

        $file = UploadedFile::fake()->image('photo.png', 320, 200);
        $response = $this->be($admin, 'admin')->postJson('/admin/assets', ['file' => $file]);
        $response->assertOk()->assertJsonPath('success', true);

        $asset = AdminAsset::latest('id')->first();
        $this->assertSame(320, $asset->width);
        $this->assertSame(200, $asset->height);
        $this->assertSame('320×200', $asset->dimensions);
    }

    public function test_bulk_update_applies_only_flagged_fields(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeAsset(['label' => 'keep-a', 'description' => 'desc-a']);
        $b = $this->makeAsset(['label' => 'keep-b', 'description' => 'desc-b']);
        $untouched = $this->makeAsset(['label' => 'other']);

        $response = $this->be($admin, 'admin')->postJson('/admin/assets/bulk-update', [
            'ids'          => [$a->id, $b->id],
            'apply_label'  => 1,
            'label'        => 'Hero art',
            'apply_folder' => 1,
            'folder'       => 'Brand Kit',
        ]);
        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('updated', 2);

        $a->refresh();
        $b->refresh();
        $this->assertSame('Hero art', $a->label);
        $this->assertSame('Hero art', $b->label);
        $this->assertSame('brand-kit', $a->folder);
        $this->assertSame('brand-kit', $b->folder);
        // Description was not flagged, so it must be untouched.
        $this->assertSame('desc-a', $a->description);
        $this->assertSame('desc-b', $b->description);
        // Unselected asset stays as-is.
        $this->assertSame('other', $untouched->fresh()->label);
        // The target folder is auto-registered.
        $this->assertNotNull(AdminAssetFolder::where('slug', 'brand-kit')->first());
    }

    public function test_bulk_update_requires_a_flagged_field(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeAsset();

        $this->be($admin, 'admin')
            ->postJson('/admin/assets/bulk-update', ['ids' => [$a->id], 'label' => 'ignored'])
            ->assertStatus(422);
    }

    public function test_bulk_update_can_clear_folder_to_unfiled(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeAsset(['folder' => 'somewhere']);

        $this->be($admin, 'admin')
            ->postJson('/admin/assets/bulk-update', [
                'ids'          => [$a->id],
                'apply_folder' => 1,
                'folder'       => '',
            ])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertNull($a->fresh()->folder);
    }
}
