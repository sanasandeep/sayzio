<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Marketing\CampaignWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingStudioWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $plan = Plan::create(['name' => 'Studio', 'slug' => 'studio', 'monthly_price' => 100, 'status' => 'active', 'features' => ['brand_studio' => true, 'marketing_strategist' => true, 'max_links' => 100, 'max_biolinks' => 100, 'max_forms' => 100, 'max_qr_codes' => 100, 'max_brand_studio_bulk' => 10]]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'onboarded_at' => now()]);
        $this->actingAs($user);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($user));
        app()->instance('workspace_owner', $user);
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $this->mock(AiUsageCharger::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(10000));

        return $user;
    }

    public function test_planner_handoff_is_idempotent_prefills_context_and_keeps_four_simple_tabs(): void
    {
        $user = $this->owner();
        $brand = BrandKit::create(['user_id' => $user->id, 'name' => 'Aurora', 'config' => ['palette' => ['primary' => '#3454ef']]]);
        $mind = AiMind::create(['user_id' => $user->id, 'name' => 'Product knowledge']);
        $plan = NoteActionPlan::create(['user_id' => $user->id, 'title' => 'Startup launch', 'purpose' => 'project', 'notes' => 'Launch notes', 'result' => ['summary' => 'Launch an accounting service.'], 'planner_settings' => ['goal' => 'Get demo bookings', 'resources' => [['kind' => 'brand', 'id' => $brand->id], ['kind' => 'mind', 'id' => $mind->id]]]]);
        $this->post('/user/marketing-studio/from-planner/'.$plan->id)->assertRedirect();
        $this->post('/user/marketing-studio/from-planner/'.$plan->id)->assertRedirect();
        $this->assertDatabaseCount('marketing_campaigns', 1);
        $campaign = MarketingCampaign::first();
        $this->assertSame([$mind->id], $campaign->brief['mind_ids']);
        $this->assertSame($brand->id, $campaign->brief['brand_kit_id']);
        $html = $this->get('/user/marketing-studio/'.$campaign->id)->assertOk()->assertSee('Get demo bookings')->assertSee('Open connected Planner')->getContent();
        file_put_contents('/tmp/marketing-campaign.html', $html);
        $html = $this->get('/user/marketing-studio/'.$campaign->id.'/build?output=biolink')->assertOk()->assertSee('Generate editable proposal')->getContent();
        file_put_contents('/tmp/marketing-build.html', $html);
        file_put_contents('/tmp/marketing-home.html', $this->get('/user/marketing-studio')->assertOk()->getContent());
        $this->get('/user/marketing-studio/create')->assertOk()->assertSee('Quick create')->assertSee('AI Minds')->assertSee('Audience persona');
        $this->assertDatabaseCount('brand_studio_kits', 0);
        $this->get('/user/marketing-studio/'.$campaign->id.'/strategy')->assertOk()->assertSee('Get demo bookings')->assertSee('name="campaign_id"', false);
    }

    public function test_estimate_is_required_context_is_used_and_confirm_creates_drafts_back_in_planner_once(): void
    {
        $user = $this->owner();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Launch']);
        $plan = NoteActionPlan::create(['user_id' => $user->id, 'title' => 'Launch', 'purpose' => 'project', 'notes' => 'Launch notes', 'project_id' => $project->id, 'result' => ['summary' => 'Launch'], 'planner_settings' => []]);
        $mind = AiMind::create(['user_id' => $user->id, 'name' => 'Product']);
        $mind->sources()->create(['type' => 'text', 'title' => 'Pricing', 'body' => 'Unique fact: service starts at 99.', 'status' => 'ready']);
        $old = BrandStudioKit::create(['user_id' => $user->id, 'name' => 'Existing', 'mode' => 'kit', 'status' => 'created', 'proposal' => [], 'results' => []]);
        $customFolder = Project::create(['user_id' => $user->id, 'name' => 'Custom folder']);
        $published = Link::create(['user_id' => $user->id, 'type' => 'biolink', 'title' => 'Already live', 'project_id' => $customFolder->id, 'alias' => 'already-live', 'is_active' => true]);
        $old->update(['results' => ['assets' => [['kind' => 'biolink', 'id' => $published->id]]]]);
        $campaign = MarketingCampaign::create(['user_id' => $user->id, 'name' => 'Launch', 'plan_id' => $plan->id, 'project_id' => $project->id, 'brief' => ['goal' => 'Book demos', 'mind_ids' => [$mind->id]], 'studio_ids' => [$old->id]]);
        $payload = ['campaign_id' => $campaign->id, 'request' => 'Create a business page and lead form.', 'mode' => 'kit'];
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(20);
        $mock->shouldReceive('chat')->once()->andReturnUsing(function ($user, $model, $messages) {
            $this->assertStringContainsString('Unique fact: service starts at 99.', json_encode($messages));

            return ['content' => json_encode(['name' => 'Launch pack', 'assets' => [['kind' => 'biolink', 'title' => 'Book a demo', 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Welcome', 'size' => 'h1']]]], ['kind' => 'form', 'title' => 'Enquire', 'description' => 'Tell us about your business.', 'template' => 'contact']]]), 'credits_spent' => 3, 'model' => 'test'];
        });
        $this->postJson('/user/brand-studio/plan', $payload)->assertStatus(422);
        $quote = $this->postJson('/user/brand-studio/estimate', $payload)->assertOk()->json('quote');
        $this->postJson('/user/brand-studio/plan', $payload + ['quote' => $quote])->assertOk();
        $kit = BrandStudioKit::latest('id')->first();
        $this->postJson('/user/brand-studio/plan', $payload + ['quote' => $quote])->assertStatus(422);
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['selection_submitted' => 1])->assertSessionHasErrors('keep');
        $this->assertSame(1, Link::count());
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0, 1], 'edits' => [0 => ['title' => 'Our edited page']]])->assertRedirect();
        $page = Link::where('title', 'Our edited page')->firstOrFail();
        $this->assertFalse($page->is_active);
        $this->assertFalse(Form::where('title', 'Enquire')->firstOrFail()->is_active);
        $this->assertTrue($published->fresh()->is_active);
        $this->assertSame($customFolder->id, $published->fresh()->project_id);
        $this->assertSame($project->id, $page->project_id);
        $count = Link::count();
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0, 1]])->assertRedirect();
        $this->assertSame($count, Link::count());
        $this->assertTrue(ProjectItem::where('project_id', $project->id)->where('kind', 'link')->where('resource_id', $page->id)->exists());
    }

    public function test_brief_versions_restore_and_reverse_planner_connection(): void
    {
        $user = $this->owner();
        $this->post('/user/marketing-studio', ['name' => 'Original', 'path' => 'quick', 'goal' => 'Book demos'])->assertRedirect();
        $campaign = MarketingCampaign::firstOrFail();
        $this->put('/user/marketing-studio/'.$campaign->id, ['name' => 'Updated', 'path' => 'strategy', 'goal' => 'Collect enquiries'])->assertRedirect();
        $this->post('/user/marketing-studio/'.$campaign->id.'/restore/0')->assertRedirect();
        $this->assertSame('Original', $campaign->fresh()->name);
        $plan = NoteActionPlan::create(['user_id' => $user->id, 'title' => 'Existing plan', 'notes' => 'Keep tasks', 'purpose' => 'project', 'result' => [], 'planner_settings' => []]);
        $this->post('/user/marketing-studio/'.$campaign->id.'/planner', ['plan_id' => $plan->id])->assertRedirect();
        $this->assertSame($plan->id, $campaign->fresh()->plan_id);
        $this->assertNotNull($plan->fresh()->project_id);
        $this->post('/user/marketing-studio/'.$campaign->id.'/planner', ['plan_id' => $plan->id])->assertRedirect();
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_retry_skipped_materials_does_not_duplicate_or_unpublish_successful_items(): void
    {
        $user = $this->owner();
        $features = $user->plan->features;
        $features['max_links'] = 1;
        $user->plan->update(['features' => $features]);
        $user->unsetRelation('plan');
        $kit = BrandStudioKit::create(['user_id' => $user->id, 'name' => 'Two links', 'mode' => 'kit', 'status' => 'proposal', 'brand' => [], 'proposal' => ['assets' => [['kind' => 'short_link', 'title' => 'One', 'url' => 'https://example.com/one'], ['kind' => 'short_link', 'title' => 'Two', 'url' => 'https://example.com/two']]]]);
        $campaign = MarketingCampaign::create(['user_id' => $user->id, 'name' => 'Retry', 'brief' => ['goal' => 'Leads'], 'studio_ids' => [$kit->id]]);
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0, 1]])->assertRedirect();
        $this->assertSame(1, Link::count());
        $this->assertCount(1, $kit->fresh()->results['pending_assets']);
        $first = Link::first();
        $first->update(['is_active' => true]);
        $features['max_links'] = 10;
        $user->plan->update(['features' => $features]);
        $user->unsetRelation('plan');
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['retry' => 1])->assertRedirect();
        $this->assertSame(2, Link::count());
        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse(Link::where('title', 'Two')->firstOrFail()->is_active);
        $this->assertCount(0, $kit->fresh()->results['pending_assets']);
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['retry' => 1])->assertRedirect();
        $this->assertSame(2, Link::count());
    }

    public function test_strategy_generation_stays_connected_and_is_available_to_creation(): void
    {
        $user = $this->owner();
        $campaign = MarketingCampaign::create(['user_id' => $user->id, 'name' => 'Launch', 'brief' => ['goal' => 'Book demos', 'offer' => 'Accounting service'], 'studio_ids' => [], 'strategy_ids' => []]);
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(20);
        $mock->shouldReceive('chat')->once()->andReturn(['content' => json_encode(['title' => 'Launch strategy', 'summary' => 'Focus on qualified demo bookings.', 'organic' => [['channel' => 'LinkedIn', 'title' => 'Explain startup accounting', 'rationale' => 'Reach founders', 'steps' => ['Share a useful example'], 'sayzio_features' => ['Biolink']]], 'paid' => [], 'kpis' => ['Demo bookings'], 'suggestions' => []]), 'credits_spent' => 3, 'model' => 'test']);
        $payload = ['campaign_id' => $campaign->id, 'goal' => 'Book demos', 'parameters' => ['depth' => 1], 'sources' => []];
        $this->postJson('/user/ai/marketing-strategist', $payload)->assertStatus(422);
        $quote = $this->postJson('/user/ai/marketing-strategist/estimate', $payload)->assertOk()->json('quote');
        $this->post('/user/ai/marketing-strategist', $payload + ['quote' => $quote])->assertRedirect();
        $strategy = MarketingStrategy::firstOrFail();
        $this->assertSame([$strategy->id], $campaign->fresh()->strategy_ids);
        $brief = app(CampaignWorkspace::class)->brief($campaign->fresh(), $strategy->id);
        $this->assertStringContainsString('Focus on qualified demo bookings.', $brief);
        $this->get('/user/ai/marketing-strategist/'.$strategy->id)->assertOk()->assertSee('Build with this strategy');
    }

    public function test_foreign_campaigns_private_plans_and_context_are_rejected_without_ai_calls(): void
    {
        $user = $this->owner();
        $other = User::factory()->create();
        $foreign = MarketingCampaign::create(['user_id' => $other->id, 'name' => 'Private', 'brief' => []]);
        $this->get('/user/marketing-studio/'.$foreign->id)->assertNotFound();
        $plan = NoteActionPlan::create(['user_id' => $other->id, 'title' => 'Private', 'purpose' => 'project', 'notes' => 'Private notes', 'result' => [], 'planner_settings' => []]);
        $this->post('/user/marketing-studio/from-planner/'.$plan->id)->assertForbidden();
        $brand = BrandKit::create(['user_id' => $other->id, 'name' => 'Private brand', 'config' => []]);
        $this->post('/user/marketing-studio', ['name' => 'Test', 'path' => 'quick', 'goal' => 'Leads', 'brand_kit_id' => $brand->id])->assertNotFound();
        $otherWorkspace = Workspace::create(['owner_user_id' => $user->id, 'name' => 'Other team', 'slug' => 'other-team', 'is_personal' => false]);
        $foreignWorkspaceCampaign = MarketingCampaign::create(['user_id' => $user->id, 'workspace_id' => $otherWorkspace->id, 'name' => 'Other workspace secret', 'brief' => []]);
        $this->get('/user/marketing-studio/'.$foreignWorkspaceCampaign->id)->assertNotFound();
        $this->get('/user/marketing-studio')->assertOk()->assertDontSee('Other workspace secret');
        $this->assertDatabaseCount('brand_studio_kits', 0);
    }
}
