<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saving a contact (create or edit) that now matches an existing contact
 * flashes a secondary "possible duplicate" notice on the redirect to the
 * show page, linking to the duplicates review screen. Dismissed pairs are
 * respected so the notice never re-surfaces for pairs the user marked as
 * not-duplicates.
 */
class ContactDuplicateSaveNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_flashes_duplicate_notice_when_name_matches_existing(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Jane Doe']);

        $response = $this->actingAs($user)->post(route('user.contacts.store'), [
            'display_name' => 'Jane Doe',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('duplicate_notice');
    }

    public function test_store_without_match_has_no_notice(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('user.contacts.store'), [
            'display_name' => 'Unique Person',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('duplicate_notice');
    }

    public function test_update_flashes_notice_when_edit_creates_phone_match(): void
    {
        $user = User::factory()->create();
        $existing = Contact::create(['user_id' => $user->id, 'display_name' => 'Person One']);
        $existing->phones()->create(['value' => '+1 555 0100', 'value_e164' => '+15550100', 'is_primary' => false]);

        $target = Contact::create(['user_id' => $user->id, 'display_name' => 'Person Two']);

        $response = $this->actingAs($user)->put(route('user.contacts.update', $target), [
            'display_name' => 'Person Two',
            'phones' => [['value' => '+1 555 0100', 'label' => 'mobile']],
        ]);

        $response->assertRedirect(route('user.contacts.show', $target));
        $response->assertSessionHas('duplicate_notice');
    }

    public function test_dismissed_pair_does_not_trigger_notice(): void
    {
        $user = User::factory()->create();
        $a = Contact::create(['user_id' => $user->id, 'display_name' => 'Twin Name']);
        $b = Contact::create(['user_id' => $user->id, 'display_name' => 'Twin Name']);

        DB::table('contact_dismissed_pairs')->insert([
            'user_id'      => $user->id,
            'contact_id_a' => min($a->id, $b->id),
            'contact_id_b' => max($a->id, $b->id),
            'dismissed_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('user.contacts.update', $b), [
            'display_name' => 'Twin Name',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('duplicate_notice');
    }
}
