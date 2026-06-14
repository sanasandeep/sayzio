<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the unified upload-error contract introduced for File Share and
 * extended to every other upload-accepting link controller (short-link
 * favicon/SEO images, biolink page assets).
 *
 * The contract: when a quota / per-plan size check fails, an automation
 * client (Accept: application/json) gets a structured {error: {message}}
 * envelope with an appropriate HTTP status, while a browser form post keeps
 * the redirect-back-with-flash behaviour so the inline error still renders.
 */
class UploadErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function biolink(User $u): Link
    {
        return $u->links()->create([
            'user_id' => $u->id, 'type' => 'biolink',
            'alias'   => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    private function shortLink(User $u): Link
    {
        return $u->links()->create([
            'user_id'  => $u->id, 'type' => 'url',
            'alias'    => 'sl' . substr(Str::random(8), 0, 8),
            'long_url' => 'https://example.com',
            'is_active' => true,
        ]);
    }

    // ===== Short-link store: SEO image upload =====

    public function test_short_link_store_returns_json_error_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        // storage_limit_mb=0 -> zero quota, so any upload trips the quota guard
        // inside UserFile::createFromUpload (after validation passes).
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));

        $resp = $this->actingAs($u)->postJson('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'seo_image' => UploadedFile::fake()->image('share.png', 600, 400),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('Storage quota', $resp->json('error.message'));
        $this->assertSame(0, $u->links()->count());
    }

    public function test_short_link_store_redirects_with_flash_for_browser_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));

        // Browser form post (no Accept: application/json) -> redirect + flash.
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'seo_image' => UploadedFile::fake()->image('share.png', 600, 400),
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertSame(0, $u->links()->count());
    }

    public function test_short_link_store_returns_json_error_on_plan_size_gate(): void
    {
        Storage::fake('user_files');
        // Generous storage, but a 1 MB per-file plan cap. A ~1.5 MB seo_image
        // passes the 2 MB UploadPolicy validation rule, then trips the
        // per-plan size guard inside createFromUpload.
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 1, 'storage_limit_mb' => 100,
        ]));

        $resp = $this->actingAs($u)->postJson('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'seo_image' => UploadedFile::fake()->create('share.png', 1500, 'image/png'),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('maximum size', $resp->json('error.message'));
        $this->assertSame(0, $u->links()->count());
    }

    // ===== Short-link update: favicon upload =====

    public function test_short_link_update_returns_json_error_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));
        $link = $this->shortLink($u);

        $resp = $this->actingAs($u)->putJson('/user/links/' . $link->id, [
            'long_url' => 'https://example.com',
            'favicon'  => UploadedFile::fake()->image('fav.png', 64, 64),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('Storage quota', $resp->json('error.message'));
    }

    // ===== Biolink page-settings: OG image upload =====

    public function test_biolink_page_settings_returns_json_error_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->postJson('/user/links/' . $link->id . '/page-settings', [
            'og_image_upload' => UploadedFile::fake()->image('og.png', 600, 400),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('Storage quota', $resp->json('error.message'));
    }

    public function test_biolink_page_settings_redirects_with_flash_for_browser_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'og_image_upload' => UploadedFile::fake()->image('og.png', 600, 400),
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    // ===== Vault upload API (UserFileController) =====

    public function test_vault_upload_returns_json_error_on_quota_exceeded(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_files' => 100, 'max_file_size_mb' => 10, 'storage_limit_mb' => 0,
        ]));

        // The vault dropzone is an AJAX-only JSON endpoint; it must speak the
        // same {error:{message}} envelope as the link controllers.
        $resp = $this->actingAs($u)->postJson('/user/files/upload', [
            'file' => UploadedFile::fake()->image('pic.png', 600, 400),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('Storage quota', $resp->json('error.message'));
    }

    public function test_vault_upload_returns_json_error_on_plan_size_gate(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan([
            'max_files' => 100, 'max_file_size_mb' => 1, 'storage_limit_mb' => 100,
        ]));

        // ~1.5 MB upload against a 1 MB per-file plan cap -> size guard inside
        // createFromUpload (upload() only validates required|file, no max).
        $resp = $this->actingAs($u)->postJson('/user/files/upload', [
            'file' => UploadedFile::fake()->create('big.png', 1500, 'image/png'),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['error' => ['message']]);
        $this->assertStringContainsString('maximum size', $resp->json('error.message'));
    }
}
