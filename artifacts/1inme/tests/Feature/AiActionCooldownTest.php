<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\QrArtService;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Resume\ResumeTailorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Double-charge guard ({@see AiActionCooldown}) on the one-tap paid AI
 * buttons: AI biolink builder generate (web + API), resume tailor run (web),
 * and AI Artistic QR generation (web + API).
 *
 * For each surface: the first run charges normally, an identical re-run
 * inside the cooldown window is served from cache with `cached: true` and
 * zero charge (the underlying paid service is NOT called again), different
 * inputs bypass the cache, and a concurrent identical request is rejected
 * with 429 `in_progress` while the first is still in flight.
 */
class AiActionCooldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeUser(array $planFeatures = []): User
    {
        $plan = Plan::create([
            'name'     => 'Cooldown Plan ' . Str::random(4),
            'slug'     => 'plan-' . Str::lower(Str::random(8)),
            'status'   => true,
            'features' => $planFeatures,
        ]);

        return User::factory()->create(['plan_id' => $plan->id])->fresh();
    }

    protected function makeBiolink(User $user): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'biolink',
            'alias'        => 'cd-' . Str::lower(Str::random(10)),
            'url'          => null,
            'settings'     => [],
        ]);
    }

    /** Bind a charger double so responses can report a balance. */
    protected function stubBalance(int $balance = 42): void
    {
        $charger = Mockery::mock(AiUsageCharger::class);
        $charger->shouldReceive('getBalance')->andReturn($balance);
        $this->app->instance(AiUsageCharger::class, $charger);
    }

    /** Bind a builder double whose generate() may run exactly $times times. */
    protected function stubBuilder(int $times): void
    {
        $builder = Mockery::mock(AiBiolinkBuilderService::class);
        $builder->shouldReceive('generate')->times($times)->andReturn([
            'blocks'        => 5,
            'credits_spent' => 9,
        ]);
        $this->app->instance(AiBiolinkBuilderService::class, $builder);
    }

    private const BUILDER_PAYLOAD = [
        'description' => 'A friendly neighbourhood bakery page with hours and menu.',
    ];

    // ── AI biolink builder — web ────────────────────────────────────────

    public function test_web_builder_rerun_within_cooldown_is_cached_and_free(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);
        $this->stubBuilder(1); // charged run happens exactly once
        $this->stubBalance();

        $first = $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD);
        $first->assertOk();
        $this->assertSame(9, $first->json('credits_spent'));
        $this->assertNull($first->json('cached'));

        $second = $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD);
        $second->assertOk();
        $this->assertTrue($second->json('cached'));
        $this->assertSame(0, $second->json('credits_spent'));
        $this->assertSame(5, $second->json('blocks'));
        $this->assertNotEmpty($second->json('generated_at'));
    }

    public function test_web_builder_different_description_bypasses_cooldown(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);
        $this->stubBuilder(2); // both distinct runs charge
        $this->stubBalance();

        $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD)
            ->assertOk();

        $other = $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), [
                'description' => 'A completely different portfolio page for a photographer.',
            ]);
        $other->assertOk();
        $this->assertNull($other->json('cached'));
        $this->assertSame(9, $other->json('credits_spent'));
    }

    public function test_web_builder_concurrent_identical_request_gets_429(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);
        // One stub for the whole test: the guarded duplicate must NOT reach
        // the builder; only the post-release retry does (Laravel memoizes the
        // resolved controller per route inside a test, so re-stubbing after
        // the first request would not take effect).
        $this->stubBuilder(1);
        $this->stubBalance();

        // Simulate an identical request currently executing.
        $key = AiActionCooldown::key('biolink_builder', $user->id, ['link' => $link->id] + [
            'description'   => self::BUILDER_PAYLOAD['description'],
            'links'         => [],
            'images'        => [],
            'files'         => [],
            'use_brand_kit' => true,
        ]);
        $this->assertTrue(AiActionCooldown::begin($key));

        $res = $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD);
        $res->assertStatus(429);
        $this->assertSame('in_progress', $res->json('code'));

        // Once the first run releases the lock, the button works again.
        AiActionCooldown::end($key);
        $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD)
            ->assertOk();
    }

    public function test_web_builder_failed_run_is_not_cached(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);
        $this->stubBalance();

        $builder = Mockery::mock(AiBiolinkBuilderService::class);
        $builder->shouldReceive('generate')->twice()->andThrow(new \RuntimeException('The AI response could not be used.'));
        $this->app->instance(AiBiolinkBuilderService::class, $builder);

        // Both attempts hit the (failing) builder — a failure never seeds the
        // cooldown cache, so retrying after an error still runs for real.
        $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD)
            ->assertStatus(422);
        $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD)
            ->assertStatus(422);
    }

    // ── AI biolink builder — API (mobile parity) ────────────────────────

    public function test_api_builder_rerun_within_cooldown_is_cached_and_free(): void
    {
        AiEngineSettings::setEnabled(true);
        $user  = $this->makeUser();
        $link  = $this->makeBiolink($user);
        $token = $user->createToken('test')->plainTextToken;
        $this->stubBuilder(1);
        $this->stubBalance();

        $first = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/generate", self::BUILDER_PAYLOAD);
        $first->assertOk();
        $this->assertSame(9, $first->json('data.credits_spent'));

        $second = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/generate", self::BUILDER_PAYLOAD);
        $second->assertOk();
        $this->assertTrue($second->json('data.cached'));
        $this->assertSame(0, $second->json('data.credits_spent'));
        $this->assertSame(5, $second->json('data.blocks'));
    }

    public function test_web_and_api_builder_share_one_cooldown(): void
    {
        AiEngineSettings::setEnabled(true);
        $user  = $this->makeUser();
        $link  = $this->makeBiolink($user);
        $token = $user->createToken('test')->plainTextToken;
        $this->stubBuilder(1); // web run seeds the cache; API re-run is free
        $this->stubBalance();

        $this->actingAs($user, 'web')
            ->postJson(route('user.links.ai-builder.generate', $link), self::BUILDER_PAYLOAD)
            ->assertOk();

        $api = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/generate", self::BUILDER_PAYLOAD);
        $api->assertOk();
        $this->assertTrue($api->json('data.cached'));
        $this->assertSame(0, $api->json('data.credits_spent'));
    }

    // ── Resume tailor run — web ─────────────────────────────────────────

    private const JD = 'We are hiring a senior backend engineer with strong PHP and PostgreSQL experience to build scalable APIs for our creator platform.';

    protected function stubTailor(int $times): void
    {
        $tailor = Mockery::mock(ResumeTailorService::class);
        $tailor->shouldReceive('run')->times($times)->andReturn([
            'suggestions'   => ['summary' => ['text' => 'Tailored summary.']],
            'credits_spent' => 6,
        ]);
        $tailor->shouldReceive('recentRuns')->andReturn([]);
        $this->app->instance(ResumeTailorService::class, $tailor);
    }

    public function test_resume_tailor_rerun_same_jd_is_cached_and_free(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser(['ai_resume_tools' => true]);
        $this->stubTailor(1);
        $this->stubBalance();

        $first = $this->actingAs($user, 'web')
            ->postJson(route('user.resume.tailor.run'), ['job_description' => self::JD]);
        $first->assertOk();
        $this->assertSame(6, $first->json('credits_spent'));

        $second = $this->actingAs($user, 'web')
            ->postJson(route('user.resume.tailor.run'), ['job_description' => self::JD]);
        $second->assertOk();
        $this->assertTrue($second->json('cached'));
        $this->assertSame(0, $second->json('credits_spent'));
        $this->assertSame('Tailored summary.', $second->json('suggestions.summary.text'));
    }

    public function test_resume_tailor_different_jd_runs_again(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser(['ai_resume_tools' => true]);
        $this->stubTailor(2);
        $this->stubBalance();

        $this->actingAs($user, 'web')
            ->postJson(route('user.resume.tailor.run'), ['job_description' => self::JD])
            ->assertOk();

        $other = $this->actingAs($user, 'web')
            ->postJson(route('user.resume.tailor.run'), [
                'job_description' => 'Totally different role: product designer with Figma expertise and a strong portfolio of mobile app work.',
            ]);
        $other->assertOk();
        $this->assertNull($other->json('cached'));
    }

    public function test_resume_tailor_concurrent_identical_request_gets_429(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser(['ai_resume_tools' => true]);
        $this->stubTailor(0);
        $this->stubBalance();

        $resume = $user->ensureResume();
        $key = AiActionCooldown::key('resume_tailor', $user->id, [
            'resume' => $resume->id,
            'jd'     => self::JD,
        ]);
        $this->assertTrue(AiActionCooldown::begin($key));

        $res = $this->actingAs($user, 'web')
            ->postJson(route('user.resume.tailor.run'), ['job_description' => self::JD]);
        $res->assertStatus(429);
        $this->assertSame('in_progress', $res->json('code'));
    }

    // ── AI Artistic QR — web + API ──────────────────────────────────────

    private const QR_PAYLOAD = [
        'data'   => 'https://1in.me/example',
        'prompt' => 'a cozy autumn forest, warm light',
    ];

    protected function stubQrArt(int $times): void
    {
        $art = Mockery::mock(QrArtService::class);
        $art->shouldReceive('enabled')->andReturn(true);
        $art->shouldReceive('generate')->times($times)->andReturn([
            'image_url' => 'https://cdn.test/ai-qr.png',
            'file_id'   => 123,
            'cost'      => 15,
            'balance'   => 85,
        ]);
        $this->app->instance(QrArtService::class, $art);
    }

    public function test_web_qr_art_rerun_within_cooldown_is_cached_and_free(): void
    {
        $user = $this->makeUser(['qr_art' => true]);
        $this->stubQrArt(1);
        $this->stubBalance(85);

        $first = $this->actingAs($user, 'web')
            ->postJson(route('user.qr-codes.generate-art'), self::QR_PAYLOAD);
        $first->assertOk();
        $this->assertSame(15, $first->json('cost'));

        $second = $this->actingAs($user, 'web')
            ->postJson(route('user.qr-codes.generate-art'), self::QR_PAYLOAD);
        $second->assertOk();
        $this->assertTrue($second->json('cached'));
        $this->assertSame(0, $second->json('cost'));
        $this->assertSame('https://cdn.test/ai-qr.png', $second->json('image_url'));
        $this->assertSame(123, $second->json('file_id'));
    }

    public function test_web_qr_art_different_prompt_generates_again(): void
    {
        $user = $this->makeUser(['qr_art' => true]);
        $this->stubQrArt(2);
        $this->stubBalance(85);

        $this->actingAs($user, 'web')
            ->postJson(route('user.qr-codes.generate-art'), self::QR_PAYLOAD)
            ->assertOk();

        $other = $this->actingAs($user, 'web')
            ->postJson(route('user.qr-codes.generate-art'), [
                'data'   => self::QR_PAYLOAD['data'],
                'prompt' => 'a neon cyberpunk cityscape at night',
            ]);
        $other->assertOk();
        $this->assertNull($other->json('cached'));
        $this->assertSame(15, $other->json('cost'));
    }

    public function test_web_qr_art_concurrent_identical_request_gets_429(): void
    {
        $user = $this->makeUser(['qr_art' => true]);
        $this->stubQrArt(0);
        $this->stubBalance(85);

        $key = AiActionCooldown::key('qr_art', $user->id, [
            'data'            => self::QR_PAYLOAD['data'],
            'prompt'          => self::QR_PAYLOAD['prompt'],
            'negative_prompt' => null,
            'strength'        => null,
        ]);
        $this->assertTrue(AiActionCooldown::begin($key));

        $res = $this->actingAs($user, 'web')
            ->postJson(route('user.qr-codes.generate-art'), self::QR_PAYLOAD);
        $res->assertStatus(429);
        $this->assertSame('in_progress', $res->json('code'));
    }

    public function test_api_qr_art_rerun_within_cooldown_is_cached_and_free(): void
    {
        $user  = $this->makeUser(['qr_art' => true]);
        $token = $user->createToken('test')->plainTextToken;
        $this->stubQrArt(1);
        $this->stubBalance(85);

        $first = $this->withToken($token)
            ->postJson('/api/v1/qr-codes/generate-art', self::QR_PAYLOAD);
        $first->assertOk();
        $this->assertSame(15, $first->json('data.cost'));

        $second = $this->withToken($token)
            ->postJson('/api/v1/qr-codes/generate-art', self::QR_PAYLOAD);
        $second->assertOk();
        $this->assertTrue($second->json('data.cached'));
        $this->assertSame(0, $second->json('data.cost'));
        $this->assertSame('https://cdn.test/ai-qr.png', $second->json('data.image_url'));
        $this->assertSame(self::QR_PAYLOAD['data'], $second->json('data.encoded'));
    }

    // ── Helper semantics ────────────────────────────────────────────────

    public function test_cooldown_key_varies_by_feature_user_and_payload(): void
    {
        $a = AiActionCooldown::key('qr_art', 1, ['p' => 'x']);
        $this->assertNotSame($a, AiActionCooldown::key('qr_art', 2, ['p' => 'x']));
        $this->assertNotSame($a, AiActionCooldown::key('qr_art', 1, ['p' => 'y']));
        $this->assertNotSame($a, AiActionCooldown::key('biolink_builder', 1, ['p' => 'x']));
        $this->assertSame($a, AiActionCooldown::key('qr_art', 1, ['p' => 'x']));
    }

    public function test_cached_result_expires_after_cooldown_window(): void
    {
        $key = AiActionCooldown::key('qr_art', 1, ['p' => 'x']);
        AiActionCooldown::remember($key, ['image_url' => 'u']);
        $this->assertNotNull(AiActionCooldown::fresh($key));

        $this->travel(AiActionCooldown::FRESH_MINUTES + 1)->minutes();
        $this->assertNull(AiActionCooldown::fresh($key));
        $this->travelBack();
    }
}
