<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Two-way Google Contacts sync must never duplicate rows or drop deletes, and
 * must be throttled/serialized so a hot account can't be hammered against the
 * People API. These paths can't run against a live provider in the isolated
 * env (cross-region RDS, no *_testing provider creds), so the provider is
 * mocked and every branch of GoogleContactsSyncService is driven directly.
 *
 * See .agents/memory/google-contacts-realtime-sync.md — all sync triggers
 * funnel through the throttled syncNow(); attemptTombstoneDelete() is the one
 * place the delete retry/backoff lives.
 */
class GoogleContactsSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $plan = Plan::create([
            'name'          => 'Free',
            'slug'          => 'free-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => ['contacts_max' => 5000],
        ]);

        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ])->fresh();
    }

    private function account(User $user, array $attrs = []): GoogleContactsAccount
    {
        return GoogleContactsAccount::create(array_merge([
            'user_id'          => $user->id,
            'account_email'    => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'     => true,
            'push_enabled'     => true,
            'last_sync_status' => null,
        ], $attrs));
    }

    /** Bind a mocked provider so the container-resolved service uses it. */
    private function mockProvider(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GoogleContactsProvider::class);
        $this->app->instance(GoogleContactsProvider::class, $mock);
        return $mock;
    }

    private function service(): GoogleContactsSyncService
    {
        return $this->app->make(GoogleContactsSyncService::class);
    }

    /** A minimal Google-normalized person payload (what listPeople yields). */
    private function person(string $rn, array $overrides = []): array
    {
        return array_merge([
            'resource_name' => $rn,
            'deleted'       => false,
            'display_name'  => 'Remote Person',
            'given_name'    => 'Remote',
            'family_name'   => 'Person',
            'organization'  => null,
            'job_title'     => null,
            'notes'         => null,
            'photo_url'     => null,
            'etag'          => 'etag-' . Str::random(4),
            'phones'        => [],
            'emails'        => [],
        ], $overrides);
    }

    public function test_first_sync_runs_and_a_second_call_within_cooldown_is_throttled(): void
    {
        $provider = $this->mockProvider();
        // pull/push disabled: no provider traffic, we only exercise the gate.
        $account = $this->account($this->user(), ['pull_enabled' => false, 'push_enabled' => false]);
        $provider->shouldReceive('listPeople')->never();

        $first = $this->service()->syncNow($account);
        $this->assertSame('ok', $first['status']);

        $second = $this->service()->syncNow($account);
        $this->assertSame('throttled', $second['status']);
        $this->assertGreaterThan(0, $second['retry_after']);
    }

    public function test_force_bypasses_the_cooldown(): void
    {
        $provider = $this->mockProvider();
        $account = $this->account($this->user(), ['pull_enabled' => false, 'push_enabled' => false]);
        $provider->shouldReceive('listPeople')->never();

        $this->service()->syncNow($account);
        $forced = $this->service()->syncNow($account, true);

        $this->assertSame('ok', $forced['status']);
    }

    public function test_a_concurrent_run_is_serialized_and_reports_in_progress(): void
    {
        $provider = $this->mockProvider();
        $account = $this->account($this->user(), ['pull_enabled' => false, 'push_enabled' => false]);
        $provider->shouldReceive('listPeople')->never();

        // Hold the per-account lock as if another worker is mid-sync. force=true
        // skips the cooldown gate so we exercise the lock branch specifically.
        $held = Cache::lock("google-contacts:sync-lock:{$account->id}", 180);
        $this->assertTrue($held->get());

        try {
            $result = $this->service()->syncNow($account, true);
            $this->assertSame('in_progress', $result['status']);
        } finally {
            $held->release();
        }
    }

    public function test_pull_creates_a_new_contact_from_google(): void
    {
        $provider = $this->mockProvider();
        $account = $this->account($this->user(), ['push_enabled' => false]);
        $provider->shouldReceive('listPeople')
            ->once()
            ->andReturn([$this->person('people/g1', [
                'phones' => [['label' => 'mobile', 'value' => '+15551230001', 'primary' => true]],
                'emails' => [['label' => 'home', 'value' => 'g1@example.com', 'primary' => true]],
            ])]);

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['created']);
        $this->assertDatabaseHas('contacts', [
            'user_id'              => $account->user_id,
            'google_resource_name' => 'people/g1',
        ]);
        // No duplicate: exactly one row for this resource name.
        $this->assertSame(1, Contact::where('google_resource_name', 'people/g1')->count());
    }

    public function test_pull_is_idempotent_and_does_not_duplicate_on_a_second_pass(): void
    {
        $provider = $this->mockProvider();
        $account = $this->account($this->user(), ['push_enabled' => false]);
        $provider->shouldReceive('listPeople')
            ->twice()
            ->andReturn([$this->person('people/g1')]);

        $this->service()->syncAccount($account);
        $second = $this->service()->syncAccount($account);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, Contact::where('google_resource_name', 'people/g1')->count());
    }

    public function test_pull_propagates_a_google_side_delete(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user, ['push_enabled' => false]);
        $contact = Contact::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/gone',
            'display_name'               => 'Will Be Deleted',
            'last_synced_at'             => now(),
        ]);

        $provider->shouldReceive('listPeople')
            ->once()
            ->andReturn([$this->person('people/gone', ['deleted' => true])]);

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['deleted']);
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_pull_never_overwrites_a_pending_local_edit(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        // push disabled so the local edit stays pending (not pushed away first).
        $account = $this->account($user, ['push_enabled' => false]);
        $contact = Contact::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/conflict',
            'display_name'               => 'Local Edit Wins',
            'last_synced_at'             => now()->subMinutes(5),
            'locally_modified_at'        => now(), // newer than last_synced_at
        ]);

        $provider->shouldReceive('listPeople')
            ->once()
            ->andReturn([$this->person('people/conflict', ['display_name' => 'Remote Overwrite'])]);

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['skipped']);
        $this->assertSame('Local Edit Wins', $contact->fresh()->display_name);
    }

    public function test_push_creates_a_brand_new_local_contact_on_google_and_links_it(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user, ['pull_enabled' => false]);
        $contact = Contact::create([
            'user_id'             => $user->id,
            'display_name'        => 'New Local',
            'locally_modified_at' => now(),
        ]);

        $provider->shouldReceive('createPerson')
            ->once()
            ->andReturn(['resource_name' => 'people/new1', 'etag' => 'etag-new']);

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['pushed']);
        $fresh = $contact->fresh();
        $this->assertSame('people/new1', $fresh->google_resource_name);
        $this->assertSame($account->id, $fresh->google_contacts_account_id);
    }

    public function test_push_sends_locally_modified_contacts_and_stamps_sync_time(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user, ['pull_enabled' => false]);
        $contact = Contact::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/exist',
            'google_etag'                => 'etag-old',
            'display_name'               => 'Edited Local',
            'last_synced_at'             => now()->subMinute(),
            'locally_modified_at'        => now(),
        ]);

        $provider->shouldReceive('updatePerson')
            ->once()
            ->with(Mockery::any(), 'people/exist', 'etag-old', Mockery::any())
            ->andReturn(['resource_name' => 'people/exist', 'etag' => 'etag-new']);

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['pushed']);
        $fresh = $contact->fresh();
        $this->assertSame('etag-new', $fresh->google_etag);
        // last_synced_at bumped strictly past locally_modified_at so the next
        // pass won't re-push the same edit (no duplicate writes to Google).
        $this->assertTrue($fresh->last_synced_at->gt($fresh->locally_modified_at));
    }

    public function test_tombstone_delete_success_removes_the_tombstone(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user);
        $tombstone = ContactDeletionTombstone::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/del-ok',
        ]);

        $provider->shouldReceive('deletePerson')
            ->once()
            ->with(Mockery::any(), 'people/del-ok')
            ->andReturnNull();

        $ok = $this->service()->attemptTombstoneDelete($account, $tombstone);

        $this->assertTrue($ok);
        $this->assertDatabaseMissing('contact_deletion_tombstones', ['id' => $tombstone->id]);
    }

    public function test_tombstone_delete_failure_keeps_it_and_bumps_attempts(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user);
        $tombstone = ContactDeletionTombstone::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/del-fail',
        ]);

        $provider->shouldReceive('deletePerson')
            ->once()
            ->andThrow(new \RuntimeException('People API 503'));

        $ok = $this->service()->attemptTombstoneDelete($account, $tombstone);

        $this->assertFalse($ok);
        $this->assertDatabaseHas('contact_deletion_tombstones', [
            'id'       => $tombstone->id,
            'attempts' => 1,
        ]);
        $this->assertNotNull($tombstone->fresh()->last_error);
    }

    public function test_scheduled_pass_drains_pending_deletion_tombstones(): void
    {
        $provider = $this->mockProvider();
        $user = $this->user();
        $account = $this->account($user, ['pull_enabled' => false]);
        $tombstone = ContactDeletionTombstone::create([
            'user_id'                    => $user->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/drain',
        ]);

        $provider->shouldReceive('deletePerson')->once()->andReturnNull();

        $stats = $this->service()->syncAccount($account);

        $this->assertSame(1, $stats['deleted']);
        $this->assertDatabaseMissing('contact_deletion_tombstones', ['id' => $tombstone->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
