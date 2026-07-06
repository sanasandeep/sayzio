<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\Role as AdminRole;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Integrations\PlatformServiceSettings;
use App\Services\Integrations\StorageHealthAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the proactive "S3 storage misconfigured" admin alerting
 * (StorageHealthAlerts + the storage:check-s3-config command) and the
 * prominent warning banner on Admin > Integrations > Storage.
 *
 * Pins: alert fan-out to ops admins when S3 is incomplete, the cooldown
 * that stops an hourly cadence from spamming, --force bypass, the recovery
 * all-clear once configured, and the storage-page banner.
 */
class StorageHealthAlertTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string|false> */
    private array $savedEnv = [];

    private const AWS_VARS = [
        'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION',
        'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT', 'AWS_USE_PATH_STYLE_ENDPOINT',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Neutralize any AWS_* values in the process env so the effective
        // S3 config resolves to "misconfigured" unless a test stores admin
        // values. Restored in tearDown.
        foreach (self::AWS_VARS as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        parent::tearDown();
    }

    private function makeOpsAdmin(): User
    {
        $role = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::lower(Str::random(6)),
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

    private function makeAdmin(): Admin
    {
        $role = AdminRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function storeCompleteS3AdminConfig(): void
    {
        PlatformServiceSettings::setS3Key('AKIATESTKEY123');
        PlatformServiceSettings::setS3Secret('test-secret');
        PlatformServiceSettings::setS3Region('us-east-1');
        PlatformServiceSettings::setS3Bucket('test-bucket');
    }

    // ─────────────────────────────────────────────────────────────

    public function test_missing_pieces_reflect_effective_config(): void
    {
        $this->assertSame(
            ['access key', 'secret key', 'bucket', 'region'],
            PlatformServiceSettings::s3MissingPieces()
        );
        $this->assertFalse(PlatformServiceSettings::s3Configured());

        $this->storeCompleteS3AdminConfig();

        $this->assertSame([], PlatformServiceSettings::s3MissingPieces());
        $this->assertTrue(PlatformServiceSettings::s3Configured());
    }

    public function test_command_alerts_ops_admins_when_s3_misconfigured(): void
    {
        $ops = $this->makeOpsAdmin();

        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $notes = UserNotification::where('user_id', $ops->id)
            ->where('type', 'storage_misconfigured')->get();
        $this->assertCount(1, $notes, 'exactly one in-app alert for the ops admin');
        $this->assertStringContainsString('S3 storage misconfigured', $notes->first()->data['subject']);
        $this->assertNotEmpty($notes->first()->data['missing']);

        $state = AppSetting::get(StorageHealthAlerts::STATE_KEY, []);
        $this->assertTrue((bool) ($state['alerting'] ?? false), 'episode must be open');
        $this->assertNotEmpty($state['last_sent_at'] ?? null);
    }

    public function test_cooldown_prevents_resend_and_force_bypasses_it(): void
    {
        $ops = $this->makeOpsAdmin();

        $this->artisan('storage:check-s3-config')->assertExitCode(0);
        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_misconfigured')->count(),
            'second run within cooldown must not re-send'
        );

        $this->artisan('storage:check-s3-config', ['--force' => true])->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_misconfigured')->count(),
            '--force must bypass the cooldown'
        );
    }

    public function test_recovery_all_clear_sent_once_configured(): void
    {
        $ops = $this->makeOpsAdmin();

        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $this->storeCompleteS3AdminConfig();

        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_configured')->count(),
            'all-clear must be sent once fixed'
        );
        $state = AppSetting::get(StorageHealthAlerts::STATE_KEY, []);
        $this->assertFalse((bool) ($state['alerting'] ?? true), 'episode must be closed');

        // A further run with a healthy config must not send anything more.
        $this->artisan('storage:check-s3-config')->assertExitCode(0);
        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_configured')->count()
        );
    }

    public function test_rebreak_after_recovery_alerts_immediately_despite_cooldown(): void
    {
        $ops = $this->makeOpsAdmin();

        // Break → alert → fix → all-clear, all within the cooldown window.
        $this->artisan('storage:check-s3-config')->assertExitCode(0);
        $this->storeCompleteS3AdminConfig();
        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        // Break again (wipe one required piece) — a NEW episode must alert
        // immediately; the cooldown only guards repeats of an OPEN episode.
        PlatformServiceSettings::setS3Bucket('');
        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_misconfigured')->count(),
            're-break after recovery must alert immediately, not wait out the cooldown'
        );
    }

    public function test_no_alert_when_configured(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->storeCompleteS3AdminConfig();

        $this->artisan('storage:check-s3-config')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    public function test_storage_page_shows_prominent_banner_when_misconfigured(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.integrations.storage.edit'));

        $resp->assertOk();
        $resp->assertSee('S3 storage is misconfigured', false);
        $resp->assertSee('access key');
    }

    public function test_storage_page_hides_banner_when_configured(): void
    {
        $this->storeCompleteS3AdminConfig();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.integrations.storage.edit'));

        $resp->assertOk();
        $resp->assertDontSee('S3 storage is misconfigured', false);
    }

    public function test_saving_storage_settings_sends_immediate_all_clear(): void
    {
        $ops = $this->makeOpsAdmin();

        // Open an episode first.
        $this->artisan('storage:check-s3-config')->assertExitCode(0);
        $this->assertSame(1, UserNotification::where('user_id', $ops->id)->count());

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.integrations.storage.update'), [
                's3_key'    => 'AKIATESTKEY123',
                's3_secret' => 'test-secret',
                's3_region' => 'us-east-1',
                's3_bucket' => 'test-bucket',
            ]);

        $resp->assertRedirect(route('admin.integrations.storage.edit'));

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'storage_configured')->count(),
            'fixing the config via the admin form must send the all-clear immediately'
        );
    }
}
