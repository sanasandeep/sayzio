<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AudienceTypeEstimationService;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AudienceTypeEstimationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserAndLink(): array
    {
        $plan = Plan::create([
            'name'   => 'Test Plan '.Str::random(4),
            'slug'   => 'plan-'.Str::lower(Str::random(8)),
            'status' => true,
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id]);

        $link = Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => 'aud-'.Str::lower(Str::random(10)),
            'url'      => null,
            'settings' => [],
        ]);

        return [$user, $link];
    }

    public function test_estimate_returns_empty_result_when_no_sessions(): void
    {
        [$user, $link] = $this->makeUserAndLink();

        // No page_sessions rows exist — the service must short-circuit
        // BEFORE any AI call, exercising the gatherSignals() key contract
        // (a signal-key mismatch throws ErrorException here).
        $service = app(AudienceTypeEstimationService::class);

        $result = $service->estimate($user, $link, now()->subDays(30), now());

        $this->assertSame([], $result['estimated']);
        $this->assertSame(0, $result['tokens_in']);
        $this->assertSame(0, $result['tokens_out']);
        $this->assertSame(0, $result['credits_spent']);
    }

    public function test_estimate_with_sessions_reads_all_signal_keys_and_parses_ai_response(): void
    {
        [$user, $link] = $this->makeUserAndLink();

        DB::table('page_sessions')->insert([
            [
                'link_id'      => $link->id,
                'session_id'   => (string) Str::uuid(),
                'started_at'   => now()->subDays(2),
                'last_seen_at' => now()->subDays(2),
                'device_type'  => 'mobile',
                'language'     => 'en',
                'country_code' => 'US',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'link_id'      => $link->id,
                'session_id'   => (string) Str::uuid(),
                'started_at'   => now()->subDays(1),
                'last_seen_at' => now()->subDays(1),
                'device_type'  => 'desktop',
                'language'     => 'de',
                'country_code' => 'DE',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);

        $aiResponse = json_encode([
            'personas' => [
                ['type' => 'student',      'pct' => 30],
                ['type' => 'professional', 'pct' => 40],
                ['type' => 'business',     'pct' => 10],
                ['type' => 'creator',      'pct' => 15],
                ['type' => 'other',        'pct' => 5],
            ],
        ]);

        $ai = $this->mock(OpenAiService::class);
        $ai->shouldReceive('chat')->once()->andReturn([
            'content'       => $aiResponse,
            'tokens_in'     => 120,
            'tokens_out'    => 60,
            'credits_spent' => 2,
        ]);

        $service = new AudienceTypeEstimationService(
            app(OpenAiService::class),
            app(AiUsageCharger::class),
        );

        $result = $service->estimate($user, $link, now()->subDays(30), now());

        $this->assertNotEmpty($result['estimated']);
        $this->assertSame('professional', $result['estimated'][0]['type']);
        $this->assertSame(40, $result['estimated'][0]['pct']);
        $this->assertSame(100, array_sum(array_column($result['estimated'], 'pct')));
    }
}
