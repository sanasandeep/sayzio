<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\BrandAssetImageClient;
use App\Services\AI\OpenAiService;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Biolink\BuilderImageSourcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Auto-sourced images for the AI biolink builder (Task #5720).
 *
 * Proves the strict priority order (uploads > extracted-from-links >
 * AI-generated), the vault storage of extracted/generated images, the
 * per-image coin charge with refund-on-build-failure for generated
 * images, and that a page still builds when nothing is sourceable.
 *
 * All HTTP is Http::fake'd (OgMetadataService uses the Http facade for
 * both the page fetch and the image download); BrandAssetImageClient is
 * a Mockery double so no real gpt-image-1 call happens.
 */
class AiBuilderImageSourcingTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG — a real decodable image for downloadImage(). */
    private const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('user_files');
        // assertS3Configured() reads config, not the (faked) disk instance.
        config()->set('filesystems.disks.user_files', [
            'driver' => 's3', 'key' => 'test', 'secret' => 'test',
            'bucket' => 'test-bucket', 'region' => 'us-east-1',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function png(): string
    {
        return base64_decode(self::PNG_B64);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Sourcing Plan', 'slug' => 'sourcing-' . Str::random(6),
            'monthly_price' => 0, 'annual_price' => 0, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 0,
            'features' => [
                'max_links' => 100, 'max_biolinks' => 100,
                'block_types_allowed' => ['profile_card_v1', 'heading', 'paragraph', 'link', 'image'],
            ],
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'user', 'plan_id' => $this->plan()->id])->fresh();
    }

    private function biolink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type' => 'biolink', 'alias' => Str::random(8),
            'title' => 'My page', 'is_active' => true,
        ]);
    }

    /** Minimal valid model response so the build succeeds. */
    private function bindChat(bool $throw = false): void
    {
        $calls =& $this->chatCalls;
        $mock = Mockery::mock(OpenAiService::class);
        if ($throw) {
            $mock->shouldReceive('chat')->andThrow(new \RuntimeException('model down'));
        } else {
            $mock->shouldReceive('chat')->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$calls) {
                $calls[] = ['messages' => $messages, 'opts' => $opts];
                return [
                    'content' => json_encode(['blocks' => [
                        ['type' => 'heading', 'settings' => ['text' => 'Hi', 'size' => 'h1']],
                    ]]),
                    'tool_calls' => [], 'finish_reason' => 'stop',
                    'tokens_in' => 0, 'tokens_out' => 0,
                    'credits_spent' => 3, 'model' => $model, 'raw' => [],
                ];
            });
        }
        $this->app->instance(OpenAiService::class, $mock);
    }

    /** The prompt text the model saw (user message). */
    private function promptText(): string
    {
        return (string) ($this->chatCalls[0]['messages'][1]['content'] ?? '');
    }

    /** Bind an image client double. */
    private function bindImageClient(bool $enabled, bool $throw = false): \Mockery\MockInterface
    {
        $mock = Mockery::mock(BrandAssetImageClient::class);
        $mock->shouldReceive('enabled')->andReturn($enabled);
        if ($throw) {
            $mock->shouldReceive('generate')->andThrow(new \RuntimeException('image API failed'));
        } else {
            $mock->shouldReceive('generate')->andReturn($this->png());
        }
        $this->app->instance(BrandAssetImageClient::class, $mock);
        return $mock;
    }

    /** Real charger substitute that hands out transactions with ids. */
    private function bindCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::mock(AiUsageCharger::class);
        $charger->shouldReceive('charge')->andReturnUsing(function () {
            $tx = new WalletTransaction();
            $tx->id = random_int(1000, 999999);
            return $tx;
        })->byDefault();
        $charger->shouldReceive('refund')->andReturnUsing(function () {
            return new WalletTransaction();
        })->byDefault();
        $this->app->instance(AiUsageCharger::class, $charger);
        return $charger;
    }

    // ── 1. uploads win outright — nothing fetched or generated ────────

    public function test_uploads_short_circuit_all_auto_sourcing(): void
    {
        Http::fake();
        $this->bindChat();
        $imageClient = $this->bindImageClient(true);
        $imageClient->shouldNotHaveReceived('generate');
        $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', ['https://bobbakes.test'], ['/f/9/photo.png'], [],
        );

        Http::assertNothingSent();
        $this->assertStringContainsString('/f/9/photo.png', $this->promptText());
        $this->assertStringNotContainsString('auto-sourced', $this->promptText());
        $this->assertSame(0, UserFile::count());
    }

    // ── 2. extraction from links: og:image → vault, free ──────────────

    public function test_extracts_og_image_from_links_into_vault(): void
    {
        Http::fake([
            'https://bobbakes.test' => Http::response(
                '<html><head><meta property="og:image" content="https://bobbakes.test/cover.png"></head></html>',
                200, ['Content-Type' => 'text/html'],
            ),
            'https://bobbakes.test/cover.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->bindChat();
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', ['https://bobbakes.test'], [], [],
        );

        $file = UserFile::first();
        $this->assertNotNull($file, 'Expected the og:image to be stored in the vault.');
        $this->assertSame('ai_builder', $file->context);
        $this->assertSame($user->id, $file->user_id);

        // The extracted vault URL (relative) reached the prompt, flagged auto-sourced.
        $this->assertStringContainsString($file->url_path, $this->promptText());
        $this->assertStringContainsString('auto-sourced', $this->promptText());

        // Extraction is free — no image charge was made.
        $charger->shouldNotHaveReceived('charge');
    }

    // ── 3. generation fallback: charged per image, files in vault ─────

    public function test_generates_avatar_and_cover_when_nothing_extractable(): void
    {
        Http::fake(); // no links supplied → nothing fetched
        $this->bindChat();
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', [], [], [],
        );

        $files = UserFile::all();
        $this->assertCount(2, $files, 'Expected a generated avatar + cover.');
        foreach ($files as $f) {
            $this->assertSame('ai_builder', $f->context);
            $this->assertStringContainsString($f->url_path, $this->promptText());
        }

        // One coin charge per generated image, none refunded (build succeeded).
        $charger->shouldHaveReceived('charge')->twice();
        $charger->shouldNotHaveReceived('refund');
    }

    // ── 4. chat failure after generation → refund + file cleanup ──────

    public function test_generated_images_are_refunded_and_deleted_when_build_fails(): void
    {
        Http::fake();
        $this->bindChat(throw: true);
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        try {
            app(AiBiolinkBuilderService::class)->generate($user, $link, 'A bakery page', [], [], []);
            $this->fail('Expected generate() to rethrow the chat failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('model down', $e->getMessage());
        }

        // Both generation charges rolled back, both files removed.
        $charger->shouldHaveReceived('refund')->twice()->with(
            Mockery::type(User::class),
            Mockery::type('int'),
            Mockery::on(fn ($o) => is_array($o)
                && str_starts_with((string) ($o['idempotency_key'] ?? ''), 'ai_builder_image_rollback:')),
        );
        $this->assertSame(0, UserFile::count());
    }

    // ── 5. failed image render → per-image refund, build continues ────

    public function test_failed_generation_refunds_and_page_still_builds(): void
    {
        Http::fake();
        $this->bindChat();
        $this->bindImageClient(true, throw: true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $result = app(AiBiolinkBuilderService::class)->generate($user, $link, 'A bakery page', [], [], []);

        // Both slots charged then refunded; no files stored; page still built.
        $charger->shouldHaveReceived('refund')->twice()->with(
            Mockery::type(User::class),
            Mockery::type('int'),
            Mockery::on(fn ($o) => is_array($o)
                && str_starts_with((string) ($o['idempotency_key'] ?? ''), 'ai_builder_image_refund:')),
        );
        $this->assertSame(0, UserFile::count());
        $this->assertGreaterThan(0, $result['blocks']);
        $this->assertStringContainsString('No images were supplied', $this->promptText());
    }

    // ── 6. generation disabled → graceful no-image build ──────────────

    public function test_builds_without_images_when_generation_disabled(): void
    {
        Http::fake();
        $this->bindChat();
        $this->bindImageClient(false);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $result = app(AiBiolinkBuilderService::class)->generate($user, $link, 'A bakery page', [], [], []);

        $this->assertGreaterThan(0, $result['blocks']);
        $this->assertSame(0, UserFile::count());
        $charger->shouldNotHaveReceived('charge');
        $this->assertStringContainsString('No images were supplied', $this->promptText());
    }

    // ── 7. estimate includes the generation fallback when no uploads ──

    public function test_estimate_includes_fallback_generation_cost_only_without_uploads(): void
    {
        $this->bindImageClient(true);

        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('estimateChatCoins')->andReturn(10);
        $this->app->instance(OpenAiService::class, $openai);

        $user = $this->makeUser();
        $builder = app(AiBiolinkBuilderService::class);
        $perImage = app(BuilderImageSourcer::class)->generationCoinCost($user);

        $withUploads = $builder->estimateCredits($user, 'A bakery page', [], ['/f/9/photo.png']);
        $withoutUploads = $builder->estimateCredits($user, 'A bakery page', [], []);

        $this->assertSame(10, $withUploads);
        $this->assertSame(10 + 2 * $perImage, $withoutUploads);
    }

    // ── 8. preview(): free extraction + generation info (Task #5722) ──

    public function test_preview_extracts_images_and_reports_generation_info(): void
    {
        Http::fake([
            'https://bobbakes.test' => Http::response(
                '<html><head><meta property="og:image" content="https://bobbakes.test/cover.png"></head></html>',
                200, ['Content-Type' => 'text/html'],
            ),
            'https://bobbakes.test/cover.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $preview = app(BuilderImageSourcer::class)->preview($user, ['https://bobbakes.test']);

        $this->assertCount(1, $preview['extracted']);
        $this->assertTrue($preview['generation']['enabled']);
        $this->assertSame(['avatar', 'cover'], $preview['generation']['slots']);
        $this->assertGreaterThan(0, $preview['generation']['cost_per_image']);
        $this->assertSame(1, UserFile::count());
        $charger->shouldNotHaveReceived('charge');
    }

    // ── 9. kept list is authoritative — no re-extraction, no generation ──

    public function test_kept_extracted_images_are_used_verbatim_without_refetch(): void
    {
        Http::fake();
        $this->bindChat();
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', ['https://bobbakes.test'], [], [],
            '', true, '', ['kept' => ['/f/9/kept-cover.png']],
        );

        // No page fetch, no generation charge — the kept URL reached the prompt.
        Http::assertNothingSent();
        $charger->shouldNotHaveReceived('charge');
        $this->assertStringContainsString('/f/9/kept-cover.png', $this->promptText());
        $this->assertStringContainsString('auto-sourced', $this->promptText());
    }

    // ── 10. kept empty + all slots skipped → zero-image build ─────────

    public function test_empty_kept_with_all_slots_skipped_builds_without_images(): void
    {
        Http::fake();
        $this->bindChat();
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $result = app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', ['https://bobbakes.test'], [], [],
            '', true, '', ['kept' => [], 'skip_slots' => ['avatar', 'cover']],
        );

        Http::assertNothingSent();
        $charger->shouldNotHaveReceived('charge');
        $this->assertSame(0, UserFile::count());
        $this->assertGreaterThan(0, $result['blocks']);
        $this->assertStringContainsString('No images were supplied', $this->promptText());
    }

    // ── 11. skipping one generation slot only produces the other ──────

    public function test_skipping_one_generation_slot_generates_only_the_other(): void
    {
        Http::fake();
        $this->bindChat();
        $this->bindImageClient(true);
        $charger = $this->bindCharger();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        app(AiBiolinkBuilderService::class)->generate(
            $user, $link, 'A bakery page', [], [], [],
            '', true, '', ['kept' => [], 'skip_slots' => ['cover']],
        );

        $this->assertSame(1, UserFile::count(), 'Only the avatar slot should generate.');
        $charger->shouldHaveReceived('charge')->once();
    }

    // ── 12. estimate honors kept images and skipped slots ─────────────

    public function test_estimate_honors_kept_images_and_skipped_slots(): void
    {
        $this->bindImageClient(true);

        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('estimateChatCoins')->andReturn(10);
        $this->app->instance(OpenAiService::class, $openai);

        $user = $this->makeUser();
        $builder = app(AiBiolinkBuilderService::class);
        $perImage = app(BuilderImageSourcer::class)->generationCoinCost($user);

        // Kept extracted images → extraction is free, no fallback in the quote.
        $withKept = $builder->estimateCredits(
            $user, 'A bakery page', [], [], [], '', '',
            ['kept' => ['/f/9/kept.png']],
        );
        // Kept empty + one slot skipped → only the remaining slot is quoted.
        $oneSlot = $builder->estimateCredits(
            $user, 'A bakery page', [], [], [], '', '',
            ['kept' => [], 'skip_slots' => ['cover']],
        );
        // Kept empty + both slots skipped → no generation cost at all.
        $noSlots = $builder->estimateCredits(
            $user, 'A bakery page', [], [], [], '', '',
            ['kept' => [], 'skip_slots' => ['avatar', 'cover']],
        );

        $this->assertSame(10, $withKept);
        $this->assertSame(10 + $perImage, $oneSlot);
        $this->assertSame(10, $noSlots);
    }
}
