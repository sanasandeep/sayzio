<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BelongsToWorkspace fills workspace_id from the container-bound
 * `current_workspace` and then filters every read by it. Request
 * middleware binds that; a queue worker, a webhook and an artisan
 * command do not. So a contact written outside a request landed with
 * NULL and became invisible to the person who owns it.
 *
 * This already cost a CSV import once -- it reported "completed" while
 * every contact it created vanished. That job was fixed by binding the
 * workspace inside it. CrmSyncService, GoogleContactsSyncService and
 * WhatsAppAgentTools create contacts with no binding at all, which is
 * why the floor is on the model rather than at each call site.
 */
class AContactIsNeverBornWithoutAWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithWorkspace(): array
    {
        $u  = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($u);

        // Everything below is explicitly the no-request case, so leave
        // nothing bound.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return [$u->fresh(), $ws];
    }

    public function test_a_contact_created_with_nothing_bound_still_gets_a_workspace(): void
    {
        [$owner, $ws] = $this->ownerWithWorkspace();

        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'Queued Arrival']);

        $this->assertNotNull($c->workspace_id, 'a contact was written with no workspace and is now invisible to its owner');
        $this->assertSame($ws->id, $c->workspace_id);
    }

    public function test_the_owner_can_actually_find_it_again(): void
    {
        // The point of the column. Writing it is only half -- the read
        // scope has to match.
        [$owner, $ws] = $this->ownerWithWorkspace();

        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'Findable']);

        app()->instance('current_workspace', $ws);

        $this->assertNotNull(
            Contact::where('user_id', $owner->id)->find($c->id),
            'the contact exists but the workspace read scope hides it from its owner'
        );
    }

    public function test_an_explicit_workspace_is_never_overridden(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        $other = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Second', 'slug' => 'second-' . uniqid()]);

        // Set directly, not through create(). workspace_id is NOT in
        // Contact::$fillable, so passing it to create() is silently
        // dropped by mass-assignment protection and never reaches the
        // model at all -- the first version of this test asserted
        // against that and failed for a reason that had nothing to do
        // with the fallback. Direct assignment is how the column is
        // really written, by the trait's hook and by callers alike.
        $c = new Contact(['user_id' => $owner->id, 'display_name' => 'Pinned']);
        $c->workspace_id = $other->id;
        $c->save();

        $this->assertSame($other->id, $c->fresh()->workspace_id,
            'the fallback overwrote a workspace the caller had already set');
    }

    public function test_mass_assigning_a_workspace_is_dropped_as_it_always_was(): void
    {
        // Pinning the surprise above so nobody rediscovers it the hard
        // way: this is mass-assignment protection, not the fallback.
        [$owner, $own] = $this->ownerWithWorkspace();
        $other = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Ignored', 'slug' => 'ignored-' . uniqid()]);

        $c = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Mass Assigned',
            'workspace_id' => $other->id,
        ]);

        $this->assertNotSame($other->id, $c->workspace_id);
        $this->assertSame($own->id, $c->workspace_id);
    }

    public function test_a_bound_workspace_still_wins_over_the_fallback(): void
    {
        // An ordinary request write must behave exactly as before: the
        // trait fills it and the fallback never runs.
        [$owner, $ws] = $this->ownerWithWorkspace();
        app()->instance('current_workspace', $ws);

        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'From A Request']);

        $this->assertSame($ws->id, $c->workspace_id);
    }

    public function test_the_owners_active_workspace_is_preferred_over_their_first(): void
    {
        [$owner, $first] = $this->ownerWithWorkspace();

        $second = Workspace::create(['owner_user_id' => $owner->id, 'name' => 'Active', 'slug' => 'active-' . uniqid()]);
        $owner->forceFill(['active_workspace_id' => $second->id])->save();

        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'Follows The Pointer']);

        $this->assertSame($second->id, $c->workspace_id,
            'the fallback ignored the owner\'s active workspace and used their first');
        $this->assertNotSame($first->id, $c->workspace_id);
    }

    public function test_an_active_workspace_the_owner_does_not_own_is_not_used(): void
    {
        // active_workspace_id can point at a workspace they are only a
        // member of. A contact is owner-scoped, so that is the wrong
        // answer and the fallback must drop to one they own.
        [$owner, $own] = $this->ownerWithWorkspace();

        $stranger  = User::factory()->create();
        $theirs    = app(WorkspaceContext::class)->resolve($stranger);
        app()->forgetInstance('current_workspace');

        $owner->forceFill(['active_workspace_id' => $theirs->id])->save();

        $c = Contact::create(['user_id' => $owner->id, 'display_name' => 'Not Theirs']);

        $this->assertSame($own->id, $c->workspace_id);
        $this->assertNotSame($theirs->id, $c->workspace_id);
    }

    public function test_an_owner_with_no_workspace_is_left_as_before(): void
    {
        // Not a new failure mode: NULL is what happens today. The fix is
        // a floor, not a guarantee, and it must not invent a workspace.
        $orphan = User::factory()->create();
        Workspace::where('owner_user_id', $orphan->id)->delete();

        $c = Contact::create(['user_id' => $orphan->id, 'display_name' => 'No Workspace Anywhere']);

        $this->assertNull($c->workspace_id);
    }
}
