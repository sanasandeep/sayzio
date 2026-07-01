<?php

namespace Tests\Feature;

use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Performance + correctness coverage for the universal Dialer finder's
 * "Followed" group (App\Modules\User\Support\DialerSearch).
 *
 * The finder gates every followed link through canViewLink(). The naive path
 * ran a per-link follow/subscriber `exists()` query — an N+1 that gets slow
 * once a user follows many creators. followedLinkItems() now pre-resolves the
 * viewer's active subscriptions in a SINGLE query and reuses it for every link,
 * so this suite asserts BOTH:
 *
 *   (1) visibility gating still resolves correctly (followers always pass,
 *       subscribers pass only for creators the viewer actually subscribes to),
 *   (2) the `subscribers` table is touched at most once regardless of how many
 *       subscriber-gated followed links are in the result set (no N+1).
 */
class DialerSearchScaleTest extends TestCase
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

    private function follow(User $viewer, User $creator): void
    {
        Follow::create(['follower_id' => $viewer->id, 'creator_id' => $creator->id]);
    }

    private function biolink(User $creator, string $token, string $visibility): Link
    {
        return $creator->links()->create([
            'user_id'    => $creator->id,
            'type'       => 'biolink',
            'alias'      => 'bl' . substr(Str::random(10), 0, 10),
            'title'      => $token . ' page',
            'is_active'  => true,
            'visibility' => $visibility,
        ]);
    }

    private function subscribe(User $creator, User $viewer): void
    {
        Subscriber::create([
            'user_id' => $creator->id,
            'type'    => 'email',
            'email'   => $viewer->email,
            'status'  => 'active',
        ]);
    }

    public function test_followed_group_gates_visibility_correctly(): void
    {
        $viewer = $this->makeUser('viewer');
        $token = 'zqfind';

        // Followed creator A: subscribers-only link, viewer IS subscribed → visible.
        $a = $this->makeUser('a');
        $this->follow($viewer, $a);
        $this->subscribe($a, $viewer);
        $linkA = $this->biolink($a, $token, 'subscribers');

        // Followed creator B: subscribers-only link, viewer NOT subscribed → hidden.
        $b = $this->makeUser('b');
        $this->follow($viewer, $b);
        $linkB = $this->biolink($b, $token, 'subscribers');

        // Followed creator C: followers-only link → visible (viewer follows them).
        $c = $this->makeUser('c');
        $this->follow($viewer, $c);
        $linkC = $this->biolink($c, $token, 'followers');

        // Followed creator D: public link → always visible.
        $d = $this->makeUser('d');
        $this->follow($viewer, $d);
        $linkD = $this->biolink($d, $token, 'public');

        $result = DialerSearch::universal($viewer, $token);
        $followed = collect($result['groups'])->firstWhere('key', 'followed');

        $this->assertNotNull($followed, 'Followed group should be present');
        $ids = collect($followed['items'])->pluck('id')->all();

        $this->assertContains($linkA->id, $ids, 'subscribed → subscribers link visible');
        $this->assertContains($linkC->id, $ids, 'followers-only link visible to a follower');
        $this->assertContains($linkD->id, $ids, 'public link visible');
        $this->assertNotContains($linkB->id, $ids, 'unsubscribed → subscribers link hidden');
    }

    public function test_followed_visibility_does_not_n_plus_1_on_subscribers(): void
    {
        $viewer = $this->makeUser('viewer');
        $token = 'zqscale';

        // Follow many creators, each with a subscribers-gated link. The naive
        // path would run one subscribers `exists()` per link.
        for ($i = 0; $i < 8; $i++) {
            $creator = $this->makeUser('c' . $i);
            $this->follow($viewer, $creator);
            $this->biolink($creator, $token, 'subscribers');
            // Subscribe the viewer to half of them for a realistic mix.
            if ($i % 2 === 0) {
                $this->subscribe($creator, $viewer);
            }
        }

        DB::enableQueryLog();
        DialerSearch::universal($viewer, $token);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $subscriberQueries = collect($queries)
            ->filter(fn ($q) => str_contains($q['query'], 'subscribers'))
            ->count();

        // At most one subscribers query for the whole followed batch (0 is also
        // fine if no gated link needed a check, but here they all do → exactly 1).
        $this->assertLessThanOrEqual(
            1,
            $subscriberQueries,
            'Followed-link visibility must batch the subscriber check, not run it per link'
        );
    }
}
