<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum-surface coverage for GET /api/v1/contacts/{id}/merge-candidates —
 * the searchable candidate list behind the mobile "Merge into…" picker
 * (web parity with user.contacts.merge-candidates). Pins:
 *
 *   - candidates are the token user's OTHER contacts (never self, never
 *     another account's contacts),
 *   - ?q= filters by name / organization / email / phone,
 *   - a foreign contact id 404s,
 *   - unauthenticated requests are rejected.
 */
class ApiContactMergeCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function route(int $id, string $q = ''): string
    {
        return "/api/v1/contacts/{$id}/merge-candidates" . ($q !== '' ? '?q=' . urlencode($q) : '');
    }

    public function test_lists_own_other_contacts_excluding_self_and_foreign(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $self     = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane Dupe']);
        $survivor = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane Real']);
        Contact::create(['user_id' => $other->id, 'display_name' => 'Foreign Guy']);

        $resp = $this->withToken($this->token($owner))
            ->getJson($this->route($self->id))
            ->assertOk();

        $ids = collect($resp->json('data.candidates'))->pluck('id');
        $this->assertTrue($ids->contains($survivor->id));
        $this->assertFalse($ids->contains($self->id), 'a contact must never offer itself');
        $this->assertCount(1, $ids, 'foreign contacts must never be listed');
    }

    public function test_query_filters_by_name_email_and_phone(): void
    {
        $owner = User::factory()->create();
        $self  = Contact::create(['user_id' => $owner->id, 'display_name' => 'Base']);

        $byName  = Contact::create(['user_id' => $owner->id, 'display_name' => 'Zed Zebra']);
        $byEmail = Contact::create(['user_id' => $owner->id, 'display_name' => 'Mail Person']);
        ContactEmail::create(['contact_id' => $byEmail->id, 'value' => 'findme-' . Str::random(4) . '@example.com', 'is_primary' => true]);
        $byPhone = Contact::create(['user_id' => $owner->id, 'display_name' => 'Phone Person']);
        ContactPhone::create(['contact_id' => $byPhone->id, 'value' => '+15559871234', 'value_e164' => '+15559871234', 'is_primary' => true]);

        $token = $this->token($owner);

        $ids = collect($this->withToken($token)->getJson($this->route($self->id, 'Zebra'))->json('data.candidates'))->pluck('id');
        $this->assertSame([$byName->id], $ids->all());

        $ids = collect($this->withToken($token)->getJson($this->route($self->id, 'findme'))->json('data.candidates'))->pluck('id');
        $this->assertSame([$byEmail->id], $ids->all());

        $ids = collect($this->withToken($token)->getJson($this->route($self->id, '5559871234'))->json('data.candidates'))->pluck('id');
        $this->assertSame([$byPhone->id], $ids->all());
    }

    public function test_foreign_contact_id_is_not_found(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $foreign = Contact::create(['user_id' => $other->id, 'display_name' => 'Foreign']);

        $this->withToken($this->token($owner))
            ->getJson($this->route($foreign->id))
            ->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'Solo']);

        $this->getJson($this->route($c->id))->assertUnauthorized();
    }
}
