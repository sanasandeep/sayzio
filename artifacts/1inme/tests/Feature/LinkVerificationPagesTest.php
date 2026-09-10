<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VerificationRequest;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page of the Link Verification feature answers 200.
 *
 * All four of them were answering 500 in production, and nothing caught it,
 * because the failure was invisible from the controllers: Blade resolves a
 * view by NAME, and both this feature and Profile Verification (Task #5439)
 * rendered `user.verification.index`, `.request`, `.admin-index` and
 * `.admin-review`. One set of files sat on disk under those names and served
 * both features. Profile verification's version won, so every page here blew
 * up on a variable it was never passed -- "Undefined variable $user" on the
 * user-facing pages, $queue/$pendingNewCount on the admin queue.
 *
 * The fix moved this feature's templates to
 * resources/views/user/link-verification/. This test is what stops the next
 * one: it renders each page for real, so a template that stops matching what
 * its controller passes fails here instead of in front of a user.
 *
 * Mostly these assert a 200, which is what a broken data contract turns into.
 * Two of them also assert the link's alias appears, because a 200 alone is a
 * weaker signal than it looks: the first cut of these pages read $link->slug,
 * a column Link does not have, and Eloquent hands back null for a missing
 * attribute rather than raising. Every row rendered its path as a bare "/"
 * and every test still passed. Asserting on a value the page is supposed to
 * show is what closes that gap.
 *
 * The sibling profile-verification pages are asserted too, so a future fix to
 * one feature cannot quietly re-break the other.
 */
class LinkVerificationPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A biolink the controller will actually find.
     *
     * Two things have to be right or the page renders its empty state and a
     * status-code assertion passes while proving nothing:
     *
     *  - `type` must be in Link::BIOLINK_FAMILY, since that is what
     *    VerificationController::index() filters on.
     *  - `workspace_id` must be the user's active workspace. Rows created
     *    outside a request get a NULL workspace_id, and the BelongsToWorkspace
     *    global scope filters on the bound `current_workspace` during the
     *    request -- so a NULL row is invisible to the controller even though
     *    it belongs to the user.
     */
    private function biolinkFor(User $user): Link
    {
        return Link::factory()->create([
            'user_id'      => $user->id,
            'type'         => Link::BIOLINK_FAMILY[0],
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
        ]);
    }

    /** A user holding the permission the admin routes gate on. */
    private function reviewer(): User
    {
        $user = User::factory()->create();

        $permission = Permission::firstOrCreate(
            ['slug' => 'user.verifications.review'],
            ['name' => 'Review verifications'],
        );
        $role = Role::firstOrCreate(
            ['slug' => 'verification-reviewer'],
            ['name' => 'Verification reviewer'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_the_requests_list_renders(): void
    {
        $user = User::factory()->create();
        $link = $this->biolinkFor($user);

        $this->actingAs($user)
            ->get(route('user.verification.index'))
            ->assertOk()
            // A Link's public path is `alias`; there is no `slug` column.
            // Reading a missing attribute off an Eloquent model is silently
            // null, so the first cut of this page rendered every row's path
            // as a bare "/" and still answered 200 -- a status-code-only
            // assertion sailed straight past it.
            ->assertSee($link->alias);
    }

    public function test_the_requests_list_renders_with_no_pages_and_no_requests(): void
    {
        // The empty state is its own branch and reads different variables.
        $this->actingAs(User::factory()->create())
            ->get(route('user.verification.index'))
            ->assertOk();
    }

    public function test_the_requests_list_renders_an_existing_request(): void
    {
        $user = User::factory()->create();
        $link = $this->biolinkFor($user);

        foreach (['pending', 'approved', 'rejected'] as $status) {
            VerificationRequest::create([
                'user_id'       => $user->id,
                'link_id'       => $link->id,
                'category'      => 'artist_creator',
                'business_name' => 'Example Ltd',
                'display_name'  => 'Example',
                'purpose'       => 'Because.',
                'proof_files'   => [],
                'status'        => $status,
                'admin_notes'   => $status === 'rejected' ? 'Needs a clearer logo.' : null,
            ]);
        }

        $this->actingAs($user)
            ->get(route('user.verification.index'))
            ->assertOk();
    }

    public function test_the_request_form_renders(): void
    {
        $user = User::factory()->create();
        $link = $this->biolinkFor($user);

        $this->actingAs($user)
            ->get(route('user.verification.request', ['link_id' => $link->id]))
            ->assertOk()
            ->assertSee($link->alias);
    }

    public function test_the_admin_queue_renders(): void
    {
        $reviewer = $this->reviewer();
        $applicant = User::factory()->create();
        $link = $this->biolinkFor($applicant);

        VerificationRequest::create([
            'user_id'       => $applicant->id,
            'link_id'       => $link->id,
            'category'      => 'business_product',
            'business_name' => 'Example Ltd',
            'display_name'  => 'Example',
            'purpose'       => 'Because.',
            'proof_files'   => [],
            'status'        => 'pending',
        ]);

        $this->actingAs($reviewer)
            ->get(route('user.verification.admin'))
            ->assertOk();
    }

    public function test_the_admin_queue_renders_when_empty(): void
    {
        $this->actingAs($this->reviewer())
            ->get(route('user.verification.admin'))
            ->assertOk();
    }

    public function test_the_admin_review_page_renders(): void
    {
        $reviewer = $this->reviewer();
        $applicant = User::factory()->create();
        $link = $this->biolinkFor($applicant);

        $request = VerificationRequest::create([
            'user_id'       => $applicant->id,
            'link_id'       => $link->id,
            'category'      => 'artist_creator',
            'business_name' => 'Example Ltd',
            'display_name'  => 'Example',
            'purpose'       => 'Because.',
            'proof_files'   => ['uploads/proof-one.pdf'],
            'status'        => 'pending',
        ]);

        $this->actingAs($reviewer)
            ->get(route('user.verification.admin.review', $request))
            ->assertOk();
    }

    /**
     * The other half of the collision. If someone ever "fixes" link
     * verification by pointing it back at user.verification.*, these fail.
     */
    public function test_the_profile_verification_pages_still_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.profile-verification.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('user.profile-verification.request'))
            ->assertOk();
    }
}
