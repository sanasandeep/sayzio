<?php

namespace Tests\Feature;

use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6247: the REST API create endpoint (POST /api/v1/links) must fully
 * provision `type='file'` links so the public short URL actually serves the
 * file. Historically the API path created only the Link row, and
 * RedirectController::handleFileDownload 404s when the companion FileLink row
 * is missing — so API-created file links were dead on arrival.
 *
 * The desktop Zio Browser flow uploads to the Files vault first
 * (POST /api/v1/me/files/upload) then creates the link with
 * settings.file.id referencing the vault file.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware.
 */
class ApiFileLinkCreateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeVaultFile(User $user): UserFile
    {
        return UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'report.pdf',
            'filename'      => 'abc123.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 12345,
            'type'          => 'document',
            'context'       => 'files',
            'disk'          => 'public',
            'path'          => 'user-files/' . $user->id . '/abc123.pdf',
        ]);
    }

    public function test_file_link_create_provisions_file_link_row(): void
    {
        $user = $this->makeUser();
        $file = $this->makeVaultFile($user);

        $res = $this->withToken($this->token($user))->postJson('/api/v1/links', [
            'type'     => 'file',
            'settings' => ['file' => ['id' => $file->id]],
        ]);

        $res->assertStatus(201);
        $linkId = $res->json('data.link.id');
        $this->assertNotNull($linkId);

        $link = Link::withoutGlobalScopes()->findOrFail($linkId);
        $this->assertSame('file', $link->type);
        // Title falls back to the vault file's original name.
        $this->assertSame('report.pdf', $link->title);

        $fileLink = FileLink::where('link_id', $linkId)->first();
        $this->assertNotNull($fileLink, 'Companion FileLink row must exist so the public download route works');
        $this->assertSame('report.pdf', $fileLink->original_name);
        $this->assertSame($file->path, $fileLink->stored_path);
        $this->assertSame('application/pdf', $fileLink->mime_type);
        $this->assertSame(12345, (int) $fileLink->file_size);
    }

    public function test_file_link_create_rejects_missing_file_reference(): void
    {
        $user = $this->makeUser();

        $res = $this->withToken($this->token($user))->postJson('/api/v1/links', [
            'type' => 'file',
        ]);

        $res->assertStatus(422);
        // No dangling link left behind.
        $this->assertSame(0, Link::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_file_link_create_rejects_foreign_file(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();
        $file     = $this->makeVaultFile($owner);

        $res = $this->withToken($this->token($attacker))->postJson('/api/v1/links', [
            'type'     => 'file',
            'settings' => ['file' => ['id' => $file->id]],
        ]);

        $res->assertStatus(422);
        $this->assertSame('file_not_found', $res->json('error.code'));
        $this->assertSame(0, Link::withoutGlobalScopes()->where('user_id', $attacker->id)->count());
    }
}
