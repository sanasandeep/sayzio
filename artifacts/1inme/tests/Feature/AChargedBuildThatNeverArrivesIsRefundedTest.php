<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiUsageCharger;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana lost 25 coins and was left with an empty link (3003) after an AI
 * build. The builder does refund on failure -- but only in a catch block,
 * and a catch block in a process that has been killed never runs. A
 * worker timeout, an OOM, a deploy mid-request: the charge is on the
 * ledger, the page was never built, and nobody is coming to put the coins
 * back.
 *
 * The tests below are written around that specific hole. The important
 * one is the first: it does not simulate an exception, because an
 * exception is the case that already works. It simulates the process
 * simply ceasing -- a spend on the ledger, an empty link, and no refund,
 * with nothing left running that could ever fix it.
 *
 * The rest exist to stop the cure being worse than the disease. A sweep
 * that refunds money must never pay twice, must never touch a build that
 * delivered, and must never reach a page someone has put work into.
 */
class AChargedBuildThatNeverArrivesIsRefundedTest extends TestCase
{
    use RefreshDatabase;

    private const SEED = 1_000;

    private function user(): User
    {
        $slug = 'p' . Str::lower(Str::random(8));
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug, 'monthly_price' => 0,
            'annual_price' => 0, 'trial_days' => 0, 'status' => 'active',
        ]);

        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
            'onboarded_at' => now(),
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        app(WalletService::class)->credit($u, self::SEED, ['reason' => 'test seed']);

        return $u;
    }

    private function link(User $u, string $title = 'Better than example.com'): Link
    {
        return Link::create([
            'user_id'   => $u->id,
            'type'      => 'biolink',
            'alias'     => 'bl' . fake()->unique()->numerify('#####'),
            'title'     => $title,
            'is_active' => true,
        ]);
    }

    /** Charge for a build against a link, exactly as the builder does. */
    private function charge(User $u, Link $link, int $coins = 25): WalletTransaction
    {
        $txn = app(AiUsageCharger::class)->charge($u, $coins, [
            'feature'    => AiBiolinkBuilderService::FEATURE,
            'related_id' => $link->id,
            'reason'     => 'AI Link in Bio page builder',
        ]);

        return $txn;
    }

    /** Push a transaction into the past so the sweep's age guard lets it through. */
    private function age(WalletTransaction $txn, int $minutes = 90): void
    {
        WalletTransaction::where('id', $txn->id)
            ->update(['created_at' => now()->subMinutes($minutes)]);
    }

    private function balance(User $u): int
    {
        return (int) WalletTransaction::where('user_id', $u->id)->sum('delta_coins');
    }

    public function test_a_build_whose_process_died_is_refunded_and_its_empty_link_removed(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $txn  = $this->charge($u, $link, 25);
        $this->age($txn);

        $this->assertSame(self::SEED - 25, $this->balance($u), 'The charge should have landed.');

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED, $this->balance($u), 'The 25 coins should be back.');
        $this->assertNull(Link::withoutGlobalScopes()->find($link->id), 'The empty link should be gone.');
    }

    public function test_running_it_again_does_not_pay_twice(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $this->age($this->charge($u, $link, 25));

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();
        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();
        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED, $this->balance($u), 'Three runs, one refund.');
        $this->assertSame(
            1,
            WalletTransaction::where('user_id', $u->id)->where('type', 'refund')->count(),
            'Exactly one refund row should exist.'
        );
    }

    public function test_a_build_that_delivered_is_left_alone(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $this->age($this->charge($u, $link, 25));

        // Blocks are the only honest evidence that a build delivered. The
        // link itself is not: the builder creates it before it charges.
        BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'heading',
            'settings' => ['text' => 'Hello'], 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED - 25, $this->balance($u), 'A delivered build must stay paid for.');
        $this->assertNotNull(Link::withoutGlobalScopes()->find($link->id), 'And its page must survive.');
    }

    public function test_a_failure_the_builder_already_refunded_is_not_refunded_again(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $this->age($this->charge($u, $link, 25));

        // The builder's own catch block, which carries no idempotency key
        // and can only be recognised by what it is against.
        app(AiUsageCharger::class)->refund($u, 25, [
            'feature'    => AiBiolinkBuilderService::FEATURE,
            'related_id' => $link->id,
            'reason'     => 'AI Link in Bio builder failed — auto refund',
        ]);

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED, $this->balance($u), 'Already settled; the sweep must not top it up again.');
        $this->assertSame(
            1,
            WalletTransaction::where('user_id', $u->id)->where('type', 'refund')->count(),
            'Still just the builder\'s own refund.'
        );
    }

    public function test_a_build_that_might_still_be_running_is_not_touched(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $this->charge($u, $link, 25);   // deliberately NOT aged

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED - 25, $this->balance($u), 'A build in flight must be left alone.');
        $this->assertNotNull(Link::withoutGlobalScopes()->find($link->id));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $this->age($this->charge($u, $link, 25));

        $this->artisan('ai:reconcile-abandoned-builds', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(self::SEED - 25, $this->balance($u), 'Dry run must not move money.');
        $this->assertNotNull(Link::withoutGlobalScopes()->find($link->id), 'Dry run must not delete anything.');
    }

    public function test_coins_come_back_even_when_the_link_is_already_gone(): void
    {
        $u    = $this->user();
        $link = $this->link($u);
        $id   = $link->id;
        $this->age($this->charge($u, $link, 25));

        // The catch block got as far as deleting the shell and then the
        // process died before the builder could refund.
        Link::withoutGlobalScopes()->where('id', $id)->delete();

        $this->artisan('ai:reconcile-abandoned-builds')->assertSuccessful();

        $this->assertSame(self::SEED, $this->balance($u), 'The money matters more than the debris.');
    }

    public function test_another_users_abandoned_build_is_untouched_when_scoped_to_one_user(): void
    {
        $a = $this->user();
        $linkA = $this->link($a);
        $this->age($this->charge($a, $linkA, 25));

        $b = $this->user();
        $linkB = $this->link($b);
        $this->age($this->charge($b, $linkB, 25));

        $this->artisan('ai:reconcile-abandoned-builds', ['--user' => $a->id])->assertSuccessful();

        $this->assertSame(self::SEED, $this->balance($a));
        $this->assertSame(self::SEED - 25, $this->balance($b), 'Scoping to one user must not sweep everyone.');
    }
}
