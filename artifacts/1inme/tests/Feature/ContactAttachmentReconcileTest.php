<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cleanup coverage for stale caller-ID attachments (Task #3336).
 *
 * The read-time gate (DialerReachability, DialerCallerIdReachabilityTest) only
 * HIDES a `contacts.biolink_user_id` pointing at a now-unreachable creator; the
 * row persists and would silently reappear if the creator became reachable
 * again. BiolinkAttachResolver::reconcile() (driven by the scheduled
 * `contacts:reconcile-attachments` command) actively clears such rows:
 *
 *   - a suspended/deactivated/deleted creator is only cleared, so reactivation
 *     lets the normal resolve path re-attach;
 *   - a creator that has BLOCKED the owner is additionally recorded in detach
 *     memory, so an unblock can't silently re-attach;
 *   - a reachable creator is left untouched; and detach memory is never undone.
 */
class ContactAttachmentReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u', string $status = 'active'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
            'status' => $status,
            'handle' => strtolower($prefix) . substr(Str::random(8), 0, 8),
        ]);
    }

    /** A saved contact for $owner already attached to $creator. */
    private function attachedContact(User $owner, User $creator, string $e164 = '+15551234567'): Contact
    {
        $contact = Contact::create([
            'user_id'             => $owner->id,
            'display_name'        => 'Book ' . Str::random(4),
            'biolink_user_id'     => $creator->id,
            'biolink_attached_at' => now(),
        ]);
        ContactPhone::create([
            'contact_id' => $contact->id,
            'value'      => $e164,
            'value_e164' => $e164,
            'is_primary' => true,
        ]);
        return $contact->fresh();
    }

    private function resolver(): BiolinkAttachResolver
    {
        return app(BiolinkAttachResolver::class);
    }

    public function test_reconcile_keeps_a_reachable_creator(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $contact = $this->attachedContact($owner, $creator);

        $this->resolver()->reconcile($contact);

        $this->assertSame($creator->id, $contact->fresh()->biolink_user_id);
    }

    public function test_reconcile_clears_a_suspended_creator_without_detach_memory(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator', 'suspended');
        $contact = $this->attachedContact($owner, $creator);

        $this->resolver()->reconcile($contact);

        $fresh = $contact->fresh();
        $this->assertNull($fresh->biolink_user_id, 'a suspended creator must be cleared');
        $this->assertNull($fresh->biolink_attached_at);
        // Only cleared, not remembered: reactivation may re-attach later.
        $this->assertNotContains($creator->id, (array) $fresh->detached_biolink_user_ids);
    }

    public function test_reconcile_clears_a_deleted_creator(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $contact = $this->attachedContact($owner, $creator);
        $creator->delete();

        $this->resolver()->reconcile($contact);

        $this->assertNull($contact->fresh()->biolink_user_id, 'a deleted creator must be cleared');
    }

    public function test_reconcile_records_detach_for_a_creator_that_blocked_the_owner(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $contact = $this->attachedContact($owner, $creator);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $owner->id]);

        $this->resolver()->reconcile($contact);

        $fresh = $contact->fresh();
        $this->assertNull($fresh->biolink_user_id, 'a blocking creator must be cleared');
        // Remembered so an unblock can't silently re-attach.
        $this->assertContains($creator->id, (array) $fresh->detached_biolink_user_ids);
    }

    public function test_reconcile_never_undoes_existing_detach_memory(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator', 'suspended');
        $contact = $this->attachedContact($owner, $creator);
        $contact->forceFill(['detached_biolink_user_ids' => [999]])->save();

        $this->resolver()->reconcile($contact);

        $this->assertContains(999, (array) $contact->fresh()->detached_biolink_user_ids);
    }

    public function test_reconcile_is_a_noop_for_an_unattached_contact(): void
    {
        $owner   = $this->makeUser('owner');
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'X']);

        $this->resolver()->reconcile($contact->fresh());

        $this->assertNull($contact->fresh()->biolink_user_id);
    }

    public function test_command_clears_stale_attachments_across_contacts(): void
    {
        $owner       = $this->makeUser('owner');
        $reachable   = $this->makeUser('creator');
        $suspended   = $this->makeUser('creator', 'suspended');
        $keep        = $this->attachedContact($owner, $reachable, '+15550000001');
        $drop        = $this->attachedContact($owner, $suspended, '+15550000002');

        $this->artisan('contacts:reconcile-attachments')->assertSuccessful();

        $this->assertSame($reachable->id, $keep->fresh()->biolink_user_id);
        $this->assertNull($drop->fresh()->biolink_user_id);
    }
}
