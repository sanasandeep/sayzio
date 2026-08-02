<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Most active" contacts sort + has-activity filter (Task #6510): the
 * contacts index supports `?sort=activity` (linked-activity count desc via a
 * single bulk UNION-ALL derived-table join — never per-contact queries) and
 * `?has_activity=1` (only contacts with at least one linked record). Covers
 * both the Sanctum API index and the web contacts index.
 */
class ApiContactActivitySortTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** @return array{User, Contact, Contact, Contact} owner + busy(2) / mid(1) / quiet(0) */
    private function seedContacts(): array
    {
        $owner = User::factory()->create();

        // Names chosen so alphabetical order is the REVERSE of activity order.
        $busy  = Contact::create(['user_id' => $owner->id, 'display_name' => 'Zed Busy']);
        $mid   = Contact::create(['user_id' => $owner->id, 'display_name' => 'Mia Mid']);
        $quiet = Contact::create(['user_id' => $owner->id, 'display_name' => 'Al Quiet']);

        $capture = function (int $contactId, string $email) use ($owner) {
            $s = Subscriber::withoutGlobalScope('workspace')->create(['user_id' => $owner->id, 'email' => $email]);
            $s->forceFill(['contact_id' => $contactId])->saveQuietly();
        };

        $capture($busy->id, 'b1@example.com');
        $capture($busy->id, 'b2@example.com');
        $capture($mid->id, 'm1@example.com');

        return [$owner, $busy, $mid, $quiet];
    }

    public function test_api_sort_activity_orders_most_active_first(): void
    {
        [$owner, $busy, $mid, $quiet] = $this->seedContacts();

        $ids = collect($this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts?sort=activity')
            ->assertOk()
            ->json('data.items'))->pluck('id')
            ->intersect([$busy->id, $mid->id, $quiet->id])->values()->all();

        $this->assertSame([$busy->id, $mid->id, $quiet->id], $ids);
    }

    public function test_api_default_sort_stays_alphabetical(): void
    {
        [$owner, $busy, $mid, $quiet] = $this->seedContacts();

        $ids = collect($this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->json('data.items'))->pluck('id')
            ->intersect([$busy->id, $mid->id, $quiet->id])->values()->all();

        $this->assertSame([$quiet->id, $mid->id, $busy->id], $ids);
    }

    public function test_api_has_activity_filter_hides_untouched_contacts(): void
    {
        [$owner, $busy, $mid, $quiet] = $this->seedContacts();

        $ids = collect($this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts?has_activity=1')
            ->assertOk()
            ->json('data.items'))->pluck('id')->all();

        $this->assertContains($busy->id, $ids);
        $this->assertContains($mid->id, $ids);
        $this->assertNotContains($quiet->id, $ids);
    }

    public function test_web_index_sort_activity_orders_most_active_first(): void
    {
        [$owner, $busy, $mid, $quiet] = $this->seedContacts();

        $res = $this->actingAs($owner)
            ->get(route('user.contacts.index', ['sort' => 'activity']))
            ->assertOk();

        $ids = collect($res->viewData('contacts')->items())->pluck('id')
            ->intersect([$busy->id, $mid->id, $quiet->id])->values()->all();
        $this->assertSame([$busy->id, $mid->id, $quiet->id], $ids);
    }

    public function test_web_index_default_stays_alphabetical(): void
    {
        [$owner, $busy, $mid, $quiet] = $this->seedContacts();

        $res = $this->actingAs($owner)
            ->get(route('user.contacts.index'))
            ->assertOk();

        $ids = collect($res->viewData('contacts')->items())->pluck('id')
            ->intersect([$busy->id, $mid->id, $quiet->id])->values()->all();
        $this->assertSame([$quiet->id, $mid->id, $busy->id], $ids);
    }
}
