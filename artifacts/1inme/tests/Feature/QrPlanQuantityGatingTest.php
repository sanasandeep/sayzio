<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\PlanFormCatalogue;
use App\Modules\Common\Support\PremiumFeatures;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\QrCodeCatalog;
use App\Services\AI\AiUsageCharger;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6666 — QR art style presets + plan quantity gating:
 * `max_qr_codes` (saved-QR cap) and `max_qr_art_monthly` (monthly AI art
 * allowance), both defaulting to -1 = unlimited, enforced on web + API,
 * bypass-permission users unlimited, no PHP_INT_MAX sentinel leaks.
 */
class QrPlanQuantityGatingTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = '9223372036854775807';

    // ---------------- helpers ----------------

    private function makeUser(array $features = []): User
    {
        $slug = 'p' . Str::lower(Str::random(6));
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);
    }

    private function grantBypass(User $user): User
    {
        $role = Role::create([
            'name' => 'Bypass ' . Str::random(4),
            'slug' => 'r-' . Str::lower(Str::random(8)),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.plan_limits.bypass'],
            ['name' => 'Bypass plan limits', 'group' => 'user']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    private function bindWorkspace(User $user): void
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    private function makeQr(User $user, string $name = 'QR'): QrCode
    {
        $this->bindWorkspace($user);
        return QrCode::create([
            'user_id' => $user->id,
            'name'    => $name,
            'type'    => 'url',
            'payload' => ['url' => 'https://example.com'],
            'design'  => [],
        ]);
    }

    private function qrPayload(string $name = 'New QR'): array
    {
        return [
            'name'    => $name,
            'type'    => 'url',
            'payload' => ['url' => 'https://example.com/x'],
            'design'  => [],
        ];
    }

    /** Record one counted (successful) qr_art generation this month. */
    private function seedArtSpend(User $user): WalletTransaction
    {
        app(WalletService::class)->credit($user, 50, ['reason' => 'test']);
        return app(AiUsageCharger::class)->charge($user, 5, ['feature' => 'qr_art']);
    }

    // ---------------- presets ----------------

    public function test_new_style_presets_present(): void
    {
        $presets = QrCodeCatalog::aiArtStylePresets();
        $labels  = array_column($presets, 'label');
        $this->assertGreaterThanOrEqual(21, count($presets));
        foreach ([
            'Cyberpunk', 'Watercolor', // original set survives
            'Anime', '3D Render', 'Pixel Art', 'Origami', 'Stained Glass',
            'Graffiti', 'Line Art', 'Art Deco', 'Steampunk', 'Low-Poly',
            'Winter', 'Autumn', 'Holidays', 'Ocean', 'Coffee',
        ] as $label) {
            $this->assertContains($label, $labels, "Preset '{$label}' missing");
        }
        foreach ($presets as $p) {
            $this->assertNotSame('', trim($p['label']));
            $this->assertNotSame('', trim($p['prompt']));
        }
    }

    // ---------------- plan plumbing ----------------

    public function test_limit_keys_registered_in_plan_catalogues(): void
    {
        $keys = array_column(PlanFormCatalogue::quantityLimits(), null, 'key');
        foreach (['max_qr_codes', 'max_qr_art_monthly'] as $key) {
            $this->assertArrayHasKey($key, $keys);
            $this->assertSame(-1, $keys[$key]['default']);
        }
        $premium = array_column(PremiumFeatures::catalogue(), 'key');
        $this->assertContains('max_qr_codes', $premium);
        $this->assertContains('max_qr_art_monthly', $premium);
    }

    // ---------------- saved-QR cap: web ----------------

    public function test_web_create_blocked_at_cap_and_edit_still_works(): void
    {
        $user = $this->makeUser(['max_qr_codes' => 1]);
        $qr = $this->makeQr($user);
        $this->be($user);
        $this->bindWorkspace($user);

        $res = $this->post(route('user.qr-codes.store'), $this->qrPayload());
        $res->assertSessionHas('error');
        $this->assertStringNotContainsString(self::SENTINEL, (string) session('error'));
        $this->assertSame(1, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());

        // Editing existing QR codes stays unrestricted at cap.
        $this->bindWorkspace($user);
        $this->put(route('user.qr-codes.update', $qr), $this->qrPayload('Renamed'))
            ->assertSessionMissing('error');
        $this->assertSame('Renamed', $qr->fresh()->name);
    }

    public function test_web_duplicate_blocked_at_cap(): void
    {
        $user = $this->makeUser(['max_qr_codes' => 1]);
        $qr = $this->makeQr($user);
        $this->be($user);
        $this->bindWorkspace($user);

        $this->post(route('user.qr-codes.duplicate', $qr))->assertSessionHas('error');
        $this->assertSame(1, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
    }

    public function test_web_create_unlimited_and_bypass_pass(): void
    {
        // Explicit -1 = unlimited.
        $user = $this->makeUser(['max_qr_codes' => -1]);
        $this->makeQr($user);
        $this->be($user);
        $this->bindWorkspace($user);
        $this->post(route('user.qr-codes.store'), $this->qrPayload())->assertSessionMissing('error');
        $this->assertSame(2, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());

        // Bypass permission beats a cap of 1.
        $bypass = $this->grantBypass($this->makeUser(['max_qr_codes' => 1]));
        $this->makeQr($bypass);
        $this->be($bypass);
        $this->bindWorkspace($bypass);
        $this->post(route('user.qr-codes.store'), $this->qrPayload())->assertSessionMissing('error');
        $this->assertSame(2, QrCode::withoutGlobalScope('workspace')->where('user_id', $bypass->id)->count());
    }

    // ---------------- saved-QR cap: API ----------------

    public function test_api_store_and_bulk_blocked_at_cap(): void
    {
        $user = $this->makeUser(['max_qr_codes' => 1]);
        $this->makeQr($user);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/v1/qr-codes', $this->qrPayload());
        $res->assertStatus(403)->assertJsonPath('error.code', 'plan_limit_reached');
        $this->assertStringNotContainsString(self::SENTINEL, $res->getContent());

        // Bulk that would overflow the cap is rejected too.
        $this->withToken($token)
            ->postJson('/api/v1/qr-codes/bulk', ['items' => [$this->qrPayload('a'), $this->qrPayload('b')]])
            ->assertStatus(403);

        $this->assertSame(1, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
    }

    public function test_api_store_allowed_when_unlimited_default(): void
    {
        $user = $this->makeUser([]); // key absent → default -1 = unlimited
        $this->makeQr($user);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/qr-codes', $this->qrPayload())->assertCreated();
    }

    // ---------------- AI art monthly allowance ----------------

    public function test_api_generate_blocked_at_monthly_cap_without_coin_charge(): void
    {
        config(['services.replicate.api_token' => 'test-token']);
        $user = $this->makeUser(['max_qr_art_monthly' => 1]);
        $this->seedArtSpend($user);
        $balance = app(WalletService::class)->getBalance($user->fresh());
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/v1/qr-codes/generate-art', [
            'data' => 'https://example.com', 'prompt' => 'a nice mountain',
        ]);
        $res->assertStatus(403)->assertJsonPath('error.code', 'allowance_exhausted');
        $this->assertStringNotContainsString(self::SENTINEL, $res->getContent());
        // No coin charge happened.
        $this->assertSame($balance, app(WalletService::class)->getBalance($user->fresh()));
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->where('type', 'spend')->count());
    }

    public function test_refunded_generation_does_not_consume_allowance(): void
    {
        config(['services.replicate.api_token' => 'test-token']);
        $user = $this->makeUser(['max_qr_art_monthly' => 1]);
        $tx = $this->seedArtSpend($user);
        // Simulate a failed generation refunded the way QrArtService does.
        app(AiUsageCharger::class)->refund($user, 5, [
            'feature'         => 'qr_art',
            'idempotency_key' => 'qr_art_refund:' . $tx->id,
            'meta'            => ['related_id' => $tx->id],
        ]);

        $art = app(\App\Services\AI\QrArtService::class);
        $this->assertSame(0, $art->monthlyUsed($user->fresh()));
        $this->assertSame(1, $art->monthlyRemaining($user->fresh()));

        // The generate path proceeds past the allowance gate: with a drained
        // wallet it fails on coins (402), NOT on allowance (403).
        app(WalletService::class)->debit($user->fresh(), app(WalletService::class)->getBalance($user->fresh()), ['reason' => 'drain']);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/qr-codes/generate-art', [
            'data' => 'https://example.com', 'prompt' => 'a nice mountain',
        ])->assertStatus(402);
    }

    public function test_generate_unlimited_default_and_bypass(): void
    {
        $art = app(\App\Services\AI\QrArtService::class);

        // Absent key → -1 unlimited.
        $user = $this->makeUser([]);
        $this->assertSame(-1, $art->monthlyAllowance($user));
        $this->assertSame(-1, $art->monthlyRemaining($user));

        // Bypass user with a cap of 1 → unlimited, sentinel normalized to -1.
        $bypass = $this->grantBypass($this->makeUser(['max_qr_art_monthly' => 1]));
        $this->seedArtSpend($bypass);
        $this->assertSame(-1, $art->monthlyAllowance($bypass));
        $this->assertSame(-1, $art->monthlyRemaining($bypass));
    }

    public function test_api_art_availability_reports_normalized_quota(): void
    {
        config(['services.replicate.api_token' => 'test-token']);
        $user = $this->makeUser(['max_qr_art_monthly' => 3]);
        $this->seedArtSpend($user);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/v1/qr-codes/art-availability');
        $res->assertOk()
            ->assertJsonPath('data.monthly_allowance', 3)
            ->assertJsonPath('data.monthly_used', 1)
            ->assertJsonPath('data.monthly_remaining', 2);
        $this->assertStringNotContainsString(self::SENTINEL, $res->getContent());

        // Presets ride along on the same endpoint.
        $labels = array_column($res->json('data.presets'), 'label');
        $this->assertContains('Anime', $labels);
    }
}
