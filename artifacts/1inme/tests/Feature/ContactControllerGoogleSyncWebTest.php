<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Web contact CRUD must keep the two-way Google sync honest: create/update
 * stamp locally_modified_at (so the pull-side conflict guard won't clobber the
 * edit) and mirror to Google immediately; delete parks a ContactDeletionTombstone
 * and attempts an immediate best-effort delete — the tombstone survives (with a
 * bumped attempt counter) when Google is unreachable so the delete is never
 * lost. Provider mocked; renders the real routes/controllers via HTTP.
 */
class ContactControllerGoogleSyncWebTest extends TestCase
{
    use RefreshDatabase;

    private GoogleContactsAccount $account;

    private function user(): User
    {
        $plan = Plan::create([
            'name'          => 'Pro',
            'slug'          => 'pro-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => [
                'leads'        => true,
                'contacts_max' => 5000,
                'max_links'    => 100,
                'max_biolinks' => 100,
            ],
        ]);

        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ])->fresh();

        // Push-enabled, pull-disabled so create/update/delete hit only the push
        // helpers (no listPeople traffic) — the pull path is covered elsewhere.
        $this->account = GoogleContactsAccount::create([
            'user_id'       => $user->id,
            'account_email' => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'  => false,
            'push_enabled'  => true,
        ]);

        return $user;
    }

    private function mockProvider(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GoogleContactsProvider::class);
        $this->app->instance(GoogleContactsProvider::class, $mock);
        return $mock;
    }

    private function linkedContact(User $user): Contact
    {
        return Contact::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $this->account->id,
            'google_resource_name'       => 'people/web-' . Str::random(4),
            'google_etag'                => 'etag-old',
            'display_name'               => 'Linked Web Contact',
            'last_synced_at'             => now()->subMinutes(5),
        ]);
    }

    public function test_store_stamps_locally_modified_at_and_pushes_to_google(): void
    {
        $user = $this->user();
        $provider = $this->mockProvider();
        $provider->shouldReceive('createPerson')
            ->once()
            ->andReturn(['resource_name' => 'people/web-new', 'etag' => 'etag-new']);

        $this->actingAs($user)
            ->post(route('user.contacts.store'), [
                'display_name' => 'Web New',
                'phones'       => [['label' => 'mobile', 'value' => '+15551110000']],
            ])
            ->assertRedirect();

        $contact = Contact::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($contact->locally_modified_at);
        $this->assertSame('people/web-new', $contact->google_resource_name);
    }

    public function test_update_stamps_locally_modified_at_and_pushes_to_google(): void
    {
        $user = $this->user();
        $contact = $this->linkedContact($user);

        $provider = $this->mockProvider();
        $provider->shouldReceive('updatePerson')
            ->once()
            ->andReturn(['resource_name' => $contact->google_resource_name, 'etag' => 'etag-fresh']);

        $this->actingAs($user)
            ->put(route('user.contacts.update', $contact), [
                'display_name' => 'Renamed Web Contact',
            ])
            ->assertRedirect();

        $fresh = $contact->fresh();
        $this->assertNotNull($fresh->locally_modified_at);
        $this->assertSame('etag-fresh', $fresh->google_etag);
        $this->assertSame('Renamed Web Contact', $fresh->display_name);
    }

    public function test_destroy_parks_a_tombstone_and_finalises_it_on_success(): void
    {
        $user = $this->user();
        $contact = $this->linkedContact($user);
        $rn = $contact->google_resource_name;

        $provider = $this->mockProvider();
        $provider->shouldReceive('deletePerson')
            ->once()
            ->with(Mockery::any(), $rn)
            ->andReturnNull();

        $this->actingAs($user)
            ->delete(route('user.contacts.destroy', $contact))
            ->assertRedirect();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        // Immediate delete succeeded, so no tombstone is left behind.
        $this->assertDatabaseCount('contact_deletion_tombstones', 0);
    }

    public function test_destroy_keeps_the_tombstone_when_google_is_unreachable(): void
    {
        $user = $this->user();
        $contact = $this->linkedContact($user);
        $rn = $contact->google_resource_name;

        $provider = $this->mockProvider();
        $provider->shouldReceive('deletePerson')
            ->once()
            ->andThrow(new \RuntimeException('People API down'));

        $this->actingAs($user)
            ->delete(route('user.contacts.destroy', $contact))
            ->assertRedirect();

        // Row gone locally, but the delete is not lost: the tombstone survives
        // with a bumped attempt counter for the scheduled drain to retry.
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contact_deletion_tombstones', [
            'user_id'              => $user->id,
            'google_resource_name' => $rn,
            'attempts'             => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
