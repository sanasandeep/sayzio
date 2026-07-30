<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for Task #6193 / #6198: users holding the
 * `user.plan_limits.bypass` permission get PHP_INT_MAX back from
 * getPlanFeature() for every numeric plan limit. No rendered page or
 * JSON payload may ever surface that raw sentinel
 * ("9223372036854775807") to the client — surfaces must translate it
 * into an "Unlimited" presentation instead.
 *
 * This test logs in as a bypass-permission user and sweeps the key
 * plan-limit surfaces (team, calendar accounts, API keys / developer
 * settings, files + files quota, /api/v1/auth/me, /api/v1/me/api-usage),
 * failing whenever the sentinel appears anywhere in the response body.
 * Any future controller/resource that passes a raw getPlanFeature()
 * number through to a view or API payload trips this cheaply.
 */
class PlanLimitBypassSentinelLeakTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = '9223372036854775807';

    private function makeBypassUser(): User
    {
        $slug = 'p' . Str::lower(Str::random(6));
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            // api_access so the developer/API-keys page renders instead of
            // redirecting to the upgrade page (bypass also grants it, but
            // be explicit about the surface under test).
            'features' => ['api_access' => true],
        ]);

        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);

        $role = Role::create([
            'name'  => 'Bypass ' . Str::random(4),
            'slug'  => 'r-' . Str::lower(Str::random(8)),
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

    /** Bind the user's workspace so workspace.can-gated routes resolve. */
    private function bindWorkspace(User $user): void
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    private function assertNoSentinel(string $body, string $surface): void
    {
        $this->assertStringNotContainsString(
            self::SENTINEL,
            $body,
            "Surface [{$surface}] leaked the raw PHP_INT_MAX plan-limit sentinel. " .
            'Bypass-permission users must see an "Unlimited" presentation, never ' .
            'the raw 9-quintillion number. Translate the getPlanFeature() value ' .
            'before it reaches the view / API payload.'
        );
    }

    /** Sanity: the bypass scenario is real — numeric limits ARE the sentinel. */
    public function test_bypass_user_gets_php_int_max_from_plan_features(): void
    {
        $user = $this->makeBypassUser();

        $this->assertSame(PHP_INT_MAX, $user->getPlanFeature('max_seats_per_workspace', 1));
        $this->assertSame(self::SENTINEL, (string) PHP_INT_MAX);
    }

    /**
     * Web plan-limit surfaces rendered for a bypass user must never
     * contain the raw sentinel.
     */
    public function test_web_plan_limit_pages_never_render_the_sentinel(): void
    {
        $user = $this->makeBypassUser();
        $this->bindWorkspace($user);

        $pages = [
            '/user/team',
            '/user/calendar',
            '/user/settings/developer',
            '/user/files',
        ];

        foreach ($pages as $path) {
            $res = $this->actingAs($user, 'web')->get($path);
            $this->assertTrue(
                $res->status() < 400,
                "Surface [{$path}] unexpectedly failed with HTTP {$res->status()} — " .
                'the sentinel sweep needs the page to render.'
            );
            $this->assertNoSentinel($res->getContent(), $path);
        }
    }

    public function test_files_quota_json_never_contains_the_sentinel(): void
    {
        $user = $this->makeBypassUser();
        $this->bindWorkspace($user);

        $res = $this->actingAs($user, 'web')->getJson('/user/files/quota');
        $res->assertOk();
        $this->assertNoSentinel($res->getContent(), '/user/files/quota');
    }

    /**
     * API plan-limit payloads for a bypass user must never contain the raw
     * sentinel (uses a REAL bearer token, per the Sanctum test convention).
     */
    public function test_api_payloads_never_contain_the_sentinel(): void
    {
        $user  = $this->makeBypassUser();
        $token = $user->createToken('t')->plainTextToken;

        foreach (['/api/v1/auth/me', '/api/v1/me/api-usage'] as $path) {
            $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->getJson($path);
            $res->assertOk();
            $this->assertNoSentinel($res->getContent(), $path);
        }
    }
}
