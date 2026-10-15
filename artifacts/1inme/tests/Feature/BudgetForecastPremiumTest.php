<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\BudgetForecast;
use App\Services\MarketingPlanDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BudgetForecastPremiumTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $plan = Plan::create(['name' => 'Forecast', 'slug' => 'forecast', 'monthly_price' => 0, 'status' => 'active', 'features' => ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1, 'marketing_strategist' => true]]);
        $u = User::factory()->create(['role' => 'user', 'plan_id' => $plan->id, 'onboarded_at' => now()]);
        $this->actingAs($u);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($u));
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function saved(User $u): MarketingPlanCalc
    {
        return MarketingPlanCalc::create(['user_id' => $u->id, 'workspace_id' => app('current_workspace')->id, 'name' => 'Launch budget', 'payload' => MarketingPlanDefaults::defaults($u)]);
    }

    public function test_editor_renders_premium_workflow_and_versions_restore_without_dropping_actuals(): void
    {
        $u = $this->owner();
        $p = $this->saved($u);
        $html = $this->get(route('user.marketing-plan.edit', $p))->assertOk()->assertSee('Budget & Forecast')->assertSee('Create materials in Studio')->assertSee('AI Assist')->getContent();
        file_put_contents('/tmp/forecast-premium.html', $html);
        $body = $p->payload;
        $body['annual_budget'] = 12000;
        $body['costs'] = [['name' => 'Content', 'amount' => 100, 'frequency' => 'monthly', 'month' => 0]];
        $body['actuals_log'] = [['revenue' => 333]];
        $this->putJson(route('user.marketing-plan.update', $p), ['name' => 'Revised', 'payload' => $body])->assertOk();
        $v = $this->getJson(route('user.marketing-plan.versions', $p))->assertOk()->json('versions.0.id');
        $this->postJson(route('user.marketing-plan.restore', [$p, $v]))->assertOk()->assertJsonPath('payload.annual_budget', 180000)->assertJsonPath('payload.actuals_log.0.revenue', 333);
        $this->assertDatabaseCount('marketing_forecast_versions', 2);
        $campaign = MarketingCampaign::create(['user_id' => $u->id, 'name' => 'Campaign budget', 'brief' => ['budget' => 'INR 10,000 per month']]);
        $this->get(route('user.marketing-plan.create', ['from_campaign' => $campaign->id]))->assertOk()->assertViewHas('payload', fn ($b) => (int) $b['annual_budget'] === 120000 && $b['campaign_id'] === $campaign->id);
    }

    public function test_native_connections_are_idempotent_and_reject_foreign_projects(): void
    {
        $u = $this->owner();
        $p = $this->saved($u);
        $project = Project::create(['user_id' => $u->id, 'name' => 'Launch']);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('user.marketing-plan.connect', $p), ['action' => 'studio', 'project_id' => $project->id])->assertOk();
        }
        $this->assertDatabaseCount('marketing_campaigns', 1);
        $this->assertSame($project->id, MarketingCampaign::first()->project_id);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('user.marketing-plan.connect', $p), ['action' => 'planner'])->assertOk();
        }
        $this->assertDatabaseCount('note_action_plans', 1);
        $second = $this->saved($u);
        $this->postJson(route('user.marketing-plan.connect', $second), ['action' => 'studio', 'campaign_id' => MarketingCampaign::first()->id])->assertOk();
        $this->postJson(route('user.marketing-plan.connect', $second), ['action' => 'planner'])->assertOk();
        $this->assertDatabaseCount('note_action_plans', 1);
        $other = User::factory()->create();
        $foreign = Project::create(['user_id' => $other->id, 'name' => 'Private']);
        $this->postJson(route('user.marketing-plan.connect', $p), ['action' => 'project', 'project_id' => $foreign->id])->assertNotFound();
    }

    public function test_report_share_is_signed_snapshot_and_revocable(): void
    {
        $u = $this->owner();
        $p = $this->saved($u);
        $url = $this->postJson(route('user.marketing-plan.share', $p), ['days' => 7])->assertOk()->json('url');
        Auth::logout();
        $this->get($url)->assertOk()->assertSee('read-only snapshot')->assertDontSee('mind_ids');
        $this->actingAs($u);
        $this->travel(8)->days();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        $this->get(str_replace('version=', 'version=9', $url).'&bad=1')->assertForbidden();
        $this->deleteJson(route('user.marketing-plan.revoke', $p))->assertOk();
        $this->get($url)->assertForbidden();
    }

    public function test_versions_are_private_and_invalid_ai_response_is_refunded(): void
    {
        $u = $this->owner();
        $p = $this->saved($u);
        $other = User::factory()->create(['plan_id' => $u->plan_id, 'onboarded_at' => now()]);
        $foreign = MarketingPlanCalc::create(['user_id' => $other->id, 'workspace_id' => app('current_workspace')->id, 'name' => 'Private', 'payload' => []]);
        $this->getJson(route('user.marketing-plan.versions', $foreign))->assertNotFound();
        $this->postJson(route('user.marketing-plan.share', $foreign), ['days' => 7])->assertNotFound();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $charger = $this->mock(AiUsageCharger::class);
        $charger->shouldReceive('getBalance')->andReturn(1000);
        $charger->shouldReceive('refund')->once()->withArgs(fn ($owner, $coins) => $owner->id === $u->id && $coins === 4);
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(10);
        $mock->shouldReceive('chat')->once()->andReturn(['content' => 'invalid JSON', 'credits_spent' => 4]);
        $d = ['operation' => 'review', 'payload' => $p->payload];
        $q = $this->postJson(route('user.marketing-plan.assist.estimate'), $d)->assertOk()->json('quote');
        $this->postJson(route('user.marketing-plan.assist.generate'), $d + ['quote' => $q])->assertStatus(422)->assertJsonPath('message', 'The AI suggestion could not be read. Any charged coins were refunded. Estimate again to retry.');
    }

    public function test_period_costs_delayed_sales_refunds_and_currency_safe_calculation(): void
    {
        $p = ['period_months' => 3, 'annual_budget' => 3000, 'weights' => array_fill(0, 12, 1), 'channels' => [['key' => 'search', 'alloc' => 100, 'cpv' => 10, 'vl' => 10, 'lc' => 50, 'acv' => 100, 'fixed' => false]], 'costs' => [['name' => 'Content', 'amount' => 100, 'frequency' => 'monthly'], ['name' => 'Setup', 'amount' => 500, 'frequency' => 'once', 'month' => 0]], 'sales_delay' => 1, 'refund_rate' => 10, 'gross_margin' => 50];
        $r = app(BudgetForecast::class)->calculate($p);
        $this->assertEquals(3800, $r['totals']['spend']);
        $this->assertEquals(10, $r['totals']['customers']);
        $this->assertEquals(900, $r['totals']['revenue']);
        $this->assertEquals(-3350, $r['totals']['contribution']);
        file_put_contents('/tmp/forecast-parity.json', json_encode(['payload' => $p, 'expected' => $r]));
    }

    public function test_assistance_requires_fresh_quote_uses_mind_facts_and_does_not_apply_changes(): void
    {
        $u = $this->owner();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $this->mock(AiUsageCharger::class, fn ($m) => $m->shouldReceive('getBalance')->andReturn(1000));
        $mind = AiMind::create(['user_id' => $u->id, 'name' => 'Knowledge']);
        $mind->sources()->create(['type' => 'text', 'title' => 'Policy', 'body' => 'Our maximum launch budget is INR 5000.', 'status' => 'ready']);
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(10);
        $mock->shouldReceive('chat')->once()->andReturnUsing(function ($owner, $model, $messages) {
            $this->assertStringContainsString('Our maximum launch budget', json_encode($messages));

            return ['content' => json_encode(['explanation' => 'A smaller test budget is appropriate.', 'suggestions' => ['annual_budget' => 5000, 'arbitrary' => 'discarded']]), 'credits_spent' => 2];
        });
        $d = ['operation' => 'suggest_budget', 'payload' => MarketingPlanDefaults::defaults($u), 'mind_ids' => [$mind->id]];
        $this->postJson(route('user.marketing-plan.assist.generate'), $d)->assertStatus(422);
        $q = $this->postJson(route('user.marketing-plan.assist.estimate'), $d)->assertOk()->json('quote');
        $this->postJson(route('user.marketing-plan.assist.generate'), $d + ['quote' => $q])->assertOk()->assertJsonPath('suggestions.annual_budget', 5000)->assertJsonMissingPath('suggestions.arbitrary');
        $this->assertDatabaseCount('marketing_plan_calcs', 0);
        $this->postJson(route('user.marketing-plan.assist.generate'), $d + ['quote' => $q])->assertStatus(422);
    }
}
