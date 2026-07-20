<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for GET /user/links/{link}/blocks/link-picker
 * The endpoint returns the owner's links for the block-editor picker.
 * It must: require auth, scope results to the biolink's owner,
 * exclude the biolink itself, support ?q search, and never expose other
 * users' links.
 */
class BiolinkLinkPickerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $ws   = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $user;
    }

    private function makeLink(array $attrs = []): Link
    {
        if (!isset($attrs['user_id'])) {
            $attrs['user_id'] = $this->makeUser()->id;
        }
        return Link::create(array_merge([
            'type'      => 'short',
            'alias'     => Link::generateAlias(),
            'title'     => 'Test Link',
            'is_active' => true,
        ], $attrs));
    }


    private function makeBiolink(User $user): Link
    {
        return $this->makeLink(['user_id' => $user->id, 'type' => 'biolink']);
    }

    private function get_(User $user, Link $link, string $qs = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->get(route('user.links.blocks.linkPicker', $link) . $qs, [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => 'application/json',
            ]);
    }

    public function test_requires_authentication(): void
    {
        $link = $this->makeLink(['type' => 'biolink']);
        $this->getJson(route('user.links.blocks.linkPicker', $link))
            ->assertUnauthorized();
    }

    public function test_rejects_non_owner(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link  = $this->makeBiolink($owner);

        $this->get_($other, $link, '')
            ->assertForbidden();
    }

    public function test_rejects_non_biolink_link_type(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink(['user_id' => $user->id, 'type' => 'short']);

        $this->get_($user, $link, '')
            ->assertForbidden();
    }

    public function test_returns_owners_links_excluding_self(): void
    {
        $user   = $this->makeUser();
        $biolink = $this->makeBiolink($user);
        $short  = $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'mylink', 'title' => 'My Short Link']);

        $response = $this->get_($user, $biolink, '')->assertOk();
        $links    = $response->json('links');

        $ids = collect($links)->pluck('id')->toArray();
        $this->assertContains($short->id, $ids);
        $this->assertNotContains($biolink->id, $ids, 'The biolink itself must be excluded from the picker.');
    }

    public function test_does_not_expose_other_users_links(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $biolink = $this->makeBiolink($owner);
        $this->makeLink(['user_id' => $other->id, 'type' => 'short', 'alias' => 'theirlink', 'title' => 'Their Link']);

        $links = $this->get_($owner, $biolink, '')->assertOk()->json('links');
        $ids   = collect($links)->pluck('id')->toArray();

        $this->assertNotContains(
            Link::where('user_id', $other->id)->value('id'),
            $ids,
            "Other users' links must not appear in the picker."
        );
    }

    public function test_search_filters_by_title(): void
    {
        $user    = $this->makeUser();
        $biolink = $this->makeBiolink($user);
        $match   = $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'match1', 'title' => 'GitHub Profile']);
        $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'other1', 'title' => 'Twitter Page']);

        $links = $this->get_($user, $biolink, '?q=github')->assertOk()->json('links');
        $ids   = collect($links)->pluck('id')->toArray();

        $this->assertContains($match->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_search_filters_by_alias(): void
    {
        $user    = $this->makeUser();
        $biolink = $this->makeBiolink($user);
        $match   = $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'special-alias', 'title' => 'Something']);
        $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'other-alias', 'title' => 'Other']);

        $links = $this->get_($user, $biolink, '?q=special')->assertOk()->json('links');
        $ids   = collect($links)->pluck('id')->toArray();

        $this->assertContains($match->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_response_shape_is_correct(): void
    {
        $user    = $this->makeUser();
        $biolink = $this->makeBiolink($user);
        $this->makeLink(['user_id' => $user->id, 'type' => 'short', 'alias' => 'shape-test', 'title' => 'Shape Test']);

        $links = $this->get_($user, $biolink, '')->assertOk()->json('links');

        $this->assertNotEmpty($links);
        $first = $links[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('alias', $first);
        $this->assertArrayHasKey('url', $first);
    }

    public function test_empty_query_returns_all_links(): void
    {
        $user    = $this->makeUser();
        $biolink = $this->makeBiolink($user);

        for ($i = 0; $i < 3; $i++) {
            $this->makeLink([
                'user_id' => $user->id,
                'type'    => 'short',
                'alias'   => "link-{$i}",
                'title'   => "Link {$i}",
            ]);
        }

        $links = $this->get_($user, $biolink, '')->assertOk()->json('links');
        $this->assertCount(3, $links);
    }
}
