<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CardBrochureExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the "Re-scan with a new focus" flow: the review screen can re-run
 * extraction on the SAME vaulted source files with a different instruction,
 * without a re-upload, producing a fresh CardScan that leaves the original
 * intact.
 */
class CardScanRescanTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'     => 'Scan ' . Str::random(4),
            'slug'     => 'scan-' . Str::lower(Str::random(8)),
            'status'   => true,
            'features' => ['card_scan' => true],
        ]);
        return User::factory()->create(['plan_id' => $plan->id]);
    }

    private function makeImageFile(User $user): UserFile
    {
        $file = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'card.jpg',
            'filename'      => 'card-' . Str::random(8) . '.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 12345,
            'type'          => 'image',
            'disk'          => 'public',
            'path'          => 'test/card.jpg',
        ]);
        // workspace_id is not mass-assignable; stamp it to the user's personal
        // workspace so the workspace-scoped read in the request can see it.
        $file->forceFill(['workspace_id' => $user->ownedWorkspaces()->value('id')])->save();
        return $file;
    }

    private function completedScan(User $user, array $fileIds): CardScan
    {
        return CardScan::create([
            'user_id'         => $user->id,
            'actor_user_id'   => $user->id,
            'source_file_id'  => $fileIds[0] ?? null,
            'source_file_ids' => $fileIds,
            'status'          => 'completed',
            'idempotency_key' => 'card_scan:' . Str::random(16),
            'credits_spent'   => 3,
            'extracted'       => ['kind' => 'card', 'full_name' => 'Jane Smith'],
        ]);
    }

    private function enableEngine(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-key');
    }

    public function test_rescan_reuses_vaulted_files_with_new_instruction(): void
    {
        $this->enableEngine();
        $user  = $this->makeUser();
        $file  = $this->makeImageFile($user);
        $scan  = $this->completedScan($user, [$file->id]);
        $fresh = $this->completedScan($user, [$file->id]);

        $captured = null;
        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($fresh, &$captured) {
                $m->shouldReceive('extractFromVaultedFiles')
                    ->once()
                    ->withArgs(function ($owner, $actor, $files, $instruction) use (&$captured) {
                        $captured = ['files' => $files, 'instruction' => $instruction];
                        return true;
                    })
                    ->andReturn($fresh);
            })
        );

        $this->actingAs($user, 'web')
            ->post("/user/contacts/scan/{$scan->id}/rescan", [
                'instruction' => 'also grab the brand colors',
            ])
            ->assertRedirect("/user/contacts/scan/{$fresh->id}?from=contacts");

        $this->assertNotNull($captured);
        $this->assertCount(1, $captured['files']);
        $this->assertSame($file->id, $captured['files'][0]->id);
        $this->assertSame('also grab the brand colors', $captured['instruction']);
    }

    public function test_rescan_rejects_scans_owned_by_others(): void
    {
        $this->enableEngine();
        $owner   = $this->makeUser();
        $file    = $this->makeImageFile($owner);
        $scan    = $this->completedScan($owner, [$file->id]);
        $stranger = $this->makeUser();

        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) {
                $m->shouldReceive('extractFromVaultedFiles')->never();
            })
        );

        $this->actingAs($stranger, 'web')
            ->post("/user/contacts/scan/{$scan->id}/rescan", ['instruction' => 'x'])
            ->assertForbidden();
    }

    // ── mobile (Sanctum) API parity ──────────────────────────────────

    public function test_api_rescan_reuses_vaulted_files_with_new_instruction(): void
    {
        $this->enableEngine();
        $user  = $this->makeUser();
        $file  = $this->makeImageFile($user);
        $scan  = $this->completedScan($user, [$file->id]);
        $fresh = $this->completedScan($user, [$file->id]);

        $captured = null;
        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($fresh, &$captured) {
                $m->shouldReceive('extractFromVaultedFiles')
                    ->once()
                    ->withArgs(function ($owner, $actor, $files, $instruction) use (&$captured) {
                        $captured = ['files' => $files, 'instruction' => $instruction];
                        return true;
                    })
                    ->andReturn($fresh);
            })
        );

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->post("/api/v1/card-scans/{$scan->id}/rescan", [
            'instruction' => '  also grab the brand colors  ',
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.scan.id', $fresh->id);

        $this->assertNotNull($captured);
        $this->assertCount(1, $captured['files']);
        $this->assertSame($file->id, $captured['files'][0]->id);
        $this->assertSame('also grab the brand colors', $captured['instruction']);
    }

    public function test_api_rescan_collapses_whitespace_instruction_to_null(): void
    {
        $this->enableEngine();
        $user  = $this->makeUser();
        $file  = $this->makeImageFile($user);
        $scan  = $this->completedScan($user, [$file->id]);
        $fresh = $this->completedScan($user, [$file->id]);

        $captured = 'UNSET';
        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($fresh, &$captured) {
                $m->shouldReceive('extractFromVaultedFiles')
                    ->once()
                    ->withArgs(function ($owner, $actor, $files, $instruction) use (&$captured) {
                        $captured = $instruction;
                        return true;
                    })
                    ->andReturn($fresh);
            })
        );

        $this->withToken($user->createToken('test')->plainTextToken);
        $this->post("/api/v1/card-scans/{$scan->id}/rescan", [
            'instruction' => "  \n\t ",
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertNull($captured);
    }

    public function test_api_rescan_rejects_scans_owned_by_others(): void
    {
        $this->enableEngine();
        $owner    = $this->makeUser();
        $file     = $this->makeImageFile($owner);
        $scan     = $this->completedScan($owner, [$file->id]);
        $stranger = $this->makeUser();

        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) {
                $m->shouldReceive('extractFromVaultedFiles')->never();
            })
        );

        $this->withToken($stranger->createToken('test')->plainTextToken);
        $this->post("/api/v1/card-scans/{$scan->id}/rescan", [
            'instruction' => 'x',
        ], ['Accept' => 'application/json'])->assertStatus(404);
    }

    public function test_api_rescan_returns_new_scan_even_when_not_completed(): void
    {
        // A re-scan that produces a failed/incomplete new scan must still be
        // returned so the client can transition to it and remain retryable —
        // mirroring the web flow, which redirects to the new scan regardless.
        $this->enableEngine();
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);
        $scan = $this->completedScan($user, [$file->id]);

        $failed = CardScan::create([
            'user_id'         => $user->id,
            'actor_user_id'   => $user->id,
            'source_file_id'  => $file->id,
            'source_file_ids' => [$file->id],
            'status'          => 'failed',
            'extracted'       => null,
        ]);

        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($failed) {
                $m->shouldReceive('extractFromVaultedFiles')
                    ->once()
                    ->andReturn($failed);
            })
        );

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->post("/api/v1/card-scans/{$scan->id}/rescan", [
            'instruction' => 'try again',
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.scan.id', $failed->id);
        $resp->assertJsonPath('data.scan.status', 'failed');
    }

    public function test_api_rescan_rejects_overlong_instruction_before_service(): void
    {
        $this->enableEngine();
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);
        $scan = $this->completedScan($user, [$file->id]);

        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) {
                $m->shouldReceive('extractFromVaultedFiles')->never();
            })
        );

        $this->withToken($user->createToken('test')->plainTextToken);
        $this->post("/api/v1/card-scans/{$scan->id}/rescan", [
            'instruction' => str_repeat('x', CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH + 1),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
