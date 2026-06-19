<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Wallet;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the one-time "AI credits → coin wallet" conversion migration
 * ({@see database/migrations/2027_06_19_000001_migrate_ai_credits_to_coins.php}).
 *
 * The migration's contract is "no paid value is ever lost":
 *   - leftover credit balances convert at the old wallet→credits rate,
 *     ROUNDING UP so a sub-coin remainder lands in the user's favour
 *     rather than being discarded;
 *   - the old balance is only zeroed AFTER the wallet adjustment confirms
 *     success, so a mid-migration failure leaves the row intact and
 *     retryable;
 *   - a stable per-user idempotency key makes the whole step re-run safe.
 */
class AiCreditMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Default old exchange rate: 10 credits per 1 wallet coin. */
    private const RATE = 10;

    private function makeUser(string $prefix = 'mig'): User
    {
        return User::create([
            'name'     => Str::title($prefix).' User '.Str::random(4),
            'email'    => $prefix.Str::random(6).'@example.test',
            'password' => bcrypt('secret'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    /**
     * The `ai_credit_balances` table has since been dropped from the live
     * schema (the credit system is fully retired). This conversion
     * migration is historical but still ships in the repo, so recreate the
     * legacy table on the fly to keep its value-preservation contract
     * covered.
     */
    private function ensureLegacyTable(): void
    {
        if (Schema::hasTable('ai_credit_balances')) {
            return;
        }
        Schema::create('ai_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('lifetime_purchased')->default(0);
            $table->bigInteger('lifetime_spent')->default(0);
            $table->timestamps();
        });
    }

    private function seedBalance(User $user, int $balance): void
    {
        $this->ensureLegacyTable();
        DB::table('ai_credit_balances')->insert([
            'user_id'    => $user->id,
            'balance'    => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Run the migration's up() in isolation. */
    private function runMigration(): void
    {
        $migration = include database_path(
            'migrations/2027_06_19_000001_migrate_ai_credits_to_coins.php'
        );
        $migration->up();
    }

    private function walletBalance(User $user): int
    {
        return (int) (Wallet::where('user_id', $user->id)->value('balance') ?? 0);
    }

    private function ledgerBalance(User $user): int
    {
        return (int) (DB::table('ai_credit_balances')->where('user_id', $user->id)->value('balance') ?? 0);
    }

    public function test_non_divisible_balance_rounds_up_so_no_value_is_lost(): void
    {
        // 15 credits @ rate 10 must NOT truncate to 1 coin (which would
        // silently discard 5 credits); it rounds up to 2.
        $user = $this->makeUser();
        $this->seedBalance($user, 15);

        $this->runMigration();

        $this->assertSame(2, $this->walletBalance($user), 'remainder credits must round up, never truncate');
        $this->assertSame(0, $this->ledgerBalance($user), 'old balance is zeroed only after a successful credit');
    }

    public function test_sub_coin_balance_still_grants_one_coin(): void
    {
        // 3 credits @ rate 10 would floor to 0 — the user would lose
        // everything. Rounding up grants 1 coin.
        $user = $this->makeUser();
        $this->seedBalance($user, 3);

        $this->runMigration();

        $this->assertSame(1, $this->walletBalance($user));
        $this->assertSame(0, $this->ledgerBalance($user));
    }

    public function test_exact_multiple_converts_without_bonus(): void
    {
        $user = $this->makeUser();
        $this->seedBalance($user, 50);

        $this->runMigration();

        $this->assertSame(5, $this->walletBalance($user));
    }

    public function test_rerun_does_not_double_credit_even_if_balance_reappears(): void
    {
        $user = $this->makeUser();
        $this->seedBalance($user, 25);

        $this->runMigration();
        $this->assertSame(3, $this->walletBalance($user)); // ceil(25/10)

        // Simulate a flawed re-run where the legacy balance somehow still
        // shows value: the stable idempotency key must prevent a 2nd credit.
        DB::table('ai_credit_balances')->where('user_id', $user->id)->update(['balance' => 25]);
        $this->runMigration();

        $this->assertSame(3, $this->walletBalance($user), 'idempotency key must block a second conversion credit');
        $this->assertSame(1, WalletTransaction::where('idempotency_key', 'ai-credit-migration:'.$user->id)->count());
    }

    public function test_failed_adjustment_leaves_balance_intact_for_retry(): void
    {
        $user = $this->makeUser();
        $this->seedBalance($user, 40);

        // Force the wallet adjustment to throw; the migration must NOT zero
        // the legacy balance, so the value is preserved and retryable.
        $throwing = \Mockery::mock(WalletService::class);
        $throwing->shouldReceive('adjust')->andThrow(new \RuntimeException('wallet locked'));
        $this->app->instance(WalletService::class, $throwing);

        $this->runMigration();

        $this->assertSame(0, $this->walletBalance($user), 'no coins credited when adjustment fails');
        $this->assertSame(40, $this->ledgerBalance($user), 'legacy balance must survive a failed conversion');

        // A clean re-run with the real service must then convert it fully.
        $this->app->forgetInstance(WalletService::class);
        $this->runMigration();

        $this->assertSame(4, $this->walletBalance($user));
        $this->assertSame(0, $this->ledgerBalance($user));
    }

    public function test_orphan_balance_row_without_user_is_left_untouched(): void
    {
        // Insert a balance row pointing at a non-existent user id.
        $this->ensureLegacyTable();
        DB::table('ai_credit_balances')->insert([
            'user_id'    => 999999,
            'balance'    => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame(
            30,
            (int) DB::table('ai_credit_balances')->where('user_id', 999999)->value('balance'),
            'orphan history rows are preserved, not destroyed'
        );
    }
}
