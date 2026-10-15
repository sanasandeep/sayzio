<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class AskZioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_insights_page_renders_for_enabled_user(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $user = User::factory()->create();
        $this->withoutExceptionHandling();
        $this->actingAs($user)->get('/user/ai/ask-coach')->assertOk();
    }

    public function test_pages_render_with_previous_deployment_route_collection(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $user = User::factory()->create();
        $routes = new RouteCollection;
        foreach (app('router')->getRoutes() as $route) {
            if (! in_array($route->getName(), ['user.ai.zio-conversations', 'user.ai.zio-conversations.show', 'user.ai.staff.reports', 'admin.ai-staff-reports'])) {
                $routes->add($route);
            }
        }
        app('router')->setRoutes($routes);
        app('url')->setRoutes($routes);
        $this->withoutExceptionHandling();
        $this->actingAs($user)->get('/user/ai/ask-coach')->assertOk()->assertDontSee('Page guidance history');
        $this->get('/user/ai/staff')->assertOk()->assertDontSee('Reports &amp; activity');
    }
}
