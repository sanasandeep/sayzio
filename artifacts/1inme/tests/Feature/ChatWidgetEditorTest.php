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

 /**
  * PLACEMENT_PAGE has been offered in the picker since the start with
  * nowhere to send anyone: no route served it, and the editor fell through
  * to the Link-in-Bio instructions, which are about a different surface
  * entirely.
  */
 public function test_the_full_page_placement_has_a_page_and_the_editor_shows_its_link(): void
 {
  AiEngineSettings::setEnabled(true);
  $user=User::factory()->create();
  $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Support agent','system_prompt'=>'Help users','model'=>'test','fallback_behavior'=>'clarify','greeting'=>'Hi, what can I do for you?']);
  $widget=AiCompanion::create(['user_id'=>$user->id,'persona_id'=>$agent->id,'public_id'=>'cmp_fullpagefixture00001','name'=>'Support','placement'=>'page','free_turns_per_month'=>-1,'config'=>AiCompanion::defaultConfig()]);
  $estimator=\Mockery::mock(AiCostEstimator::class);$estimator->shouldReceive('companionPerDay')->andReturn(['per_day'=>0,'based_on_usage'=>false,'avg_per_turn'=>0,'ceiling_per_day'=>0]);$this->app->instance(AiCostEstimator::class,$estimator);

  // The page itself, logged out: it is a public destination.
  $page=$this->get('/companion/'.$widget->public_id)->assertOk();
  $page->assertSee('Support');
  $page->assertSee('Hi, what can I do for you?');

  // And the editor hands the owner the link rather than Link-in-Bio advice.
  $html=$this->actingAs($user)->get('/user/ai-companions/'.$widget->id)->assertOk()->getContent();
  $this->assertStringContainsString('/companion/'.$widget->public_id,$html,'the editor did not show the chat page link');
  $this->assertStringNotContainsString('How to add it to a Link in Bio',$html,'a full-page widget was given Link-in-Bio instructions');
 }

 /**
  * The URL exists exactly when the owner chose that placement. Otherwise
  * every embed widget would also quietly have a public chat page its owner
  * never asked for.
  */
 public function test_a_widget_that_is_not_full_page_has_no_chat_page(): void
 {
  $user=User::factory()->create();
  $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Support agent','system_prompt'=>'Help users','model'=>'test','fallback_behavior'=>'clarify']);
  $widget=AiCompanion::create(['user_id'=>$user->id,'persona_id'=>$agent->id,'public_id'=>'cmp_embedonlyfixture0001','name'=>'Support','placement'=>'embed','free_turns_per_month'=>-1,'config'=>AiCompanion::defaultConfig()]);

  $this->get('/companion/'.$widget->public_id)->assertNotFound();
 }

 /**
  * Every key the model carries has to be settable from the editor.
  *
  * This is the check that was missing. defaultConfig() grew to fourteen
  * keys, the web validation knew eleven, the form offered ten and the
  * mobile API wrote a different twelve -- so a custom avatar and the
  * white-label footer were supported end to end, rendered on every public
  * surface, gated by a paid entitlement, and reachable only from the phone.
  *
  * A key that is deliberately set somewhere else goes in $setElsewhere with
  * a reason, rather than being quietly dropped from the form.
  */
 public function test_every_config_key_the_model_supports_is_reachable_from_the_editor(): void
 {
  $setElsewhere = [];

  $markup = file_get_contents(resource_path('views/user/ai-companions/edit.blade.php'))
   . file_get_contents(resource_path('views/user/ai-companions/appearance.blade.php'));

  $missing = [];
  foreach (array_keys(AiCompanion::defaultConfig()) as $key) {
   if (in_array($key, $setElsewhere, true)) { continue; }
   if (! str_contains($markup, "config[{$key}]")) { $missing[] = $key; }
  }

  sort($missing);
  $this->assertSame([], $missing, 'the model supports these config keys but the editor offers no way to set them: '.implode(', ', $missing));
 }

 public function test_the_editor_can_save_an_avatar_and_custom_branding(): void
 {
  AiEngineSettings::setEnabled(true);
  $user=User::factory()->create();
  $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Support agent','system_prompt'=>'Help users','model'=>'test','fallback_behavior'=>'clarify']);
  $widget=AiCompanion::create(['user_id'=>$user->id,'persona_id'=>$agent->id,'public_id'=>'cmp_fixture_branding','name'=>'Support','placement'=>'embed','free_turns_per_month'=>-1,'config'=>AiCompanion::defaultConfig()]);

  $this->actingAs($user)->put('/user/ai-companions/'.$widget->id, [
   'name'=>'Support','persona_id'=>$agent->id,'placement'=>'embed',
   'config'=>[
    'avatar_url'=>'https://cdn.example.com/agent.png',
    'custom_branding_text'=>'Powered by Acme',
    'custom_branding_url'=>'https://acme.example.com',
    'launcher_icon'=>'fa-robot',
   ],
  ])->assertSessionHasNoErrors();

  $saved = $widget->fresh()->config;
  $this->assertSame('https://cdn.example.com/agent.png',$saved['avatar_url'],'the editor could not save an agent avatar');
  $this->assertSame('Powered by Acme',$saved['custom_branding_text'],'the editor could not save the white-label footer text');
  $this->assertSame('https://acme.example.com',$saved['custom_branding_url'],'the editor could not save the white-label footer link');
  $this->assertSame('fa-robot',$saved['launcher_icon'],'the editor could not save the launcher icon');

  // A javascript: avatar must not survive validation.
  $this->putJson('/user/ai-companions/'.$widget->id,['name'=>'Support','persona_id'=>$agent->id,'placement'=>'embed','config'=>['avatar_url'=>'javascript:alert(1)']])->assertUnprocessable();
 }

 /**
  * The fields are offered only to an owner whose plan includes them. The
  * values themselves are enforced again at read time in brandingConfig(),
  * so this is about not advertising a capability someone has not bought.
  */
 public function test_an_unentitled_owner_is_not_offered_the_branding_fields(): void
 {
  AiEngineSettings::setEnabled(true);
  $user=User::factory()->create();
  $agent=AiPersonaAgent::create(['user_id'=>$user->id,'name'=>'Support agent','system_prompt'=>'Help users','model'=>'test','fallback_behavior'=>'clarify']);
  $widget=AiCompanion::create(['user_id'=>$user->id,'persona_id'=>$agent->id,'public_id'=>'cmp_fixture_ungated','name'=>'Support','placement'=>'embed','free_turns_per_month'=>-1,'config'=>AiCompanion::defaultConfig()]);
  $estimator=\Mockery::mock(AiCostEstimator::class);$estimator->shouldReceive('companionPerDay')->andReturn(['per_day'=>0,'based_on_usage'=>false,'avg_per_turn'=>0,'ceiling_per_day'=>0]);$this->app->instance(AiCostEstimator::class,$estimator);

  $caps=$widget->brandingCapabilities();
  $html=$this->actingAs($user)->get('/user/ai-companions/'.$widget->id)->assertOk()->getContent();

  if (! $caps['can_custom_branding']) {
   $this->assertStringNotContainsString('config[custom_branding_text]',$html,'an owner without custom branding was shown the white-label fields');
  }
  if (! $caps['can_avatar']) {
   $this->assertStringNotContainsString('config[avatar_url]',$html,'an owner without the avatar capability was shown the avatar field');
  }
  $this->assertStringContainsString('config[launcher_icon]',$html,'the launcher icon is ungated and should always be offered');
 }
}
