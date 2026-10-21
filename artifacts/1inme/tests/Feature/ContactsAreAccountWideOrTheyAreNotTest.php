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
 * THIS TEST IS EXPECTED TO FAIL until the workspace global scope comes
 * off Contact. It is written first, on purpose, to show the bug exists
 * before anything is done about it.
 *
 * Contacts are account-based. They belong to the person, not to the
 * workspace they happened to be standing in when the contact was saved,
 * and they deliberately cross workspaces.
 *
 * The code only half agrees. Contact still carries the `workspace`
 * global scope, and on API routes EnforceApiWorkspaceFeature binds a
 * current_workspace, so the scope is live. Roughly half the query sites
 * in the two ContactControllers opt out of it by hand and the rest do
 * not -- 26 hand-written withoutGlobalScope('workspace') calls, each one
 * a bug somebody found the hard way, one at a time.
 *
 * The result is an inconsistency INSIDE one controller, which is what
 * these tests pin down. index, show and update opt out, so a contact
 * saved in another workspace is listed and can be renamed. updateTags,
 * updateNotes, setFollowUp, clearFollowUp, share, destroy, callLogs,
 * logCalls and mergeCandidates do not, so the same contact 404s.
 *
 * You can see it. You can rename it. You cannot tag it, note it, set a
 * reminder on it, or delete it. Nothing about that is a decision anyone
 * made; it is 40-odd call sites each answering the question separately.
 *
 * The fix is to stop asking per call site -- drop the `workspace` scope
 * from Contact so account-based means account-based. The
 * `workspace_record_access` scope stays: which team MEMBER may see which
 * record is a different question with a real answer.
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
        // The sharpest one. A contact the API will happily show and
        // rename cannot be removed, which leaves somebody unable to
        // delete their own data from the surface that is showing it.
        [$user, $contact, $second] = $this->contactSavedInAnotherWorkspace();

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/contacts/{$contact->id}", ['workspace_id' => $second->id])
            ->assertOk();
    }
}
