<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * REST parity for the two-way Google sync: the API create/update/merge paths
 * stamp locally_modified_at and mirror the change to Google, and delete parks
 * a ContactDeletionTombstone that survives a failed immediate delete so the
 * deletion is never lost. Uses a REAL Sanctum bearer token — Sanctum::actingAs
 * breaks TouchSessionToken (see .agents/memory/sanctum-api-tests.md). Provider
 * mocked; no live People API in the isolated env.
 */
class ContactControllerGoogleSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private GoogleContactsAccount $account;
    private array $headers = [];

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
            'features'      => ['contacts_max' => 5000],
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

        $this->account = GoogleContactsAccount::create([
            'user_id'       => $user->id,
            'account_email' => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'  => false,
            'push_enabled'  => true,
        ]);

        // Real bearer token: Sanctum::actingAs would skip TouchSessionToken.
        $token = $user->createToken('test', ['*'])->plainTextToken;
        $this->headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];

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
            'google_resource_name'       => 'people/api-' . Str::random(4),
            'google_etag'                => 'etag-old',
            'display_name'               => 'Linked Api Contact',
            'last_synced_at'             => now()->subMinutes(5),
        ]);
    }

    public function test_store_stamps_locally_modified_at_and_pushes_to_google(): void
    {
        $user = $this->user();
        $provider = $this->mockProvider();
        $provider->shouldReceive('createPerson')
            ->once()
            ->andReturn(['resource_name' => 'people/api-new', 'etag' => 'etag-new']);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/contacts', [
                'display_name' => 'Api New',
                'emails'       => [['label' => 'home', 'value' => 'apinew@example.com']],
                'phones'       => [['label' => 'mobile', 'value' => '+15552220000']],
            ])
            ->assertCreated();

        $contact = Contact::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($contact->locally_modified_at);
        $this->assertSame('people/api-new', $contact->google_resource_name);
    }

    public function test_update_stamps_locally_modified_at_and_pushes_to_google(): void
    {
        $user = $this->user();
        $contact = $this->linkedContact($user);

        $provider = $this->mockProvider();
        $provider->shouldReceive('updatePerson')
            ->once()
            ->andReturn(['resource_name' => $contact->google_resource_name, 'etag' => 'etag-fresh']);

        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/contacts/' . $contact->id, [
                'display_name' => 'Renamed Api Contact',
            ])
            ->assertOk();

        $fresh = $contact->fresh();
        $this->assertNotNull($fresh->locally_modified_at);
        $this->assertSame('etag-fresh', $fresh->google_etag);
        $this->assertSame('Renamed Api Contact', $fresh->display_name);
    }

    public function test_merge_stamps_locally_modified_at_and_pushes_to_google(): void
    {
        $user = $this->user();
        // Existing row not yet linked to Google → merge push creates it.
        $contact = Contact::create([
            'user_id'      => $user->id,
            'display_name' => 'Mergeable',
        ]);

        $provider = $this->mockProvider();
        $provider->shouldReceive('createPerson')
            ->once()
            ->andReturn(['resource_name' => 'people/api-merged', 'etag' => 'etag-merged']);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/contacts/' . $contact->id . '/merge', [
                'emails' => [['label' => 'work', 'value' => 'merge@example.com']],
            ])
            ->assertOk();

        $fresh = $contact->fresh();
        $this->assertNotNull($fresh->locally_modified_at);
        $this->assertSame('people/api-merged', $fresh->google_resource_name);
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

        $this->withHeaders($this->headers)
            ->deleteJson('/api/v1/contacts/' . $contact->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
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

        $this->withHeaders($this->headers)
            ->deleteJson('/api/v1/contacts/' . $contact->id)
            ->assertNoContent();

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
