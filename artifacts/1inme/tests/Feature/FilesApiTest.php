<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5956 — Sanctum vault endpoints backing the mobile photo-sticker
 * add flow: GET /api/v1/me/files (picker list) + POST /api/v1/me/files/upload
 * (device upload). The upload must yield a UserFile the block sanitizer's
 * sanitizePhotoStickers ownership check accepts (owned, type=image,
 * not flagged).
 *
 * NOTE: auth uses a real Bearer token, NOT Sanctum::actingAs — the latter
 * skips the TouchSessionToken middleware the API path relies on.
 */
class FilesApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Files ' . Str::random(4),
            'email'    => 'files-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        return $user->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_upload_stores_image_file_owned_by_caller(): void
    {
        Storage::fake('user_files');
        $user = $this->makeUser();

        $res = $this->withToken($this->token($user))->post('/api/v1/me/files/upload', [
            'file' => UploadedFile::fake()->image('sticker.png', 300, 300),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);
        $data = $res->json('data.file');
        $this->assertIsArray($data);
        $this->assertSame('image', $data['type']);
        $this->assertNotEmpty($data['url_path']);

        $file = UserFile::find($data['id']);
        $this->assertNotNull($file);
        $this->assertSame((int) $user->id, (int) $file->user_id);
        $this->assertSame('image', $file->type);
    }

    public function test_upload_rejects_disallowed_file_type(): void
    {
        Storage::fake('user_files');
        $user = $this->makeUser();

        $res = $this->withToken($this->token($user))->post('/api/v1/me/files/upload', [
            'file' => UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422);
        $this->assertNotEmpty($res->json('error.message'));
    }

    public function test_upload_over_storage_quota_returns_plan_gate_envelope(): void
    {
        Storage::fake('user_files');
        $user = $this->makeUser();

        // Fill the caller's storage to the plan cap so the next upload
        // trips the quota check in UserFile::createFromUpload.
        UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'huge.bin',
            'filename'      => 'huge.bin',
            'mime_type'     => 'application/octet-stream',
            'size_bytes'    => $user->getStorageLimitBytes(),
            'type'          => 'document',
            'disk'          => 'user_files',
            'path'          => $user->id . '/documents/huge.bin',
        ]);

        $res = $this->withToken($this->token($user))->post('/api/v1/me/files/upload', [
            'file' => UploadedFile::fake()->image('sticker.png', 300, 300),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(402);
        $this->assertSame('plan_limit_reached', $res->json('error.code'));
        $this->assertStringContainsString('quota', strtolower((string) $res->json('error.message')));
        $this->assertSame('storage_limit_mb', $res->json('error.details.feature'));
    }

    public function test_index_lists_only_own_files_with_type_filter(): void
    {
        Storage::fake('user_files');
        $user  = $this->makeUser();
        $other = $this->makeUser();

        $token = $this->token($user);
        $this->withToken($token)->post('/api/v1/me/files/upload', [
            'file' => UploadedFile::fake()->image('mine.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->withToken($this->token($other))->post('/api/v1/me/files/upload', [
            'file' => UploadedFile::fake()->image('theirs.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        // flush() prevents withToken's default-header leak between callers.
        $this->flushHeaders();

        $res = $this->withToken($token)->getJson('/api/v1/me/files?type=image');
        $res->assertOk();
        $files = $res->json('data.files');
        $this->assertCount(1, $files);
        $this->assertSame('mine.png', $files[0]['original_name']);
        $this->assertSame('image', $files[0]['type']);
    }

    public function test_index_supports_name_search_and_pagination(): void
    {
        Storage::fake('user_files');
        $user  = $this->makeUser();
        $token = $this->token($user);

        foreach (['holiday-banner.png', 'logo-dark.png', 'logo-light.png'] as $name) {
            $this->withToken($token)->post('/api/v1/me/files/upload', [
                'file' => UploadedFile::fake()->image($name),
            ], ['Accept' => 'application/json'])->assertStatus(201);
        }
        $this->flushHeaders();

        // Case-insensitive name search matches only the two logo files.
        $res = $this->withToken($token)->getJson('/api/v1/me/files?type=image&q=LOGO');
        $res->assertOk();
        $names = collect($res->json('data.files'))->pluck('original_name')->sort()->values()->all();
        $this->assertSame(['logo-dark.png', 'logo-light.png'], $names);

        // SQL wildcard characters in q are treated literally, not as wildcards.
        $res = $this->withToken($token)->getJson('/api/v1/me/files?q=' . urlencode('%'));
        $res->assertOk();
        $this->assertCount(0, $res->json('data.files'));

        // per_page + page paginate the search results.
        $res = $this->withToken($token)->getJson('/api/v1/me/files?q=logo&per_page=1&page=2');
        $res->assertOk();
        $this->assertCount(1, $res->json('data.files'));
        $this->assertSame(2, $res->json('data.pagination.current_page'));
        $this->assertSame(2, $res->json('data.pagination.last_page'));
        $this->assertSame(2, $res->json('data.pagination.total'));
    }

    public function test_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/me/files')->assertStatus(401);
        $this->postJson('/api/v1/me/files/upload')->assertStatus(401);
    }
}
