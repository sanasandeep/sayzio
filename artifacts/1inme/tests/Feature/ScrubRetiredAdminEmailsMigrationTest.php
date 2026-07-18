<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the one-off cleanup migration
 * ({@see database/migrations/2028_01_24_000001_scrub_retired_admin_emails_from_app_settings.php})
 * that scrubs the retired admin email addresses out of admin-managed
 * `app_settings` records left over from before the admin-email consolidation.
 *
 * Contract:
 *   - the billing CC list has every retired address swapped for the canonical
 *     `sayzioapp@gmail.com`, de-duplicated with order preserved;
 *   - single-value recipient/from settings are swapped only when they exactly
 *     equal a retired address;
 *   - unrelated values and un-set keys are never touched;
 *   - re-running is a no-op (idempotent).
 */
class ScrubRetiredAdminEmailsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICAL = 'sayzioapp@gmail.com';

    private function runMigration(): void
    {
        $migration = include database_path(
            'migrations/2028_01_24_000001_scrub_retired_admin_emails_from_app_settings.php'
        );
        $migration->up();
    }

    public function test_retired_addresses_in_cc_list_are_replaced_deduped_and_ordered(): void
    {
        AppSetting::put('billing.cc_recipients', [
            'finance@sayzio.app',
            'sanasandeep@gmail.com',   // retired -> canonical
            'official1inme@gmail.com', // retired -> canonical (collapses w/ prev)
            'sayzioapp@gmail.com',     // already canonical (collapses too)
        ]);

        $this->runMigration();

        $this->assertSame(
            ['finance@sayzio.app', self::CANONICAL],
            AppSetting::get('billing.cc_recipients'),
            'retired entries become canonical, duplicates collapse, order preserved'
        );
    }

    public function test_case_insensitive_match_in_cc_list(): void
    {
        AppSetting::put('billing.cc_recipients', ['ADMIN@1INME.COM']);

        $this->runMigration();

        $this->assertSame([self::CANONICAL], AppSetting::get('billing.cc_recipients'));
    }

    public function test_clean_cc_list_is_left_untouched(): void
    {
        $clean = ['finance@sayzio.app', 'sayzioapp@gmail.com'];
        AppSetting::put('billing.cc_recipients', $clean);

        $this->runMigration();

        $this->assertSame($clean, AppSetting::get('billing.cc_recipients'));
    }

    public function test_unset_cc_list_stays_unset(): void
    {
        $this->runMigration();

        $this->assertNull(AppSetting::get('billing.cc_recipients'));
        $this->assertFalse(AppSetting::query()->where('key', 'billing.cc_recipients')->exists());
    }

    public function test_scalar_recipient_and_from_are_swapped_only_when_retired(): void
    {
        AppSetting::put('contact_recipient_email', 'official1inme@gmail.com');
        AppSetting::put('mail.from_address', 'admin@1inme.com');

        $this->runMigration();

        $this->assertSame(self::CANONICAL, AppSetting::get('contact_recipient_email'));
        $this->assertSame(self::CANONICAL, AppSetting::get('mail.from_address'));
    }

    public function test_non_retired_scalar_values_are_preserved(): void
    {
        AppSetting::put('contact_recipient_email', 'leads@sayzio.app');
        AppSetting::put('mail.from_address', 'no-reply@sayzio.app');

        $this->runMigration();

        $this->assertSame('leads@sayzio.app', AppSetting::get('contact_recipient_email'));
        $this->assertSame('no-reply@sayzio.app', AppSetting::get('mail.from_address'));
    }

    public function test_migration_is_idempotent(): void
    {
        AppSetting::put('billing.cc_recipients', ['sanasandeep@gmail.com', 'x@sayzio.app']);
        AppSetting::put('contact_recipient_email', 'admin@1inme.com');

        $this->runMigration();
        $this->runMigration();

        $this->assertSame([self::CANONICAL, 'x@sayzio.app'], AppSetting::get('billing.cc_recipients'));
        $this->assertSame(self::CANONICAL, AppSetting::get('contact_recipient_email'));
    }
}
