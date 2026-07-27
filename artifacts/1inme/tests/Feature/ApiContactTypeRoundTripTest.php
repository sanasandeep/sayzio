<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Round-trip coverage for the Brand/Personal contact classification on
 * the Sanctum contact API: create with/without a type, update, and the
 * `?contact_type=` list filter the dialer chips use.
 */
class ApiContactTypeRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_store_persists_brand_type(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts', ['display_name' => 'Acme Corp', 'contact_type' => 'brand'])
            ->assertCreated()
            ->assertJsonPath('data.contact.contact_type', 'brand');

        $this->assertSame('brand', Contact::where('user_id', $user->id)->value('contact_type'));
    }

    public function test_store_defaults_to_personal_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts', ['display_name' => 'Jane Doe'])
            ->assertCreated()
            ->assertJsonPath('data.contact.contact_type', 'personal');
    }

    public function test_update_changes_type_and_partial_update_preserves_it(): void
    {
        $user = User::factory()->create();
        $c = Contact::create(['user_id' => $user->id, 'display_name' => 'Switchy', 'contact_type' => 'personal']);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$c->id}", ['contact_type' => 'brand'])
            ->assertOk()
            ->assertJsonPath('data.contact.contact_type', 'brand');

        // A partial edit that omits contact_type must not clobber it.
        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$c->id}", ['display_name' => 'Switchy Renamed'])
            ->assertOk()
            ->assertJsonPath('data.contact.contact_type', 'brand');
    }

    public function test_index_filters_by_contact_type(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Brand Co', 'contact_type' => 'brand']);
        Contact::create(['user_id' => $user->id, 'display_name' => 'Pal', 'contact_type' => 'personal']);

        $res = $this->withToken($this->token($user))
            ->getJson('/api/v1/contacts?contact_type=brand')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $res);
        $this->assertSame('Brand Co', $res[0]['display_name']);
    }

    public function test_store_rejects_invalid_type_value(): void
    {
        $user = User::factory()->create();

        // Invalid values are ignored by the validator and fall back to personal.
        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts', ['display_name' => 'Weird', 'contact_type' => 'corporate'])
            ->assertCreated()
            ->assertJsonPath('data.contact.contact_type', 'personal');
    }
}
