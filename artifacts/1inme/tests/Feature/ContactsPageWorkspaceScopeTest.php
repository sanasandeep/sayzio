<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The standalone Contacts page (ContactController::index) must show the
 * creator's FULL address book — account-wide — not just the contacts saved
 * while the active workspace was bound.
 *
 * The web contacts routes run under `workspace.scope`, which binds the active
 * workspace. Contacts use the BelongsToWorkspace trait, so without opting the
 * index/count queries out of the `workspace` global scope, a contact the same
 * user saved while a DIFFERENT (non-active) workspace was bound is silently
 * hidden on the page — even though the dialer finder and the Sanctum/mobile
 * API (no workspace binding) both return it. This test locks the page to the
 * account-wide behavior so the same person's address book is one consistent
 * size across every surface.
 *
 * See .agents/memory/dialer-search-workspace-scope-parity.md.
 */
class ContactsPageWorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::create([
            'name'     => $prefix . Str::random(4),
            'email'    => $prefix . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    public function test_contacts_page_shows_a_contact_saved_in_a_non_active_workspace(): void
    {
        $owner = $this->makeUser('owner');

        // The user's personal workspace becomes the active one for the request.
        $activeWs = app(WorkspaceContext::class)->resolve($owner);

        // A second workspace owned by the same user, where a contact will live.
        $otherWs = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Other WS ' . Str::random(4),
            'is_personal'   => false,
        ]);

        // A contact saved while the ACTIVE workspace was bound.
        $activeContact = new Contact();
        $activeContact->forceFill([
            'user_id'      => $owner->id,
            'workspace_id' => $activeWs->id,
            'display_name' => 'Active WS Contact ' . Str::random(4),
        ])->save();

        // A contact saved while the OTHER (non-active) workspace was bound.
        // workspace_id isn't mass-assignable on Contact, so set it via forceFill.
        $otherContact = new Contact();
        $otherContact->forceFill([
            'user_id'      => $owner->id,
            'workspace_id' => $otherWs->id,
            'display_name' => 'Other WS Contact ' . Str::random(4),
        ])->save();

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $activeWs->id])
            ->get(route('user.contacts.index'));

        $resp->assertOk();
        // Both contacts appear even though only one lives in the active workspace.
        $resp->assertSee($activeContact->display_name);
        $resp->assertSee($otherContact->display_name);
    }

    public function test_contacts_page_total_count_is_account_wide(): void
    {
        $owner = $this->makeUser('owner');
        $activeWs = app(WorkspaceContext::class)->resolve($owner);
        $otherWs = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Other WS ' . Str::random(4),
            'is_personal'   => false,
        ]);

        foreach ([$activeWs->id, $otherWs->id] as $wsId) {
            (new Contact())->forceFill([
                'user_id'      => $owner->id,
                'workspace_id' => $wsId,
                'display_name' => 'C ' . Str::random(6),
            ])->save();
        }

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $activeWs->id])
            ->get(route('user.contacts.index'));

        $resp->assertOk();
        // The stats.total banner must count contacts across all workspaces.
        $resp->assertViewHas('stats', fn ($stats) => (int) ($stats['total'] ?? 0) === 2);
    }

    public function test_contact_saved_in_a_non_active_workspace_can_be_opened(): void
    {
        // Regression: with the index now account-wide, opening a contact saved
        // in a non-active workspace must not 404 under the workspace global
        // scope (Contact::resolveRouteBinding opts out; ownership is enforced
        // by the controller's abort_if guard).
        $owner = $this->makeUser('owner');
        $activeWs = app(WorkspaceContext::class)->resolve($owner);
        $otherWs = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Other WS ' . Str::random(4),
            'is_personal'   => false,
        ]);

        $otherContact = new Contact();
        $otherContact->forceFill([
            'user_id'      => $owner->id,
            'workspace_id' => $otherWs->id,
            'display_name' => 'Openable Contact ' . Str::random(4),
        ])->save();

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $activeWs->id])
            ->get(route('user.contacts.show', $otherContact));

        $resp->assertOk();
        $resp->assertSee($otherContact->display_name);
    }
}
