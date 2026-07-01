<?php

namespace Tests\Feature;

use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\DialerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Privacy boundary coverage for the universal Dialer finder
 * (.agents/memory/dialer-everyday.md). The finder searches across owned AND
 * followed records, and the ONLY thing standing between a searcher and another
 * creator's private/subscriber-only biolink (or its SEO meta) is
 * DialerSearch::canViewLink() — a read-side mirror of the public renderer's
 * visibility enforcement. A regression here leaks private links, so this suite
 * locks the gate three ways:
 *
 *   (1) canViewLink() itself, exercised directly (it is a private static, so we
 *       reach it via reflection). This is surface-independent and covers every
 *       branch, including the followers-only-non-follower case that the grouped
 *       search can never reach on its own (the "Followed" pool only contains
 *       creators the searcher already follows, so the followers gate always
 *       passes there — the true gate for that tier lives in this method).
 *   (2) GET /api/v1/dialer/search with a REAL Bearer token (Sanctum path binds
 *       no workspace — see .agents/memory/api-workspace-scope.md — so the
 *       cross-account "Followed" group is populated and canViewLink is the sole
 *       filter). Asserts a follower sees a followed creator's PUBLIC link but
 *       NOT their subscribers-only link unless the searcher is an active
 *       subscriber, and that the owner always sees their own restricted link.
 *   (3) GET /user/dialer/search (session + bound workspace, gated by
 *       workspace.can:settings.view — see .agents/memory/api-workspace-scope.md).
 *       Asserts the {data} envelope, the owner-sees-own-record case, that a
 *       followed creator's subscribers-only link never leaks, and that the
 *       route requires authentication.
 */
class DialerSearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** A token unique enough to isolate our seeded links from any noise. */
    private const TOKEN = 'zqprivyfind';

    private function makeUser(string $prefix = 'u'): User
    {
        return User::create([
            'name'     => $prefix . Str::random(4),
            'email'    => $prefix . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    /**
     * Seed a link whose TITLE carries the search token so the finder's text
     * match picks it up. The token lives only in link titles (never in user
     * names or workspace names) so the People / Workspaces groups stay empty
     * and our assertions read cleanly off the link groups.
     */
    private function makeLink(User $owner, string $visibility, string $type = 'biolink'): Link
    {
        return $owner->links()->create([
            'user_id'    => $owner->id,
            'type'       => $type,
            'alias'      => 'a' . substr(Str::random(10), 0, 10),
            'title'      => self::TOKEN . ' ' . $visibility . ' ' . $type,
            'is_active'  => true,
            'visibility' => $visibility,
        ]);
    }

    private function subscribe(User $creator, User $subscriber, string $status = 'active'): Subscriber
    {
        return Subscriber::create([
            'user_id' => $creator->id,
            'type'    => 'email',
            'email'   => $subscriber->email,
            'status'  => $status,
        ]);
    }

    /** Invoke the private static DialerSearch::canViewLink() gate directly. */
    private function canView(User $viewer, Link $link): bool
    {
        $m = new ReflectionMethod(DialerSearch::class, 'canViewLink');
        $m->setAccessible(true);
        return (bool) $m->invoke(null, $viewer, $link);
    }

    /** Flatten every link_id the finder returned across all groups. */
    private function linkIds(array $result): array
    {
        $ids = [];
        foreach ($result['groups'] as $g) {
            foreach ($g['items'] as $item) {
                if (($item['type'] ?? null) === 'link') {
                    $ids[] = $item['action']['link_id'] ?? $item['id'];
                }
            }
        }
        return $ids;
    }

    /** Flatten link_ids within a single named group (e.g. 'followed'). */
    private function groupLinkIds(array $result, string $key): array
    {
        foreach ($result['groups'] as $g) {
            if ($g['key'] === $key) {
                return array_values(array_filter(array_map(
                    fn ($i) => ($i['type'] ?? null) === 'link' ? ($i['action']['link_id'] ?? $i['id']) : null,
                    $g['items']
                )));
            }
        }
        return [];
    }

    // ===== (1) canViewLink() gate — every branch =====

    public function test_public_link_is_visible_to_a_stranger(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        $link    = $this->makeLink($creator, 'public');

        $this->assertTrue($this->canView($viewer, $link));
    }

    public function test_owner_always_sees_their_own_restricted_link(): void
    {
        $owner = $this->makeUser('owner');

        // Every restricted tier still resolves true for the owner.
        foreach (['registered', 'followers', 'subscribers'] as $vis) {
            $link = $this->makeLink($owner, $vis);
            $this->assertTrue(
                $this->canView($owner, $link),
                "owner should see their own {$vis} link"
            );
        }
    }

    public function test_registered_link_is_visible_to_any_authenticated_viewer(): void
    {
        // The Dialer viewer is always authenticated, so "registered" passes for
        // everyone — even a stranger who neither follows nor subscribes.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        $link    = $this->makeLink($creator, 'registered');

        $this->assertTrue($this->canView($viewer, $link));
    }

    public function test_followers_only_link_is_hidden_from_a_non_follower(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        $link    = $this->makeLink($creator, 'followers');

        $this->assertFalse($this->canView($viewer, $link));
    }

    public function test_followers_only_link_is_visible_to_a_follower(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $link = $this->makeLink($creator, 'followers');

        $this->assertTrue($this->canView($viewer, $link));
    }

    public function test_subscribers_only_link_is_hidden_without_an_active_subscription(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        // Follows but does NOT subscribe — following is not enough.
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $link = $this->makeLink($creator, 'subscribers');

        $this->assertFalse($this->canView($viewer, $link));
    }

    public function test_subscribers_only_link_is_visible_to_an_active_subscriber(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        $this->subscribe($creator, $viewer, 'active');
        $link = $this->makeLink($creator, 'subscribers');

        $this->assertTrue($this->canView($viewer, $link));
    }

    public function test_subscribers_only_link_is_hidden_from_an_unsubscribed_subscriber(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        // A lapsed subscriber (status != active) must not slip through.
        $this->subscribe($creator, $viewer, 'unsubscribed');
        $link = $this->makeLink($creator, 'subscribers');

        $this->assertFalse($this->canView($viewer, $link));
    }

    public function test_gated_non_biolink_type_respects_visibility(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        // 'file' is a gated non-biolink type: subscribers-only must be enforced.
        $file = $this->makeLink($creator, 'subscribers', 'file');

        $this->assertFalse($this->canView($viewer, $file));
    }

    public function test_ungated_non_biolink_type_ignores_visibility(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        // 'qr' is neither biolink-family nor a gated type, so it carries no real
        // visibility gate — it stays visible regardless of the column value.
        $qr = $this->makeLink($creator, 'subscribers', 'qr');

        $this->assertTrue($this->canView($viewer, $qr));
    }

    // ===== (2) API endpoint /api/v1/dialer/search (Sanctum, no workspace) =====

    private function asUser(User $user): self
    {
        // Real Sanctum token (see .agents/memory/sanctum-api-tests.md):
        // Sanctum::actingAs breaks the TouchSessionToken middleware.
        $this->withToken($user->createToken('dialer-privacy-test')->plainTextToken);
        return $this;
    }

    public function test_api_search_hides_a_followed_creators_subscribers_only_link(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);

        $publicLink = $this->makeLink($creator, 'public');
        $subsLink   = $this->makeLink($creator, 'subscribers');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains($publicLink->id, $followed, 'public followed link should surface');
        $this->assertNotContains($subsLink->id, $followed, 'subscribers-only link must not leak');

        // Belt and suspenders: it must not appear anywhere in the payload.
        $this->assertNotContains($subsLink->id, $this->linkIds($resp->json('data')));
    }

    public function test_api_search_reveals_subscribers_only_link_to_an_active_subscriber(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $this->subscribe($creator, $viewer, 'active');

        $subsLink = $this->makeLink($creator, 'subscribers');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains($subsLink->id, $followed);
    }

    public function test_api_search_owner_sees_their_own_subscribers_only_link(): void
    {
        $owner = $this->makeUser('owner');
        $own   = $this->makeLink($owner, 'subscribers');

        $resp = $this->asUser($owner)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $mine = $this->groupLinkIds($resp->json('data'), 'my_links');
        $this->assertContains($own->id, $mine, 'owner must always see their own records');
    }

    public function test_api_search_does_not_leak_an_unfollowed_creators_public_link(): void
    {
        // No follow, no contact link — a stranger's records are simply not in
        // the searchable universe (the finder never queries a global directory).
        $viewer   = $this->makeUser('viewer');
        $stranger = $this->makeUser('stranger');
        $link     = $this->makeLink($stranger, 'public');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $this->assertNotContains($link->id, $this->linkIds($resp->json('data')));
    }

    public function test_api_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/dialer/search?q=' . self::TOKEN)->assertUnauthorized();
    }

    // ===== (3) WEB endpoint /user/dialer/search (session + workspace) =====

    /**
     * Bind an active workspace in the session, mirroring what the
     * `workspace.scope` (SetActiveWorkspace) middleware resolves at request
     * time. The web dialer route is gated by `workspace.can:settings.view`;
     * the user owns the resolved workspace, so the permission is granted (see
     * .agents/memory/api-workspace-scope.md).
     */
    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    public function test_web_search_returns_envelope_and_owner_sees_own_record(): void
    {
        $owner = $this->makeUser('owner');
        // Created with a bound workspace so it lands in the owner's active
        // workspace and survives the read-side workspace scope on the web route.
        $this->actingAsWeb($owner);
        $own = $this->makeLink($owner, 'subscribers');

        $resp = $this->actingAsWeb($owner)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();
        $resp->assertJsonStructure(['data' => ['q', 'filter', 'total', 'groups']]);

        $mine = $this->groupLinkIds($resp->json('data'), 'my_links');
        $this->assertContains($own->id, $mine, 'owner must see their own record on the web surface');
    }

    public function test_web_search_returns_a_followed_creators_public_link(): void
    {
        // Regression: the web dialer runs under `workspace.scope`, which binds
        // the searcher's active workspace. A followed creator's link lives in
        // the CREATOR's workspace, so without opting the followed-links query
        // out of the BelongsToWorkspace global scope the entire "Followed" group
        // was silently filtered to empty (the API surface, which binds no
        // workspace, returned it correctly). This locks web/API parity.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $publicLink = $this->makeLink($creator, 'public');

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $publicLink->id,
            $followed,
            'a followed creator\'s public link must surface on the web dialer surface'
        );
    }

    public function test_web_search_never_leaks_a_followed_creators_subscribers_only_link(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $subsLink = $this->makeLink($creator, 'subscribers');

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $this->assertNotContains(
            $subsLink->id,
            $this->linkIds($resp->json('data')),
            'a non-subscriber must never see a subscribers-only link'
        );
    }

    public function test_web_search_requires_authentication(): void
    {
        $this->get(route('user.dialer.search', ['q' => self::TOKEN]))->assertRedirect('/user/login');
    }
}
