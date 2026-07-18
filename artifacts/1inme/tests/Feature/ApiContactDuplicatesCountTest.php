<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sanctum-surface coverage for GET /api/v1/contacts/duplicates/count — the
 * cheap cached duplicate-group count the mobile contacts screen polls to
 * show its "N duplicate groups found" banner. Mirrors the web
 * user.contacts.duplicates.count route (see ContactDuplicateCountCacheTest
 * for cache-invalidation coverage); this suite pins:
 *
 *   - the endpoint returns the group count in the {data:{count}} envelope,
 *   - it goes through the detector's CACHE (a hit primes the cache key, so
 *     repeated screen-focus polls don't re-run detection),
 *   - the count is scoped to the token's user,
 *   - unauthenticated requests are rejected.
 */
class ApiContactDuplicatesCountTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/v1/contacts/duplicates/count';

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_count_returned_and_cache_primed(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);

        $this->assertFalse(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));

        $this->withToken($this->token($user))
            ->getJson(self::ROUTE)
            ->assertOk()
            ->assertJson(['data' => ['count' => 1]]);

        // The endpoint must use the detector's cached count so mobile
        // screen-focus polls are cheap.
        $this->assertTrue(Cache::has(ContactDuplicateDetector::countCacheKey($user->id)));
    }

    public function test_count_is_scoped_to_the_token_user(): void
    {
        $dupOwner = User::factory()->create();
        Contact::create(['user_id' => $dupOwner->id, 'display_name' => 'Twin']);
        Contact::create(['user_id' => $dupOwner->id, 'display_name' => 'Twin']);

        $clean = User::factory()->create();
        Contact::create(['user_id' => $clean->id, 'display_name' => 'Only One']);

        $this->withToken($this->token($clean))
            ->getJson(self::ROUTE)
            ->assertOk()
            ->assertJson(['data' => ['count' => 0]]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson(self::ROUTE)->assertStatus(401);
    }
}
