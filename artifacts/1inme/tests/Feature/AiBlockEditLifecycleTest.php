<?php
namespace Tests\Feature;

use App\Modules\User\Controllers\AiBlockEditorController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AiBlockEditLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Cache::flush(); AiEngineSettings::setEnabled(true);
        $user=User::create(['name'=>'Editor','email'=>Str::random(12).'@example.com','password'=>bcrypt('test'),'status'=>'active']);
        $this->actingAs($user);
        $link=Link::create(['user_id'=>$user->id,'type'=>'biolink','alias'=>'edit-'.Str::random(8),'title'=>'Original','is_active'=>true,'settings'=>[]]);
        $block=$link->biolinkBlocks()->create(['type'=>'text','settings'=>['text'=>'Old'],'is_active'=>true,'sort_order'=>0]);
        $controller=app(AiBlockEditorController::class);
        $state=(new \ReflectionMethod($controller,'state'))->invoke($controller,$link);
        $token=(string) Str::uuid(); $key='ai-block-edit:'.$user->id.':'.$link->id.':'.$token;
        Cache::put($key,['state'=>$state,'hash'=>hash('sha256',json_encode($state)),'data'=>['scope'=>'selected','ids'=>[(int)$block->id]],'messages'=>[],'model'=>AiEngineSettings::featureModel('biolink_builder',$user),'types'=>['text'],'estimate'=>5],600);
        $request=Request::create('/','POST',['token'=>$token]);$request->setUserResolver(fn()=>$user);
        return [$controller,$link,$block,$request,$key];
    }
    public function test_generation_retry_is_cached_and_apply_is_idempotent(): void
    {
        [$controller,$link,$block,$request]=$this->fixture();
        $ai=Mockery::mock(OpenAiService::class);
        $ai->shouldReceive('estimateChatCoins')->once()->andReturn(5);
        $ai->shouldReceive('chat')->once()->andReturn(['content'=>json_encode(['updates'=>[['id'=>(int)$block->id,'settings'=>['text'=>'New']]]]),'credits_spent'=>3]);
        $this->app->instance(OpenAiService::class,$ai);
        $credits=Mockery::mock(AiUsageCharger::class);$credits->shouldReceive('getBalance')->once()->andReturn(17);$this->app->instance(AiUsageCharger::class,$credits);
        $first=$controller->generate($request,$link)->getData(true);
        $second=$controller->generate($request,$link)->getData(true);
        $this->assertSame($first,$second);
        $this->assertSame('Old',$block->fresh()->settings['text']);
        $controller->apply($request,$link);$controller->apply($request,$link);
        $this->assertSame('New',$block->fresh()->settings['text']);
    }
    public function test_invalid_ai_output_is_refunded_and_never_applied(): void
    {
        [$controller,$link,$block,$request]=$this->fixture();
        $ai=Mockery::mock(OpenAiService::class);$ai->shouldReceive('estimateChatCoins')->once()->andReturn(5);$ai->shouldReceive('chat')->once()->andReturn(['content'=>'not json','credits_spent'=>3]);$this->app->instance(OpenAiService::class,$ai);
        $credits=Mockery::mock(AiUsageCharger::class);$credits->shouldReceive('refund')->once()->withArgs(fn($u,$amount,$meta)=>$amount===3)->andReturn(new \App\Modules\User\Models\WalletTransaction());$this->app->instance(AiUsageCharger::class,$credits);
        $this->assertSame(422,$controller->generate($request,$link)->getStatusCode());
        $this->assertSame('Old',$block->fresh()->settings['text']);
    }
    public function test_stale_draft_cannot_overwrite_a_manual_edit(): void
    {
        [$controller,$link,$block,$request,$key]=$this->fixture();
        $quote=Cache::get($key);$quote['draft']=['changes'=>['updates'=>[],'delete_ids'=>[],'add'=>[],'order'=>[],'page'=>['title'=>'AI title']]];Cache::put($key,$quote,600);
        $block->update(['settings'=>['text'=>'Manual edit']]);
        try { $controller->apply($request,$link);$this->fail('Stale draft must be rejected.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->assertSame('Manual edit',$block->fresh()->settings['text']);$this->assertSame('Original',$link->fresh()->title);
    }
}
