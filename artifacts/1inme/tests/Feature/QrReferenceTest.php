<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\AI\QrArtGenerationException;
use App\Services\AI\QrArtService;
use App\Services\AI\QrReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function setupReference(): array
    {
        Cache::flush();
        Http::preventStrayRequests();
        $u = User::factory()->create();
        $ws = $u->ownedWorkspaces()->first();
        $this->actingAs($u)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('fake-test-key');
        AiEngineSettings::setModels([['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true, 'supports_vision' => true, 'in_coins_per_1k' => 1, 'out_coins_per_1k' => 1]]);
        $art = $this->mock(QrArtService::class);
        $art->shouldReceive('enabled')->andReturn(true);
        $art->shouldReceive('model')->andReturn('test/qr-model');
        $art->shouldReceive('monthlyRemaining')->andReturn(-1);
        $art->shouldReceive('monthlyAllowance')->andReturn(-1);
        $art->shouldReceive('coinCost')->andReturn(12);
        $charger = $this->mock(AiUsageCharger::class);
        $charger->shouldReceive('getBalance')->andReturn(1000);
        $ai = $this->mock(OpenAiService::class);
        $ai->shouldReceive('estimateChatCoins')->andReturn(3);

        return [$u, $art, $ai, $charger];
    }

    private function quote()
    {
        return $this->postJson('/user/qr-codes/reference-estimate', ['data' => 'https://example.com', 'resemblance' => 'balanced', 'reference' => UploadedFile::fake()->image('logo.png', 200, 200)]);
    }

    public function test_estimate_is_free_generation_binds_quote_and_replay_does_not_charge(): void
    {
        [$u,$art,$ai,$charger] = $this->setupReference();
        $ai->shouldReceive('chat')->once()->andReturn(['content' => 'Purple character silhouette, soft rounded shapes', 'credits_spent' => 3]);
        $art->shouldReceive('generate')->once()->withArgs(fn ($payer, $data, $prompt, $opts) => $payer->id === $u->id && $data === 'https://example.com' && $opts['strength'] === 50)->andReturn(['image_url' => 'https://example.com/art.png', 'file_id' => 1, 'cost' => 12, 'balance' => 985]);
        $q = $this->quote()->assertOk()->assertJsonPath('cost', 15)->json('quote');
        Http::assertNothingSent();
        $this->postJson('/user/qr-codes/reference-generate', ['quote' => $q])->assertOk()->assertJsonPath('cost', 15)->assertJsonPath('data', 'https://example.com');
        $this->postJson('/user/qr-codes/reference-generate', ['quote' => $q])->assertOk()->assertJsonPath('cost', 0);
        if (getenv('QR_REFERENCE_FIXTURE')) {
            file_put_contents('/tmp/ai-qr-builder.html', $this->get('/user/qr-codes/create')->assertOk()->getContent());
        }
    }

    public function test_failure_refunds_analysis_and_expired_or_foreign_quote_is_rejected(): void
    {
        [$u,$art,$ai,$charger] = $this->setupReference();
        $ai->shouldReceive('chat')->once()->andReturn(['content' => 'Purple artwork', 'credits_spent' => 3]);
        $art->shouldReceive('generate')->once()->andThrow(new QrArtGenerationException('Provider failed'));
        $charger->shouldReceive('refund')->once()->withArgs(fn ($payer, $cost, $opts) => $payer->id === $u->id && $cost === 3);
        $q = $this->quote()->assertOk()->json('quote');
        $this->postJson('/user/qr-codes/reference-generate', ['quote' => $q])->assertStatus(422);
        $foreignQuote = $this->quote()->assertOk()->json('quote');
        Cache::forget('qr-reference:'.$q);
        $this->postJson('/user/qr-codes/reference-generate', ['quote' => $q])->assertStatus(422);
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/user/qr-codes/reference-generate', ['quote' => $foreignQuote])->assertStatus(422);
    }

    public function test_setup_error_identifies_missing_configuration_and_where_to_fix_it(): void
    {
        $this->setupReference();
        AiEngineSettings::setEnabled(false);
        $response = $this->quote()->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->assertStringContainsString('AI Engine is switched off', $response->json('errors.reference.0'));
        $this->assertStringContainsString('Admin → AI Engine', $response->json('errors.reference.0'));
        $page = $this->get('/user/qr-codes/create')->assertOk()->assertSee('AI QR setup is incomplete')->assertSee('Upload')->assertSee('My Files')->assertSee('Advanced');
        Http::assertNothingSent();
        if (getenv('QR_REFERENCE_FIXTURE')) {
            file_put_contents('/tmp/ai-qr-disabled.html', $page->getContent());
        }
    }

    public function test_setup_lists_missing_vision_model_and_qr_provider_separately(): void
    {
        $this->setupReference();
        AiEngineSettings::setModels([['name'=>'text-only','kind'=>'chat','enabled'=>true,'supports_vision'=>false,'in_coins_per_1k'=>1,'out_coins_per_1k'=>1]]);
        AiEngineSettings::setReplicateKey(null);
        config(['services.replicate.api_token' => null]);
        $service = new QrReferenceService(app(OpenAiService::class), new QrArtService(app(AiUsageCharger::class)), app(AiUsageCharger::class));
        $setup = $service->setup();
        $this->assertFalse($setup['ready']);
        $this->assertContains('The Replicate QR generator API key is missing.', $setup['issues']);
        $this->assertContains('No enabled chat model supports image input (vision).', $setup['issues']);
        Http::assertNothingSent();
    }

    public function test_reference_file_cannot_be_read_from_another_owner_and_svg_is_rejected(): void
    {
        $this->setupReference();
        $other = User::factory()->create();
        $foreign = UserFile::create(['user_id' => $other->id, 'original_name' => 'private.png', 'filename' => 'private.png', 'mime_type' => 'image/png', 'size_bytes' => 20, 'disk' => 'local', 'path' => 'private.png', 'scan_status' => 'clean']);
        $this->postJson('/user/qr-codes/reference-estimate', ['data' => 'https://example.com', 'resemblance' => 'balanced', 'file_id' => $foreign->id])->assertNotFound();
        $this->postJson('/user/qr-codes/reference-estimate', ['data' => 'https://example.com', 'resemblance' => 'balanced', 'reference' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')])->assertStatus(422);
        Http::assertNothingSent();
    }
}
