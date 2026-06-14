<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\ApiUsageCounter;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proactive monthly API-allowance warnings (task #1396).
 *
 * The MeterApiUsage middleware warns a developer-key holder:
 *   - once when they cross 80% of the plan's monthly included allowance,
 *   - once when they exhaust it (100%, now on coin overage),
 *   - once when overage can no longer be covered (wallet disabled / out
 *     of coins) and their calls start getting rejected.
 *
 * Each warning fires at most once per (user, period). We assert on the
 * in-app `user_notifications` rows — these are written in the same
 * best-effort delivery loop as the emails (Mail::raw isn't recordable
 * by Mail::fake), so they're the reliable signal that a warning fired.
 *
 * Auth uses a real Bearer api_key token (Sanctum::actingAs would bypass
 * the TouchSessionToken middleware this route group runs).
 */
class ApiUsageWarningTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAllowance(int $allowance): array
    {
        $plan = Plan::create([
            'name'          => 'p' . Str::random(4),
            'slug'          => 'p' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => [
                'api_access'        => true,
                'api_calls_monthly' => $allowance,
            ],
        ]);

        $user = User::create([
            'name'     => 'Dev',
            'email'    => 'dev-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $plan->id,
        ]);

        $new = $user->createToken('key', ['*']);
        $new->accessToken->forceFill(['client_kind' => 'api_key'])->save();

        return [$user, $new->plainTextToken];
    }

    private function hit(string $token): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/auth/me');
    }

    private function warnings(User $user): \Illuminate\Support\Collection
    {
        return UserNotification::where('user_id', $user->id)
            ->where('type', 'api.usage_warning')
            ->orderBy('id')
            ->get();
    }

    public function test_fires_80_then_100_then_overage_blocked_warnings_once_each(): void
    {
        // Allowance 5 → 80% threshold = ceil(5*0.8) = 4.
        [$user, $token] = $this->makeUserWithAllowance(5);

        // Calls 1–3: under 80%, no warning yet.
        for ($i = 0; $i < 3; $i++) {
            $this->hit($token)->assertOk();
        }
        $this->assertCount(0, $this->warnings($user));

        // Call 4: used hits 4 (80%) → the 80% warning fires.
        $this->hit($token)->assertOk();
        $w = $this->warnings($user);
        $this->assertCount(1, $w);
        $this->assertEquals(80, $w[0]->data['threshold']);

        // Call 5: used hits 5 (100%) → the 100% warning fires.
        $this->hit($token)->assertOk();
        $w = $this->warnings($user);
        $this->assertCount(2, $w);
        $this->assertEquals(100, $w[1]->data['threshold']);

        // Call 6: allowance exhausted + wallet disabled → 402 rejection
        // and the overage-blocked warning fires.
        $this->hit($token)->assertStatus(402);
        $w = $this->warnings($user);
        $this->assertCount(3, $w);
        $this->assertEquals('overage_blocked', $w[2]->data['threshold']);

        // Call 7: still rejected, but no duplicate warning (deduped).
        $this->hit($token)->assertStatus(402);
        $this->assertCount(3, $this->warnings($user));

        // Dedup stamps recorded on the period counter.
        $counter = ApiUsageCounter::where('user_id', $user->id)
            ->where('period', ApiUsageCounter::currentPeriod())
            ->first();
        $this->assertNotNull($counter->warned_80_at);
        $this->assertNotNull($counter->warned_100_at);
        $this->assertNotNull($counter->overage_unavailable_notified_at);
    }

    public function test_unlimited_allowance_never_warns(): void
    {
        // -1 = unlimited.
        [$user, $token] = $this->makeUserWithAllowance(-1);

        for ($i = 0; $i < 10; $i++) {
            $this->hit($token)->assertOk();
        }

        $this->assertCount(0, $this->warnings($user));
    }
}
