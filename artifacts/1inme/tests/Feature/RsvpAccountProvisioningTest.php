<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3694 — a free RSVP silently provisions a lightweight free Sayzio
 * account for the attendee (mirroring the paid-ticket guest flow), unless
 * the visitor is already signed in or an account with that email already
 * exists (which must be reused untouched).
 */
class RsvpAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Ada Organizer'): User
    {
        $u = User::factory()->create([
            'name'     => $name,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeFreeEvent(User $user): Link
    {
        $link = Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Launch Party',
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);
        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);
        return $link;
    }

    public function test_new_email_creates_free_account_with_workspace(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeFreeEvent($host);

        $this->assertNull(User::where('email', 'newattendee@ex.com')->first());

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name'     => 'New Attendee',
            'email'    => 'newattendee@ex.com',
            'response' => 'yes',
        ]);
        $resp->assertOk();

        $attendee = User::where('email', 'newattendee@ex.com')->first();
        $this->assertNotNull($attendee, 'RSVP must silently create a Sayzio account.');
        $this->assertSame('New Attendee', $attendee->name);
        $this->assertNotNull($attendee->plan_id, 'New account must land on a default plan.');
        $this->assertTrue($attendee->ownedWorkspaces()->where('is_personal', true)->exists());
    }

    public function test_existing_account_email_is_reused_untouched(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeFreeEvent($host);

        $existing = User::create([
            'name'     => 'Original Name',
            'email'    => 'existing@ex.com',
            'password' => Hash::make('super-secret'),
            'plan_id'  => null,
            'status'   => 'active',
        ]);
        $originalPasswordHash = $existing->password;

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name'     => 'Different Display Name',
            'email'    => 'existing@ex.com',
            'response' => 'yes',
        ]);
        $resp->assertOk();

        $this->assertSame(1, User::where('email', 'existing@ex.com')->count());
        $fresh = $existing->fresh();
        $this->assertSame('Original Name', $fresh->name);
        $this->assertSame($originalPasswordHash, $fresh->password);
        $this->assertNull($fresh->plan_id);
    }

    public function test_signed_in_visitor_does_not_create_a_new_account(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeFreeEvent($host);

        $attendeeAccount = $this->makeUser('Signed In Attendee');

        // Creating the attendee account rebinds current_workspace/workspace_owner
        // to it; the public alias route runs with no SetActiveWorkspace
        // middleware, so leaving that binding in place wrongly scopes
        // resolveByAlias() to the attendee's workspace and 404s the host's
        // link (see mobile/isolated-env test note on workspace-scope leak).
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $userCountBefore = User::count();

        // The RSVP form is a plain web (session-guard) route, so "signed
        // in" here means an authenticated browser session, not a bearer
        // token — actingAs the 'web' guard.
        $resp = $this->actingAs($attendeeAccount, 'web')
            ->postJson('/' . $link->alias . '/rsvp', [
                'name'     => $attendeeAccount->name,
                'email'    => 'somethingelse@ex.com',
                'response' => 'yes',
            ]);
        $resp->assertOk();

        $this->assertSame($userCountBefore, User::count(), 'No new account should be created for a signed-in visitor.');
        $this->assertNull(User::where('email', 'somethingelse@ex.com')->first());
    }
}
