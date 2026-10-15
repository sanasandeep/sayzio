<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingCampaign;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Brand\AiBrandStudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioMixedPagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $features = []): User
    {
        $plan = Plan::create(['name' => 'Studio', 'slug' => 'studio', 'monthly_price' => 100, 'status' => 'active', 'features' => $features + ['brand_studio' => true, 'max_links' => 100, 'max_biolinks' => 100, 'max_brand_studio_bulk' => 10]]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'onboarded_at' => now()]);
        $this->actingAs($user);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($user));
        app()->instance('workspace_owner', $user);
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $this->mock(AiUsageCharger::class, fn ($m) => $m->shouldReceive('getBalance')->andReturn(10000));

        return $user;
    }

    private function assets(): array
    {
        return [
            ['kind' => 'calendar', 'title' => 'Events', 'description' => 'Upcoming community events'],
            ['kind' => 'restaurant_menu', 'title' => 'Cafe menu', 'currency' => 'INR', 'categories' => [['name' => 'Coffee', 'items' => [['name' => 'Latte', 'price' => 150, 'description' => 'Fresh coffee']]]]],
            ['kind' => 'store_menu', 'title' => 'Store', 'currency' => 'INR', 'categories' => [['name' => 'Merch', 'items' => [['name' => 'Mug', 'price' => 250, 'description' => 'Ceramic mug']]]]],
            ['kind' => 'biolink', 'title' => 'Welcome', 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Hello', 'size' => 'h1']]]],
        ];
    }

    public function test_mixed_combination_creates_native_editable_pages_with_mind_context_and_project(): void
    {
        $user = $this->owner();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        $project = Project::create(['user_id' => $user->id, 'name' => 'Cafe launch']);
        $mind = AiMind::create(['user_id' => $user->id, 'name' => 'Cafe knowledge']);
        $mind->sources()->create(['type' => 'text', 'title' => 'Facts', 'body' => 'Our shop opens at nine.', 'status' => 'ready']);
        $html = $this->get('/user/brand-studio')->assertOk()->assertSee('Mix page types')->assertSee('Cafe launch')->assertSee('Cafe knowledge')->getContent();
        file_put_contents('/tmp/studio-mix-builder.html', $html);
        $brand = BrandKit::create(['user_id' => $user->id, 'name' => 'Cafe identity', 'config' => ['palette' => ['primary' => '#123456']]]);
        $assets = $this->assets();
        $composition = array_map(fn ($a) => ['kind' => $a['kind'], 'count' => 1, 'purpose' => $a['title']], $assets);
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(12);
        $mock->shouldReceive('chat')->once()->andReturnUsing(function ($u, $model, $messages) use ($assets) {
            $this->assertStringContainsString('Our shop opens at nine.', json_encode($messages));
            $this->assertStringContainsString('calendar', json_encode($messages));

            return ['content' => json_encode(['name' => 'Cafe launch', 'assets' => $assets]), 'credits_spent' => 0];
        });
        $payload = ['brand_kit_id' => $brand->id, 'request' => 'Build my cafe pages', 'mode' => 'kit', 'composition' => $composition, 'project_id' => $project->id, 'mind_ids' => [$mind->id]];
        $this->postJson('/user/brand-studio/estimate', $payload)->assertOk();
        $this->postJson('/user/brand-studio/plan', $payload)->assertOk();
        $kit = BrandStudioKit::firstOrFail();
        $this->get('/user/brand-studio/'.$kit->id)->assertOk()->assertSee('Latte')->assertSee('250.00');
        $this->postJson('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0, 1, 2, 3]])->assertOk()->assertJsonPath('created', 4);
        $this->assertSame(4, Link::where('project_id', $project->id)->count());
        $this->assertSame(4, Link::where('settings->brand_identity->kit_id', $brand->id)->count());
        $calendar = Calendar::firstOrFail();
        $this->assertSame(0, $calendar->events()->count());
        $this->assertSame($calendar->id, $calendar->link->calendar_id);
        $this->assertFalse($calendar->link->is_active);
        $this->assertSame('Latte', RestaurantMenu::firstOrFail()->items()->firstOrFail()->name);
        $this->assertSame('250.00', StoreMenu::firstOrFail()->products()->firstOrFail()->price);
        $this->get('/user/brand-studio/'.$kit->id)->assertOk()->assertSee('/store', false)->assertSee('/restaurant', false)->assertSee('/editor', false);
        $this->postJson('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0, 1, 2, 3]])->assertOk()->assertJsonPath('created', 0);
        $this->assertDatabaseCount('links', 4);
    }

    public function test_run_brand_takes_priority_over_campaign_default(): void
    {
        $user = $this->owner();
        $default = BrandKit::create(['user_id' => $user->id, 'name' => 'Default identity', 'config' => ['palette' => ['primary' => '#111111']]]);
        $selected = BrandKit::create(['user_id' => $user->id, 'name' => 'Selected identity', 'config' => ['palette' => ['primary' => '#223344']]]);
        $campaign = MarketingCampaign::create(['user_id' => $user->id, 'name' => 'Campaign', 'brief' => ['goal' => 'Launch events', 'brand_kit_id' => $default->id]]);
        $mock = $this->mock(OpenAiService::class);
        $mock->shouldReceive('estimateChatCoins')->andReturn(12);
        $mock->shouldReceive('chat')->once()->andReturnUsing(function ($u, $model, $messages) {
            $this->assertStringContainsString('Selected identity', json_encode($messages));
            $this->assertStringNotContainsString('Default identity', json_encode($messages));

            return ['content' => json_encode(['name' => 'Event pack', 'assets' => [['kind' => 'calendar', 'title' => 'Events']]]), 'credits_spent' => 0];
        });
        $payload = ['request' => 'Build a calendar', 'campaign_id' => $campaign->id, 'brand_kit_id' => $selected->id, 'composition' => [['kind' => 'calendar', 'count' => 1]]];
        $quote = $this->postJson('/user/brand-studio/estimate', $payload)->assertOk()->json('quote');
        $this->postJson('/user/brand-studio/plan', $payload + ['quote' => $quote])->assertOk();
        $kit = BrandStudioKit::firstOrFail();
        $this->post('/user/brand-studio/'.$kit->id.'/confirm', ['keep' => [0]])->assertRedirect();
        $this->assertSame($selected->id, Link::firstOrFail()->settings['brand_identity']['kit_id']);
    }

    public function test_module_caps_skip_native_page_and_foreign_context_is_rejected(): void
    {
        $user = $this->owner(['module_store_menu' => false]);
        $service = app(AiBrandStudioService::class);
        $assets = $service->sanitizeAssets($this->assets(), 'kit', null, 1);
        $kit = BrandStudioKit::create(['user_id' => $user->id, 'name' => 'Mixed', 'mode' => 'kit', 'status' => 'proposal', 'proposal' => ['assets' => $assets]]);
        $result = $service->materialize($user, $kit);
        $this->assertSame(3, $result['created']);
        $this->assertDatabaseCount('store_menus', 0);
        $this->assertStringContainsString('not included', implode(' ', $result['skipped']));
        $other = User::factory()->create();
        $mind = AiMind::create(['user_id' => $other->id, 'name' => 'Private']);
        $this->postJson('/user/brand-studio/estimate', ['request' => 'Hello', 'mind_ids' => [$mind->id]])->assertNotFound();
        $project = Project::create(['user_id' => $other->id, 'name' => 'Private']);
        $this->postJson('/user/brand-studio/estimate', ['request' => 'Hello', 'project_id' => $project->id])->assertNotFound();
    }
}
