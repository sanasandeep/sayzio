<?php

namespace Tests\Feature;

use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLeadsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_requires_authentication(): void
    {
        $this->getJson('/api/v1/leads')->assertUnauthorized();
    }

    public function test_queue_is_owner_scoped_and_dismisses_without_deleting_source(): void
    {
        $owner = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $mine = Subscriber::create(['user_id' => $owner->id, 'name' => 'My lead', 'email' => 'mine@example.test', 'status' => 'active', 'is_spam' => false]);
        $mine->workspace_id = $owner->ensureDefaultWorkspace()->id;
        $mine->save();
        Subscriber::create(['user_id' => $other->id, 'name' => 'Other lead', 'email' => 'other@example.test', 'status' => 'active', 'is_spam' => false]);
        $this->withToken($owner->createToken('mobile')->plainTextToken);
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.name', 'My lead');
        $this->postJson('/api/v1/leads/decision', ['source_type' => 'subscriber', 'source_id' => $mine->id, 'decision' => 'dismiss'])->assertOk();
        $this->assertDatabaseHas('subscribers', ['id' => $mine->id]);
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson('/api/v1/leads/decision', ['source_type' => 'subscriber', 'source_id' => $mine->id, 'decision' => 'dismiss'])->assertStatus(409);
    }
}
