<?php

namespace Tests\Feature\AI;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\CardBrochureExtractionService;
use App\Services\AI\OpenAiService;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the refund-on-failure safety net of the
 * "Scan a card / brochure" feature (Task #4476).
 *
 * The user-visible guarantee: if the AI produces unusable output mid
 * extraction (after OpenAI was already called and the coin wallet was
 * already charged), the scan is marked failed AND the exact spend is
 * refunded, so the user is never billed for a scan that produced
 * nothing.
 *
 * Strategy mirrors AiCreditMeteringTest: enable the real engine, fake
 * the OpenAI HTTP layer, and drive the real service so the charge flows
 * through OpenAiService -> AiUsageCharger -> wallet exactly as in prod.
 * We assert on the *wallet balance* returning to its pre-scan state
 * (charge netted out by refund), plus the failed scan row, plus that a
 * real spend actually happened first (so the test can't pass trivially).
 *
 * Two failure modes are covered:
 *   1. The model returns non-JSON — json_decode fails inside callVision
 *      AFTER the charge, so the outer catch must refund.
 *   2. The model returns valid JSON but normalise() throws — a downstream
 *      step failing after the charge must also refund.
 */
class CardScanRefundOnFailureTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_COINS = 10_000;

    protected function setUp(): void
    {
        parent::setUp();

        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

    private function plan(): Plan
    {
        $slug = 'p' . Str::lower(Str::random(8));
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['card_scan' => true],
        ]);
    }

    private function user(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $this->plan()->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        app(WalletService::class)->credit($u, self::SEED_COINS, ['reason' => 'test seed']);

        return $u;
    }

    /** Vault a small-but-real PNG so rasteriseToImages() has bytes to send. */
    private function vaultedImage(User $user): UserFile
    {
        Storage::fake('public');
        // 1x1 transparent PNG.
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $path = 'test/card-' . Str::random(8) . '.png';
        Storage::disk('public')->put($path, $bytes);

        $file = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'card.png',
            'filename'      => basename($path),
            'mime_type'     => 'image/png',
            'size_bytes'    => strlen($bytes),
            'type'          => 'image',
            'disk'          => 'public',
            'path'          => $path,
        ]);
        $file->forceFill(['workspace_id' => $user->ownedWorkspaces()->value('id')])->save();

        return $file;
    }

    /** A metered chat completion whose usage guarantees a non-zero coin charge. */
    private function fakeVisionResponse(string $content): array
    {
        return [
            'id'      => 'chatcmpl-fake-' . Str::random(8),
            'object'  => 'chat.completion',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            // 5000 in @ 0.5/1k + 500 out @ 1.5/1k = 3.25 -> ceil 4 coins.
            'usage'   => ['prompt_tokens' => 5000, 'completion_tokens' => 500, 'total_tokens' => 5500],
            'model'   => 'gpt-4o-mini',
        ];
    }

    private function aiSpend(User $user): int
    {
        return (int) abs(
            WalletTransaction::where('user_id', $user->id)
                ->where('type', 'spend')
                ->where('meta->ai', true)
                ->sum('delta_coins')
        );
    }

    private function refundTotal(User $user): int
    {
        return (int) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'refund')
            ->sum('delta_coins');
    }

    // ── 1. non-JSON model output → charged, then refunded ─────────────

    public function test_unparseable_response_marks_scan_failed_and_refunds_the_charge(): void
    {
        $user = $this->user();
        $file = $this->vaultedImage($user);

        // Real OpenAI charge happens, but the body isn't valid JSON so
        // callVision throws AFTER the wallet was debited.
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(
                $this->fakeVisionResponse('Sorry, I could not read that card. Here is some prose instead.')
            ),
        ]);

        $before = app(AiUsageCharger::class)->getBalance($user);
        $this->assertSame(self::SEED_COINS, $before);

        $thrown = null;
        try {
            app(CardBrochureExtractionService::class)
                ->extractFromVaultedFiles($user, $user, [$file]);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A broken extraction must propagate its failure to the caller.');

        // A real charge must have happened first — otherwise there is no
        // refund path to exercise and this test would be meaningless.
        $spend = $this->aiSpend($user);
        $this->assertGreaterThan(0, $spend, 'The vision call must have charged the wallet before failing.');

        // The scan row ends failed with its recorded spend zeroed out.
        $scan = CardScan::withoutGlobalScope('workspace')->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($scan);
        $this->assertSame('failed', $scan->status);
        $this->assertSame(0, (int) $scan->credits_spent, 'The refunded scan must not still show a spend.');

        // The refund exactly offset the charge…
        $this->assertSame($spend, $this->refundTotal($user), 'The refund must return the full amount charged.');
        // …so the wallet is back to its pre-scan balance.
        $this->assertSame(
            $before,
            app(AiUsageCharger::class)->getBalance($user),
            'A failed scan must leave the wallet exactly as it was before.'
        );
    }

    // ── 2. valid JSON but normalise() throws → charged, then refunded ──

    public function test_downstream_normalise_failure_marks_scan_failed_and_refunds_the_charge(): void
    {
        $user = $this->user();
        $file = $this->vaultedImage($user);

        // The model returns perfectly valid JSON, so callVision succeeds
        // and charges the wallet. The failure is simulated one step later,
        // inside normalise(), to prove the refund covers ANY post-charge
        // breakage — not just a JSON parse error.
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(
                $this->fakeVisionResponse('{"kind":"card","person":{"full_name":"Jane Smith"}}')
            ),
        ]);

        // Partial mock: real constructor deps + real callVision, but
        // normalise() blows up.
        $service = \Mockery::mock(
            CardBrochureExtractionService::class,
            [app(OpenAiService::class), app(AiUsageCharger::class)]
        )->makePartial();
        $service->shouldReceive('normalise')
            ->once()
            ->andThrow(new \RuntimeException('Normalise blew up after the charge.'));
        $this->instance(CardBrochureExtractionService::class, $service);

        $before = app(AiUsageCharger::class)->getBalance($user);
        $this->assertSame(self::SEED_COINS, $before);

        $thrown = null;
        try {
            $service->extractFromVaultedFiles($user, $user, [$file]);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A normalise() failure must propagate to the caller.');

        $spend = $this->aiSpend($user);
        $this->assertGreaterThan(0, $spend, 'The vision call must have charged the wallet before normalise failed.');

        $scan = CardScan::withoutGlobalScope('workspace')->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($scan);
        $this->assertSame('failed', $scan->status);
        $this->assertSame(0, (int) $scan->credits_spent);

        $this->assertSame($spend, $this->refundTotal($user), 'The refund must return the full amount charged.');
        $this->assertSame(
            $before,
            app(AiUsageCharger::class)->getBalance($user),
            'A failed scan must leave the wallet exactly as it was before.'
        );
    }
}
