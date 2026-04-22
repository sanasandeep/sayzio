<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiCreditBalance;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\AI\AiCreditService;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiCreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected AiCreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credits = app(AiCreditService::class);
    }

    protected function makeUser(): User
    {
        return User::create([
            'name' => 'AI User '.Str::random(4),
            'email' => 'ai'.Str::random(6).'@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function test_grant_is_idempotent_for_same_key(): void
    {
        $user = $this->makeUser();

        $first = $this->credits->grant($user, 50, ['idempotency_key' => 'grant-abc']);
        $second = $this->credits->grant($user, 50, ['idempotency_key' => 'grant-abc']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(50, $this->credits->getBalance($user));
        $this->assertSame(1, AiCreditTransaction::where('user_id', $user->id)->count());
    }

    public function test_grant_with_different_key_creates_separate_transaction(): void
    {
        $user = $this->makeUser();

        $this->credits->grant($user, 25, ['idempotency_key' => 'grant-1']);
        $this->credits->grant($user, 25, ['idempotency_key' => 'grant-2']);

        $this->assertSame(50, $this->credits->getBalance($user));
        $this->assertSame(2, AiCreditTransaction::where('user_id', $user->id)->count());
    }

    public function test_charge_with_insufficient_balance_throws_and_keeps_balance(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 10);

        try {
            $this->credits->charge($user, 25, ['feature' => 'mind']);
            $this->fail('Expected InsufficientAiCreditsException to be thrown.');
        } catch (InsufficientAiCreditsException $e) {
            $this->assertSame(25, $e->required);
            $this->assertSame(10, $e->balance);
        }

        $this->assertSame(10, $this->credits->getBalance($user));
        // No spend transaction was written.
        $this->assertSame(
            0,
            AiCreditTransaction::where('user_id', $user->id)->where('type', 'spend')->count()
        );
        // Lifetime spent must remain zero on a failed charge.
        $this->assertSame(0, (int) $this->credits->balanceFor($user)->lifetime_spent);
    }

    public function test_charge_succeeds_when_balance_is_sufficient(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 100);

        $tx = $this->credits->charge($user, 30, ['feature' => 'mind', 'model' => 'gpt-4o-mini']);

        $this->assertSame('spend', $tx->type);
        $this->assertSame(-30, (int) $tx->delta_credits);
        $this->assertSame(70, (int) $tx->balance_after);
        $this->assertSame(70, $this->credits->getBalance($user));
        $this->assertSame(30, (int) $this->credits->balanceFor($user)->lifetime_spent);
    }

    public function test_refund_increments_balance(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 100);
        $this->credits->charge($user, 40);

        $this->assertSame(60, $this->credits->getBalance($user));

        $tx = $this->credits->refund($user, 25, ['feature' => 'mind', 'reason' => 'failed call']);

        $this->assertSame('refund', $tx->type);
        $this->assertSame(25, (int) $tx->delta_credits);
        $this->assertSame(85, (int) $tx->balance_after);
        $this->assertSame(85, $this->credits->getBalance($user));
    }

    public function test_admin_adjust_positive_increments_balance(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 20);

        $tx = $this->credits->adminAdjust($user, 15, 'goodwill bump', adminId: 7);

        $this->assertSame('admin_adjustment', $tx->type);
        $this->assertSame(15, (int) $tx->delta_credits);
        $this->assertSame(35, (int) $tx->balance_after);
        $this->assertSame('goodwill bump', $tx->reason);
        $this->assertSame(7, (int) $tx->admin_id);
        $this->assertSame(35, $this->credits->getBalance($user));
    }

    public function test_admin_adjust_negative_decrements_balance(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 50);

        $tx = $this->credits->adminAdjust($user, -20, 'clawback', adminId: 9);

        $this->assertSame(-20, (int) $tx->delta_credits);
        $this->assertSame(30, (int) $tx->balance_after);
        $this->assertSame(30, $this->credits->getBalance($user));
    }

    public function test_admin_adjust_negative_below_zero_throws_and_does_not_change_balance(): void
    {
        $user = $this->makeUser();
        $this->credits->grant($user, 5);

        $this->expectException(InsufficientAiCreditsException::class);
        try {
            $this->credits->adminAdjust($user, -10, 'overdrawn', adminId: 1);
        } finally {
            $this->assertSame(5, $this->credits->getBalance($user));
        }
    }

    public function test_admin_adjust_requires_nonzero_delta(): void
    {
        $user = $this->makeUser();
        $this->expectException(\InvalidArgumentException::class);
        $this->credits->adminAdjust($user, 0, 'noop');
    }

    public function test_admin_adjust_requires_reason(): void
    {
        $user = $this->makeUser();
        $this->expectException(\InvalidArgumentException::class);
        $this->credits->adminAdjust($user, 5, '   ');
    }

    public function test_purchase_with_wallet_debits_wallet_and_grants_credits_atomically(): void
    {
        $user = $this->makeUser();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 1000, ['idempotency_key' => 'seed-wallet']);

        $tx = $this->credits->purchaseWithWallet($user, 200, 500, [
            'idempotency_key' => 'buy-1',
            'reason' => 'pack 200',
        ]);

        $this->assertSame('purchase', $tx->type);
        $this->assertSame(200, (int) $tx->delta_credits);
        $this->assertSame(200, (int) $tx->balance_after);
        $this->assertSame(200, $this->credits->getBalance($user));

        // Wallet was debited.
        $this->assertSame(500, $wallets->getBalance($user));

        // Cross-reference wallet transaction is linked.
        $this->assertNotNull($tx->wallet_transaction_id);
        $walletTx = WalletTransaction::find($tx->wallet_transaction_id);
        $this->assertNotNull($walletTx);
        $this->assertSame(-500, (int) $walletTx->delta_coins);
        $this->assertSame('spend', $walletTx->type);

        // Lifetime purchased increments.
        $this->assertSame(200, (int) $this->credits->balanceFor($user)->lifetime_purchased);
    }

    public function test_purchase_with_wallet_is_idempotent_on_key(): void
    {
        $user = $this->makeUser();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 1000, ['idempotency_key' => 'seed-wallet-2']);

        $first = $this->credits->purchaseWithWallet($user, 100, 250, ['idempotency_key' => 'buy-x']);
        $second = $this->credits->purchaseWithWallet($user, 100, 250, ['idempotency_key' => 'buy-x']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(100, $this->credits->getBalance($user));
        $this->assertSame(750, $wallets->getBalance($user));
        $this->assertSame(
            1,
            AiCreditTransaction::where('user_id', $user->id)->where('type', 'purchase')->count()
        );
    }

    public function test_purchase_with_wallet_rolls_back_when_wallet_balance_insufficient(): void
    {
        $user = $this->makeUser();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 100, ['idempotency_key' => 'seed-small']);

        $creditsBefore = $this->credits->getBalance($user);
        $walletBefore = $wallets->getBalance($user);

        try {
            $this->credits->purchaseWithWallet($user, 200, 500, ['idempotency_key' => 'buy-fail']);
            $this->fail('Expected InsufficientCoinsException.');
        } catch (InsufficientCoinsException $e) {
            // expected
        }

        // Neither side moved.
        $this->assertSame($creditsBefore, $this->credits->getBalance($user));
        $this->assertSame($walletBefore, $wallets->getBalance($user));

        // No purchase ledger row was created.
        $this->assertSame(
            0,
            AiCreditTransaction::where('user_id', $user->id)->where('type', 'purchase')->count()
        );
        // Lifetime purchased did not move.
        $this->assertSame(0, (int) $this->credits->balanceFor($user)->lifetime_purchased);
    }

    public function test_charge_rejects_non_positive_amount(): void
    {
        $user = $this->makeUser();
        $this->expectException(\InvalidArgumentException::class);
        $this->credits->charge($user, 0);
    }

    public function test_refund_rejects_non_positive_amount(): void
    {
        $user = $this->makeUser();
        $this->expectException(\InvalidArgumentException::class);
        $this->credits->refund($user, -5);
    }

    public function test_purchase_with_wallet_rolls_back_when_credit_side_fails_after_wallet_debit(): void
    {
        $user = $this->makeUser();
        $wallets = app(WalletService::class);
        $wallets->credit($user, 1000, ['idempotency_key' => 'seed-credit-fail']);

        // Subclass AiCreditService so the credit-side ledger write blows up
        // *after* the wallet has already been debited inside the outer
        // transaction. Proves the outer DB::transaction rolls the wallet
        // debit back so the user is never "charged but not credited".
        $brokenCredits = new class($wallets) extends AiCreditService {
            protected function record(User $user, int $delta, string $type, array $opts): AiCreditTransaction
            {
                if ($type === 'purchase') {
                    throw new \RuntimeException('simulated credit ledger failure');
                }
                return parent::record($user, $delta, $type, $opts);
            }
        };

        $walletBefore = $wallets->getBalance($user);
        $creditsBefore = $this->credits->getBalance($user);

        try {
            $brokenCredits->purchaseWithWallet($user, 200, 500, [
                'idempotency_key' => 'buy-credit-fail',
            ]);
            $this->fail('Expected the credit-side failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated credit ledger failure', $e->getMessage());
        }

        // Wallet debit must have been rolled back.
        $this->assertSame($walletBefore, $wallets->getBalance($user));
        // No AI credits granted.
        $this->assertSame($creditsBefore, $this->credits->getBalance($user));
        // No purchase ledger row, and no orphan wallet 'spend' row.
        $this->assertSame(
            0,
            AiCreditTransaction::where('user_id', $user->id)->where('type', 'purchase')->count()
        );
        $this->assertSame(
            0,
            WalletTransaction::where('user_id', $user->id)->where('type', 'spend')->count()
        );
    }

    public function test_balance_for_creates_row_lazily(): void
    {
        $user = $this->makeUser();
        $this->assertNull(AiCreditBalance::where('user_id', $user->id)->first());

        $row = $this->credits->balanceFor($user);
        $this->assertInstanceOf(AiCreditBalance::class, $row);
        $this->assertSame(0, (int) $row->balance);
        $this->assertSame(1, AiCreditBalance::where('user_id', $user->id)->count());
    }
}
