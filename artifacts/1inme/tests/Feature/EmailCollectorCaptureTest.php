<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailCollectorCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Queue::fake();
        $user = User::factory()->create();
        $link = Link::create(['user_id' => $user->id, 'type' => 'biolink', 'alias' => Link::generateAlias(), 'title' => 'Bio', 'is_active' => true]);
        $block = BiolinkBlock::create(['link_id' => $link->id, 'type' => 'email_collector', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        return [$link, $block];
    }

    public function test_email_submission_is_saved(): void
    {
        [$link, $block] = $this->fixture();
        $this->postJson("/{$link->alias}/subscribe", ['block_id' => $block->id, 'type' => 'email', 'email' => 'reader@example.com'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('subscribers', ['link_id' => $link->id, 'block_id' => $block->id, 'type' => 'email', 'email' => 'reader@example.com']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        [$link, $block] = $this->fixture();
        $this->postJson("/{$link->alias}/subscribe", ['block_id' => $block->id, 'type' => 'email', 'email' => 'invalid'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('subscribers', ['block_id' => $block->id]);
    }
}
