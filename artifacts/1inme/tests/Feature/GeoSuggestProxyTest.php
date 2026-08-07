<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The map-picker autocomplete must never hit Nominatim from the browser;
 * suggestions go through /user/geo/suggest which caches results and gates
 * upstream calls behind a global 1-req/second limiter.
 */
class GeoSuggestProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('nominatim-suggest-upstream');
    }

    private function fakeUpstream(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['place_id' => 42, 'display_name' => 'Eiffel Tower, Paris, France', 'lat' => '48.8583701', 'lon' => '2.2944813'],
            ]),
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/user/geo/suggest?q=eiffel')->assertStatus(401);
    }

    public function test_proxies_and_normalizes_upstream_results(): void
    {
        $this->fakeUpstream();
        $this->actingAs(User::factory()->create());

        $this->getJson('/user/geo/suggest?q=eiffel tower')
            ->assertOk()
            ->assertExactJson(['suggestions' => [
                ['id' => 42, 'label' => 'Eiffel Tower, Paris, France', 'lat' => '48.8583701', 'lng' => '2.2944813'],
            ]]);

        Http::assertSentCount(1);
    }

    public function test_repeat_queries_are_served_from_cache_without_upstream_calls(): void
    {
        $this->fakeUpstream();
        $this->actingAs(User::factory()->create());

        $this->getJson('/user/geo/suggest?q=eiffel tower')->assertOk();
        // Case-insensitive cache key: identical query must not re-hit upstream.
        $this->getJson('/user/geo/suggest?q=Eiffel Tower')->assertOk()
            ->assertJsonPath('suggestions.0.id', 42);

        Http::assertSentCount(1);
    }

    public function test_global_upstream_gate_blocks_second_distinct_query_within_a_second(): void
    {
        $this->fakeUpstream();
        $this->actingAs(User::factory()->create());

        $this->getJson('/user/geo/suggest?q=eiffel tower')->assertOk();
        // Distinct query while the 1-req/second gate is hot → empty, no upstream call.
        $this->getJson('/user/geo/suggest?q=space needle')->assertOk()
            ->assertExactJson(['suggestions' => []]);

        Http::assertSentCount(1);
    }

    public function test_short_or_url_queries_short_circuit_without_upstream(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->getJson('/user/geo/suggest?q=ab')->assertOk()->assertExactJson(['suggestions' => []]);
        $this->getJson('/user/geo/suggest?q=' . urlencode('https://example.com'))->assertOk()
            ->assertExactJson(['suggestions' => []]);

        Http::assertNothingSent();
    }

    public function test_upstream_failure_is_not_cached(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(null, 503)]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/user/geo/suggest?q=eiffel tower')->assertOk()
            ->assertExactJson(['suggestions' => []]);

        $this->assertNull(Cache::get('geo:suggest:' . sha1('eiffel tower')));
    }
}
