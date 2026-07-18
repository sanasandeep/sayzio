<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The duplicate-group count is cached per user, and any contact edit that
 * can change duplicate state (rename, phone/email change, delete, dismiss)
 * invalidates the cache so the /contacts banner reflects the new state
 * without waiting for the TTL.
 */
class ContactDuplicateCountCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_count_is_cached_and_flushed_on_contact_rename(): void
    {
        $user = $this->makeUser();
        $detector = app(ContactDuplicateDetector::class);

        $a = Contact::create(['user_id' => $user->id, 'display_name' => 'Alice Smith']);
        $b = Contact::create(['user_id' => $user->id, 'display_name' => 'Bob Jones']);

        $this->assertSame(0, $detector->count($user->id));
        $this->assertTrue(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));

        // Rename B to collide with A — the saved hook must flush the cache
        // so the next count() reflects the new duplicate group immediately.
        $b->update(['display_name' => 'Alice Smith']);
        $this->assertFalse(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));
        $this->assertSame(1, $detector->count($user->id));
    }

    public function test_count_flushed_on_phone_and_email_changes(): void
    {
        $user = $this->makeUser();
        $detector = app(ContactDuplicateDetector::class);

        $a = Contact::create(['user_id' => $user->id, 'display_name' => 'Person One']);
        $b = Contact::create(['user_id' => $user->id, 'display_name' => 'Person Two']);
        $this->assertSame(0, $detector->count($user->id));

        // Adding a shared phone via the relation must flush + re-detect.
        $a->phones()->create(['value' => '+1 555 0100', 'value_e164' => '+15550100', 'is_primary' => false]);
        $b->phones()->create(['value' => '+1 555 0100', 'value_e164' => '+15550100', 'is_primary' => false]);
        $this->assertFalse(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));
        $this->assertSame(1, $detector->count($user->id));

        // Shared email on a third contact grows the picture too.
        $c = Contact::create(['user_id' => $user->id, 'display_name' => 'Person Three']);
        $c->emails()->create(['value' => 'same@example.com', 'is_primary' => false]);
        $a->emails()->create(['value' => 'same@example.com', 'is_primary' => false]);
        $this->assertSame(1, $detector->count($user->id)); // a,b,c merge into one group via a

        // Deleting the duplicate phone rows must flush again.
        $b->phones->each->delete();
        $this->assertFalse(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));
    }

    public function test_count_flushed_on_contact_delete_and_dismiss(): void
    {
        $user = $this->makeUser();
        $detector = app(ContactDuplicateDetector::class);

        $a = Contact::create(['user_id' => $user->id, 'display_name' => 'Twin Name']);
        $b = Contact::create(['user_id' => $user->id, 'display_name' => 'Twin Name']);
        $this->assertSame(1, $detector->count($user->id));

        // Dismissing the pair (web endpoint, raw upsert path) must flush.
        $this->actingAs($user)
            ->post(route('user.contacts.duplicates.dismiss'), ['pairs' => ["{$a->id}:{$b->id}"]])
            ->assertRedirect(route('user.contacts.duplicates'));
        $this->assertSame(0, $detector->count($user->id));

        // Un-dismissable second twin: deleting a contact must flush too.
        $c = Contact::create(['user_id' => $user->id, 'display_name' => 'Twin Name']);
        $this->assertSame(1, $detector->count($user->id));
        $c->delete();
        $this->assertFalse(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));
        $this->assertSame(0, $detector->count($user->id));
    }

    public function test_duplicates_count_endpoint_returns_fresh_count(): void
    {
        $user = $this->makeUser();

        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);

        $this->actingAs($user)
            ->getJson(route('user.contacts.duplicates.count'))
            ->assertOk()
            ->assertJson(['data' => ['count' => 1]]);
    }
}
