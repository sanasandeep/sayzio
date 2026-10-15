<?php
namespace Tests\Feature;
use App\Modules\User\Models\User;
use App\Services\AI\{AiEngineSettings,AiFeatureCatalog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RetiredLinkOptimizerTest extends TestCase {
 use RefreshDatabase;
 public function test_legacy_page_redirects_and_generation_is_unavailable(): void {
  $this->actingAs(User::factory()->create())->get('/user/ai/coach')->assertRedirect(route('user.links.index'));
  $this->post('/user/ai/coach/suggest')->assertNotFound();
  $this->get('/user/ai/staff')->assertOk()->assertDontSee('AI Link Optimizer');
  $this->assertTrue(\Illuminate\Support\Facades\Route::has('user.links.coach-action'));
  $this->assertTrue(\Illuminate\Support\Facades\Route::has('user.links.performance-coach.settings'));
 }
 public function test_admin_and_plan_model_catalogs_exclude_retired_tool_but_keep_ledger_label(): void {
  $this->assertNotContains('coach',AiEngineSettings::FEATURES);
  $this->assertNotContains('coach',AiFeatureCatalog::FEATURES);
  AiEngineSettings::setPlanFeatureModels(['pro'=>['coach'=>'gpt-4o-mini','persona'=>'gpt-4o-mini']]);
  $this->assertArrayNotHasKey('coach',AiEngineSettings::planFeatureModels()['pro']??[]);
  $this->assertStringContainsString('retired',AiFeatureCatalog::featureLabel('coach'));
 }
}
