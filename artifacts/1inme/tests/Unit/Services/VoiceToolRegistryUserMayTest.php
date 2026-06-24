<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\Voice\VoiceToolRegistry;
use App\Services\Billing\WalletService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit coverage for VoiceToolRegistry::userMay.
 *
 * userMay is the single chokepoint that decides which tools are
 * advertised to the LLM (`functionDefinitionsFor`), surfaced in the
 * "What I can do" panel (`visibleTo`), and re-checked at execute time.
 * If it ever silently lets an admin-only tool through to a regular
 * user, we leak privileged actions to the model. These tests exercise
 * the gate directly via reflection plus the public projections.
 */
class VoiceToolRegistryUserMayTest extends TestCase
{
    protected VoiceToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        // Pure unit tests: registry only touches AiUsageCharger /
        // WalletService inside the *handlers* (not in userMay), so
        // mocks with no expectations are safe here.
        $this->registry = new VoiceToolRegistry(
            Mockery::mock(AiUsageCharger::class),
            Mockery::mock(WalletService::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Reflection helper to call the protected userMay method. */
    private function may(array $spec, bool $isAdmin): bool
    {
        $u = new User(['id' => 1, 'name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;
        $m = new ReflectionMethod(VoiceToolRegistry::class, 'userMay');
        $m->setAccessible(true);
        return (bool) $m->invoke($this->registry, $u, $spec, $isAdmin);
    }

    public function test_admin_only_tool_is_denied_to_normal_user(): void
    {
        $spec = ['role' => 'admin', 'destructive' => false];
        $this->assertFalse($this->may($spec, false));
    }

    public function test_admin_only_tool_is_allowed_for_admin_caller(): void
    {
        $spec = ['role' => 'admin', 'destructive' => false];
        $this->assertTrue($this->may($spec, true));
    }

    public function test_user_role_tool_with_no_permission_is_open_to_anyone(): void
    {
        $spec = ['role' => 'user', 'destructive' => false];
        $this->assertTrue($this->may($spec, false));
        $this->assertTrue($this->may($spec, true));
    }

    public function test_permission_required_tool_is_blocked_when_no_workspace_or_user_bound(): void
    {
        // WorkspacePermissions::userCan() returns false when no
        // current_workspace is bound (e.g. unit context). This proves
        // the gate fails *closed* rather than open.
        $spec = ['role' => 'user', 'destructive' => false, 'permission' => 'links.create'];
        $this->assertFalse($this->may($spec, false));
        $this->assertFalse($this->may($spec, true));
    }

    public function test_visible_to_strips_admin_tools_for_non_admin_callers(): void
    {
        $u = new User(['name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;
        $tools = $this->registry->visibleTo($u, false);

        // Admin-only entries from the catalogue must not appear.
        $this->assertArrayNotHasKey('admin_grant_credits', $tools);

        // Tools with no permission requirement should always be present.
        $this->assertArrayHasKey('navigate', $tools);
        $this->assertArrayHasKey('get_credit_balance', $tools);
        $this->assertArrayHasKey('switch_plan', $tools);
    }

    public function test_visible_to_includes_admin_tools_for_admin_callers(): void
    {
        $u = new User(['name' => 'a', 'email' => 'a@example.com']);
        $u->id = 1;
        $tools = $this->registry->visibleTo($u, true);

        $this->assertArrayHasKey('admin_grant_credits', $tools);
        $this->assertSame('admin', $tools['admin_grant_credits']['role']);
    }

    public function test_function_definitions_for_normal_user_omit_admin_tools(): void
    {
        $u = new User(['name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;
        $defs = $this->registry->functionDefinitionsFor($u, false);
        $names = array_map(fn($d) => $d['function']['name'], $defs);

        $this->assertNotContains('admin_grant_credits', $names);
        $this->assertContains('navigate', $names);
    }

    public function test_execute_re_checks_permission_for_admin_only_tool(): void
    {
        $u = new User(['name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;

        // Even if the model somehow asks for admin_grant_credits as a
        // non-admin user (e.g. a smuggled tool name), execute() must
        // refuse rather than fall through to the handler.
        $result = $this->registry->execute($u, false, 'admin_grant_credits', [
            'user_id' => 1,
            'credits' => 10,
        ], confirmed: true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString("don't have permission", $result['error']);
    }

    // ── surface-driving tools (#2120) ─────────────────────────────
    //
    // search_app / explain_link_type are read-only with no permission,
    // so they must always be advertised. choose_link_type and the
    // wizard_* tools carry `permission => links.create`, so they must
    // fail *closed* in a context with no bound workspace — exactly like
    // any other permission-gated tool — rather than leaking to the LLM.

    public function test_read_only_surface_tools_are_open_to_any_user(): void
    {
        $u = new User(['name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;
        $tools = $this->registry->visibleTo($u, false);

        $this->assertArrayHasKey('search_app', $tools);
        $this->assertArrayHasKey('explain_link_type', $tools);
    }

    public function test_create_permission_surface_tools_fail_closed_without_workspace(): void
    {
        foreach (['choose_link_type', 'wizard_set_answer', 'wizard_advance', 'wizard_generate'] as $name) {
            $spec = ['role' => 'user', 'destructive' => $name === 'wizard_generate', 'permission' => 'links.create'];
            $this->assertFalse(
                $this->may($spec, false),
                "{$name} must fail closed when no workspace permission is bound.",
            );
        }
    }

    public function test_function_definitions_for_user_include_read_only_surface_tools(): void
    {
        $u = new User(['name' => 'u', 'email' => 'u@example.com']);
        $u->id = 1;
        $defs  = $this->registry->functionDefinitionsFor($u, false);
        $names = array_map(fn($d) => $d['function']['name'], $defs);

        $this->assertContains('search_app', $names);
        $this->assertContains('explain_link_type', $names);
    }
}
