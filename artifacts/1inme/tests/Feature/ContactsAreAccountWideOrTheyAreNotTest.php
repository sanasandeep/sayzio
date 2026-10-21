<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contacts are account-based. They belong to the person, not to the
 * workspace they happened to be standing in when the contact was saved,
 * and they deliberately cross workspaces.
 *
 * The code only half agreed. Contact carried the `workspace` global
 * scope, and on API routes EnforceApiWorkspaceFeature binds a
 * current_workspace, so the scope was live. About forty of the sixty
 * query sites across the two ContactControllers ran through it and the
 * rest opted out by hand -- 26 withoutGlobalScope('workspace') calls,
 * each one a bug somebody had found the hard way, one at a time.
 *
 * These tests were written and run BEFORE the fix, to see the bug on
 * its own. Five of the six failed. The one that passed is the listing,
 * and it is the control: it proves the fixture really does put the
 * contact in another workspace and really does make the request under
 * a second one. Without it, five 404s would have been equally
 * consistent with a broken test setup.
 *
 * What the run showed, on one controller: `index` and `show` opted out,
 * so a contact saved under another workspace was listed and could be
 * opened. Everything else -- update, updateTags, updateNotes,
 * setFollowUp, clearFollowUp, share, destroy -- did not. You could read
 * a contact on mobile and then be refused every single edit to it, with
 * nothing on screen to say why.
 *
 * The fix stopped asking per call site: Contact::readsAreWorkspaceScoped()
 * returns false, so the scope no longer applies to it at all.
 * `workspace_record_access` is untouched -- which team MEMBER may see
 * which record is a different question with a real answer.
 *
 * Keep the listing test. It is what stops this file from passing for the
 * wrong reason if the fixture ever stops reproducing the situation.
 */
class ContactsAreAccountWideOrTheyAreNotTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * An owner with two workspaces of their own, a contact saved while
     * the FIRST was active, and the SECOND one to make requests under.
     *
     * @return array{0:User,1:Contact,2:Workspace}
     */
    private function contactSavedInAnotherWorkspace(): array
    {
        $user  = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);
        $first = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $first);
        app()->instance('workspace_owner', $user);

        $contact = Contact::create([
            'user_id' => $user->id, 'display_name' => 'Priya Sharma',
        ]);

        // Saved under the first workspace, which is the whole setup.
        $this->assertSame((int) $first->id, (int) $contact->fresh()->workspace_id,
            'the fixture did not reproduce the situation it is about');

        $second = Workspace::create([
            'owner_user_id' => $user->id, 'name' => 'Second Space',
            'slug' => 'second-' . Str::random(8), 'is_personal' => false,
        ]);

        return [$user, $contact->fresh(), $second];
    }

    public function test_a_contact_saved_in_another_workspace_is_listed(): void
    {
        // The half that works, and the reason the other half is a bug
        // rather than a policy: the contact is right there in the list.
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/contacts?workspace_id=' . $second->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $contact->id]);
    }

    public function test_a_contact_you_can_see_can_also_be_renamed(): void
    {
        // update() opts out of the scope, so this one passes today. It is
        // here as the control: if this ever starts failing, the problem
        // is the request setup, not the scope.
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$contact->id}", [
                'display_name' => 'Priya Sharma-Rao', 'workspace_id' => $second->id,
            ])
            ->assertOk();
    }

    public function test_a_contact_you_can_rename_can_also_be_tagged(): void
    {
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$contact->id}/tags", [
                'tags' => ['speaker'], 'workspace_id' => $second->id,
            ])
            ->assertOk();
    }

    public function test_a_contact_you_can_rename_can_also_take_a_note(): void
    {
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/contacts/{$contact->id}/notes", [
                'notes' => 'Builds payment rails.', 'workspace_id' => $second->id,
            ])
            ->assertOk();
    }

    public function test_a_contact_you_can_rename_can_also_take_a_follow_up(): void
    {
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->postJson("/api/v1/contacts/{$contact->id}/follow-up", [
                'follow_up_at' => now()->addWeek()->toIso8601String(),
                'workspace_id' => $second->id,
            ])
            ->assertOk();
    }

    public function test_a_contact_you_can_rename_can_also_be_deleted(): void
    {
        // The sharpest one: before the fix, a contact the API would
        // happily show and rename could not be removed, leaving
        // somebody unable to delete their own data from the surface
        // showing it to them.
        //
        // 204, not 200 -- the endpoint answers No Content, which is
        // right for a delete. The first draft asserted assertOk() and
        // failed against a working delete; that was the test being
        // wrong about the contract, not the endpoint.
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/contacts/{$contact->id}", ['workspace_id' => $second->id])
            ->assertNoContent();

        $this->assertNull(Contact::find($contact->id), 'the delete reported success without deleting');
    }
}
