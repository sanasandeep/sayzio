<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin Site Assistant mascot uploader
 * ({@see \App\Modules\Admin\Controllers\SiteAssistantController::resolveAvatarUrl()}
 * and {@see ...::pruneReplacedAvatar()}).
 *
 * The uploader is the only way an admin can rebrand the assistant face,
 * and it has three behaviours that are easy to break silently as the app
 * changes around it:
 *
 *   1. A genuine image is moved into public/branding and its relative
 *      path is persisted via SiteAssistantSettings::update().
 *   2. Clearing both the file and the URL reverts to the bundled mascot
 *      (avatar_url=null => avatarUrlFor() returns branding/zio-bot.png).
 *   3. Replacing or clearing a previously *managed* upload deletes the
 *      old file, while admin-pasted external URLs and the bundled
 *      default are left on disk.
 *   4. A spoofed (wrong-MIME) file is rejected, never landing an
 *      executable in the web-served public dir.
 */
class SiteAssistantAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    /** Managed avatar files this test creates, removed in tearDown. */
    private array $createdAvatarFiles = [];

    protected function tearDown(): void
    {
        // Only ever touch our own managed uploads — never the bundled
        // zio-bot.png or any other branding asset.
        foreach ($this->createdAvatarFiles as $path) {
            if (preg_match('#/branding/assistant-avatar-[^/]+$#', $path) && File::isFile($path)) {
                File::delete($path);
            }
        }
        $this->createdAvatarFiles = [];
        parent::tearDown();
    }

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Avatar Admin',
            'email'    => 'avatar' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /** Minimal valid payload for the settings update endpoint. */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'enabled_marketing' => '1',
            'enabled_app'       => '1',
            'launcher_position' => 'bottom-right',
            'accent_color'      => '#7c3aed',
        ], $overrides);
    }

    private function trackAvatar(?string $avatarUrl): void
    {
        if (is_string($avatarUrl) && str_starts_with($avatarUrl, '/branding/assistant-avatar-')) {
            $this->createdAvatarFiles[] = public_path(ltrim($avatarUrl, '/'));
        }
    }

    public function test_uploading_an_image_persists_it_under_public_branding(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_file' => UploadedFile::fake()->image('mascot.png', 96, 96),
            ]))
            ->assertRedirect(route('admin.site-assistant.edit'));

        $stored = SiteAssistantSettings::get()['avatar_url'] ?? null;
        $this->trackAvatar($stored);

        // The persisted value is a managed relative path...
        $this->assertIsString($stored);
        $this->assertMatchesRegularExpression(
            '#^/branding/assistant-avatar-[0-9a-f-]+\.png$#',
            $stored
        );
        // ...and the file actually landed on disk in the public dir.
        $this->assertFileExists(public_path(ltrim($stored, '/')));
    }

    public function test_clearing_file_and_url_reverts_to_bundled_mascot(): void
    {
        // Seed an existing external avatar so we have something to clear.
        SiteAssistantSettings::update(['avatar_url' => 'https://cdn.example.com/face.png']);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_url' => '',
            ]))
            ->assertRedirect(route('admin.site-assistant.edit'));

        $cfg = SiteAssistantSettings::get();
        $this->assertNull($cfg['avatar_url']);

        // Empty avatar_url => bundled Zio Bot mascot.
        $this->assertSame(
            asset(SiteAssistantSettings::DEFAULT_AVATAR_PATH),
            SiteAssistantSettings::avatarUrlFor($cfg)
        );
        $this->assertStringContainsString('branding/zio-bot.png', SiteAssistantSettings::avatarUrlFor($cfg));
    }

    public function test_replacing_a_managed_upload_deletes_the_old_file(): void
    {
        $admin = $this->makeAdmin();

        // First upload.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_file' => UploadedFile::fake()->image('first.png', 64, 64),
            ]))
            ->assertRedirect();
        $first = SiteAssistantSettings::get()['avatar_url'];
        $this->trackAvatar($first);
        $firstPath = public_path(ltrim($first, '/'));
        $this->assertFileExists($firstPath);

        // Second upload replaces the first.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_file' => UploadedFile::fake()->image('second.png', 64, 64),
            ]))
            ->assertRedirect();
        $second = SiteAssistantSettings::get()['avatar_url'];
        $this->trackAvatar($second);

        $this->assertNotSame($first, $second);
        // Old managed file is gone, new one is present.
        $this->assertFileDoesNotExist($firstPath);
        $this->assertFileExists(public_path(ltrim($second, '/')));
    }

    public function test_clearing_a_managed_upload_deletes_the_old_file(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_file' => UploadedFile::fake()->image('only.png', 64, 64),
            ]))
            ->assertRedirect();
        $managed = SiteAssistantSettings::get()['avatar_url'];
        $this->trackAvatar($managed);
        $managedPath = public_path(ltrim($managed, '/'));
        $this->assertFileExists($managedPath);

        // Clear both file and URL -> revert to bundled mascot, old file pruned.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_url' => '',
            ]))
            ->assertRedirect();

        $this->assertNull(SiteAssistantSettings::get()['avatar_url']);
        $this->assertFileDoesNotExist($managedPath);
    }

    public function test_external_url_and_bundled_default_files_are_left_untouched(): void
    {
        // The bundled mascot must survive any save that swaps the avatar.
        $bundled = public_path(SiteAssistantSettings::DEFAULT_AVATAR_PATH);
        $this->assertFileExists($bundled);

        // Previous value is an admin-pasted external URL: the prune logic
        // must not try to (and cannot) delete a remote file, and the save
        // proceeds cleanly to a managed upload.
        SiteAssistantSettings::update(['avatar_url' => 'https://cdn.example.com/face.png']);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                'avatar_file' => UploadedFile::fake()->image('swap.png', 64, 64),
            ]))
            ->assertRedirect();

        $this->trackAvatar(SiteAssistantSettings::get()['avatar_url']);

        // Bundled default never gets pruned even when it was the prior value.
        $this->assertFileExists($bundled);
    }

    public function test_spoofed_file_is_rejected_and_not_written(): void
    {
        $before = glob(public_path('branding/assistant-avatar-*')) ?: [];

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.site-assistant.update'), $this->basePayload([
                // A PHP payload masquerading via mime — must fail validation
                // before it can be moved into the web-served public dir.
                'avatar_file' => UploadedFile::fake()->create('avatar.php', 16, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('avatar_file');

        // Avatar config untouched and nothing landed in public/branding.
        $this->assertNull(SiteAssistantSettings::get()['avatar_url']);
        $after = glob(public_path('branding/assistant-avatar-*')) ?: [];
        $this->assertSame($before, $after);
    }
}
