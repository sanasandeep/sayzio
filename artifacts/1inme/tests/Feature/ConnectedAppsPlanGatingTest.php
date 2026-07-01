<?php

namespace Tests\Feature;

use App\Jobs\ForwardAnalyticsEventJob;
use App\Jobs\PushLeadToCrmJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in that Connected Apps behavior is gated on the `connected_apps` plan
 * feature at every layer — not just at connect time. The regression that
 * matters: a creator who connected a CRM / GA property while entitled must stop
 * syncing and forwarding after a downgrade, and can no longer reconfigure or
 * manually sync a connection, while a still-entitled creator keeps full access.
 *
 * Layers covered:
 *   - background workers (PushLeadToCrmJob / ForwardAnalyticsEventJob
 *     ::shouldQueue), which gate both dispatch and the scheduled pull path,
 *   - the REST mutation/sync endpoints (Api\ConnectedAppController
 *     update / syncNow).
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware, so we mint a real token.
 */
class ConnectedAppsPlanGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $entitled): User
    {
        $plan = Plan::create([
            'name'               => $entitled ? 'Pro' : 'Free',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => ['connected_apps' => $entitled],
        ]);

        $user = User::create([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    private function crm(User $user): ConnectedApp
    {
        return ConnectedApp::create([
            'user_id'      => $user->id,
            'provider'     => 'hubspot',
            'kind'         => 'crm',
            'status'       => ConnectedApp::STATUS_CONNECTED,
            'push_enabled' => true,
            'pull_enabled' => true,
            'connected_at' => now(),
        ]);
    }

    private function analytics(User $user): ConnectedApp
    {
        return ConnectedApp::create([
            'user_id'      => $user->id,
            'provider'     => 'google_analytics',
            'kind'         => 'analytics',
            'status'       => ConnectedApp::STATUS_CONNECTED,
            'settings'     => ['measurement_id' => 'G-TEST123'],
            'connected_at' => now(),
        ]);
    }

    // ── Background workers hard-stop after downgrade ───────────────────

    public function test_crm_push_job_is_gated_by_plan_feature(): void
    {
        $downgraded = $this->makeUser(false);
        $this->crm($downgraded);
        $this->assertFalse(
            PushLeadToCrmJob::shouldQueue($downgraded->id),
            'a connected CRM must not push once the plan drops connected_apps'
        );

        $entitled = $this->makeUser(true);
        $this->crm($entitled);
        $this->assertTrue(PushLeadToCrmJob::shouldQueue($entitled->id));
    }

    public function test_analytics_forward_job_is_gated_by_plan_feature(): void
    {
        $downgraded = $this->makeUser(false);
        $this->analytics($downgraded);
        $this->assertFalse(
            ForwardAnalyticsEventJob::shouldQueue($downgraded->id),
            'a connected GA property must not forward once the plan drops connected_apps'
        );

        $entitled = $this->makeUser(true);
        $this->analytics($entitled);
        $this->assertTrue(ForwardAnalyticsEventJob::shouldQueue($entitled->id));
    }

    // ── REST mutation / sync endpoints ─────────────────────────────────

    public function test_api_update_is_gated_by_plan_feature(): void
    {
        $downgraded = $this->makeUser(false);
        $conn = $this->crm($downgraded);
        $token = $downgraded->createToken('t')->plainTextToken;

        $blocked = $this->withToken($token)
            ->patchJson('/api/v1/connected-apps/' . $conn->id, ['paused' => true]);
        $blocked->assertStatus(402);
        $blocked->assertJsonPath('error.code', 'plan_upgrade_required');
        $this->assertNull($conn->fresh()->paused_at);

        $entitled = $this->makeUser(true);
        $conn2 = $this->crm($entitled);
        $token2 = $entitled->createToken('t')->plainTextToken;
        $ok = $this->withToken($token2)
            ->patchJson('/api/v1/connected-apps/' . $conn2->id, ['paused' => true]);
        $ok->assertOk();
        $this->assertNotNull($conn2->fresh()->paused_at);
    }

    public function test_api_sync_is_gated_by_plan_feature(): void
    {
        $downgraded = $this->makeUser(false);
        $conn = $this->crm($downgraded);
        $token = $downgraded->createToken('t')->plainTextToken;

        $blocked = $this->withToken($token)
            ->postJson('/api/v1/connected-apps/' . $conn->id . '/sync');
        $blocked->assertStatus(402);
        $blocked->assertJsonPath('error.code', 'plan_upgrade_required');
    }

    // ── Per-app sync status is surfaced in the API payload ─────────────

    public function test_api_index_exposes_sync_status_fields(): void
    {
        $user = $this->makeUser(true);
        $conn = $this->crm($user);
        $conn->forceFill([
            'records_sent'     => 7,
            'records_pulled'   => 3,
            'last_synced_at'   => now()->subHour(),
            'last_sync_status' => 'error',
            'last_sync_error'  => 'Token expired — reconnect required.',
        ])->save();
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/v1/connected-apps');
        $res->assertOk();

        // Locate this provider's connection payload in the providers list.
        $providers = collect($res->json('data.providers'));
        $entry = $providers->firstWhere('connection.id', $conn->id);
        $this->assertNotNull($entry, 'the connected provider must appear in the index');

        $this->assertSame(7, $entry['connection']['records_sent']);
        $this->assertSame(3, $entry['connection']['records_pulled']);
        $this->assertSame('error', $entry['connection']['last_sync_status']);
        $this->assertSame(
            'Token expired — reconnect required.',
            $entry['connection']['last_sync_error']
        );
        $this->assertNotNull($entry['connection']['last_synced_at']);
    }
}
