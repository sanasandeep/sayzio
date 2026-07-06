<?php

namespace Tests\Feature;

use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Support\DialerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Performance + correctness coverage for the reachability gate on the grouped
 * universal Dialer finder (DialerSearch::universal — the People / Followed
 * groups), the search-surface counterpart to the caller-ID gate locked by
 * DialerCallerIdReachabilityScaleTest.
 *
 * The caller-ID surfaces (single lookup, recents, frequent, favorites) now hide
 * a creator who has since been suspended/deactivated or who has blocked the
 * searcher, and the block check there is batched into a SINGLE `user_blocks`
 * query per surface (.agents/memory/dialer-callerid-reachability.md,
 * .agents/memory/dialer-search-scaling.md). The universal finder applies the
 * same directional reachability rule inside peopleItems() and
 * followedLinkItems() — each with its own single `whereIn` block query — but
 * there was no regression test asserting BOTH that the excluded creator fully
 * drops out of search results AND that the block check stays batched (never one
 * `user_blocks` query per reachable creator) as the search set grows. A
 * regression on either surface re-opens the same leak / N+1 on a different
 * surface, so this suite locks:
 *
 *   (1) a suspended / blocking creator is excluded from BOTH the People and the
 *       Followed groups (correctness), asserted on the shared contract; and
 *   (2) `user_blocks` is touched at most once per group — and at most twice for
 *       a full universal() call (People + Followed) — no matter how many
 *       reachable creators are in the search set (no N+1).
 */
class DialerSearchReachabilityScaleTest extends TestCase
{
    use RefreshDatabase;

    /** A token unique enough to isolate our seeded records from any noise. */
    private const TOKEN = 'zqreachscale';

    private function makeUser(string $prefix = 'u', string $status = 'active'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
            'status' => $status,
            'handle' => strtolower($prefix) . substr(Str::random(8), 0, 8),
        ]);
    }

    /**
     * A Sayzio account whose display NAME carries the search token so the People
     * group's name match picks it up. The token lives only in the account name
     * (never in link titles or workspace names) so the People assertions read
     * cleanly.
     */
    private function makePerson(string $prefix = 'p', string $status = 'active'): User
    {
        $u = $this->makeUser($prefix, $status);
        $u->name   = self::TOKEN . ' ' . $prefix . Str::random(3);
        $u->handle = strtolower($prefix) . substr(Str::random(8), 0, 8);
        $u->save();
        return $u;
    }

    /**
     * A link whose TITLE carries the search token so the finder's Followed-group
     * text match picks it up. Public visibility so canViewLink() always passes —
     * only the account-level reachability gate can keep it out.
     */
    private function makeLink(User $owner, string $visibility = 'public', string $type = 'biolink'): Link
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

    /** Invoke the private static DialerSearch::peopleItems() builder directly. */
    private function peopleItems(User $viewer, string $q, bool $onlyVerified = false): array
    {
        $m = new ReflectionMethod(DialerSearch::class, 'peopleItems');
        $m->setAccessible(true);
        return (array) $m->invoke(null, $viewer, $q, $onlyVerified);
    }

    /** Invoke the private static DialerSearch::followedLinkItems() builder directly. */
    private function followedLinkItems(User $viewer, string $q, bool $onlyVerified = false): array
    {
        $m = new ReflectionMethod(DialerSearch::class, 'followedLinkItems');
        $m->setAccessible(true);
        return (array) $m->invoke(null, $viewer, $q, $onlyVerified);
    }

    /** Flatten every user_id the 'people' group returned from universal(). */
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

    // ===== Correctness — the grouped finder obeys the reachability gate =====

    public function test_people_group_excludes_a_suspended_or_blocking_creator(): void
    {
        $viewer    = $this->makePerson('viewer');
        $reachable = $this->makePerson('reachable');
        $suspended = $this->makePerson('suspended');
        $blocker   = $this->makePerson('blocker');

        // All three are followed (reachable set), but two can no longer be
        // reached: one suspended, one that has blocked the searcher.
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $people = $this->peopleUserIds(DialerSearch::universal($viewer, self::TOKEN));

        $this->assertContains($viewer->id, $people, 'self must always resolve');
        $this->assertContains($reachable->id, $people, 'an active, non-blocking account must still surface');
        $this->assertNotContains($suspended->id, $people, 'a suspended account must not surface in People');
        $this->assertNotContains($blocker->id, $people, 'an account that blocked the searcher must not surface in People');
    }

    public function test_followed_group_excludes_a_suspended_or_blocking_creators_link(): void
    {
        $viewer    = $this->makeUser('viewer');
        $reachable = $this->makeUser('reachable');
        $suspended = $this->makeUser('suspended');
        $blocker   = $this->makeUser('blocker');

        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $reachable->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $suspended->id]);
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $blocker->id]);

        // Public links (canViewLink passes) — only the account-level reachability
        // gate can keep them out.
        $reachableLink = $this->makeLink($reachable);
        $suspendedLink = $this->makeLink($suspended);
        $blockerLink   = $this->makeLink($blocker);
        $suspended->forceFill(['status' => 'suspended'])->save();
        UserBlock::create(['blocker_user_id' => $blocker->id, 'blocked_user_id' => $viewer->id]);

        $followed = $this->groupLinkIds(DialerSearch::universal($viewer, self::TOKEN), 'followed');

        $this->assertContains($reachableLink->id, $followed, 'an active, non-blocking creator\'s link must still surface');
        $this->assertNotContains($suspendedLink->id, $followed, 'a suspended creator\'s link must not surface in Followed');
        $this->assertNotContains($blockerLink->id, $followed, 'a blocking creator\'s link must not surface in Followed');
    }

    // ===== Scale — the block check is batched, never per reachable creator =====

    public function test_people_group_reachability_does_not_n_plus_1_on_user_blocks(): void
    {
        $viewer = $this->makePerson('viewer');

        // Many followed accounts in the reachable set. The naive path would run
        // one `user_blocks` exists() per candidate; the batched path runs one.
        for ($i = 0; $i < 12; $i++) {
            $creator = $this->makePerson('creator');
            Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
            if ($i % 4 === 0) {
                UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $viewer->id]);
            }
        }

        DB::enableQueryLog();
        $this->peopleItems($viewer, self::TOKEN);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            1,
            $blockQueries,
            'People-group reachability must batch the user_blocks check, not run it per reachable creator'
        );
    }

    public function test_followed_group_reachability_does_not_n_plus_1_on_user_blocks(): void
    {
        $viewer = $this->makeUser('viewer');

        for ($i = 0; $i < 12; $i++) {
            $creator = $this->makeUser('creator');
            Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
            $this->makeLink($creator);
            if ($i % 4 === 0) {
                UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $viewer->id]);
            }
        }

        DB::enableQueryLog();
        $this->followedLinkItems($viewer, self::TOKEN);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            1,
            $blockQueries,
            'Followed-group reachability must batch the user_blocks check, not run it per followed creator'
        );
    }

    public function test_universal_search_batches_block_checks_across_groups(): void
    {
        // A full universal() call runs BOTH the People and the Followed groups,
        // each of which issues at most one `user_blocks` query — so the whole
        // search touches `user_blocks` at most twice, independent of how many
        // reachable creators / links are in the set.
        $viewer = $this->makePerson('viewer');

        for ($i = 0; $i < 12; $i++) {
            $creator = $this->makePerson('creator');
            Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
            $this->makeLink($creator);
            if ($i % 4 === 0) {
                UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $viewer->id]);
            }
        }

        DB::enableQueryLog();
        DialerSearch::universal($viewer, self::TOKEN);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            2,
            $blockQueries,
            'a full universal() search must batch reachability to one user_blocks query per gated group (People + Followed)'
        );
    }

    /** @param array<int,array{query:string}> $log */
    private function countBlockQueries(array $log): int
    {
        return collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'user_blocks'))
            ->count();
    }
}
