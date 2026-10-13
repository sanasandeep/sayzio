<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\AiGrowthController;
use App\Modules\User\Models\AiGrowthReport;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiGrowthInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Cache::flush();
        AiEngineSettings::setEnabled(true);
        $u = User::create(['name' => 'Owner', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($u);
        $l = Link::create(['user_id' => $u->id, 'type' => 'restaurant_menu', 'alias' => Str::random(10), 'title' => 'Tiffins', 'is_active' => true, 'settings' => []]);
        $ai = Mockery::mock(OpenAiService::class);
        $ai->shouldReceive('estimateChatCoins')->andReturn(8);
        $this->app->instance(OpenAiService::class, $ai);
        $wallet = Mockery::mock(AiUsageCharger::class);
        $wallet->shouldReceive('getBalance')->andReturn(100);
        $this->app->instance(AiUsageCharger::class, $wallet);

        return [$u, $l, $ai, $wallet];
    }

    private function request(array $data): Request
    {
        $r = Request::create('/', 'POST', $data);
        $r->setUserResolver(fn () => auth()->user());

        return $r;
    }

    private function quote(Link $l): array
    {
        return app(AiGrowthController::class)->estimate($this->request(['link_id' => $l->id, 'context' => ['purpose' => 'Sell breakfast', 'audience' => 'Local families', 'goal' => 'Orders'], 'research' => false]))->getData(true);
    }

    private function aiOutput(): array
    {
        return ['content' => json_encode(['overview' => 'Limited data: test a clearer order CTA.', 'opportunities' => ['Promote breakfast'], 'actions' => ['Test CTA for 2 weeks; measure orders.'], 'market' => ['Unsupported market claim'], 'answer' => '', 'suggested_context' => ['goal' => 'Orders']]), 'credits_spent' => 3];
    }

    public function test_estimate_and_state_are_free_and_generation_retry_is_idempotent(): void
    {
        [$u,$l,$ai] = $this->fixture();
        $ai->shouldReceive('chat')->once()->withArgs(function ($user, $model, $messages, $opts) {
            $data = json_decode($messages[1]['content'], true);
            $this->assertNull($data['analytics']['engagement']);
            $this->assertTrue($data['analytics']['limited_data']);
            $this->assertSame('restaurant_menu', $data['analytics']['links'][0]['type']);

            return $opts['feature'] === 'coach.growth';
        })->andReturn($this->aiOutput());
        $c = app(AiGrowthController::class);
        $this->assertNull($c->state($this->request(['link_id' => $l->id]))->getData(true)['latest']);
        $q = $this->quote($l);
        $this->assertSame(8, $q['estimated_coins']);
        $first = $c->generate($this->request(['token' => $q['token']]))->getData(true);
        $retry = $c->generate($this->request(['token' => $q['token']]))->getData(true);
        $this->assertSame($first, $retry);
        $this->assertSame([], $first['report']['market']);
        $this->assertSame(1, AiGrowthReport::count());
        $this->assertFalse($c->state($this->request(['link_id' => $l->id]))->getData(true)['latest']['changed']);
    }

    public function test_other_owners_link_is_rejected(): void
    {
        [$u,$l] = $this->fixture();
        $other = User::create(['name' => 'Other', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $l->update(['user_id' => $other->id]);
        try {
            $this->quote($l);
            $this->fail('Expected access rejection');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_changed_analytics_rejects_quote_without_chat(): void
    {
        [$u,$l] = $this->fixture();
        $q = $this->quote($l);
        $l->update(['title' => 'Changed']);
        try {
            app(AiGrowthController::class)->generate($this->request(['token' => $q['token']]));
            $this->fail('Expected stale estimate');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_malformed_report_is_refunded_and_retry_does_not_charge(): void
    {
        [$u,$l,$ai,$wallet] = $this->fixture();
        $ai->shouldReceive('chat')->once()->andReturn(['content' => 'not JSON', 'credits_spent' => 3]);
        $wallet->shouldReceive('refund')->once()->andReturn(new WalletTransaction);
        $q = $this->quote($l);
        $c = app(AiGrowthController::class);
        $this->assertSame(422, $c->generate($this->request(['token' => $q['token']]))->getStatusCode());
        $this->assertSame(0, AiGrowthReport::count());
        try {
            $c->generate($this->request(['token' => $q['token']]));
            $this->fail('Expected failed token');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_folder_scope_excludes_other_folders_and_personal_identifiers(): void
    {
        [$u,$l,$ai] = $this->fixture();
        $p = Project::create(['user_id' => $u->id, 'name' => 'Breakfast', 'description' => 'More orders']);
        $l->update(['project_id' => $p->id]);
        Link::create(['user_id' => $u->id, 'type' => 'biolink', 'alias' => Str::random(10), 'title' => 'Unrelated', 'is_active' => true, 'settings' => []]);
        $ai->shouldReceive('chat')->once()->withArgs(function ($u, $m, $messages) {
            $snapshot = json_decode($messages[1]['content'], true)['analytics'];
            $this->assertSame(1, $snapshot['link_count']);
            $this->assertCount(1, $snapshot['links']);
            $this->assertStringNotContainsString('ip_address', json_encode($snapshot));

            return true;
        })->andReturn($this->aiOutput());
        $c = app(AiGrowthController::class);
        $q = $c->estimate($this->request(['project_id' => [(string) $p->id], 'context' => ['purpose' => 'Breakfast marketing'], 'research' => false]))->getData(true);
        $c->generate($this->request(['token' => $q['token']]));
    }

    public function test_followup_requires_existing_report(): void
    {
        [$u,$l] = $this->fixture();
        try {
            app(AiGrowthController::class)->estimate($this->request(['link_id' => $l->id, 'context' => [], 'research' => false, 'question' => 'What next?']));
            $this->fail('No report');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_market_search_is_opt_in_bounded_and_cited(): void
    {
        [$u,$l,$ai] = $this->fixture();
        PlatformServiceSettings::setGoogleCseApiKey('test-key');
        PlatformServiceSettings::setGoogleCseEngineId('test-engine');
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['link' => 'https://example.com/research', 'title' => 'Breakfast research', 'snippet' => 'Sample source'], ['link' => 'javascript:alert(1)', 'title' => 'Unsafe', 'snippet' => 'Unsafe']]])]);
        $c = app(AiGrowthController::class);
        $q = $c->estimate($this->request(['link_id' => $l->id, 'context' => ['purpose' => 'Breakfast orders'], 'research' => true]))->getData(true);
        Http::assertNothingSent();
        $ai->shouldReceive('chat')->once()->withArgs(function ($u, $m, $messages) {
            $sources = json_decode($messages[1]['content'], true)['sources'];
            $this->assertCount(1, $sources);
            $this->assertSame('https://example.com/research', $sources[0]['url']);

            return true;
        })->andReturn($this->aiOutput());
        $result = $c->generate($this->request(['token' => $q['token']]))->getData(true);
        $this->assertCount(1, $result['report']['sources']);
        Http::assertSent(fn ($r) => ! isset($r['searchType']) && $r['num'] === 5);
    }

    public function test_quote_cannot_be_used_by_another_account(): void
    {
        [$u,$l] = $this->fixture();
        $q = $this->quote($l);
        $other = User::create(['name' => 'Other', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($other);
        try {
            app(AiGrowthController::class)->generate($this->request(['token' => $q['token']]));
            $this->fail('Quote must be private');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_card_renders_both_scopes_without_generating_ai(): void
    {
        [$u,$l] = $this->fixture();
        $dates = ['from' => now()->subDays(7), 'to' => now()];
        $single = view('user.links.partials.ai-growth', ['growthLink' => $l, 'startDate' => $dates['from'], 'endDate' => $dates['to']])->render();
        $folder = view('user.links.partials.ai-growth', ['r' => $dates])->render();
        $this->assertStringContainsString('AI Growth Insights', $single);
        $this->assertStringContainsString('this project', $folder);
        $this->assertStringContainsString('data-estimate', $single);
        if (getenv('GROWTH_UI_FIXTURE')) {
            file_put_contents('/tmp/growth-fixture.html', '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:system-ui;margin:30px;background:#f8f9fe}input{border:1px solid #d9deeb;border-radius:10px;padding:12px;box-sizing:border-box}button{cursor:pointer}button:focus-visible,input:focus-visible{outline:2px solid #4365e9}button{font-family:inherit}</style></head><body>'.$single.'</body></html>');
        }
    }

    public function test_unfiled_scope_and_estimate_ceiling(): void
    {
        [$u,$l,$ai,$wallet] = $this->fixture();
        $out = $this->aiOutput();
        $out['credits_spent'] = 12;
        $ai->shouldReceive('chat')->once()->andReturn($out);
        $wallet->shouldReceive('refund')->once()->withArgs(fn ($user, $coins, $meta) => $coins === 4)->andReturn(new WalletTransaction);
        $c = app(AiGrowthController::class);
        $this->assertNull($c->state($this->request(['project_id' => ['none']]))->getData(true)['latest']);
        $q = $c->estimate($this->request(['project_id' => ['none'], 'context' => [], 'research' => false]))->getData(true);
        $result = $c->generate($this->request(['token' => $q['token']]))->getData(true);
        $this->assertSame(8, $result['coins_spent']);
    }

    public function test_followup_is_estimated_and_saved_separately(): void
    {
        [$u,$l,$ai] = $this->fixture();
        $ai->shouldReceive('chat')->twice()->andReturn($this->aiOutput());
        $c = app(AiGrowthController::class);
        $q = $this->quote($l);
        $c->generate($this->request(['token' => $q['token']]));
        $follow = $c->estimate($this->request(['link_id' => $l->id, 'context' => [], 'research' => false, 'question' => 'Which experiment first?']))->getData(true);
        $this->assertSame(1, AiGrowthReport::count());
        $result = $c->generate($this->request(['token' => $follow['token']]))->getData(true);
        $this->assertSame('Which experiment first?', $result['report']['question']);
        $this->assertSame(2, AiGrowthReport::count());
    }
}
