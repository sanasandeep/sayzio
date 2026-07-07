<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the array-vs-object mismatch that crashed the dialer
 * caller-profile page (HTTP 500): a blade block used object access
 * ($r->number_e164, $recent->isNotEmpty()) on data that DialerData transforms
 * return as PLAIN PHP ARRAYS (transformLog, transformFavorite, activityFor,
 * groupedRecents, frequent all end in ->all() / array building).
 *
 * The crash only surfaced once matching rows EXISTED — an empty list renders
 * fine because the @foreach never iterates and Collection-style calls are
 * never reached. So these tests seed EVERY transform-backed surface with real
 * data (favorites strip, frequent strip, grouped recents incl. flags/tag/
 * outcome enrichment, profile recent-activity log, pending callback) and
 * assert the pages render 200 with the seeded values visible. Any future
 * blade edit that reintroduces object access on these arrays fatals here.
 *
 * Pattern follows tests/Feature/DialerChannelActionsPreferenceTest.php
 * (web guard + bound workspace session).
 */
class DialerDataArrayRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER      = '+15550002222';
    private const SPAM_NUMBER = '+15550003333';

    private function makeUser(): User
    {
        return User::factory()->create([
            'name'  => 'dar' . Str::random(4),
            'email' => 'dar-' . Str::random(8) . '@example.com',
        ]);
    }

    /** Web dialer routes are gated by `workspace.can:settings.view`. */
    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    /**
     * A contact resolved by number so enrichment (name/initials) kicks in.
     * Contact is workspace-scoped (BelongsToWorkspace), so it must carry the
     * workspace the web request binds or contactsForNumbers() won't see it.
     */
    private function seedContact(User $user, string $number, string $name): Contact
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $contact = new Contact([
            'user_id'      => $user->id,
            'display_name' => $name,
        ]);
        $contact->workspace_id = $ws->id;
        $contact->save();
        ContactPhone::create([
            'contact_id'  => $contact->id,
            'value'       => $number,
            'value_e164'  => $number,
            'is_primary'  => true,
        ]);
        return $contact;
    }

    /**
     * Index page: favorites, frequent and grouped recents are all plain
     * arrays from DialerData transforms; render with every one populated —
     * including the enrichment fields (calls count, tag, outcome, spam flag)
     * that only appear once real rows exist.
     */
    public function test_index_renders_with_all_transform_surfaces_populated(): void
    {
        $user = $this->makeUser();
        $contact = $this->seedContact($user, self::NUMBER, 'Array Contact');

        DialerFavorite::create([
            'user_id'     => $user->id,
            'contact_id'  => $contact->id,
            'number_e164' => self::NUMBER,
            'label'       => 'Fav Array',
            'sort_order'  => 1,
        ]);

        // Repeated calls -> grouped recents collapse with a ×N badge, and the
        // number lands in the frequent strip. Newest row carries outcome/tag.
        foreach ([3, 2, 1] as $daysAgo) {
            DialerLookup::create([
                'user_id'      => $user->id,
                'number_e164'  => self::NUMBER,
                'contact_id'   => $contact->id,
                'looked_up_at' => now()->subDays($daysAgo),
            ]);
        }
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => self::NUMBER,
            'contact_id'   => $contact->id,
            'looked_up_at' => now()->subHours(2),
            'outcome'      => 'call_back',
            'note'         => 'Ring again tomorrow',
            'tag'          => 'lead',
        ]);

        // A second, spam-flagged number exercises the flags map merge.
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => self::SPAM_NUMBER,
            'looked_up_at' => now()->subHours(1),
        ]);
        DialerNumberFlag::create([
            'user_id'     => $user->id,
            'number_e164' => self::SPAM_NUMBER,
            'is_spam'     => true,
            'is_blocked'  => false,
        ]);

        $response = $this->actingAsWeb($user)->get(route('user.dialer.index'));
        $response->assertOk();

        // Favorites strip (transformFavorite output).
        $response->assertSee('Fav Array');

        // Grouped recents (groupedRecents output): contact-resolved name,
        // collapsed call count, latest tag + outcome, spam badge.
        $response->assertSee('Array Contact');
        $response->assertSee('×4', false);
        $response->assertSee('lead');
        $response->assertSee('call back');
        $response->assertSee('SPAM');

        // Frequent strip (frequent output): per-number call counts render.
        $response->assertSee('calls');
    }

    /**
     * Profile page: recent activity (activityFor) and the pending callback
     * (pendingCallback -> transformLog) are plain arrays. This is the exact
     * page that 500'd before — re-render it with both populated.
     */
    public function test_profile_renders_with_activity_and_pending_callback(): void
    {
        $user = $this->makeUser();

        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => self::NUMBER,
            'looked_up_at' => now()->subDay(),
            'outcome'      => 'no_answer',
            'note'         => 'Left a voicemail',
            'tag'          => 'vendor',
        ]);
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => self::NUMBER,
            'looked_up_at' => now()->subHours(3),
            'callback_at'  => now()->addDay(),
        ]);

        $response = $this->actingAsWeb($user)->get(
            route('user.dialer.profile', ['number' => self::NUMBER]),
        );
        $response->assertOk();

        // Recent-activity list (transformLog fields rendered via array access).
        $response->assertSee(self::NUMBER);
        $response->assertSee('no answer');
        $response->assertSee('vendor');
        $response->assertSee('Left a voicemail');

        // Pending-callback banner parses $callback['callback_at'].
        $response->assertSee('Call-back reminder set for');
    }

    /**
     * Blocked numbers are KEPT in grouped recents (badged so the user can
     * unblock) — the branch reads flag enrichment for the blocked row.
     */
    public function test_index_renders_blocked_recent_with_badge(): void
    {
        $user = $this->makeUser();

        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => self::NUMBER,
            'looked_up_at' => now()->subHour(),
        ]);
        DialerNumberFlag::create([
            'user_id'     => $user->id,
            'number_e164' => self::NUMBER,
            'is_spam'     => false,
            'is_blocked'  => true,
        ]);

        $response = $this->actingAsWeb($user)->get(route('user.dialer.index'));
        $response->assertOk();
        $response->assertSee('BLOCKED');
    }
}
