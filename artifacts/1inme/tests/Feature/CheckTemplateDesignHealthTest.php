<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the scheduled template design-health alert
 * (`templates:check-design-health`), which mirrors the schema-health alert:
 * it re-validates every active page/card template snapshot and pages ops
 * admins when a saved template develops design issues, sends an all-clear
 * when resolved, and stays quiet (cooldown) when nothing changed.
 *
 * Note: the command emails via Mail::raw(), which MailFake records as a
 * no-op, so these tests assert on the reliable in-app notification signal
 * (and keep Mail::fake() only to stop real delivery during the run).
 */
class CheckTemplateDesignHealthTest extends TestCase
{
    use RefreshDatabase;

    /** A user holding the ops-alerts permission (the alert audience). */
    private function makeOpsAdmin(): User
    {
        $role = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::random(4),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.ops_alerts.receive'],
            ['name' => 'Receive operational alerts', 'group' => 'user-app'],
        );
        $role->permissions()->attach($perm->id);

        $user = User::create([
            'name'              => 'Ops Olivia',
            'email'             => 'olivia' . Str::random(6) . '@ops.test',
            'password'          => bcrypt('secret'),
            'status'            => 'active',
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();

        return $user;
    }

    private function makePageTemplate(array $snapshot, bool $active = true): PageTemplate
    {
        return PageTemplate::create([
            'slug'                 => 'tpl-' . Str::random(8),
            'name'                 => 'Template ' . Str::random(4),
            'category'             => 'general',
            'description'          => 'Test template.',
            'is_active'            => $active,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => $snapshot,
        ]);
    }

    public function test_no_alert_when_all_templates_valid(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        // A valid snapshot: a known block type, no broken variant.
        $this->makePageTemplate([
            'blocks' => [
                ['type' => 'link', 'settings' => []],
            ],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
        $this->assertFalse((bool) (AppSetting::get('template_design_health', [])['alerting'] ?? false));
    }

    public function test_alerts_admins_when_a_template_is_broken(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        // An unknown block type — would render as a blank/unknown block.
        $this->makePageTemplate([
            'blocks' => [
                ['type' => 'definitely_not_a_real_block_type', 'settings' => []],
            ],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        // In-app alert to the ops admin.
        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'template_design_issues')
            ->first();
        $this->assertNotNull($note, 'ops admin should get an in-app template-design alert');
        $this->assertSame(1, (int) ($note->data['broken_count'] ?? 0));

        // Episode state recorded.
        $state = AppSetting::get('template_design_health', []);
        $this->assertTrue((bool) ($state['alerting'] ?? false));
        $this->assertSame(1, (int) ($state['last_count'] ?? 0));
    }

    public function test_cooldown_suppresses_repeat_alert_for_same_broken_set(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        $this->makePageTemplate([
            'blocks' => [['type' => 'nope_not_real', 'settings' => []]],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);
        // Second run with the SAME broken set inside the cooldown: no new alert.
        $this->artisan('templates:check-design-health')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $admin->id)
            ->where('type', 'template_design_issues')->count());
        Mail::assertSentCount(1);
    }

    public function test_newly_broken_template_bypasses_cooldown(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        $this->makePageTemplate([
            'blocks' => [['type' => 'bad_one', 'settings' => []]],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        // A second template breaks — the broken SET changed, so re-alert even
        // though we're inside the cooldown window.
        $this->makePageTemplate([
            'blocks' => [['type' => 'bad_two', 'settings' => []]],
        ]);
        $this->artisan('templates:check-design-health')->assertExitCode(0);

        $this->assertSame(2, UserNotification::where('user_id', $admin->id)
            ->where('type', 'template_design_issues')->count());
        Mail::assertSentCount(2);
    }

    public function test_sends_all_clear_when_resolved(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        $broken = $this->makePageTemplate([
            'blocks' => [['type' => 'broken_type', 'settings' => []]],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        // Fix the template, then re-run: expect an all-clear.
        $broken->update(['snapshot' => ['blocks' => [['type' => 'link', 'settings' => []]]]]);
        $this->artisan('templates:check-design-health')->assertExitCode(0);

        $this->assertNotNull(UserNotification::where('user_id', $admin->id)
            ->where('type', 'template_design_ok')->first());
        $this->assertFalse((bool) (AppSetting::get('template_design_health', [])['alerting'] ?? false));
    }

    public function test_inactive_templates_are_ignored(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        $this->makePageTemplate([
            'blocks' => [['type' => 'broken_inactive', 'settings' => []]],
        ], active: false);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_broken_card_template_is_detected(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        // A card snapshot is a single block at the root.
        CardTemplate::create([
            'slug'        => 'card-' . Str::random(8),
            'name'        => 'Bad Card',
            'category'    => 'general',
            'description' => 'Test card.',
            'is_active'   => true,
            'sort_order'  => 0,
            'snapshot'    => ['type' => 'unknown_card_block', 'children' => []],
        ]);

        $this->artisan('templates:check-design-health')->assertExitCode(0);

        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'template_design_issues')->first();
        $this->assertNotNull($note);
        $this->assertSame('card', $note->data['broken'][0]['kind'] ?? null);
    }
}
