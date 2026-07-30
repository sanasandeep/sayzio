<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day-by-day coin ledger coverage:
 *  - user web ledger: day-grouping math, period summary, filters, CSV export
 *  - REST /api/v1/wallet/transactions: days + summary payload, from/to filters
 *  - admin Coin Ledger: access control, all-users view, user drill-down, CSV
 */
class CoinLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::put(WalletService::FEATURE_KEY, true);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    /** Seed transactions across two days with known math. */
    private function seedLedger(User $user): void
    {
        $wallet = app(WalletService::class)->walletFor($user);
        $rows = [
            // 2026-07-28: +100 purchase, -30 spend  => in 100, out 30, net +70
            ['purchase', 100, 100, '2026-07-28 09:00:00'],
            ['spend',    -30,  70, '2026-07-28 15:30:00'],
            // 2026-07-29: -20 spend, +5 refund      => in 5, out 20, net -15
            ['spend',    -20,  50, '2026-07-29 10:00:00'],
            ['refund',     5,  55, '2026-07-29 18:45:00'],
        ];
        foreach ($rows as [$type, $delta, $bal, $at]) {
            WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'user_id'       => $user->id,
                'type'          => $type,
                'delta_coins'   => $delta,
                'balance_after' => $bal,
                'created_at'    => $at,
            ]);
        }
        $wallet->update(['balance' => 55]);
    }

    public function test_user_ledger_groups_by_day_with_correct_subtotals(): void
    {
        $user = $this->makeUser();
        $this->seedLedger($user);

        $res = $this->actingAs($user)->get('/user/wallet/transactions');
        $res->assertOk();
        $res->assertSee('data-day="2026-07-29"', false);
        $res->assertSee('data-day="2026-07-28"', false);

        // Day + period math rendered by the controller aggregates.
        $days      = $res->viewData('days');
        $dayTotals = $res->viewData('dayTotals');
        $summary   = $res->viewData('summary');

        $this->assertSame(['2026-07-29', '2026-07-28'], array_keys($days));
        $this->assertSame(100, (int) $dayTotals['2026-07-28']->coins_in);
        $this->assertSame(30,  (int) $dayTotals['2026-07-28']->coins_out);
        $this->assertSame(70,  (int) $dayTotals['2026-07-28']->net);
        $this->assertSame(5,   (int) $dayTotals['2026-07-29']->coins_in);
        $this->assertSame(20,  (int) $dayTotals['2026-07-29']->coins_out);
        $this->assertSame(-15, (int) $dayTotals['2026-07-29']->net);

        $this->assertSame(105, (int) $summary->coins_in);
        $this->assertSame(50,  (int) $summary->coins_out);
        $this->assertSame(55,  (int) $summary->net);
        $this->assertSame(4,   (int) $summary->entries);
    }

    public function test_user_ledger_filters_by_type_and_date_range(): void
    {
        $user = $this->makeUser();
        $this->seedLedger($user);

        // Type filter: only the two spends count.
        $res = $this->actingAs($user)->get('/user/wallet/transactions?type=spend');
        $res->assertOk();
        $summary = $res->viewData('summary');
        $this->assertSame(2,  (int) $summary->entries);
        $this->assertSame(50, (int) $summary->coins_out);
        $this->assertSame(0,  (int) $summary->coins_in);

        // Date range: only 2026-07-29 rows.
        $res = $this->actingAs($user)->get('/user/wallet/transactions?from=2026-07-29&to=2026-07-29');
        $res->assertOk();
        $this->assertSame(['2026-07-29'], array_keys($res->viewData('days')));
        $this->assertSame(2, (int) $res->viewData('summary')->entries);
    }

    public function test_user_csv_export_streams_filtered_rows(): void
    {
        $user = $this->makeUser();
        $this->seedLedger($user);

        $res = $this->actingAs($user)->get('/user/wallet/transactions/export?type=spend');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Date,Time,Type,Description,Coins,"Balance after"', $csv);
        $this->assertSame(2, substr_count($csv, "\nspend,") + substr_count($csv, ',spend,'));
        $this->assertStringNotContainsString('purchase', $csv);
    }

    public function test_api_transactions_returns_days_and_summary(): void
    {
        $user = $this->makeUser();
        $this->seedLedger($user);
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/v1/wallet/transactions');
        $res->assertOk();
        $data = $res->json('data');

        $this->assertSame(105, $data['summary']['coins_in']);
        $this->assertSame(50,  $data['summary']['coins_out']);
        $this->assertSame(55,  $data['summary']['net']);
        $this->assertSame(4,   $data['summary']['entries']);

        $this->assertCount(2, $data['days']);
        $this->assertSame('2026-07-29', $data['days'][0]['date']);
        $this->assertSame(-15, $data['days'][0]['net']);
        $this->assertCount(2, $data['days'][0]['items']);
        $this->assertSame('2026-07-28', $data['days'][1]['date']);
        $this->assertSame(70, $data['days'][1]['net']);

        // from/to filter
        $res = $this->withToken($token)->getJson('/api/v1/wallet/transactions?from=2026-07-28&to=2026-07-28');
        $res->assertOk();
        $this->assertCount(1, $res->json('data.days'));
        $this->assertSame(2, $res->json('data.summary.entries'));
    }

    private function makeSuperAdmin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        return Admin::create([
            'name'     => 'Super',
            'email'    => 'super-' . uniqid() . '@example.test',
            'password' => bcrypt('secret-password'),
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);
    }

    public function test_admin_ledger_requires_admin_auth(): void
    {
        $user = $this->makeUser();
        $this->get('/admin/coin-ledger')->assertRedirect();
        $this->actingAs($user)->get('/admin/coin-ledger')->assertRedirect();
    }

    public function test_admin_ledger_shows_all_users_and_supports_drilldown_and_csv(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->seedLedger($a);
        $walletB = app(WalletService::class)->walletFor($b);
        WalletTransaction::create([
            'wallet_id'     => $walletB->id,
            'user_id'       => $b->id,
            'type'          => 'purchase',
            'delta_coins'   => 500,
            'balance_after' => 500,
            'created_at'    => '2026-07-29 12:00:00',
        ]);

        $admin = $this->makeSuperAdmin();

        // All-users view: platform totals include both users.
        $res = $this->be($admin, 'admin')->get('/admin/coin-ledger');
        $res->assertOk();
        $summary = $res->viewData('summary');
        $this->assertSame(5, (int) $summary->entries);
        $this->assertSame(605, (int) $summary->coins_in);
        $this->assertSame(485, (int) $res->viewData('dayTotals')['2026-07-29']->net);

        // Drill-down to user A only.
        $res = $this->get('/admin/coin-ledger?user_id=' . $a->id);
        $res->assertOk();
        $this->assertSame(4, (int) $res->viewData('summary')->entries);
        $this->assertNotNull($res->viewData('drillUser'));

        // User search by email fragment.
        $res = $this->get('/admin/coin-ledger?q=' . urlencode($b->email));
        $res->assertOk();
        $this->assertSame(1, (int) $res->viewData('summary')->entries);

        // CSV export mirrors the filter.
        $res = $this->get('/admin/coin-ledger/export?user_id=' . $b->id);
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $csv = $res->streamedContent();
        $this->assertStringContainsString($b->email, $csv);
        $this->assertStringNotContainsString($a->email, $csv);
    }
}
