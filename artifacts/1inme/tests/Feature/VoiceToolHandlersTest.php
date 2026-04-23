<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use App\Services\AI\Voice\VoiceToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Per-handler side-effect coverage for the destructive Voice Assistant
 * tools.
 *
 * VoiceAssistantTest (#575) verifies the *envelope* protocol: the
 * orchestrator short-circuits destructive tools into `confirm_required`
 * on the first pass and re-invokes them on the confirmed re-run. It
 * does NOT verify what the underlying handler closures actually do
 * once executed. Without this suite, a regression that silently
 * stopped deleting links, dispatching the digest, or granting credits
 * would still pass the existing tests.
 *
 * Each test calls the destructive tool's handler directly through the
 * registry catalogue (`tools()['name']['handler']`) so that the closure
 * body is exercised end-to-end against the real database / queue. The
 * workspace permission gate that `execute()` applies *before* the
 * handler is independently covered by VoiceToolRegistryUserMayTest.
 *
 * Asserted side-effects per tool:
 *   - delete_biolink: row removed for owner, refused for unknown ids,
 *     refused across users (cross-user row remains in DB).
 *   - send_digest: dispatches the `followers:send-digest` Artisan
 *     command scoped to the calling user's id.
 *   - admin_grant_credits: the target user's AI credit balance
 *     actually increases (and unknown ids / cross-user data are
 *     rejected with a clean error envelope).
 *   - switch_plan: navigation-only by design (real billing must go
 *     through the upgrade UI). Asserts that calling it never silently
 *     mutates the caller's plan_id.
 */
class VoiceToolHandlersTest extends TestCase
{
    use RefreshDatabase;

    private VoiceToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(VoiceToolRegistry::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(string $tag = 'h'): User
    {
        return User::create([
            'name'     => 'Voice ' . $tag,
            'email'    => $tag . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    private function makeLink(User $owner, string $alias): int
    {
        return (int) DB::table('links')->insertGetId([
            'user_id'    => $owner->id,
            'alias'      => $alias,
            'type'       => 'url',
            'long_url'   => 'https://example.com',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Invoke a tool's handler closure directly from the registry catalogue. */
    private function runHandler(string $name, User $user, array $args = []): array
    {
        $tools = $this->registry->tools();
        $this->assertArrayHasKey($name, $tools, "Tool '{$name}' is missing from the registry.");
        return ($tools[$name]['handler'])($user, $args);
    }

    // ── delete_biolink ────────────────────────────────────────────

    public function test_delete_biolink_removes_the_row_for_the_owner(): void
    {
        $user = $this->makeUser('del-ok');
        $linkId = $this->makeLink($user, 'own-' . Str::random(6));

        $result = $this->runHandler('delete_biolink', $user, ['link_id' => $linkId]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString("Deleted link #{$linkId}", $result['summary']);
        $this->assertDatabaseMissing('links', ['id' => $linkId]);
    }

    public function test_delete_biolink_refuses_an_unknown_link_id(): void
    {
        $user = $this->makeUser('del-unknown');

        $result = $this->runHandler('delete_biolink', $user, ['link_id' => 999_999]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString("couldn't find that link", $result['error']);
    }

    public function test_delete_biolink_refuses_a_zero_or_missing_id(): void
    {
        $user = $this->makeUser('del-zero');

        $resultZero = $this->runHandler('delete_biolink', $user, ['link_id' => 0]);
        $this->assertArrayHasKey('error', $resultZero);
        $this->assertStringContainsString('valid link id', $resultZero['error']);

        $resultMissing = $this->runHandler('delete_biolink', $user, []);
        $this->assertArrayHasKey('error', $resultMissing);
        $this->assertStringContainsString('valid link id', $resultMissing['error']);
    }

    public function test_delete_biolink_refuses_to_delete_another_users_link(): void
    {
        $owner    = $this->makeUser('del-owner');
        $stranger = $this->makeUser('del-stranger');
        $linkId   = $this->makeLink($owner, 'stranger-' . Str::random(6));

        $result = $this->runHandler('delete_biolink', $stranger, ['link_id' => $linkId]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString("couldn't find that link", $result['error']);
        $this->assertDatabaseHas('links', ['id' => $linkId, 'user_id' => $owner->id]);
    }

    // ── send_digest ──────────────────────────────────────────────

    public function test_send_digest_dispatches_the_followers_digest_command_for_the_caller(): void
    {
        $user = $this->makeUser('digest');

        // Spy on the Artisan facade so we can verify the exact command
        // and arguments without actually emailing anyone in the test.
        Artisan::shouldReceive('call')
            ->once()
            ->with('followers:send-digest', Mockery::on(function ($args) use ($user) {
                return ($args['--user'] ?? null) === $user->id
                    && !empty($args['--any-hour'])
                    && !empty($args['--force']);
            }))
            ->andReturn(0);

        $result = $this->runHandler('send_digest', $user);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString('queued', strtolower($result['summary']));
        $this->assertArrayHasKey('navigate_to', $result);
    }

    public function test_send_digest_returns_a_clean_error_when_dispatch_fails(): void
    {
        $user = $this->makeUser('digest-fail');

        Artisan::shouldReceive('call')
            ->once()
            ->andThrow(new \RuntimeException('queue is down'));

        $result = $this->runHandler('send_digest', $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('queue is down', $result['error']);
    }

    // ── switch_plan ──────────────────────────────────────────────

    public function test_switch_plan_navigates_to_upgrade_and_does_not_silently_swap_the_plan(): void
    {
        // switch_plan is intentionally navigation-only: the user must
        // confirm in the upgrade UI so a real charge / downgrade is
        // never silently triggered by the assistant. This test pins
        // that contract — if a future change starts mutating the
        // user's plan in this handler, billing review must catch it.
        $currentPlan = Plan::create([
            'name' => 'Current', 'slug' => 'current-' . Str::random(4),
            'monthly_price' => 0, 'annual_price' => 0,
        ]);
        $targetPlan = Plan::create([
            'name' => 'Premium', 'slug' => 'premium-' . Str::random(4),
            'monthly_price' => 9, 'annual_price' => 90,
        ]);

        $user = $this->makeUser('swp');
        $user->plan_id = $currentPlan->id;
        $user->save();

        $result = $this->runHandler('switch_plan', $user, ['plan_slug' => $targetPlan->slug]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString($targetPlan->slug, $result['summary']);
        $this->assertArrayHasKey('navigate_to', $result);

        $this->assertSame(
            $currentPlan->id,
            $user->fresh()->plan_id,
            'switch_plan must NEVER mutate the user plan_id directly — billing flows must go through the upgrade UI.'
        );
    }

    // ── admin_grant_credits ───────────────────────────────────────

    public function test_admin_grant_credits_increases_the_target_balance_on_confirmed_run(): void
    {
        $admin  = $this->makeUser('adm-ok');
        $target = $this->makeUser('tgt-ok');

        $balanceBefore = app(AiCreditService::class)->getBalance($target);

        $result = $this->runHandler('admin_grant_credits', $admin, [
            'user_id' => $target->id,
            'credits' => 250,
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString('250', $result['summary']);

        $this->assertSame(
            $balanceBefore + 250,
            app(AiCreditService::class)->getBalance($target),
            'admin_grant_credits must actually credit the target user when run (after registry confirmation).'
        );
    }

    public function test_admin_grant_credits_refuses_an_unknown_target_user(): void
    {
        $admin = $this->makeUser('adm-unknown');

        $result = $this->runHandler('admin_grant_credits', $admin, [
            'user_id' => 999_999,
            'credits' => 50,
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString("couldn't find that user", $result['error']);
    }

    public function test_admin_grant_credits_refuses_a_non_positive_amount(): void
    {
        $admin  = $this->makeUser('adm-zero');
        $target = $this->makeUser('tgt-zero');

        $balanceBefore = app(AiCreditService::class)->getBalance($target);

        $result = $this->runHandler('admin_grant_credits', $admin, [
            'user_id' => $target->id,
            'credits' => 0,
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(
            $balanceBefore,
            app(AiCreditService::class)->getBalance($target),
            'A rejected grant must NOT mutate the target balance.'
        );
    }
}
