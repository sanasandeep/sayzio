<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandKitAsset;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\BrandAssetImageClient;
use App\Services\Brand\BrandKitAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Coverage for Brand Kit AI visual assets (Task #5612): the per-asset
 * generate / regenerate (variation & alteration modes) / apply / delete
 * endpoints on /user/brand-kits/{kit}/assets and the coin charge + refund
 * contract in {@see BrandKitAssetService}.
 *
 * BrandAssetImageClient is swapped in the container (no network); the
 * charging/refund/storage logic stays real except where AiUsageCharger is
 * spied to assert the refund branch.
 */
class BrandKitAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Make the feature "enabled" without a real key by stubbing the client.
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function plan(array $features = []): Plan
    {
        return Plan::create([
            'name'          => 'Asset Plan',
            'slug'          => 'asset-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => array_merge([
                'max_links'            => 100,
                'max_biolinks'         => 100,
                'max_brand_kits'       => 5,
                'brand_kit_assets'     => true,
                'brand_asset_versions' => 10,
            ], $features),
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        return User::factory()->create([
            'role'    => 'user',
            'plan_id' => $plan->id,
        ])->fresh();
    }

    private function kit(User $user): BrandKit
    {
        return BrandKit::create([
            'user_id'    => $user->id,
            'name'       => 'Test kit',
            'slug'       => 'kit-' . Str::random(6),
            'is_default' => true,
            'config'     => [
                'palette'  => ['primary' => '#112233', 'secondary' => '#445566', 'accent' => '#778899'],
                'fonts'    => ['heading' => 'Space Grotesk', 'body' => 'Inter'],
                'voice'    => ['tone' => 'confident'],
                'taglines' => ['Links that convert'],
            ],
        ]);
    }

    /** One-pixel valid PNG bytes. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    /**
     * Spy the coin charger so generate paths need no funded wallet; charge
     * returns a WalletTransaction-shaped row (only ->id is read, and only
     * on the refund branch).
     */
    private function stubCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::spy(AiUsageCharger::class);
        $charger->shouldReceive('charge')
            ->andReturn(new \App\Modules\User\Models\WalletTransaction(['id' => 1]));
        $this->app->instance(AiUsageCharger::class, $charger);

        return $charger;
    }

    private function stubImages(?string $bytes = null, bool $fail = false): void
    {
        $mock = Mockery::mock(BrandAssetImageClient::class);
        $mock->shouldReceive('enabled')->andReturnTrue();
        if ($fail) {
            $mock->shouldReceive('generate')
                ->andThrow(new \RuntimeException('The image engine could not render this asset.'));
        } else {
            $mock->shouldReceive('generate')->andReturn($bytes ?? $this->pngBytes());
        }
        $this->app->instance(BrandAssetImageClient::class, $mock);
    }

    // ── Catalog ───────────────────────────────────────────────────────

    public function test_assets_catalog_lists_all_types_with_modes_metadata(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();

        $res = $this->actingAs($user)->getJson("/user/brand-kits/{$kit->id}/assets");
        $res->assertOk()->assertJsonPath('allowed', true);

        $types = collect($res->json('types'))->keyBy('type');
        foreach (array_keys(BrandKitAssetService::TYPES) as $type) {
            $this->assertTrue($types->has($type), "catalog missing type {$type}");
        }
        // New PPT/statement card types requested mid-task are present.
        foreach (['tagline_card', 'mission_card', 'vision_card', 'stats_card', 'ppt_cover', 'ppt_slide', 'ppt_closing'] as $type) {
            $this->assertTrue($types->has($type));
            $this->assertNull($types[$type]['asset']);
        }
        $this->assertContains('kit_logo', $types['logo']['apply_targets']);
    }

    // ── Generate + regenerate modes ───────────────────────────────────

    public function test_generate_stores_asset_and_tags_file_outside_vault_count(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();
        $this->stubCharger();

        $res = $this->actingAs($user)->postJson(
            "/user/brand-kits/{$kit->id}/assets/logo/generate",
            ['mode' => 'new']
        );
        $res->assertOk();
        $this->assertSame('ready', $res->json('asset.status'));
        $this->assertSame(1, $res->json('asset.version'));

        $asset = BrandKitAsset::where('brand_kit_id', $kit->id)->where('type', 'logo')->firstOrFail();
        $file  = UserFile::findOrFail($asset->user_file_id);
        // Exempt from max_files count, still counted toward storage bytes.
        $this->assertSame('brand_asset', $file->context);
        $this->assertSame('new', $asset->params['mode'] ?? null);
    }

    public function test_variation_and_alteration_modes_bump_version_and_record_mode(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();
        $this->stubCharger();

        $this->actingAs($user)->postJson("/user/brand-kits/{$kit->id}/assets/og_image/generate")->assertOk();

        $res = $this->actingAs($user)->postJson(
            "/user/brand-kits/{$kit->id}/assets/og_image/generate",
            ['mode' => 'variation']
        );
        $res->assertOk();
        $this->assertSame(2, $res->json('asset.version'));

        $res = $this->actingAs($user)->postJson(
            "/user/brand-kits/{$kit->id}/assets/og_image/generate",
            ['mode' => 'alteration', 'instructions' => 'Make the background darker']
        );
        $res->assertOk();
        $this->assertSame(3, $res->json('asset.version'));

        $asset = BrandKitAsset::where('brand_kit_id', $kit->id)->where('type', 'og_image')->firstOrFail();
        $this->assertSame('alteration', $asset->params['mode'] ?? null);
        $this->assertSame('Make the background darker', $asset->prompt);
        // Only one row per (kit, type): regeneration replaces, never duplicates.
        $this->assertSame(1, BrandKitAsset::where('brand_kit_id', $kit->id)->where('type', 'og_image')->count());
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();

        $this->actingAs($user)
            ->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate", ['mode' => 'remix'])
            ->assertStatus(422);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();

        $this->actingAs($user)
            ->postJson("/user/brand-kits/{$kit->id}/assets/hologram/generate")
            ->assertStatus(422);
    }

    public function test_plan_without_feature_cannot_generate(): void
    {
        $user = $this->makeUser($this->plan(['brand_kit_assets' => false]));
        $kit  = $this->kit($user);
        $this->stubImages();

        $this->actingAs($user)
            ->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate")
            ->assertStatus(422);
        $this->assertSame(0, BrandKitAsset::count());
    }

    public function test_render_failure_refunds_the_charge(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages(fail: true);
        $charger = $this->stubCharger();

        $this->actingAs($user)
            ->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate")
            ->assertStatus(422);

        $charger->shouldHaveReceived('refund');
        $this->assertSame(0, BrandKitAsset::count());
    }

    // ── Apply + delete ────────────────────────────────────────────────

    public function test_apply_logo_to_kit_and_delete_asset(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->kit($user);
        $this->stubImages();
        $this->stubCharger();

        $this->actingAs($user)->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate")->assertOk();

        $this->actingAs($user)
            ->postJson("/user/brand-kits/{$kit->id}/assets/logo/apply", ['target' => 'kit_logo'])
            ->assertOk();
        $this->assertNotEmpty($kit->fresh()->config['logo_url'] ?? null);

        $asset  = BrandKitAsset::where('brand_kit_id', $kit->id)->where('type', 'logo')->firstOrFail();
        $fileId = $asset->user_file_id;
        $this->actingAs($user)
            ->deleteJson("/user/brand-kits/{$kit->id}/assets/logo")
            ->assertOk();
        $this->assertSame(0, BrandKitAsset::count());
        $this->assertNull(UserFile::find($fileId));
    }

    public function test_other_users_kit_is_not_accessible(): void
    {
        $owner    = $this->makeUser($this->plan());
        $intruder = $this->makeUser($this->plan());
        $kit      = $this->kit($owner);
        $this->stubImages();

        $this->actingAs($intruder)
            ->getJson("/user/brand-kits/{$kit->id}/assets")
            ->assertStatus(403);
        $this->actingAs($intruder)
            ->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate")
            ->assertStatus(403);
    }

    // ── Vault count exemption ─────────────────────────────────────────

    public function test_brand_asset_files_excluded_from_file_count_but_not_bytes(): void
    {
        $user = $this->makeUser($this->plan(['max_files' => 1, 'max_storage_mb' => 100]));
        $kit  = $this->kit($user);
        $this->stubImages();
        $this->stubCharger();

        $this->actingAs($user)->postJson("/user/brand-kits/{$kit->id}/assets/logo/generate")->assertOk();

        $counted = UserFile::where('user_id', $user->id)->whereNull('context')->count();
        $all     = UserFile::where('user_id', $user->id)->count();
        $this->assertSame(0, $counted);
        $this->assertSame(1, $all);
        $this->assertGreaterThan(0, (int) UserFile::where('user_id', $user->id)->sum('size_bytes'));
    }
}
