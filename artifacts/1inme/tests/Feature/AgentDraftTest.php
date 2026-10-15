<?php
namespace Tests\Feature;
use App\Modules\User\Models\User;
use App\Modules\User\Models\AiPersonaAgent;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AgentDraftTest extends TestCase
{
    use RefreshDatabase;
    public function test_foreign_agent_cannot_be_drafted(): void
    {
        AiEngineSettings::setEnabled(true);
        $owner=User::factory()->create();
        $agent=AiPersonaAgent::create(['user_id'=>$owner->id,'name'=>'Private','system_prompt'=>'Private prompt','model'=>'test','fallback_behavior'=>'clarify']);
        $this->actingAs(User::factory()->create())->postJson("/user/ai-personas/{$agent->id}/draft",['brief'=>'Create a helpful agent'])->assertForbidden();
    }
    public function test_estimate_then_generate_does_not_save_agent_and_quote_cannot_be_replayed(): void
    {
        AiEngineSettings::setEnabled(true);
        $user=User::factory()->create();
        $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Original','system_prompt'=>'Original prompt','model'=>'test','fallback_behavior'=>'clarify']);
        $ai=\Mockery::mock(OpenAiService::class);
        $ai->shouldReceive('estimateChatCoinsForText')->andReturn(12);
        $ai->shouldReceive('chat')->once()->andReturn(['content'=>'{"name":"Draft name","system_prompt":"A useful support assistant.","allowed_actions":{"anything":true}}','credits_spent'=>8]);
        $this->app->instance(OpenAiService::class,$ai);
        $url="/user/ai-personas/{$agent->id}/draft";
        $this->actingAs($user);
        $html=$this->get("/user/ai-personas/{$agent->id}")->assertOk()->getContent();
        file_put_contents('/tmp/agent-editor-fixture.html',$html);
        $this->assertStringContainsString('My Files',$html);
        $this->assertStringContainsString('Basic info',$html);
        $quote=$this->postJson($url,['brief'=>'Create a helpful support agent'])->assertOk()->json('quote');
        $this->assertSame('Original',$agent->fresh()->name);
        $this->postJson($url,['brief'=>'Create a helpful support agent','generate'=>true,'quote'=>$quote])->assertOk()->assertJsonPath('draft.name','Draft name')->assertJsonMissingPath('draft.allowed_actions');
        $this->assertSame('Original',$agent->fresh()->name);
        $this->postJson($url,['brief'=>'Create a helpful support agent','generate'=>true,'quote'=>$quote])->assertUnprocessable();
    }
}
