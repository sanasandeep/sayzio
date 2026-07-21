<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the dialer notes productivity upgrade:
 *
 *   (1) /api/v1/dialer/notes CRUD — checklist kind, reminder re-arm, ownership.
 *   (2) Phone-number sharing — a share phone that resolves to a Sayzio account
 *       surfaces the note in that user's "shared" list, read-only.
 *   (3) DialerIdentity::payload() biolink enrichment — bio, verified flag, and
 *       the link-in-bio preview (seo_title/settings title + description + alias).
 */
class DialerNoteProductivityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    private function api(User $user)
    {
        return $this->withToken($user->createToken('notes-test')->plainTextToken);
    }

    public function test_notes_crud_with_checklist_and_reminder_rearm(): void
    {
        $user = $this->makeUser();

        $create = $this->api($user)->postJson('/api/v1/dialer/notes', [
            'title' => 'Groceries',
            'kind' => 'checklist',
            'checklist' => [
                ['text' => 'Milk', 'done' => false],
                ['text' => 'Eggs', 'done' => true],
            ],
            'remind_at' => now()->addHour()->toIso8601String(),
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertSame('checklist', $create->json('data.kind'));
        $this->assertCount(2, $create->json('data.checklist'));

        // Simulate the reminder having fired, then change remind_at → re-arms.
        DialerNote::where('id', $id)->update(['reminder_sent_at' => now()]);
        $update = $this->api($user)->patchJson("/api/v1/dialer/notes/{$id}", [
            'remind_at' => now()->addHours(3)->toIso8601String(),
            'done' => true,
        ]);
        $update->assertOk();
        $this->assertTrue($update->json('data.done'));
        $this->assertNull(DialerNote::find($id)->reminder_sent_at);

        // Another user cannot update or delete it.
        $stranger = $this->makeUser('x');
        $this->api($stranger)->patchJson("/api/v1/dialer/notes/{$id}", ['title' => 'hax'])->assertStatus(404);
        $this->api($stranger)->deleteJson("/api/v1/dialer/notes/{$id}")->assertStatus(404);

        $this->api($user)->deleteJson("/api/v1/dialer/notes/{$id}")->assertOk();
        $this->assertNull(DialerNote::find($id));
    }

    public function test_share_phone_resolves_to_account_and_lists_as_shared(): void
    {
        $owner = $this->makeUser('own');
        $friend = $this->makeUser('fr');
        // Verified phone identifier for the friend (observer already mirrors
        // email; add the phone by updating/creating a non-primary row).
        LinkedIdentifier::create([
            'user_id' => $friend->id,
            'kind' => 'phone',
            'value' => '+15550104242',
            'verified_at' => now(),
            'is_primary' => false,
        ]);

        $create = $this->api($owner)->postJson('/api/v1/dialer/notes', [
            'title' => 'Call about venue',
            'share_phones' => ['+1 (555) 010-4242'],
        ]);
        $create->assertStatus(201);
        $this->assertSame(['+15550104242'], $create->json('data.share_phones'));

        $index = $this->api($friend)->getJson('/api/v1/dialer/notes');
        $index->assertOk();
        $shared = collect($index->json('data.shared'));
        $this->assertCount(1, $shared);
        $this->assertSame('Call about venue', $shared[0]['title']);
        $this->assertFalse($shared[0]['own']);
        $this->assertSame($owner->name, $shared[0]['owner_name']);

        // Shared recipient cannot edit the note.
        $this->api($friend)
            ->patchJson('/api/v1/dialer/notes/' . $create->json('data.id'), ['title' => 'nope'])
            ->assertStatus(404);
    }

    public function test_dialer_identity_payload_includes_bio_verified_and_link_preview(): void
    {
        $creator = $this->makeUser('creator');
        $creator->update(['bio' => 'Indie maker & speaker']);

        $bio = $creator->links()->create([
            'user_id' => $creator->id,
            'type' => 'biolink',
            'alias' => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
            'seo_title' => 'My page title',
            'seo_description' => 'All my links in one place',
        ]);

        LinkedIdentifier::create([
            'user_id' => $creator->id,
            'kind' => 'phone',
            'value' => '+15550107777',
            'verified_at' => now(),
            'is_primary' => false,
        ]);

        $viewer = $this->makeUser('viewer');
        $resolved = DialerIdentity::resolve($viewer, null, '+15550107777');
        $payload = DialerIdentity::payload($viewer, $resolved);

        $this->assertNotNull($payload['biolink']);
        $this->assertSame('Indie maker & speaker', $payload['biolink']['bio']);
        $this->assertFalse($payload['biolink']['verified']);
        $this->assertSame('My page title', $payload['biolink']['link_preview']['title']);
        $this->assertSame('All my links in one place', $payload['biolink']['link_preview']['description']);
        $this->assertSame($bio->alias, $payload['biolink']['link_preview']['alias']);
    }
}
