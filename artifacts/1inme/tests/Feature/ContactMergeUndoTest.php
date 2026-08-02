<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactMergeAudit;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactMergeUndoService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Undo an accidental contact merge (Task #6512): merging records a
 * ContactMergeAudit (source snapshot + moved row ids), and within 30 days
 * the owner can undo — the source contact is recreated and the recorded
 * rows are repointed back. Idempotent and owner-safe.
 */
class ContactMergeUndoTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name'   => $prefix . Str::random(4),
            'email'  => $prefix . '-' . Str::random(8) . '@example.com',
            'status' => 'active',
            'handle' => strtolower($prefix) . substr(Str::random(8), 0, 8),
        ]);
    }

    private function actAsOwner(User $user): void
    {
        $this->be($user, 'web');
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    private function makePair(User $owner): array
    {
        $a = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Jane Email',
            'organization' => 'Acme',
            'tags'         => ['vip', 'lead'],
            'is_auto_captured' => true,
        ]);
        ContactEmail::create(['contact_id' => $a->id, 'value' => 'jane-' . Str::random(6) . '@example.com', 'is_primary' => true]);

        $b = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane Phone', 'is_auto_captured' => true]);
        ContactPhone::create(['contact_id' => $b->id, 'value' => '+15551239876', 'value_e164' => '+15551239876', 'is_primary' => true]);

        return [$a, $b];
    }

    public function test_merge_records_audit_and_undo_restores_contact_and_repoints_rows(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);
        $dupeEmail = $dupe->emails()->first()->value;

        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'cap-' . Str::random(6) . '@example.com',
            'status'  => 'active',
        ]);
        DB::table('subscribers')->where('id', $sub->id)->update(['contact_id' => $dupe->id]);

        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id])
            ->assertRedirect(route('user.contacts.show', $survivor));

        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->first();
        $this->assertNotNull($audit, 'merge must record an audit row');
        $this->assertSame($survivor->id, $audit->primary_contact_id);
        $this->assertSame($dupe->id, $audit->source_contact_id);
        $this->assertTrue($audit->isUndoable());
        $moved = (array) $audit->moved;
        $this->assertContains($sub->id, array_map('intval', (array) ($moved['subscribers'] ?? [])));

        // Undo via the web route.
        $resp = $this->post(route('user.contacts.merges.undo', $audit->id));
        $resp->assertSessionHas('success');

        $audit->refresh();
        $this->assertNotNull($audit->undone_at);
        $this->assertNotNull($audit->restored_contact_id);

        $restored = Contact::find($audit->restored_contact_id);
        $this->assertNotNull($restored);
        $this->assertSame('Jane Email', $restored->display_name);
        $this->assertSame('Acme', $restored->organization);
        $this->assertSame(['vip', 'lead'], $restored->tags, 'JSON casts must round-trip without double-encoding');
        $this->assertSame($owner->id, $restored->user_id);

        // Identifier rows restored on the new contact and removed from primary.
        $this->assertSame([$dupeEmail], $restored->emails()->pluck('value')->all());
        $this->assertSame(0, $survivor->emails()->count(), 'email added to primary by the merge must be removed');
        $this->assertSame(1, $survivor->phones()->count(), 'primary keeps its own phone');

        // Capture row repointed back.
        $this->assertSame($restored->id, (int) DB::table('subscribers')->where('id', $sub->id)->value('contact_id'));
    }

    public function test_undo_is_idempotent(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);

        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id]);
        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->first();

        $this->post(route('user.contacts.merges.undo', $audit->id))->assertSessionHas('success');
        $restoredId = $audit->refresh()->restored_contact_id;

        // Replaying the undo must fail gracefully and create nothing new.
        $this->post(route('user.contacts.merges.undo', $audit->id))->assertSessionHas('error');
        $this->assertSame($restoredId, $audit->refresh()->restored_contact_id);
        $this->assertSame(1, Contact::where('id', $restoredId)->count());
    }

    public function test_undo_rejects_expired_audits(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);

        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id]);
        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->first();
        $audit->timestamps = false;
        $audit->forceFill(['created_at' => now()->subDays(ContactMergeAudit::UNDO_WINDOW_DAYS + 1)])->save();

        $this->post(route('user.contacts.merges.undo', $audit->id))->assertSessionHas('error');
        $this->assertNull($audit->refresh()->undone_at);
    }

    public function test_undo_is_owner_safe(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);
        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id]);
        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->first();

        // A different user must not be able to undo the owner's merge.
        $this->actAsOwner($other);
        $this->post(route('user.contacts.merges.undo', $audit->id))->assertNotFound();
        $this->assertNull($audit->refresh()->undone_at);
    }

    public function test_merge_all_duplicates_records_one_undoable_audit_per_absorbed_contact(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);

        // Two independent duplicate groups: a phone-matched pair and a
        // phone-matched trio (1 primary + 2 losers = 3 absorbed contacts).
        $mkPhone = function (string $name, string $phone) use ($owner): Contact {
            $c = Contact::create(['user_id' => $owner->id, 'display_name' => $name, 'is_auto_captured' => true]);
            ContactPhone::create(['contact_id' => $c->id, 'value' => $phone, 'value_e164' => $phone, 'is_primary' => true]);
            return $c;
        };

        $a1 = $mkPhone('Pair A1', '+15550001111');
        $a2 = $mkPhone('Pair A2', '+15550001111');
        $b1 = $mkPhone('Trio B1', '+15550002222');
        $b2 = $mkPhone('Trio B2', '+15550002222');
        $b3 = $mkPhone('Trio B3', '+15550002222');

        $resp = $this->post(route('user.contacts.duplicates.merge-all'));
        $resp->assertRedirect(route('user.contacts.duplicates'));
        $resp->assertSessionHas('success');

        $audits = ContactMergeAudit::where('user_id', $owner->id)->get();
        $this->assertCount(3, $audits, 'bulk merge must record one audit row per absorbed contact');

        // Every absorbed (non-primary) contact has its own undoable audit
        // pointing at the surviving primary of its group.
        $expected = [
            $a2->id => $a1->id,
            $b2->id => $b1->id,
            $b3->id => $b1->id,
        ];
        foreach ($expected as $sourceId => $primaryId) {
            $audit = $audits->firstWhere('source_contact_id', $sourceId);
            $this->assertNotNull($audit, "absorbed contact {$sourceId} must have an audit row");
            $this->assertSame($primaryId, $audit->primary_contact_id);
            $this->assertTrue($audit->isUndoable());
        }

        // And one of them actually undoes: the absorbed contact comes back.
        $audit = $audits->firstWhere('source_contact_id', $b3->id);
        $this->post(route('user.contacts.merges.undo', $audit->id))->assertSessionHas('success');
        $restored = Contact::find($audit->refresh()->restored_contact_id);
        $this->assertNotNull($restored);
        $this->assertSame('Trio B3', $restored->display_name);
    }

    public function test_undo_does_not_steal_rows_remerged_elsewhere(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);

        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'cap-' . Str::random(6) . '@example.com',
            'status'  => 'active',
        ]);
        DB::table('subscribers')->where('id', $sub->id)->update(['contact_id' => $dupe->id]);

        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id]);
        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->first();

        // Row later moved somewhere else — undo must leave it alone.
        $third = Contact::create(['user_id' => $owner->id, 'display_name' => 'Third']);
        DB::table('subscribers')->where('id', $sub->id)->update(['contact_id' => $third->id]);

        app(ContactMergeUndoService::class)->undo($audit->refresh());

        $this->assertSame($third->id, (int) DB::table('subscribers')->where('id', $sub->id)->value('contact_id'));
    }
}
