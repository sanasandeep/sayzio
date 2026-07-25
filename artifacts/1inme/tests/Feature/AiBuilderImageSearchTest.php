<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Integrations\GoogleCseUsage;
use App\Services\Integrations\GoogleImageSearchService;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Google image search + vault import for the AI biolink builder (Task #5723).
 *
 * Proves preview mode (404 when no keys), the CSE search proxy shape
 * (results + rights disclaimer, never auto-placed), the SSRF-safe import
 * into the vault under context `ai_builder`, hash dedupe, the all-fail
 * 422, and API (mobile) parity via a real bearer token.
 */
class AiBuilderImageSearchTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG — a real decodable image for downloadImage(). */
    private const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('user_files');
        config()->set('filesystems.disks.user_files', [
            'driver' => 's3', 'key' => 'test', 'secret' => 'test',
            'bucket' => 'test-bucket', 'region' => 'us-east-1',
        ]);
    }

    private function png(): string
    {
        return base64_decode(self::PNG_B64);
    }

    /** A real 300x300 PNG — passes the auto-tier minimum-dimension filter. */
    private function bigPng(): string
    {
        $im = imagecreatetruecolor(300, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 120, 90, 200));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function enableCse(): void
    {
        config()->set('services.google_cse.api_key', 'test-key');
        config()->set('services.google_cse.engine_id', 'test-cx');
    }

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name' => 'Search Plan', 'slug' => 'search-' . Str::random(6),
            'monthly_price' => 0, 'annual_price' => 0, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 0,
            'features' => ['max_links' => 100, 'max_biolinks' => 100],
        ]);

        return User::factory()->create(['role' => 'user', 'plan_id' => $plan->id])->fresh();
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

    private function cseResponse(): array
    {
        return ['items' => [
            [
                'link' => 'https://images.example.com/a.png',
                'title' => 'A nice image',
                'displayLink' => 'images.example.com',
                'image' => ['thumbnailLink' => 'https://thumbs.example.com/a.png', 'width' => 800, 'height' => 600],
            ],
            [
                'link' => 'https://images.example.com/b.jpg',
                'title' => 'Another image',
                'displayLink' => 'images.example.com',
                'image' => ['thumbnailLink' => 'https://thumbs.example.com/b.jpg', 'width' => 640, 'height' => 480],
            ],
        ]];
    }

    // ── Preview mode: no keys ⇒ 404 + intake flag false ───────────────

    public function test_search_is_404_in_preview_mode(): void
    {
        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertStatus(404);

        $this->assertFalse(app(GoogleImageSearchService::class)->enabled());
    }

    public function test_api_intake_reports_image_search_flag(): void
    {
        $user = $this->makeUser();
        $link = $this->biolink($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/links/{$link->id}/ai-builder")
            ->assertOk()
            ->assertJsonPath('data.image_search_enabled', false);

        $this->enableCse();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson("/api/v1/links/{$link->id}/ai-builder")
            ->assertOk()
            ->assertJsonPath('data.image_search_enabled', true);
        $this->flushHeaders();
    }

    // ── Availability recheck: focus-poll endpoint (Task #5753) ─────────

    public function test_availability_endpoint_reflects_cse_config(): void
    {
        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->getJson(route('user.links.ai-builder.image-search.availability', $link))
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->enableCse();

        $this->actingAs($user)
            ->getJson(route('user.links.ai-builder.image-search.availability', $link))
            ->assertOk()
            ->assertJsonPath('enabled', true);
    }

    public function test_availability_endpoint_denies_foreign_links(): void
    {
        $owner = $this->makeUser();
        $link = $this->biolink($owner);
        $other = $this->makeUser();

        // Denied before the controller runs: the workspace.can middleware
        // 403s a caller with no rights on the owning workspace.
        $this->actingAs($other)
            ->getJson(route('user.links.ai-builder.image-search.availability', $link))
            ->assertStatus(403);
    }

    // ── Search proxy: results + disclaimer, service shape ──────────────

    public function test_web_search_returns_candidates_with_disclaimer(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $res = $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertOk()
            ->json();

        $this->assertCount(2, $res['results']);
        $this->assertSame('https://images.example.com/a.png', $res['results'][0]['url']);
        $this->assertSame('https://thumbs.example.com/a.png', $res['results'][0]['thumbnail']);
        $this->assertSame('images.example.com', $res['results'][0]['source']);
        $this->assertStringContainsString('rights', $res['disclaimer']);
    }

    public function test_search_validates_query(): void
    {
        $this->enableCse();
        Http::fake();

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'x'])
            ->assertStatus(422);
    }

    public function test_search_api_errors_degrade_to_empty_results(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response(['error' => 'quota'], 429),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    // ── Usage counters + per-user daily cap (Task #5724) ──────────────

    public function test_search_records_daily_usage_counters(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertOk();
        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'tea house'])
            ->assertOk();

        $this->assertSame(2, GoogleCseUsage::todayTotal());
        $this->assertSame(2, GoogleCseUsage::todayForUser($user->id));
        $recent = GoogleCseUsage::recentDaily();
        $this->assertSame(2, $recent[0]['queries']);
        $this->assertSame([['user_id' => $user->id, 'queries' => 2]], GoogleCseUsage::topUsersToday());
    }

    public function test_per_user_daily_cap_returns_friendly_429(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
        ]);

        PlatformServiceSettings::setGoogleCseUserDailyCap(1);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertOk();

        $res = $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertStatus(429);
        $this->assertStringContainsString('try again tomorrow', $res->json('message'));

        // API parity: capped user gets the coded 429.
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/image-search", ['query' => 'coffee shop'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'image_search_daily_cap');
        $this->flushHeaders();

        // A different user is unaffected by someone else's cap.
        $other = $this->makeUser();
        $otherLink = $this->biolink($other);
        $this->actingAs($other)
            ->postJson(route('user.links.ai-builder.image-search', $otherLink), ['query' => 'coffee shop'])
            ->assertOk();

        // Cap 0 = unlimited again. Re-bind the capped user's workspace —
        // the $other request above left its workspace bound, which would
        // hide $link from route binding.
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($user));
        app()->instance('workspace_owner', $user);
        PlatformServiceSettings::setGoogleCseUserDailyCap(0);
        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee shop'])
            ->assertOk();
    }

    // ── Import: SSRF-safe vault store, dedupe, all-fail 422 ───────────

    public function test_web_import_stores_vault_files_and_dedupes(): void
    {
        Http::fake([
            'https://images.example.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $res = $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.import-images', $link), [
                // Identical bytes → the second URL dedupes by content hash.
                'urls' => ['https://images.example.com/a.png', 'https://images.example.com/b.png'],
            ])
            ->assertOk()
            ->json();

        $this->assertCount(1, $res['images']);
        $this->assertSame('https://images.example.com/a.png', $res['images'][0]['source_url']);
        $this->assertNotSame('', (string) $res['images'][0]['url']);

        $file = UserFile::where('user_id', $user->id)->first();
        $this->assertNotNull($file);
        $this->assertSame('ai_builder', $file->context);
        $this->assertStringEndsWith('.png', $file->original_name);
    }

    public function test_import_rejects_non_http_and_too_many_urls(): void
    {
        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.import-images', $link), [
                'urls' => ['ftp://evil.example.com/a.png'],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.import-images', $link), [
                'urls' => array_map(fn ($i) => "https://images.example.com/{$i}.png", range(1, 7)),
            ])
            ->assertStatus(422);
    }

    public function test_import_returns_422_when_every_download_fails(): void
    {
        Http::fake([
            'https://images.example.com/*' => Http::response('nope', 404),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.import-images', $link), [
                'urls' => ['https://images.example.com/missing.png'],
            ])
            ->assertStatus(422);

        $this->assertSame(0, UserFile::where('user_id', $user->id)->count());
    }

    public function test_other_users_cannot_touch_the_link(): void
    {
        $this->enableCse();
        Http::fake();

        $owner = $this->makeUser();
        $link = $this->biolink($owner);
        $stranger = $this->makeUser();

        // Web denies for non-owners: 403 from authorizeLink, or 404 once the
        // workspace global scope hides the foreign link at route binding.
        $status = $this->actingAs($stranger)
            ->postJson(route('user.links.ai-builder.image-search', $link), ['query' => 'coffee'])
            ->getStatusCode();
        $this->assertContains($status, [403, 404]);

        $status = $this->actingAs($stranger)
            ->postJson(route('user.links.ai-builder.import-images', $link), [
                'urls' => ['https://images.example.com/a.png'],
            ])
            ->getStatusCode();
        $this->assertContains($status, [403, 404]);
        $this->assertSame(0, UserFile::where('user_id', $stranger->id)->count());

        // API denies with 404 (ownedBiolink scopes by user_id).
        $token = $stranger->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/image-search", ['query' => 'coffee'])
            ->assertStatus(404);
        $this->flushHeaders();
    }

    // ── API (mobile) parity ────────────────────────────────────────────

    public function test_api_search_and_import_parity(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
            'https://images.example.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);
        $token = $user->createToken('test')->plainTextToken;

        $search = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/image-search", ['query' => 'coffee shop'])
            ->assertOk()
            ->json();
        $this->assertCount(2, $search['data']['results']);
        $this->assertStringContainsString('rights', $search['data']['disclaimer']);
        $this->flushHeaders();

        $import = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/import-images", [
                'urls' => ['https://images.example.com/a.png'],
            ])
            ->assertOk()
            ->json();
        $this->assertCount(1, $import['data']['images']);
        $this->assertSame('ai_builder', UserFile::where('user_id', $user->id)->firstOrFail()->context);
        $this->flushHeaders();
    }

    // ── Automatic web-photo tier (uploads → extraction → search → gen) ─

    public function test_source_auto_searches_web_when_nothing_supplied(): void
    {
        $this->enableCse();
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
            'https://images.example.com/*' => Http::response($this->bigPng(), 200, ['Content-Type' => 'image/png']),
        ]);

        $user = $this->makeUser();

        $sourced = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->source($user, 'Personal page for Jane Doe, founder of Acme.', [], []);

        // Identical PNG bytes for both results → dedupe keeps one.
        $this->assertCount(1, $sourced['searched']);
        $this->assertSame($sourced['searched'], $sourced['images']);
        $this->assertSame([], $sourced['generated']);
        $this->assertSame('ai_builder', UserFile::where('user_id', $user->id)->firstOrFail()->context);
    }

    public function test_source_skips_web_search_when_creator_reviewed_preview(): void
    {
        $this->enableCse();
        Http::fake();

        $user = $this->makeUser();

        // Creator confirmed the preview step and kept nothing → their
        // choice is authoritative; no web search runs.
        $sourced = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->source($user, 'Personal page for Jane Doe.', [], [], null, []);

        $this->assertSame([], $sourced['searched']);
        Http::assertNothingSent();
    }

    public function test_source_web_tier_respects_daily_cap(): void
    {
        $this->enableCse();
        Http::fake();

        PlatformServiceSettings::setGoogleCseUserDailyCap(1);

        $user = $this->makeUser();
        GoogleCseUsage::record($user->id); // already at the cap today

        $sourced = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->source($user, 'Personal page for Jane Doe.', [], []);

        $this->assertSame([], $sourced['searched']);
        Http::assertNothingSent();
    }

    public function test_source_web_tier_silent_when_cse_unconfigured(): void
    {
        Http::fake();

        $user = $this->makeUser();

        $sourced = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->source($user, 'Personal page for Jane Doe.', [], []);

        $this->assertSame([], $sourced['searched']);
        $this->assertSame([], $sourced['images']);
        Http::assertNothingSent();
    }

    public function test_preview_surfaces_web_candidates_when_links_yield_nothing(): void
    {
        $this->enableCse();
        \App\Services\AI\AiEngineSettings::setEnabled(true);
        Http::fake([
            GoogleImageSearchService::ENDPOINT . '*' => Http::response($this->cseResponse()),
            'https://images.example.com/*' => Http::response($this->bigPng(), 200, ['Content-Type' => 'image/png']),
        ]);

        $user = $this->makeUser();
        $link = $this->biolink($user);

        $res = $this->actingAs($user)
            ->postJson(route('user.links.ai-builder.source-preview', $link), [
                'links'       => [],
                'description' => 'Personal page for Jane Doe, founder of Acme.',
            ])
            ->assertOk()
            ->json();

        $this->assertCount(1, $res['extracted']);
        $this->assertStringStartsWith('/f/', $res['extracted'][0]);
    }

    public function test_api_search_is_404_in_preview_mode(): void
    {
        $user = $this->makeUser();
        $link = $this->biolink($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/ai-builder/image-search", ['query' => 'coffee shop'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'image_search_unavailable');
        $this->flushHeaders();
    }
}
