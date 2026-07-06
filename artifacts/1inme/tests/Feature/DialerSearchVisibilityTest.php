<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\DialerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
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

    /** Flatten every user_id the 'people' group returned. */
    private function peopleUserIds(array $result): array
    {
        foreach ($result['groups'] as $g) {
            if ($g['key'] === 'people') {
                return array_values(array_filter(array_map(
                    fn ($i) => ($i['type'] ?? null) === 'person' ? ($i['action']['user_id'] ?? $i['id']) : null,
                    $g['items']
                )));
            }
        }
        return [];
    }

    /** Flatten every user_id in the 'people' group that carries a verified badge. */
    private function verifiedPeopleUserIds(array $result): array
    {
        foreach ($result['groups'] as $g) {
            if ($g['key'] === 'people') {
                return array_values(array_filter(array_map(
                    fn ($i) => ($i['type'] ?? null) === 'person' && ($i['verified'] ?? false)
                        ? ($i['action']['user_id'] ?? $i['id'])
                        : null,
                    $g['items']
                )));
            }
        }
        return [];
    }

    /**
     * Seed a Sayzio account whose display NAME carries the search token so the
     * People group's name/handle match picks it up. The token lives only in the
     * account name (never in link titles or workspace names) so these People
     * assertions read cleanly.
     */
    private function makePerson(string $prefix = 'p'): User
    {
        $u = $this->makeUser($prefix);
        $u->name   = self::TOKEN . ' ' . $prefix . Str::random(3);
        $u->handle = strtolower($prefix) . substr(Str::random(8), 0, 8);
        $u->save();
        return $u;
    }

    /** Link a Sayzio account into the given user's own address book. */
    private function addContactFor(User $owner, User $linked): Contact
    {
        return Contact::create([
            'user_id'         => $owner->id,
            'display_name'    => 'Book ' . Str::random(4),
            'biolink_user_id' => $linked->id,
        ]);
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

    public function test_api_search_reveals_a_followed_creators_followers_only_link(): void
    {
        // Positive followers-only mirror of the subscribers-only API test above,
        // on the surface the mobile app actually consumes. The followed-links
        // query in DialerSearch opts out of the BelongsToWorkspace global scope
        // and the Sanctum path binds no workspace, so canViewLink() is the sole
        // filter. A follower who does NOT subscribe must still see a followed
        // creator's followers-only link — following is enough for that tier, and
        // no subscription may be required. Without this assertion a regression
        // that accidentally demanded a subscription for the `followers` tier, or
        // dropped a followed creator's followers-only link, would go uncaught on
        // the API surface (only the surface-independent canViewLink() reflection
        // test and the web endpoint cover it today).
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        // Follows but never subscribes — following alone must suffice.
        $followersLink = $this->makeLink($creator, 'followers');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $followersLink->id,
            $followed,
            'a follower (non-subscriber) must see a followed creator\'s followers-only link'
        );
    }

    public function test_api_search_reveals_a_followed_creators_registered_only_link(): void
    {
        // Positive "registered"-tier assertion on the surface the mobile app
        // actually consumes. Every Dialer viewer is authenticated, so a
        // "registered" link must surface for any authenticated searcher who can
        // reach the creator (here, via a follow) — no follow-tier relationship
        // (`followers`) or subscription (`subscribers`) may be required for this
        // tier. This tier is otherwise covered only by the surface-independent
        // canViewLink() reflection test; without this assertion a regression
        // that accidentally treated "registered" like "followers"/"subscribers"
        // (demanding a follow-tier or subscription) or dropped it entirely would
        // go uncaught on the API surface. The viewer follows the creator but
        // never subscribes, so the "Followed" pool contains the creator and
        // canViewLink() is the sole filter.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        // Follows but never subscribes — "registered" must pass on authentication alone.
        $registeredLink = $this->makeLink($creator, 'registered');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $registeredLink->id,
            $followed,
            'an authenticated follower (non-subscriber) must see a followed creator\'s registered-tier link'
        );
    }

    public function test_api_search_hides_a_creators_followers_only_link_from_a_non_follower(): void
    {
        // Negative followers-only mirror on the API surface. A non-follower must
        // never see a creator's followers-only link anywhere in the payload. The
        // creator here is neither followed nor saved as a contact, so their
        // records are not even in the searchable universe — but we assert the
        // absence explicitly so a future regression that widened the followed
        // pool (or relaxed the followers gate) is caught on the mobile surface.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        $followersLink = $this->makeLink($creator, 'followers');

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $this->assertNotContains(
            $followersLink->id,
            $this->linkIds($resp->json('data')),
            'a non-follower must never see a creator\'s followers-only link'
        );
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

    public function test_web_search_returns_the_searchers_own_link_from_a_non_active_workspace(): void
    {
        // Regression: the web dialer runs under `workspace.scope`, which binds
        // the searcher's active workspace. A user can OWN links in more than one
        // workspace; without opting the "My links" query out of the
        // BelongsToWorkspace global scope, links the same user owns in their
        // OTHER (non-active) workspaces are silently filtered out (the API
        // surface, which binds no workspace, returns them all). This locks
        // web/API parity for the "My links" group.
        $owner = $this->makeUser('owner');

        // Resolve the user's personal workspace (this becomes the active one).
        $activeWs = app(WorkspaceContext::class)->resolve($owner);

        // A second workspace owned by the same user, where a link will live.
        $otherWs = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Other WS ' . Str::random(4),
            'is_personal'   => false,
        ]);

        // A link the user owns that lives in the OTHER (non-active) workspace.
        $otherLink = $owner->links()->create([
            'user_id'      => $owner->id,
            'workspace_id' => $otherWs->id,
            'type'         => 'biolink',
            'alias'        => 'a' . substr(Str::random(10), 0, 10),
            'title'        => self::TOKEN . ' other-workspace biolink',
            'is_active'    => true,
            'visibility'   => 'public',
        ]);

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $activeWs->id])
            ->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $mine = $this->groupLinkIds($resp->json('data'), 'my_links');
        $this->assertContains(
            $otherLink->id,
            $mine,
            'the searcher\'s own link from a non-active workspace must surface in "My links" on the web dialer'
        );
    }

    public function test_web_search_returns_a_contact_saved_in_a_non_active_workspace(): void
    {
        // Regression: the web dialer runs under `workspace.scope`, which binds
        // the searcher's active workspace. The "Contacts" group (contactsAdvanced)
        // resolves the searcher's OWN address book, which is account-wide — a user
        // saves contacts across every workspace they work in. Without opting the
        // contacts query out of the BelongsToWorkspace global scope, contacts the
        // same user saved while a DIFFERENT (non-active) workspace was bound are
        // silently filtered out on web, while the API/Sanctum surface (no
        // workspace binding) returns the full address book. This locks web/API
        // parity for the "Contacts" group (mirrors the "My links" case above).
        $owner = $this->makeUser('owner');

        // Resolve the user's personal workspace (this becomes the active one).
        $activeWs = app(WorkspaceContext::class)->resolve($owner);

        // A second workspace owned by the same user, where a contact will live.
        $otherWs = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Other WS ' . Str::random(4),
            'is_personal'   => false,
        ]);

        // A contact the user saved that lives in the OTHER (non-active) workspace.
        // workspace_id isn't mass-assignable on Contact, so set it via forceFill.
        $otherContact = new Contact();
        $otherContact->forceFill([
            'user_id'      => $owner->id,
            'workspace_id' => $otherWs->id,
            'display_name' => self::TOKEN . ' other-workspace contact',
        ])->save();

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $activeWs->id])
            ->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $contactIds = [];
        foreach ($resp->json('data')['groups'] as $g) {
            if ($g['key'] === 'contacts') {
                $contactIds = array_map(fn ($i) => $i['id'], $g['items']);
            }
        }

        $this->assertContains(
            $otherContact->id,
            $contactIds,
            'the searcher\'s own contact saved in a non-active workspace must surface in "Contacts" on the web dialer'
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

    public function test_web_search_reveals_subscribers_only_link_to_an_active_subscriber(): void
    {
        // Positive web mirror of
        // test_api_search_reveals_subscribers_only_link_to_an_active_subscriber.
        // The web dialer runs under `workspace.scope`, which binds the searcher's
        // active workspace. An active subscription created outside that active
        // workspace could be missed unless the subscriber query opts out of the
        // BelongsToWorkspace global scope (Task #3845 fixed subscribedCreatorIds()
        // and the legacy canViewLink() path to do exactly that). Without this
        // test only the negative web case is locked, so a future re-scoping of
        // the subscriber query would silently HIDE an entitled subscribers-only
        // link on the web surface while the API positive test still passed.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $this->subscribe($creator, $viewer, 'active');

        $subsLink = $this->makeLink($creator, 'subscribers');

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $subsLink->id,
            $followed,
            'an active subscriber must see a followed creator\'s subscribers-only link on the web dialer surface'
        );
    }

    public function test_web_search_reveals_followers_only_link_to_a_follower(): void
    {
        // Positive web mirror of the canViewLink() followers-only-visible-to-a-
        // follower branch, exercised end-to-end on the web endpoint. The web
        // dialer runs under `workspace.scope`, which binds the searcher's active
        // workspace. A followed creator's followers-only link lives in the
        // CREATOR's workspace, so unless the followed-links query opts out of the
        // BelongsToWorkspace global scope it would be silently HIDDEN on the web
        // surface — while the surface-independent canViewLink() reflection test
        // and the (workspace-less) API path still passed. This viewer follows but
        // does NOT subscribe, so only the `followers` tier — not `subscribers` —
        // grants access; without this test a future re-scoping regression on the
        // followed-links query would go uncaught on web.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);

        $followersLink = $this->makeLink($creator, 'followers');

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $followersLink->id,
            $followed,
            'a follower must see a followed creator\'s followers-only link on the web dialer surface'
        );
    }

    public function test_web_search_reveals_registered_only_link_to_a_follower(): void
    {
        // Positive web mirror of
        // test_api_search_reveals_a_followed_creators_registered_only_link, and of
        // the canViewLink() registered-tier branch, exercised end-to-end on the
        // web endpoint. Every Dialer viewer is authenticated, so a "registered"
        // link must surface for any authenticated searcher who can reach the
        // creator (here, via a follow) — no follow-tier relationship (`followers`)
        // or subscription (`subscribers`) may be required. The web dialer runs
        // under `workspace.scope`, which binds the searcher's active workspace; a
        // followed creator's registered-tier link lives in the CREATOR's
        // workspace, so unless the followed-links query opts out of the
        // BelongsToWorkspace global scope it would be silently HIDDEN on the web
        // surface — while the surface-independent canViewLink() reflection test
        // and the (workspace-less) API path still passed. This viewer follows but
        // does NOT subscribe, so "registered" must pass on authentication alone;
        // without this test a regression that treated "registered" like
        // "followers"/"subscribers" (demanding a follow-tier or subscription), or
        // a re-scoping regression on the followed-links query, would go uncaught
        // on web.
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        // Follows but never subscribes — "registered" must pass on authentication alone.
        $registeredLink = $this->makeLink($creator, 'registered');

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains(
            $registeredLink->id,
            $followed,
            'an authenticated follower (non-subscriber) must see a followed creator\'s registered-tier link on the web dialer surface'
        );
    }

    public function test_web_search_requires_authentication(): void
    {
        $this->get(route('user.dialer.search', ['q' => self::TOKEN]))->assertRedirect('/user/login');
    }

    // ===== (4) People group scope — no global user directory =====
    //
    // The "People" group (DialerSearch::peopleItems) is deliberately restricted
    // to a reachable set: the searcher themselves, accounts they follow, and
    // accounts linked from their OWN contacts — never a global user directory.
    // A regression that widened the candidate set (e.g. querying all Users)
    // would turn the dialer search box into a people-directory that surfaces
    // strangers' names / handles / public biolinks. These tests lock that scope
    // on the shared contract and on BOTH surfaces.

    public function test_people_search_does_not_surface_a_stranger_by_name(): void
    {
        $viewer   = $this->makePerson('viewer');
        // A stranger the viewer neither follows nor has a contact for. They share
        // the search token in their name, so a global directory WOULD return them.
        $stranger = $this->makePerson('stranger');

        $result = DialerSearch::universal($viewer, self::TOKEN);
        $people = $this->peopleUserIds($result);

        $this->assertNotContains($stranger->id, $people, 'a stranger must never appear in People');
    }

    public function test_people_search_does_not_surface_a_stranger_by_handle(): void
    {
        $viewer   = $this->makePerson('viewer');
        $stranger = $this->makeUser('stranger');
        // Give the stranger a handle that carries the token but a name that does
        // not, so only a handle match could surface them.
        $stranger->handle = 'zzz' . self::TOKEN;
        $stranger->save();

        $result = DialerSearch::universal($viewer, self::TOKEN);
        $people = $this->peopleUserIds($result);

        $this->assertNotContains($stranger->id, $people, 'a stranger must never resolve by handle');
    }

    public function test_people_search_resolves_self_followed_and_contact_linked(): void
    {
        $viewer     = $this->makePerson('viewer');
        $followed   = $this->makePerson('followed');
        $contactBio = $this->makePerson('contact');
        $stranger   = $this->makePerson('stranger');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        $this->addContactFor($viewer, $contactBio);

        $result = DialerSearch::universal($viewer, self::TOKEN);
        $people = $this->peopleUserIds($result);

        $this->assertContains($viewer->id, $people, 'self must resolve');
        $this->assertContains($followed->id, $people, 'a followed account must resolve');
        $this->assertContains($contactBio->id, $people, 'a contact-linked account must resolve');
        $this->assertNotContains($stranger->id, $people, 'a stranger must stay excluded');
    }

    /**
     * Task #3497 (contact privacy): the People group only ever renders
     * name/handle metadata, so it never needs ContactPrivacy::applyToPayload()
     * — locks in the contract documented on DialerSearch's class docblock.
     * Even a followed creator who has opted OUT of every share_* category
     * must still resolve in People (they're reachable), and the item must
     * carry no phone/email/location/socials field at all.
     */
    public function test_people_search_result_never_carries_privacy_gated_fields(): void
    {
        $viewer   = $this->makePerson('viewer');
        $followed = $this->makePerson('followed');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        \App\Modules\User\Support\ContactPrivacy::updateFor($followed, [
            'share_phone'    => false,
            'share_email'    => false,
            'share_location' => false,
            'share_socials'  => false,
        ]);

        $result = DialerSearch::universal($viewer, self::TOKEN);
        $people = $this->peopleUserIds($result);
        $this->assertContains($followed->id, $people, 'an opted-out but followed creator must still be reachable');

        $group = collect($result['groups'])->firstWhere('key', 'people');
        $item = collect($group['items'])->first(fn ($i) => ($i['action']['user_id'] ?? null) === $followed->id);
        $this->assertNotNull($item);
        foreach (['phone', 'email', 'number', 'number_e164', 'location', 'locations', 'socials', 'channels'] as $gatedKey) {
            $this->assertArrayNotHasKey($gatedKey, $item, "People item must never carry a `{$gatedKey}` field");
        }
    }

    public function test_api_people_search_excludes_strangers_but_keeps_reachable(): void
    {
        $viewer     = $this->makePerson('viewer');
        $followed   = $this->makePerson('followed');
        $contactBio = $this->makePerson('contact');
        $stranger   = $this->makePerson('stranger');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        $this->addContactFor($viewer, $contactBio);

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $people = $this->peopleUserIds($resp->json('data'));
        $this->assertContains($viewer->id, $people);
        $this->assertContains($followed->id, $people);
        $this->assertContains($contactBio->id, $people);
        $this->assertNotContains($stranger->id, $people, 'API People must not leak strangers');
    }

    public function test_web_people_search_excludes_strangers_but_keeps_reachable(): void
    {
        $viewer     = $this->makePerson('viewer');
        $followed   = $this->makePerson('followed');
        $contactBio = $this->makePerson('contact');
        $stranger   = $this->makePerson('stranger');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        $this->addContactFor($viewer, $contactBio);

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $people = $this->peopleUserIds($resp->json('data'));
        $this->assertContains($viewer->id, $people);
        $this->assertContains($followed->id, $people);
        $this->assertContains($contactBio->id, $people);
        $this->assertNotContains($stranger->id, $people, 'web People must not leak strangers');
    }

    public function test_web_people_search_shows_verified_badge_for_a_link_in_a_non_active_workspace(): void
    {
        // Regression: the web dialer runs under `workspace.scope`, which binds
        // the searcher's active workspace. A person's verification badge lookup
        // (`Link::whereIn('user_id', ...)->where('is_verified', true)`) would run
        // under the BelongsToWorkspace global scope, narrowing it to the
        // SEARCHER's active workspace — so a person whose only verified link
        // lives in a non-active workspace would wrongly show as UNverified on
        // the web surface (and be excluded by the `verified` filter chip), while
        // the API/Sanctum surface (no workspace binding) shows the badge. Opting
        // the verified lookup out of the workspace scope locks web/API parity.
        $viewer   = $this->makePerson('viewer');
        $followed  = $this->makePerson('followed');
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);

        // The followed person's verified link lives in a workspace of THEIRS —
        // never the searcher's active workspace.
        $otherWs = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $followed->id,
            'name'          => 'Followed WS ' . Str::random(4),
            'is_personal'   => false,
        ]);
        $followed->links()->create([
            'user_id'      => $followed->id,
            'workspace_id' => $otherWs->id,
            'type'         => 'biolink',
            'alias'        => 'a' . substr(Str::random(10), 0, 10),
            'title'        => 'a verified biolink',
            'is_active'    => true,
            'visibility'   => 'public',
            'is_verified'  => true,
        ]);

        // The viewer searches from their OWN (different) active workspace.
        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $verified = $this->verifiedPeopleUserIds($resp->json('data'));
        $this->assertContains(
            $followed->id,
            $verified,
            'a person whose verified link is in a non-active workspace must still show the badge on the web dialer'
        );

        // And the `verified` filter chip must include them too.
        $filtered = $this->actingAsWeb($viewer)
            ->getJson(route('user.dialer.search', ['q' => self::TOKEN, 'filter' => 'verified']));
        $filtered->assertOk();
        $this->assertContains(
            $followed->id,
            $this->peopleUserIds($filtered->json('data')),
            'the verified filter chip must not exclude a person whose verified link is in a non-active workspace'
        );
    }

    // ===== (5) People group reachability — status + blocks =====
    //
    // Even within the reachable set (self / followed / contact-linked), the
    // finder must never surface an account the searcher can't reach right now:
    // one that has since been suspended/deactivated (status != active), or one
    // that has blocked the searcher (UserBlock). A followed/contact-linked
    // account whose status flips, or who blocks the searcher, must silently
    // drop out of People. These tests lock both cases on the shared contract
    // and on BOTH surfaces.

    public function test_people_search_excludes_a_suspended_or_deactivated_account(): void
    {
        $viewer   = $this->makePerson('viewer');
        $followed = $this->makePerson('followed');
        $contact  = $this->makePerson('contact');

        // Both are reachable (one followed, one contact-linked) but no longer
        // active, so neither may appear in People.
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        $this->addContactFor($viewer, $contact);
        $followed->forceFill(['status' => 'suspended'])->save();
        $contact->forceFill(['status' => 'deactivated'])->save();

        $people = $this->peopleUserIds(DialerSearch::universal($viewer, self::TOKEN));

        $this->assertContains($viewer->id, $people, 'the searcher (self) must still resolve');
        $this->assertNotContains($followed->id, $people, 'a suspended account must not surface');
        $this->assertNotContains($contact->id, $people, 'a deactivated account must not surface');
    }

    public function test_people_search_excludes_an_account_that_blocked_the_searcher(): void
    {
        $viewer   = $this->makePerson('viewer');
        $followed  = $this->makePerson('followed');

        // The searcher follows this creator, but the creator has since blocked
        // the searcher — the creator must vanish from the searcher's People.
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        UserBlock::create(['blocker_user_id' => $followed->id, 'blocked_user_id' => $viewer->id]);

        $people = $this->peopleUserIds(DialerSearch::universal($viewer, self::TOKEN));

        $this->assertContains($viewer->id, $people, 'the searcher (self) must still resolve');
        $this->assertNotContains($followed->id, $people, 'an account that blocked the searcher must not surface');
    }

    public function test_people_search_still_shows_an_account_the_searcher_blocked(): void
    {
        // The block gate is directional: the searcher blocking someone hides
        // that account elsewhere, but does not change what the FINDER returns
        // for the reachable set (only "they blocked me" removes an account).
        $viewer   = $this->makePerson('viewer');
        $followed  = $this->makePerson('followed');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $followed->id]);
        UserBlock::create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $followed->id]);

        $people = $this->peopleUserIds(DialerSearch::universal($viewer, self::TOKEN));

        $this->assertContains($followed->id, $people, 'a viewer-side block must not remove the account from the finder');
    }

    public function test_api_people_search_excludes_suspended_and_blocking_accounts(): void
    {
        $viewer    = $this->makePerson('viewer');
        $suspended = $this->makePerson('suspended');
        $blocker   = $this->makePerson('blocker');
        $reachable = $this->makePerson('reachable');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $people = $this->peopleUserIds($resp->json('data'));
        $this->assertContains($reachable->id, $people, 'an active, non-blocking account must still surface');
        $this->assertNotContains($suspended->id, $people, 'API People must not leak a suspended account');
        $this->assertNotContains($blocker->id, $people, 'API People must not leak an account that blocked the searcher');
    }

    public function test_web_people_search_excludes_suspended_and_blocking_accounts(): void
    {
        $viewer    = $this->makePerson('viewer');
        $suspended = $this->makePerson('suspended');
        $blocker   = $this->makePerson('blocker');
        $reachable = $this->makePerson('reachable');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $people = $this->peopleUserIds($resp->json('data'));
        $this->assertContains($reachable->id, $people, 'an active, non-blocking account must still surface');
        $this->assertNotContains($suspended->id, $people, 'web People must not leak a suspended account');
        $this->assertNotContains($blocker->id, $people, 'web People must not leak an account that blocked the searcher');
    }

    // ===== (6) Followed links reachability — status + blocks =====
    //
    // The "Followed" group (DialerSearch::followedLinkItems) pulls links from
    // every creator the searcher follows. canViewLink() only enforces the
    // per-link visibility tiers (public/registered/followers/subscribers) — it
    // does NOT re-check the owning account's reachability. So a followed creator
    // who gets suspended/deactivated, or who blocks the searcher, must silently
    // drop out of the Followed group (their link title / alias / SEO meta /
    // biolink URL must not surface). These tests lock both cases on the shared
    // contract and on BOTH surfaces.

    public function test_followed_links_exclude_a_suspended_or_deactivated_creator(): void
    {
        $viewer     = $this->makeUser('viewer');
        $suspended  = $this->makeUser('suspended');
        $deactivated = $this->makeUser('deactivated');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $deactivated->id]);

        // Public links (canViewLink passes) — only the account-level gate can
        // keep them out.
        $suspendedLink   = $this->makeLink($suspended, 'public');
        $deactivatedLink = $this->makeLink($deactivated, 'public');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $deactivated->forceFill(['status' => 'deactivated'])->save();

        $followed = $this->groupLinkIds(DialerSearch::universal($viewer, self::TOKEN), 'followed');

        $this->assertNotContains($suspendedLink->id, $followed, 'a suspended creator\'s link must not surface');
        $this->assertNotContains($deactivatedLink->id, $followed, 'a deactivated creator\'s link must not surface');
    }

    public function test_followed_links_exclude_a_creator_that_blocked_the_searcher(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $link = $this->makeLink($creator, 'public');
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $viewer->id]);

        $followed = $this->groupLinkIds(DialerSearch::universal($viewer, self::TOKEN), 'followed');

        $this->assertNotContains($link->id, $followed, 'a link from a creator who blocked the searcher must not surface');
    }

    public function test_followed_links_still_show_a_creator_the_searcher_blocked(): void
    {
        // The block gate is directional: the searcher blocking a creator does
        // not remove that creator's links from the Followed group — only "they
        // blocked me" removes an account (mirrors the People group behavior).
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
        $link = $this->makeLink($creator, 'public');
        UserBlock::create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $creator->id]);

        $followed = $this->groupLinkIds(DialerSearch::universal($viewer, self::TOKEN), 'followed');

        $this->assertContains($link->id, $followed, 'a viewer-side block must not remove the creator\'s links from Followed');
    }

    public function test_api_followed_links_exclude_suspended_and_blocking_creators(): void
    {
        $viewer     = $this->makeUser('viewer');
        $suspended  = $this->makeUser('suspended');
        $blocker    = $this->makeUser('blocker');
        $reachable  = $this->makeUser('reachable');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);

        $suspendedLink = $this->makeLink($suspended, 'public');
        $blockerLink   = $this->makeLink($blocker, 'public');
        $reachableLink = $this->makeLink($reachable, 'public');
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $resp = $this->asUser($viewer)->getJson('/api/v1/dialer/search?q=' . self::TOKEN);
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains($reachableLink->id, $followed, 'an active, non-blocking creator\'s link must still surface');
        $this->assertNotContains($suspendedLink->id, $followed, 'API Followed must not leak a suspended creator\'s link');
        $this->assertNotContains($blockerLink->id, $followed, 'API Followed must not leak a blocking creator\'s link');

        // Belt and suspenders: neither must appear anywhere in the payload.
        $all = $this->linkIds($resp->json('data'));
        $this->assertNotContains($suspendedLink->id, $all);
        $this->assertNotContains($blockerLink->id, $all);
    }

    public function test_web_followed_links_exclude_suspended_and_blocking_creators(): void
    {
        $viewer     = $this->makeUser('viewer');
        $suspended  = $this->makeUser('suspended');
        $blocker    = $this->makeUser('blocker');
        $reachable  = $this->makeUser('reachable');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);

        $suspendedLink = $this->makeLink($suspended, 'public');
        $blockerLink   = $this->makeLink($blocker, 'public');
        $reachableLink = $this->makeLink($reachable, 'public');
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $resp = $this->actingAsWeb($viewer)->getJson(route('user.dialer.search', ['q' => self::TOKEN]));
        $resp->assertOk();

        $followed = $this->groupLinkIds($resp->json('data'), 'followed');
        $this->assertContains($reachableLink->id, $followed, 'an active, non-blocking creator\'s link must still surface');
        $this->assertNotContains($suspendedLink->id, $followed, 'web Followed must not leak a suspended creator\'s link');
        $this->assertNotContains($blockerLink->id, $followed, 'web Followed must not leak a blocking creator\'s link');
    }
}
