<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactCallLog;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\Workspace;
use App\Services\Billing\WalletService;
use App\Services\MarketingPlanActuals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6772 — real Sayzio actuals behind the Marketing Plan Calculator:
 * aggregation service (incl. empty-workspace + workspace-scope cases),
 * the actuals endpoint and the "Use my Sayzio data" create prefill.
 */
class MarketingPlanActualsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            // Task #6766 — the calculator is plan-gated; grant access with no cap.
            'features' => ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1],
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => ($plan ?? $this->plan())->id,
            'onboarded_at' => now(),
        ]);
    }

    /** A user-owned link (personal / NULL workspace unless given). */
    private function link(User $user, ?int $workspaceId = null): Link
    {
        $link = Link::withoutEvents(fn () => Link::create([
            'user_id' => $user->id,
            'type'    => 'url',
            'alias'   => 'a' . Str::lower(Str::random(10)),
            'title'   => 'Test link',
            'url'     => 'https://example.com',
        ]));
        // Force the workspace explicitly — no current_workspace is bound in tests.
        DB::table('links')->where('id', $link->id)->update(['workspace_id' => $workspaceId]);

        return $link->refresh();
    }

    private function clicks(Link $link, \DateTimeInterface $when, int $count): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            // link_clicks has NO created_at — clicked_at is the only timestamp.
            $rows[] = ['link_id' => $link->id, 'clicked_at' => $when];
        }
        DB::table('link_clicks')->insert($rows);
    }

    public function test_empty_workspace_degrades_gracefully(): void
    {
        $user = $this->user();

        $summary = MarketingPlanActuals::summary($user, null);

        $this->assertCount(MarketingPlanActuals::MONTHS, $summary['months']);
        $this->assertFalse($summary['has_data']);
        $this->assertSame(0.0, $summary['ai_spend_last_month_inr']);
        $this->assertSame(['crm' => false, 'chat' => false, 'dialer' => false], $summary['features']);
        foreach ($summary['months'] as $m) {
            $this->assertSame(0, $m['visitors']);
            $this->assertSame(0, $m['leads']);
            $this->assertSame(0.0, $m['revenue']);
        }

        $prefill = MarketingPlanActuals::prefill($user, null);
        $this->assertFalse($prefill['sufficient']);
        $this->assertSame([], $prefill['filled']);
        // The payload stays exactly the untouched defaults for the engine keys.
        $this->assertSame(8000, $prefill['payload']['organic_visitors']);

        // The create page with ?use_actuals=1 still renders — with the
        // "not enough data" explanation instead of an error.
        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['use_actuals' => 1]))
            ->assertStatus(200)
            ->assertSee('Not enough Sayzio history yet');
    }

    public function test_summary_aggregates_visitors_leads_revenue_and_ai_spend(): void
    {
        $user = $this->user();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(3);

        // Visitors — 40 clicks last month, keyed on clicked_at.
        $this->clicks($this->link($user), $lastMonth, 40);

        // Leads — 3 non-spam submissions (+1 spam that must NOT count) + 2 contacts.
        $form = new Form();
        $form->forceFill(['user_id' => $user->id, 'slug' => 's' . Str::random(8), 'title' => 'Lead form', 'fields' => []])->save();
        foreach ([false, false, false, true] as $spam) {
            $s = new FormSubmission();
            $s->forceFill(['form_id' => $form->id, 'data' => [], 'is_spam' => $spam])->save();
            DB::table('form_submissions')->where('id', $s->id)->update(['created_at' => $lastMonth]);
        }
        foreach (range(1, 2) as $i) {
            $c = Contact::withoutEvents(fn () => Contact::create(['user_id' => $user->id, 'display_name' => 'C' . $i]));
            DB::table('contacts')->where('id', $c->id)->update(['created_at' => $lastMonth]);
        }

        // Revenue — one paid ₹2,000 storefront order (a pending one must NOT count).
        $buyer = $this->user();
        ProductOrder::withoutEvents(function () use ($user, $buyer, $lastMonth) {
            ProductOrder::create([
                'buyer_user_id' => $buyer->id, 'creator_user_id' => $user->id,
                'status' => ProductOrder::STATUS_PAID, 'subtotal_cents' => 200000,
                'currency' => 'INR', 'public_token' => Str::random(40), 'paid_at' => $lastMonth,
            ]);
            ProductOrder::create([
                'buyer_user_id' => $buyer->id, 'creator_user_id' => $user->id,
                'status' => ProductOrder::STATUS_PENDING, 'subtotal_cents' => 999900,
                'currency' => 'INR', 'public_token' => Str::random(40),
            ]);
        });

        // AI spend — 150 coins spent last month (default rate: 1 coin = ₹1).
        $wallet = app(WalletService::class)->walletFor($user);
        WalletTransaction::forceCreate([
            'wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'spend',
            'delta_coins' => -150, 'balance_after' => 0, 'created_at' => $lastMonth,
        ]);

        $summary = MarketingPlanActuals::summary($user, null);
        $ym = $lastMonth->format('Y-m');
        $month = collect($summary['months'])->firstWhere('ym', $ym);

        $this->assertTrue($summary['has_data']);
        $this->assertSame(40, $month['visitors']);
        $this->assertSame(5, $month['leads']);       // 3 non-spam submissions + 2 contacts
        $this->assertSame(1, $month['customers']);
        $this->assertSame(2000.0, $month['revenue']);
        $this->assertSame(150.0, $summary['ai_spend_last_month_inr']);
        $this->assertTrue($summary['features']['crm']); // contacts exist

        // Prefill derives rates from the ratios, clamped to bounds.
        $prefill = MarketingPlanActuals::prefill($user, null, $summary);
        $this->assertTrue($prefill['sufficient']);
        $this->assertSame(40, $prefill['payload']['organic_visitors']);
        $sayzio = collect($prefill['payload']['channels'])->firstWhere('key', 'sayzio');
        $this->assertSame(12.5, $sayzio['vl']);   // 5 / 40
        $this->assertSame(20.0, $sayzio['lc']);   // 1 / 5
        $this->assertSame(2000.0, $sayzio['acv']); // 2000 / 1
        $this->assertSame(150.0, $prefill['payload']['ai_credits']);
        $this->assertEqualsCanonicalizing(
            ['organic_visitors', 'sayzio_vl', 'sayzio_lc', 'sayzio_acv', 'ai_credits'],
            $prefill['filled']
        );
    }

    public function test_actuals_respect_workspace_scoping(): void
    {
        $user = $this->user();
        $ws   = Workspace::create(['owner_user_id' => $user->id, 'name' => 'Team', 'slug' => 'w' . Str::random(6)]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

        // Personal (NULL workspace) traffic + a contact…
        $this->clicks($this->link($user, null), $lastMonth, 7);
        $c1 = Contact::withoutEvents(fn () => Contact::create(['user_id' => $user->id, 'display_name' => 'Personal']));
        DB::table('contacts')->where('id', $c1->id)->update(['workspace_id' => null, 'created_at' => $lastMonth]);

        // …and separate team-workspace traffic + a contact.
        $this->clicks($this->link($user, $ws->id), $lastMonth, 11);
        $c2 = Contact::withoutEvents(fn () => Contact::create(['user_id' => $user->id, 'display_name' => 'Team']));
        DB::table('contacts')->where('id', $c2->id)->update(['workspace_id' => $ws->id, 'created_at' => $lastMonth]);

        $ym = $lastMonth->format('Y-m');

        $personal = collect(MarketingPlanActuals::summary($user, null)['months'])->firstWhere('ym', $ym);
        $this->assertSame(7, $personal['visitors']);
        $this->assertSame(1, $personal['leads']);

        $team = collect(MarketingPlanActuals::summary($user, $ws->id)['months'])->firstWhere('ym', $ym);
        $this->assertSame(11, $team['visitors']);
        $this->assertSame(1, $team['leads']);
    }

    public function test_account_level_revenue_and_ai_spend_never_leak_into_team_workspaces(): void
    {
        $user = $this->user();
        $ws   = Workspace::create(['owner_user_id' => $user->id, 'name' => 'Team', 'slug' => 'w' . Str::random(6)]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

        // Account-level storefront order + wallet AI spend (no workspace column)…
        $buyer = $this->user();
        ProductOrder::withoutEvents(fn () => ProductOrder::create([
            'buyer_user_id' => $buyer->id, 'creator_user_id' => $user->id,
            'status' => ProductOrder::STATUS_PAID, 'subtotal_cents' => 500000,
            'currency' => 'INR', 'public_token' => Str::random(40), 'paid_at' => $lastMonth,
        ]));
        $wallet = app(\App\Services\Billing\WalletService::class)->walletFor($user);
        WalletTransaction::forceCreate([
            'wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'spend',
            'delta_coins' => -80, 'balance_after' => 0, 'created_at' => $lastMonth,
        ]);

        // …and a paid client invoice pinned to the PERSONAL (NULL) workspace.
        $inv = new \App\Modules\User\Models\Invoice();
        $inv->forceFill([
            'number' => 'T' . Str::random(8), 'financial_year' => '2026-2027', 'seq' => 1,
            'user_id' => $user->id, 'kind' => 'client', 'currency' => 'INR',
            'subtotal_minor' => 120000, 'tax_total_minor' => 0, 'grand_total_minor' => 120000,
            'amount_paid_minor' => 120000, 'paid_at' => $lastMonth,
            'billing_address_snapshot' => [], 'merchant_snapshot' => [],
            'line_items' => [], 'tax_breakdown' => [],
        ])->save();
        DB::table('invoices')->where('id', $inv->id)->update(['workspace_id' => null]);

        $ym = $lastMonth->format('Y-m');

        // Personal scope sees everything.
        $personal = MarketingPlanActuals::summary($user, null);
        $pm = collect($personal['months'])->firstWhere('ym', $ym);
        $this->assertSame(6200.0, $pm['revenue']); // ₹5,000 order + ₹1,200 invoice
        $this->assertSame(80.0, $personal['ai_spend_last_month_inr']);

        // The TEAM workspace sees none of the account-level financials.
        $team = MarketingPlanActuals::summary($user, $ws->id);
        $tm = collect($team['months'])->firstWhere('ym', $ym);
        $this->assertSame(0.0, $tm['revenue']);
        $this->assertSame(0, $tm['customers']);
        $this->assertSame(0.0, $team['ai_spend_last_month_inr']);

        // …and the derived prefill can't surface them either.
        $prefill = MarketingPlanActuals::prefill($user, $ws->id, $team);
        $this->assertArrayNotHasKey('sayzio_acv', $prefill['values']);
        $this->assertArrayNotHasKey('ai_credits', $prefill['values']);
    }

    public function test_chat_and_dialer_feature_badges_never_leak_into_team_workspaces(): void
    {
        $user = $this->user();
        $ws   = Workspace::create(['owner_user_id' => $user->id, 'name' => 'Team', 'slug' => 'w' . Str::random(6)]);

        // Account-level feature activity (no workspace column on either table).
        $persona = \App\Modules\User\Models\AiPersonaAgent::forceCreate([
            'user_id' => $user->id, 'name' => 'Zio persona',
            'system_prompt' => 'test', 'model' => 'gpt-test',
        ]);
        \App\Modules\User\Models\AiCompanion::forceCreate([
            'user_id' => $user->id, 'persona_id' => $persona->id,
            'public_id' => Str::lower(Str::random(24)), 'name' => 'Zio',
            'placement' => 'biolink', 'is_disabled' => false,
        ]);
        $contact = Contact::withoutEvents(fn () => Contact::create(['user_id' => $user->id, 'display_name' => 'Callee']));
        DB::table('contacts')->where('id', $contact->id)->update(['workspace_id' => null]);
        ContactCallLog::forceCreate([
            'user_id' => $user->id, 'contact_id' => $contact->id, 'number' => '+911234567890',
            'direction' => 'outgoing', 'occurred_at' => now(),
        ]);

        // Personal scope reports the features as in use…
        $personal = MarketingPlanActuals::features($user, null);
        $this->assertTrue($personal['chat']);
        $this->assertTrue($personal['dialer']);
        $this->assertTrue($personal['crm']);

        // …but a TEAM workspace never inherits account-level activity.
        $team = MarketingPlanActuals::features($user, $ws->id);
        $this->assertFalse($team['chat']);
        $this->assertFalse($team['dialer']);
        $this->assertFalse($team['crm']); // the contact lives in the personal scope
    }

    public function test_actuals_endpoint_returns_summary_and_prefill(): void
    {
        $user = $this->user();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();
        $this->clicks($this->link($user), $lastMonth, 12);

        $resp = $this->actingAs($user, 'web')
            ->getJson(route('user.marketing-plan.actuals'));

        $resp->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('actuals.has_data', true)
            ->assertJsonPath('prefill.sufficient', true)
            ->assertJsonPath('prefill.values.organic_visitors', 12);

        $this->assertCount(MarketingPlanActuals::MONTHS, $resp->json('actuals.months'));
    }

    public function test_member_in_owners_workspace_sees_owner_scoped_actuals_not_their_own(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();

        $teamWs = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Team', 'slug' => 'w' . Str::random(6)]);
        \App\Modules\User\Models\WorkspaceMember::create([
            // Task #6766 review — actuals are gated: only analytics-capable
            // roles (admin / analyst) may see the owner's business actuals.
            'workspace_id' => $teamWs->id, 'user_id' => $member->id, 'role' => 'analyst',
        ]);

        // The OWNER's team-workspace traffic…
        $this->clicks($this->link($owner, $teamWs->id), $lastMonth, 9);
        // …and the MEMBER's own personal traffic that must never surface here.
        $this->clicks($this->link($member, null), $lastMonth, 33);

        $resp = $this->actingAs($member, 'web')
            ->withSession([\App\Modules\User\Services\WorkspaceContext::SESSION_KEY => $teamWs->id])
            ->getJson(route('user.marketing-plan.actuals'));

        $resp->assertStatus(200)->assertJsonPath('ok', true);
        $month = collect($resp->json('actuals.months'))->firstWhere('ym', $lastMonth->format('Y-m'));
        $this->assertSame(9, $month['visitors']); // owner's workspace data, not the member's 33
    }

    public function test_unauthorized_member_cannot_access_owner_actuals(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();

        $teamWs = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Team', 'slug' => 'w' . Str::random(6)]);
        \App\Modules\User\Models\WorkspaceMember::create([
            // Editor can build plans but is NOT analytics-capable.
            'workspace_id' => $teamWs->id, 'user_id' => $member->id, 'role' => 'editor',
        ]);
        $this->clicks($this->link($owner, $teamWs->id), $lastMonth, 9);

        $session = [\App\Modules\User\Services\WorkspaceContext::SESSION_KEY => $teamWs->id];

        // JSON endpoint: hard 403.
        $this->actingAs($member, 'web')->withSession($session)
            ->getJson(route('user.marketing-plan.actuals'))
            ->assertStatus(403);

        // Editor create view: loads, but with no actuals data embedded.
        $this->actingAs($member, 'web')->withSession($session)
            ->get(route('user.marketing-plan.create'))
            ->assertStatus(200)
            ->assertDontSee('"visitors":9', false)
            ->assertDontSee('"has_data"', false);
    }

    public function test_create_with_use_actuals_prefills_and_flags_fields(): void
    {
        $user = $this->user();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();
        $this->clicks($this->link($user), $lastMonth, 25);

        $form = new Form();
        $form->forceFill(['user_id' => $user->id, 'slug' => 's' . Str::random(8), 'title' => 'F', 'fields' => []])->save();
        $s = new FormSubmission();
        $s->forceFill(['form_id' => $form->id, 'data' => [], 'is_spam' => false])->save();
        DB::table('form_submissions')->where('id', $s->id)->update(['created_at' => $lastMonth]);

        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['use_actuals' => 1]))
            ->assertStatus(200)
            ->assertSee('Pre-filled from your real Sayzio data')
            ->assertSee('monthly organic visitors');
    }

    public function test_feature_flags_reflect_dialer_usage(): void
    {
        $user    = $this->user();
        $contact = Contact::withoutEvents(fn () => Contact::create(['user_id' => $user->id, 'display_name' => 'Callee']));
        ContactCallLog::forceCreate([
            'user_id' => $user->id, 'contact_id' => $contact->id, 'number' => '+911234567890',
            'direction' => 'outgoing', 'occurred_at' => now(),
        ]);

        $features = MarketingPlanActuals::features($user, null);
        $this->assertTrue($features['dialer']);
        $this->assertFalse($features['chat']);
    }
}
