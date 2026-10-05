<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "log of AI used is missing".
 *
 * It was not missing from the data. Every AI call has been stamped
 * `meta.ai = true` since the separate credit ledger was retired, alongside
 * the feature, the model and the token counts. What was missing was any
 * way to ask for it: the coin ledger showed "−12 coins · Spend" and left
 * the creator to work out which of eleven AI features that was, and
 * whether the forty-coin one was the menu builder or the image search.
 *
 * So this is the other direction of the same fault the rest of this
 * session has been about -- not a control that does nothing, but a
 * capability with no screen offering it. Sixteen of the first kind, and
 * this is at least the second of the second.
 *
 * The tests hold the filter, the breakdown and the export to the same set
 * of rows, because three views of "the filtered set" that disagree is how
 * somebody exports a CSV that does not match the screen they exported it
 * from.
 */
class TheAiSpendCanBeLookedAtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Switched on for the test rather than skipped around. A suite that
        // skips in CI is a suite that proves nothing, and this one covers a
        // filter somebody will rely on to read their own bill.
        // ::put() and not a direct write: AppSetting keeps an in-process
        // bulk-prime key set, so a row inserted behind its back is still
        // answered from the "this key does not exist" sentinel. The setter
        // is the thing that keeps both coherent.
        \App\Modules\Admin\Models\AppSetting::put(WalletService::FEATURE_KEY, '1');
        $this->assertTrue(WalletService::isEnabled(), 'could not switch the wallet on for this test');

        $this->user = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->user);
    }

    /** A wallet with a known mix of AI and non-AI movements. */
    private function seedLedger(): void
    {
        $wallet = app(\App\Services\Billing\WalletService::class)->walletFor($this->user);

        $rows = [
            // Two different AI features, so a breakdown has something to
            // group, and one of them twice so the counts are not all 1.
            ['restaurant_menu_builder', -40, ['model' => 'claude-x', 'tokens_in' => 1200, 'tokens_out' => 900]],
            ['restaurant_menu_builder', -20, ['model' => 'claude-x', 'tokens_in' => 600,  'tokens_out' => 300]],
            ['image_search',             -5, ['model' => 'img-1',    'tokens_in' => 10,   'tokens_out' => 0]],
        ];

        $balance = 1000;
        foreach ($rows as [$feature, $delta, $extra]) {
            $balance += $delta;
            WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'user_id'       => $this->user->id,
                'type'          => 'spend',
                'delta_coins'   => $delta,
                'balance_after' => $balance,
                'reason'        => 'AI call',
                'meta'          => ['ai' => true, 'feature' => $feature] + $extra,
                'created_at'    => now(),
            ]);
        }

        // And something that is NOT an AI charge, which must stay out of
        // every AI view.
        $balance += 500;
        WalletTransaction::create([
            'wallet_id'     => $wallet->id,
            'user_id'       => $this->user->id,
            'type'          => 'purchase',
            'delta_coins'   => 500,
            'balance_after' => $balance,
            'reason'        => 'Coin pack',
            'meta'          => ['pack' => 'starter'],
            'created_at'    => now(),
        ]);
    }

    private function ledger(array $query = []): string
    {
        return $this->actingAs($this->user)
            ->get(route('user.wallet.transactions', $query))
            ->assertOk()
            ->getContent();
    }

    /**
     * The statement only, not the whole page.
     *
     * The app header carries a recent-transactions popover that lists the
     * whole wallet whatever this page is filtered to. Asserting against the
     * full HTML therefore answers "is this row anywhere on the screen",
     * which is not the question -- and it is why the first version of these
     * tests failed against a filter that was working perfectly.
     */
    private function statement(array $query = []): string
    {
        $html = $this->ledger($query);
        $start = strpos($html, 'id="ledger-statement"');
        if ($start === false) {
            return '';   // nothing matched the filter; the block is not drawn
        }

        return substr($html, $start);
    }

    public function test_the_unfiltered_ledger_still_shows_everything(): void
    {
        $this->seedLedger();
        $html = $this->statement();

        $this->assertStringContainsString('Coin pack', $html);
        $this->assertStringContainsString('AI call', $html);
    }

    public function test_ai_only_leaves_the_coin_purchase_out(): void
    {
        $this->seedLedger();
        $html = $this->statement(['ai' => '1']);

        $this->assertStringContainsString('AI call', $html);
        $this->assertStringNotContainsString('Coin pack', $html);
    }

    public function test_everything_except_ai_leaves_the_ai_calls_out(): void
    {
        $this->seedLedger();
        $html = $this->statement(['ai' => '0']);

        $this->assertStringContainsString('Coin pack', $html);
        $this->assertStringNotContainsString('AI call', $html);
    }

    public function test_the_breakdown_says_where_the_coins_went(): void
    {
        $this->seedLedger();
        $html = $this->ledger(['ai' => '1']);

        $this->assertStringContainsString('Where the AI coins went', $html);
        // Grouped and summed: 40 + 20 on the builder, 5 on image search.
        $this->assertStringContainsString('Restaurant Menu Builder', $html);
        $this->assertStringContainsString('60', $html);
        $this->assertStringContainsString('Image Search', $html);
        // Two calls on one feature, one on the other.
        $this->assertStringContainsString('2 calls', $html);
        $this->assertStringContainsString('1 call', $html);
    }

    public function test_the_breakdown_is_not_computed_when_nobody_asked_for_it(): void
    {
        $this->seedLedger();

        // A second aggregate query over the range, for a creator who is
        // reading their coin purchases and did not ask about AI.
        $this->assertStringNotContainsString('Where the AI coins went', $this->ledger());
        $this->assertStringNotContainsString('Where the AI coins went', $this->ledger(['ai' => '0']));
    }

    public function test_one_feature_can_be_singled_out(): void
    {
        $this->seedLedger();
        $html = $this->ledger(['ai' => '1', 'feature' => 'image_search']);

        // The breakdown narrows with the list, rather than continuing to
        // show totals the rows below no longer add up to.
        $this->assertStringContainsString('Image Search', $html);
        $this->assertStringNotContainsString('Restaurant Menu Builder', $html);
        $this->assertStringContainsString('Show all features', $html);
    }

    public function test_every_ai_row_says_what_it_was(): void
    {
        $this->seedLedger();
        $html = $this->ledger(['ai' => '1']);

        // "−12 coins · Spend" was the whole of it before. The three things
        // that explain a charge now sit on the charge.
        $this->assertStringContainsString('claude-x', $html);
        $this->assertStringContainsString('1,200 in', $html);
        $this->assertStringContainsString('900 out', $html);
    }

    public function test_the_export_matches_the_screen_it_came_from(): void
    {
        $this->seedLedger();

        $csv = $this->actingAs($this->user)
            ->get(route('user.wallet.transactions.export', ['ai' => '1']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Feature', $csv);
        $this->assertStringContainsString('restaurant_menu_builder', $csv);
        $this->assertStringContainsString('claude-x', $csv);
        // The filter has to reach the export too, or somebody downloads a
        // file that does not match the screen they downloaded it from.
        $this->assertStringNotContainsString('Coin pack', $csv);
    }

    public function test_a_junk_filter_does_not_quietly_widen_the_set(): void
    {
        $this->seedLedger();

        // Anything that is not '1' or '0' is not a filter, and must not be
        // read as one -- silently showing everything is how somebody
        // concludes their AI spend is their whole balance.
        $html = $this->statement(['ai' => 'yes']);
        $this->assertStringContainsString('Coin pack', $html);
        $this->assertStringNotContainsString('Where the AI coins went', $html);

        // A feature nobody ever charged against returns nothing rather than
        // everything.
        $html = $this->ledger(['ai' => '1', 'feature' => 'no_such_feature']);
        $this->assertStringNotContainsString('claude-x', $html);
    }
}
