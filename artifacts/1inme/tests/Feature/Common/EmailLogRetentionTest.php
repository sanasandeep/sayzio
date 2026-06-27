<?php

namespace Tests\Feature\Common;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Support\EmailLogRetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers email_logs retention: the chunked prune command (body trim + row
 * delete tiers, the -1 keep-forever no-op, the safety floor) and the write-time
 * body cap so the table can never grow unbounded.
 */
class EmailLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(int $ageDays, ?string $body = 'x'): int
    {
        return (int) DB::table('email_logs')->insertGetId([
            'email_key'  => 'test.key',
            'category'   => 'test',
            'recipient'  => 'a@example.test',
            'subject'    => 'Subject',
            'body'       => $body,
            'format'     => 'html',
            'status'     => 'sent',
            'created_at' => now()->subDays($ageDays),
            'updated_at' => now()->subDays($ageDays),
        ]);
    }

    public function test_prune_deletes_old_rows_and_trims_medium_aged_bodies(): void
    {
        AppSetting::put('email_logs.retention_days', 365);
        AppSetting::put('email_logs.body_retention_days', 90);

        $recent = $this->makeLog(10);     // kept whole
        $medium = $this->makeLog(120);    // body nulled, row kept
        $old    = $this->makeLog(400);    // deleted

        $this->artisan('email-logs:prune-history')->assertExitCode(0);

        $this->assertDatabaseHas('email_logs', ['id' => $recent]);
        $this->assertNotNull(DB::table('email_logs')->where('id', $recent)->value('body'));

        $this->assertDatabaseHas('email_logs', ['id' => $medium]);
        $this->assertNull(DB::table('email_logs')->where('id', $medium)->value('body'));

        $this->assertDatabaseMissing('email_logs', ['id' => $old]);
    }

    public function test_retention_minus_one_keeps_rows_forever(): void
    {
        AppSetting::put('email_logs.retention_days', -1);
        AppSetting::put('email_logs.body_retention_days', -1);

        $old = $this->makeLog(5000);

        $this->artisan('email-logs:prune-history')->assertExitCode(0);

        $this->assertDatabaseHas('email_logs', ['id' => $old]);
        $this->assertNotNull(DB::table('email_logs')->where('id', $old)->value('body'));
    }

    public function test_retention_window_is_floored_to_protect_recent_logs(): void
    {
        // An admin sets an absurdly low window; the floor must keep recent logs.
        AppSetting::put('email_logs.retention_days', 1);

        $this->assertSame(
            EmailLogRetentionPolicy::MIN_RETENTION_DAYS,
            EmailLogRetentionPolicy::retentionDays()
        );

        $row = $this->makeLog(5); // newer than the floor
        $this->artisan('email-logs:prune-history')->assertExitCode(0);
        $this->assertDatabaseHas('email_logs', ['id' => $row]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        AppSetting::put('email_logs.retention_days', 365);
        AppSetting::put('email_logs.body_retention_days', 90);

        $medium = $this->makeLog(120);
        $old    = $this->makeLog(400);

        $this->artisan('email-logs:prune-history --dry-run')->assertExitCode(0);

        $this->assertDatabaseHas('email_logs', ['id' => $old]);
        $this->assertNotNull(DB::table('email_logs')->where('id', $medium)->value('body'));
    }

    public function test_cap_body_truncates_oversized_bodies_on_write(): void
    {
        AppSetting::put('email_logs.max_body_bytes', 1024);

        $log = EmailLog::create([
            'email_key' => 'test.key',
            'category'  => 'test',
            'recipient' => 'a@example.test',
            'subject'   => 'Subject',
            'body'      => str_repeat('A', 5000),
            'format'    => 'html',
            'status'    => 'sent',
        ]);

        $stored = (string) DB::table('email_logs')->where('id', $log->id)->value('body');
        $this->assertLessThanOrEqual(1024, strlen($stored));
        $this->assertStringContainsString('body truncated', $stored);
    }

    public function test_cap_body_leaves_small_bodies_untouched(): void
    {
        AppSetting::put('email_logs.max_body_bytes', 1024);

        $this->assertSame('small body', EmailLog::capBody('small body'));
        $this->assertNull(EmailLog::capBody(null));
    }
}
