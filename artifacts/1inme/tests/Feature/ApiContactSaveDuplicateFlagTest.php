<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum-surface coverage for the `has_duplicate` flag on the contact
 * store/update responses — the mobile app uses it to flash the same
 * "possible duplicate" notice the web save flash shows, right after a
 * save that makes the contact match an existing one.
 */
class ApiContactSaveDuplicateFlagTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_store_flags_a_duplicate_by_identical_name(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts', ['display_name' => 'Same Person'])
            ->assertCreated()
            ->assertJsonPath('data.has_duplicate', true);
    }

    public function test_store_without_a_match_reports_no_duplicate(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts', ['display_name' => 'Unique Human'])
            ->assertCreated()
            ->assertJsonPath('data.has_duplicate', false);
    }

    public function test_update_flags_when_the_edit_creates_a_match(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Target Twin']);
        $c = Contact::create(['user_id' => $user->id, 'display_name' => 'Someone Else']);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$c->id}", ['display_name' => 'Target Twin'])
            ->assertOk()
            ->assertJsonPath('data.has_duplicate', true);
    }

    public function test_update_without_a_match_reports_no_duplicate(): void
    {
        $user = User::factory()->create();
        $c = Contact::create(['user_id' => $user->id, 'display_name' => 'Solo Act']);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$c->id}", ['display_name' => 'Still Solo'])
            ->assertOk()
            ->assertJsonPath('data.has_duplicate', false);
    }
}
