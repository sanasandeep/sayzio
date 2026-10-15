<?php
namespace Tests\Feature;
use App\Modules\User\Models\User;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiCompanion;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiCostEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ChatWidgetEditorTest extends TestCase
{
 use RefreshDatabase;
 public function test_editor_has_live_preview_and_preserves_unlimited_turns(): void
 {
  AiEngineSettings::setEnabled(true);
  $user=User::factory()->create();
  $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Support agent','system_prompt'=>'Help users','model'=>'test','fallback_behavior'=>'clarify']);
  $widget=AiCompanion::create(['user_id'=>$user->id,'persona_id'=>$agent->id,'public_id'=>'cmp_fixture_editor','name'=>'Support','placement'=>'embed','free_turns_per_month'=>-1,'config'=>AiCompanion::defaultConfig()]);
  $estimator=\Mockery::mock(AiCostEstimator::class);$estimator->shouldReceive('companionPerDay')->andReturn(['per_day'=>0,'based_on_usage'=>false,'avg_per_turn'=>0,'ceiling_per_day'=>0]);$this->app->instance(AiCostEstimator::class,$estimator);
  $html=$this->actingAs($user)->get('/user/ai-companions/'.$widget->id)->assertOk()->getContent();
  $this->assertStringContainsString('Live preview',$html);$this->assertStringContainsString('Basic info',$html);$this->assertStringContainsString('Unlimited',$html);
  file_put_contents('/tmp/widget-editor-fixture.html',$html);
  $this->assertSame(-1,$widget->fresh()->free_turns_per_month);
  $this->put('/user/ai-companions/'.$widget->id,['name'=>'Support','persona_id'=>$agent->id,'placement'=>'embed','config'=>['chat_mode'=>'gradient','chat_color'=>'#112233','chat_color_end'=>'#445566','user_text'=>'#ffffff','bubble_radius'=>20]])->assertSessionHasNoErrors();
  $this->assertSame('gradient',$widget->fresh()->config['chat_mode']);
  $this->assertSame(-1,$widget->fresh()->free_turns_per_month);
  $this->putJson('/user/ai-companions/'.$widget->id,['name'=>'Support','persona_id'=>$agent->id,'placement'=>'embed','config'=>['chat_image'=>'javascript:alert(1)']])->assertUnprocessable();
 }
}
