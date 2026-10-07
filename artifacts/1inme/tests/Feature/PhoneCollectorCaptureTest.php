<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhoneCollectorCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Queue::fake();
        $user = User::factory()->create();
        $link = Link::create(['user_id' => $user->id, 'type' => 'biolink', 'alias' => Link::generateAlias(), 'title' => 'Bio', 'is_active' => true]);
        $block = BiolinkBlock::create(['link_id' => $link->id, 'type' => 'phone_collector', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        return [$link, $block];
    }

    public function test_phone_submission_is_saved_and_normalized(): void
    {
        [$link, $block] = $this->fixture();
        $this->postJson("/{$link->alias}/subscribe", ['block_id' => $block->id, 'type' => 'phone', 'phone' => '+91 98765 43210'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('subscribers', ['link_id' => $link->id, 'block_id' => $block->id, 'type' => 'phone', 'phone' => '+919876543210']);
    }

    public function test_empty_phone_does_not_create_an_anonymous_capture(): void
    {
        [$link, $block] = $this->fixture();
        $this->postJson("/{$link->alias}/subscribe", ['block_id' => $block->id, 'type' => 'phone', 'phone' => ''])
            ->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('subscribers', ['block_id' => $block->id]);
    }
}
