<?php

namespace Tests\Feature;

use App\Console\Commands\CheckBgTemplateLibrary;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\BgTemplate;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the scheduled background-template-library health alert
 * (`bg-templates:check-library`), which mirrors the onboarding gallery
 * watchdog: it alerts ops admins when active bg_templates drops to zero (or
 * below the floor), stays quiet within the cooldown for the same episode,
 * escalates immediately when a low episode worsens to empty, and sends an
 * all-clear on recovery.
 *
 * Note: the command emails via Emailer (Mail::raw under the hood), which
 * MailFake records as a no-op, so these tests assert on the reliable in-app
 * notification signal (and keep Mail::fake() only to stop real delivery).
 */
class CheckBgTemplateLibraryTest extends TestCase
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

    private function seedActiveTemplates(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            BgTemplate::create([
                'name'          => 'BG ' . Str::random(6),
                'slug'          => 'bg-' . Str::lower(Str::random(10)),
                'preview_color' => '#123456',
                'css'           => 'background: #123456;',
                'category'      => 'test',
                'is_active'  => true,
                'sort_order' => $i,
            ]);
        }
    }

    public function test_no_alert_when_library_at_or_above_floor(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(CheckBgTemplateLibrary::MIN_ACTIVE);

        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_alerts_admin_when_library_empty(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();

        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'bg_template_library_empty')->first();
        $this->assertNotNull($note, 'Expected an empty-library in-app alert.');

        $state = AppSetting::get('bg_template_health', []);
        $this->assertTrue((bool) ($state['alerting'] ?? false));
        $this->assertSame('empty', $state['signature'] ?? null);
    }

    public function test_alerts_admin_when_library_below_floor(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(3);

        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'bg_template_library_low')->first();
        $this->assertNotNull($note, 'Expected a low-library in-app alert.');
        $this->assertSame(3, $note->data['active_count'] ?? null);
    }

    public function test_cooldown_suppresses_repeat_alert_for_same_episode(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();

        $this->artisan('bg-templates:check-library')->assertExitCode(0);
        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $admin->id)
                ->where('type', 'bg_template_library_empty')->count(),
            'Second run within cooldown should not re-alert.'
        );
    }

    public function test_worsening_low_to_empty_bypasses_cooldown(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(2);

        $this->artisan('bg-templates:check-library')->assertExitCode(0);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->count());

        BgTemplate::query()->delete();
        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $admin->id)
                ->where('type', 'bg_template_library_empty')->count(),
            'Low → empty should re-alert immediately despite the cooldown.'
        );
    }

    public function test_force_flag_bypasses_cooldown(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();

        $this->artisan('bg-templates:check-library')->assertExitCode(0);
        $this->artisan('bg-templates:check-library', ['--force' => true])->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $admin->id)
                ->where('type', 'bg_template_library_empty')->count()
        );
    }

    public function test_recovery_sends_all_clear_and_closes_episode(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();

        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $this->seedActiveTemplates(CheckBgTemplateLibrary::MIN_ACTIVE);
        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $note = UserNotification::where('user_id', $admin->id)
            ->where('type', 'bg_template_library_ok')->first();
        $this->assertNotNull($note, 'Expected a recovery all-clear notification.');

        $state = AppSetting::get('bg_template_health', []);
        $this->assertFalse((bool) ($state['alerting'] ?? true));
    }

    public function test_no_recovery_notice_when_never_alerted(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(CheckBgTemplateLibrary::MIN_ACTIVE);

        $this->artisan('bg-templates:check-library')->assertExitCode(0);

        $this->assertSame(
            0,
            UserNotification::where('user_id', $admin->id)
                ->where('type', 'bg_template_library_ok')->count()
        );
    }

    public function test_registered_in_scheduled_job_registry(): void
    {
        $keys = collect(ScheduledJobRegistry::all())->pluck('key');
        $this->assertTrue(
            $keys->contains('bg-templates:check-library'),
            'bg-templates:check-library must be registered in routes/schedules/health-checks.php.'
        );
    }
}
