<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminAsset;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Access control on the public admin-asset delivery route
 * (`GET /admin-assets/{id}/{filename}`, AdminAssetController::serve()).
 *
 *   - Public assets (is_public = true) are reachable by anyone.
 *   - Private assets (is_public = false) are admin-only. A guest or a
 *     plain logged-in front-end user must NOT be able to fetch them, and
 *     the response is a 404 so the asset's existence is never disclosed.
 *   - An authenticated admin (admin guard) CAN fetch a private asset.
 */
class AdminAssetServeAuthTest extends TestCase
{
    use RefreshDatabase;

    private function diskName(): string
    {
        return AdminAsset::diskName();
    }

    /**
     * Fake the admin-assets disk as a LOCAL driver. Storage::fake() swaps
     * the disk instance but leaves the config driver untouched, so we also
     * pin the driver to `local` — otherwise serve() takes its S3-redirect
     * branch (302) instead of streaming the file (200).
     */
    private function fakeLocalDisk(): void
    {
        $disk = $this->diskName();
        Storage::fake($disk);
        config(["filesystems.disks.{$disk}.driver" => 'local']);
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

    private function makeAsset(bool $isPublic): AdminAsset
    {
        $filename = uniqid('asset_') . '.txt';
        return AdminAsset::create([
            'original_name' => 'secret.txt',
            'filename'      => $filename,
            'mime_type'     => 'text/plain',
            'size_bytes'    => 12,
            'type'          => 'document',
            'disk'          => $this->diskName(),
            'path'          => 'assets/' . $filename,
            'is_public'     => $isPublic,
        ]);
    }

    public function test_public_asset_is_reachable_by_guest(): void
    {
        $this->fakeLocalDisk();
        $asset = $this->makeAsset(true);
        Storage::disk($this->diskName())->put($asset->path, 'hello world!');

        $this->get($asset->url_path)->assertOk();
    }

    public function test_private_asset_is_hidden_from_guest(): void
    {
        $asset = $this->makeAsset(false);

        $this->get($asset->url_path)->assertNotFound();
    }

    public function test_private_asset_is_hidden_from_non_admin_user(): void
    {
        $asset = $this->makeAsset(false);
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get($asset->url_path)
            ->assertNotFound();
    }

    public function test_private_asset_is_served_to_admin(): void
    {
        $this->fakeLocalDisk();
        $asset = $this->makeAsset(false);
        Storage::disk($this->diskName())->put($asset->path, 'hello world!');

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get($asset->url_path)
            ->assertOk();
    }

    /* --------------------------------------------------------------------
     * `/{alias}/download` (RedirectController::rawFileDownload()) must apply
     * the SAME visibility gate as `/{alias}`, so a visitor cannot bypass the
     * registered/followers/subscribers tiers by hitting /download directly.
     * ------------------------------------------------------------------ */

    private function makeUser(): User
    {
        $u = User::create([
            'name'     => 'u' . uniqid(),
            'email'    => 'u' . uniqid() . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeFileLink(User $owner, string $visibility): Link
    {
        $link = $owner->links()->create([
            'user_id'    => $owner->id,
            'type'       => 'file',
            'alias'      => 'f' . substr(uniqid(), -8),
            'is_active'  => true,
            'visibility' => $visibility,
        ]);
        FileLink::create([
            'link_id'       => $link->id,
            'original_name' => 'doc.txt',
            'stored_path'   => 'files/doc.txt',
            'mime_type'     => 'text/plain',
            'file_size'     => 12,
            'disk'          => 'user_files',
        ]);
        Storage::fake('user_files');
        Storage::disk('user_files')->put('files/doc.txt', 'hello world!');
        return $link;
    }

    /**
     * Public routes carry no bound workspace, so drop any workspace instance
     * leaked by the setup helpers before simulating a real visitor request —
     * otherwise Link's workspace global scope wrongly 404s the owner's link.
     */
    private function asVisitor(): void
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    public function test_registered_file_download_is_gated_for_guest(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeFileLink($owner, 'registered');

        $this->asVisitor();
        $this->get('/' . $link->alias . '/download')->assertStatus(401);
    }

    public function test_followers_file_download_is_gated_for_guest(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeFileLink($owner, 'followers');

        $this->asVisitor();
        $this->get('/' . $link->alias . '/download')->assertStatus(401);
    }

    public function test_subscribers_file_download_is_gated_for_guest(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeFileLink($owner, 'subscribers');

        $this->asVisitor();
        $this->get('/' . $link->alias . '/download')->assertStatus(401);
    }

    public function test_public_file_download_is_reachable_by_guest(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeFileLink($owner, 'public');

        $this->asVisitor();
        $this->get('/' . $link->alias . '/download')->assertOk();
    }

    public function test_followers_file_download_is_served_to_a_follower(): void
    {
        $owner    = $this->makeUser();
        $link     = $this->makeFileLink($owner, 'followers');
        $follower = $this->makeUser();
        Follow::create(['follower_id' => $follower->id, 'creator_id' => $owner->id]);

        $this->asVisitor();
        $this->actingAs($follower, 'web')
            ->get('/' . $link->alias . '/download')
            ->assertOk();
    }
}
