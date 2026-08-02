<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile contacts list shows per-contact ⚡ activity badges (parity with
 * the web list from the unified contact linking work). Covers the
 * `activity_count` field on the Sanctum contacts index: bulk counts via
 * ContactActivityService::countsFor, zero for untouched contacts, and
 * owner scoping (another creator's captures never bleed in).
 */
class ApiContactActivityCountTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_index_returns_activity_counts_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $active = Contact::create(['user_id' => $owner->id, 'display_name' => 'Busy Bee']);
        $quiet  = Contact::create(['user_id' => $owner->id, 'display_name' => 'Quiet Quill']);

        // contact_id is linked by the capture job, not mass-assignable.
        $capture = function (int $userId, int $contactId, string $email) {
            $s = Subscriber::withoutGlobalScope('workspace')->create(['user_id' => $userId, 'email' => $email]);
            $s->forceFill(['contact_id' => $contactId])->saveQuietly();
        };

        $capture($owner->id, $active->id, 'bee@example.com');
        $capture($owner->id, $active->id, 'bee2@example.com');

        // Another creator's capture pointing at a same-numbered contact id
        // must never count toward this owner's badge.
        $capture($other->id, $active->id, 'foreign@example.com');

        $res = $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->json('data.items');

        $byId = collect($res)->keyBy('id');

        $this->assertSame(2, $byId[$active->id]['activity_count']);
        $this->assertSame(0, $byId[$quiet->id]['activity_count']);
    }
}
