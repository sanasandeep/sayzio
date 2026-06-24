<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The self user payload (`GET /api/v1/auth/me`) must expose
 * `capabilities.analytics_export` so the mobile Stats screen can hide its
 * "Export CSV" control + show an upgrade prompt without a second round-trip.
 * If this silently drops or flips, mobile would either leak a paid export to
 * free plans or hide it from paid plans.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the TouchSessionToken
 * middleware — every authed request would 500).
 */
class SelfUserAnalyticsExportCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function plan(bool $analyticsExport): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => ['analytics_export' => $analyticsExport],
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        return User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_me_payload_exposes_analytics_export_false_for_free_plan(): void
    {
        $u = $this->user($this->plan(false));
        $resp = $this->withToken($this->token($u))->getJson('/api/v1/auth/me');

        $resp->assertOk();
        $resp->assertJsonPath('data.user.capabilities.analytics_export', false);
    }

    public function test_me_payload_exposes_analytics_export_true_for_paid_plan(): void
    {
        $u = $this->user($this->plan(true));
        $resp = $this->withToken($this->token($u))->getJson('/api/v1/auth/me');

        $resp->assertOk();
        $resp->assertJsonPath('data.user.capabilities.analytics_export', true);
    }
}
