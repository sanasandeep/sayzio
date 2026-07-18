<?php

namespace Tests\Feature\AI;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CardBrochureExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security coverage for the file-count and file-size bounds on the
 * "Scan a card / brochure" upload (Task #4477).
 *
 * The scanner enforces MAX_UPLOADS distinct files and MAX_UPLOAD_MB per
 * file to stop an authenticated user (web form or mobile API) from
 * flooding the vision pipeline with too-many or oversized images to run
 * up AI cost / storage. Task #4471 pinned the instruction-length cap; this
 * pins the upload bounds.
 *
 * The guarantees we pin here:
 *
 *   1. The web controller (App\Modules\User\Controllers\CardScanController)
 *      and the API controller (App\Modules\Api\Controllers\CardScanController)
 *      both reject more than MAX_UPLOADS files at the request-validation
 *      layer, before the extraction service is ever invoked.
 *   2. Both reject a single file larger than MAX_UPLOAD_MB, again before the
 *      extraction service runs.
 *   3. The web surface fails with a validation error (redirect + session
 *      errors) and the API surface fails with HTTP 422.
 *
 * In every case the extraction service is mocked with
 * shouldReceive('extract')->never() so a rejected request can never reach
 * the vault / rasterise / vision call — the expensive, cost-bearing work.
 */
class CardScanUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────

    private function plan(array $features = ['card_scan' => true]): Plan
    {
        $slug = 'p' . Str::lower(Str::random(8));
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => ($plan ?? $this->plan())->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function enableEngine(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-key');
    }

    /**
     * Bind a CardBrochureExtractionService mock that fails the test the
     * moment extract() is called — the whole point is that validation
     * short-circuits the request before we ever reach the vision pipeline.
     */
    private function neverExtract(): void
    {
        $this->instance(
            CardBrochureExtractionService::class,
            \Mockery::mock(CardBrochureExtractionService::class, function ($m) {
                $m->shouldReceive('extract')->never();
            })
        );
    }

    /** MAX_UPLOADS + 1 tiny fake images, sent under the `files[]` field. */
    private function tooManyFiles(): array
    {
        $files = [];
        for ($i = 0; $i <= CardBrochureExtractionService::MAX_UPLOADS; $i++) {
            $files[] = UploadedFile::fake()->image("card{$i}.jpg");
        }
        return $files;
    }

    /** A single fake file one KB over the MAX_UPLOAD_MB size cap. */
    private function oversizedFile(): UploadedFile
    {
        // Validation rule is `max:MAX_UPLOAD_MB * 1024` (Laravel counts KB),
        // so one KB past that must be rejected.
        $kb = CardBrochureExtractionService::MAX_UPLOAD_MB * 1024 + 1;
        return UploadedFile::fake()->create('big.jpg', $kb, 'image/jpeg');
    }

    // ── web surface ──────────────────────────────────────────────────

    public function test_web_store_rejects_too_many_files_before_service(): void
    {
        $this->enableEngine();
        $user = $this->user();
        $this->neverExtract();

        $resp = $this->actingAs($user)->post(route('user.contacts.scan.store'), [
            'files' => $this->tooManyFiles(),
        ]);

        $resp->assertSessionHasErrors('files');
    }

    public function test_web_store_rejects_oversized_file_before_service(): void
    {
        $this->enableEngine();
        $user = $this->user();
        $this->neverExtract();

        $resp = $this->actingAs($user)->post(route('user.contacts.scan.store'), [
            'file' => $this->oversizedFile(),
        ]);

        $resp->assertSessionHasErrors('file');
    }

    // ── mobile API surface ───────────────────────────────────────────

    public function test_api_store_rejects_too_many_files_before_service(): void
    {
        $this->enableEngine();
        $user = $this->user();
        $this->neverExtract();

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->post('/api/v1/card-scans', [
            'files' => $this->tooManyFiles(),
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(422);
    }

    public function test_api_store_rejects_oversized_file_before_service(): void
    {
        $this->enableEngine();
        $user = $this->user();
        $this->neverExtract();

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->post('/api/v1/card-scans', [
            'file' => $this->oversizedFile(),
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(422);
    }
}
