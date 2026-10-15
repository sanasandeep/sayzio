<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\ZioTranscriptRecorder;
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

    public function test_guidance_chats_appear_in_business_insights_list_and_search_is_owned(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([[$user, 'My launch question'], [$other, 'Private launch question']] as [$owner,$text]) {
            $session = SiteAssistantConversation::create(['bound_user_id' => $owner->id, 'visitor_token' => bin2hex(random_bytes(32)), 'turns_count' => 1, 'last_message_at' => now()]);
            $session->messages()->create(['role' => 'user', 'content' => $text]);
        }
        $this->actingAs($user)->get('/user/ai/ask-coach?q=launch')->assertOk()->assertSee('My launch question')->assertDontSee('Private launch question');
    }

    public function test_user_capabilities_exclude_administration_and_show_current_limits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/user/ai/voice/capabilities')->assertOk()->assertJsonMissingPath('tools.admin')->assertJsonFragment(['I can guide supported controls and prepare drafts. A prepared draft is not a saved change.']);
    }

    public function test_history_hides_empty_sessions_and_uses_recorded_message_counts_and_cost(): void
    {
        $user = User::factory()->create();
        $empty = SiteAssistantConversation::create(['bound_user_id' => $user->id, 'visitor_token' => bin2hex(random_bytes(32)), 'last_page_title' => 'Empty visit']);
        $token = bin2hex(random_bytes(32));
        $conversation = SiteAssistantConversation::create(['bound_user_id' => $user->id, 'visitor_token' => $token]);
        ZioTranscriptRecorder::record($user, $token, [['role' => 'user', 'content' => 'Help with my links'], ['role' => 'assistant', 'content' => 'Here is help', 'credits_spent' => 7]]);
        $this->assertSame(1, $conversation->fresh()->turns_count);
        $this->assertSame(7, $conversation->fresh()->credits_spent);
        $conversation->update(['turns_count' => 0, 'credits_spent' => 0]);
        $this->actingAs($user)->get('/user/ai/zio-conversations')->assertOk()->assertDontSee('Empty visit')->assertSee('Help with my links')->assertSee('7 recorded coins')->assertSee('1 messages from you');
    }
}
