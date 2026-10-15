<?php

namespace Tests\Feature;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\User;
use App\Services\Brand\BrandConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandConsistencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_audit_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/brand-kits/consistency')->assertUnauthorized();
    }

    public function test_legacy_clients_receive_retired_response_without_running_an_audit(): void
    {
        $user = User::factory()->create();
        BrandKit::create(['user_id' => $user->id, 'name' => 'Saved kit', 'is_default' => true]);
        $audit = \Mockery::mock(BrandConsistencyService::class);
        $audit->shouldNotReceive('audit');
        $this->app->instance(BrandConsistencyService::class, $audit);
        $this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/v1/brand-kits/consistency')->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.audit', null)->assertJsonPath('data.retired', true);
    }
}
