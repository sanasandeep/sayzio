<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile "apply Brand Kit to QR code"
 * round-trip (POST /api/v1/brand-kits/{brandKit}/apply/qr/{qrCode}), the QR
 * sibling of the apply-to-biolink path guarded by BrandKitApplyApiTest.
 *
 * Unlike biolinks there is no BrandConsistencyService equivalent for QR codes,
 * so the truest regression check is to read the persisted QrCode->design back
 * directly and assert the kit's palette actually landed on it: the dark-neutral
 * foreground, the light-neutral background and the primary corner color (see
 * {@see \App\Services\Brand\AiBrandKitService::applyToQr()}). A silent break in
 * the API apply (wrong kit resolved, palette not written, a no-op) would leave
 * the stored design untouched and fail here — the kind of regression that would
 * otherwise only surface to mobile users.
 *
 * Authenticated requests use a real personal access token, NOT
 * Sanctum::actingAs — that injects a Mockery mock the TouchSessionToken
 * middleware can't ->save(), so every authed request would 500
 * (see the sanctum-api-tests convention).
 *
 * Apply itself never calls OpenAI (it just writes the saved kit's palette onto
 * the QR design), so these tests need no AI engine and no chat double.
 */
class BrandKitApplyQrApiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Brand Plan',
            'slug'          => 'brand-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'      => 100,
                'max_biolinks'   => 100,
                'max_brand_kits' => 5,
            ],
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'role' => 'user',
            'plan_id' => $this->plan()->id,
        ])->fresh();
    }

    private function kitFor(User $user): BrandKit
    {
        return BrandKit::create([
            'user_id'    => $user->id,
            'name'       => 'Aurora Studio',
            'slug'       => 'aurora-' . Str::random(6),
            'is_default' => true,
            'config'     => [
                'palette' => [
                    'primary'   => '#3B5BDB',
                    'secondary' => '#5C7CFA',
                    'accent'    => '#F783AC',
                    'neutrals'  => ['#F8F9FA', '#212529'],
                ],
                'fonts'       => ['heading' => 'Poppins', 'body' => 'Inter'],
                'voice'       => ['tone' => 'Warm and confident', 'descriptors' => ['friendly', 'premium']],
                'taglines'    => ['Shine brighter', 'Your brand, elevated'],
                'bio'         => 'A modern studio helping creators look the part.',
                'block_theme' => 'minimal',
            ],
        ]);
    }

    private function qrCode(User $user): QrCode
    {
        // A plain QR with the catalog default colors, so any change after apply
        // is unambiguously the kit's palette landing on it.
        return QrCode::create([
            'user_id' => $user->id,
            'name'    => 'My QR',
            'type'    => 'url',
            'payload' => ['url' => 'https://example.test'],
            'design'  => ['fg_color' => '#000000', 'bg_color' => '#ffffff'],
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function applyUrl(BrandKit $kit, QrCode $qr): string
    {
        return "/api/v1/brand-kits/{$kit->id}/apply/qr/{$qr->id}";
    }

    // ── auth gate ──────────────────────────────────────────────────────

    public function test_apply_requires_authentication(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $qr   = $this->qrCode($user);

        $this->postJson($this->applyUrl($kit, $qr))->assertStatus(401);
    }

    // ── the core round-trip: the palette lands on the QR design ────────

    public function test_api_apply_writes_kit_palette_onto_the_qr_design(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $qr   = $this->qrCode($user);

        $this->withToken($this->token($user))
            ->postJson($this->applyUrl($kit, $qr))
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.qr_code.id', $qr->id);

        // Read the persisted design back directly — there is no QR audit
        // service, so the regression check is the stored row itself. Colors
        // are normalized to lowercase #rrggbb by AiBrandKitService::applyToQr.
        $design = $qr->fresh()->design;
        $this->assertSame('#212529', $design['fg_color'] ?? null);            // darkest neutral
        $this->assertSame('#f8f9fa', $design['bg_color'] ?? null);            // lightest neutral
        $this->assertSame('#3b5bdb', $design['corner_square_color'] ?? null); // palette.primary
        $this->assertSame('#f783ac', $design['corner_dot_color'] ?? null);    // palette.accent
    }

    // ── ownership parity: can't reach QR codes / kits you don't own ────

    public function test_api_apply_404s_on_a_qr_owned_by_another_user(): void
    {
        $caller = $this->makeUser();
        $kit    = $this->kitFor($caller);

        // A QR code that belongs to a different user entirely.
        $stranger   = $this->makeUser();
        $strangerQr = $this->qrCode($stranger);

        $this->withToken($this->token($caller))
            ->postJson($this->applyUrl($kit, $strangerQr))
            ->assertStatus(404);

        // The stranger's QR must be untouched (palette never landed).
        $design = $strangerQr->fresh()->design;
        $this->assertSame('#000000', $design['fg_color'] ?? null);
        $this->assertSame('#ffffff', $design['bg_color'] ?? null);
        $this->assertArrayNotHasKey('corner_square_color', $design);
    }

    public function test_api_apply_404s_when_the_kit_belongs_to_another_user(): void
    {
        $caller = $this->makeUser();
        $qr     = $this->qrCode($caller);

        // A kit owned by someone else — the caller must not be able to wield it.
        $stranger    = $this->makeUser();
        $strangerKit = $this->kitFor($stranger);

        $this->withToken($this->token($caller))
            ->postJson($this->applyUrl($strangerKit, $qr))
            ->assertStatus(404);

        // The caller's own QR must be untouched.
        $design = $qr->fresh()->design;
        $this->assertSame('#000000', $design['fg_color'] ?? null);
        $this->assertSame('#ffffff', $design['bg_color'] ?? null);
        $this->assertArrayNotHasKey('corner_square_color', $design);
    }
}
