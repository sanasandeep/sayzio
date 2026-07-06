<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\DialerController;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerSearch;
use App\Modules\User\Support\DialerT9;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

    private function makeUser(string $prefix = 'u', ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? ($prefix . Str::random(4)),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    /** Seed an address-book contact for the given owner. */
    private function makeContact(int $userId, array $attrs = []): Contact
    {
        return Contact::create(array_merge([
            'user_id'      => $userId,
            'display_name' => 'Contact ' . Str::random(4),
        ], $attrs));
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

    // ── T9 keypad-spelled name search (SQL path) ─────────────────────────
    //
    // The digit-sequence path (typing 526 to find "Jan") was moved from an
    // in-memory PHP loop into SQL. Correctness now hinges on the SQL encoding
    // (DialerT9::sqlEncode / CONTACT_NAME_SQL) staying byte-identical to the PHP
    // DialerT9::encode() and to Contact::nameForDisplay(). If either drifts,
    // keypad-spelled search quietly stops matching (and the functional GIN index
    // stops being used) with NO error. The tests below exercise a digit-sequence
    // query end to end so any drift fails loudly.

    /**
     * A followed person and an address-book contact whose names spell the same
     * digit sequence on the keypad are both returned for that sequence across
     * all three surfaces: DialerSearch::universal(), DialerSearch::contactsAdvanced()
     * and DialerController::searchContacts().
     */
    public function test_t9_digit_sequence_matches_keypad_spelled_names(): void
    {
        Queue::fake();

        // "Zephyrus" → z9 e3 p7 h4 y9 r7 u8 s7 → "93749787" (8 distinct digits,
        // long enough that it can't collide with the random names of other
        // seeded accounts).
        $name = 'Zephyrus';
        $seq  = '93749787';
        $this->assertSame($seq, DialerT9::encode($name), 'sanity: name must encode to the expected sequence');
        $this->assertTrue(DialerT9::isDigitSequence($seq));

        $viewer = $this->makeUser('viewer');

        // A followed Sayzio account named "Zephyrus" → People group.
        $person = $this->makeUser('p', $name);
        $this->follow($viewer, $person);

        // An address-book contact whose display name is "Zephyrus" → Contacts.
        $contact = $this->makeContact($viewer->id, ['display_name' => $name]);

        // A contact whose name spells the sequence only via given + family name
        // (display_name blank) exercises CONTACT_NAME_SQL's coalesce/concat.
        $splitContact = $this->makeContact($viewer->id, [
            'display_name' => null,
            'given_name'   => 'Zep',   // z9 e3 p7 → 937
            'family_name'  => 'Hyrus', // h4 y9 r7 u8 s7 → 49787
        ]);
        $this->assertSame($seq, DialerT9::encode('Zep Hyrus'), 'given+family must encode to the same sequence');

        // A followed account whose name does NOT encode to the sequence must be
        // excluded (guards against the T9 branch matching everything).
        $decoy = $this->makeUser('d', 'Bob');
        $this->follow($viewer, $decoy);

        // (1) universal(): People + Contacts groups.
        $result = DialerSearch::universal($viewer, $seq);

        $people = collect($result['groups'])->firstWhere('key', 'people');
        $this->assertNotNull($people, 'People group should be present');
        $peopleIds = collect($people['items'])->pluck('id')->all();
        $this->assertContains($person->id, $peopleIds, 'keypad-spelled person must match its digit sequence');
        $this->assertNotContains($decoy->id, $peopleIds, 'a name that does not encode to the sequence must not match');

        $contacts = collect($result['groups'])->firstWhere('key', 'contacts');
        $this->assertNotNull($contacts, 'Contacts group should be present');
        $contactIds = collect($contacts['items'])->pluck('id')->all();
        $this->assertContains($contact->id, $contactIds, 'keypad-spelled contact (display_name) must match');
        $this->assertContains($splitContact->id, $contactIds, 'keypad-spelled contact (given+family) must match');

        // (2) contactsAdvanced(): returns the matching contacts.
        $adv = DialerSearch::contactsAdvanced($viewer->id, $seq)->pluck('id')->all();
        $this->assertContains($contact->id, $adv);
        $this->assertContains($splitContact->id, $adv);

        // (3) DialerController::searchContacts() (private → reflection).
        $method = new \ReflectionMethod(DialerController::class, 'searchContacts');
        $method->setAccessible(true);
        $ctrlIds = collect($method->invoke(new DialerController(), $viewer->id, $seq))->pluck('id')->all();
        $this->assertContains($contact->id, $ctrlIds);
        $this->assertContains($splitContact->id, $ctrlIds);
    }

    /**
     * DialerT9::encode() (PHP) and DialerT9::sqlEncode() (Postgres) must produce
     * byte-identical output for a spread of names — accents, digits, punctuation,
     * mixed case and empty. This is the parity the SQL path (and the functional
     * GIN index built on the same expression) relies on; if it drifts, keypad
     * search silently stops matching.
     */
    public function test_php_and_sql_t9_encoding_are_identical(): void
    {
        $names = [
            'Jan',
            'Zephyrus',
            'Zep Hyrus',
            'José García',       // accents → dropped by both
            "O'Brien-Smith",     // punctuation → dropped
            'Anne-Marie',
            'abc123',            // letters + digits
            '(555) 123-4567',    // phone-style digits + punctuation
            'naïve café',        // more accents
            'MixedCASE Name',
            'ЖenyaПётр',         // non-latin letters → dropped
            '   ',               // whitespace only → empty
            '',                  // empty
            '2Pac',              // leading digit
        ];

        // Evaluate the SQL encoding in one round trip: build a UNION-free VALUES
        // scan and apply sqlEncode() to each bound name.
        foreach ($names as $name) {
            $row = DB::selectOne(
                'select ' . DialerT9::sqlEncode('?') . ' as t9',
                [$name]
            );
            $sql = (string) ($row->t9 ?? '');
            $php = DialerT9::encode($name);

            $this->assertSame(
                $php,
                $sql,
                "PHP encode() and sqlEncode() diverged for name: " . var_export($name, true)
            );
        }
    }

    /**
     * Free-text (non-digit) queries must behave exactly as before: they match
     * names/handles via ILIKE and do NOT engage the T9 digit branch.
     */
    public function test_free_text_search_behavior_unchanged(): void
    {
        Queue::fake();

        $viewer = $this->makeUser('viewer');

        $person = $this->makeUser('p', 'Zephyrus');
        $this->follow($viewer, $person);
        $contact = $this->makeContact($viewer->id, ['display_name' => 'Zephyrus']);

        $q = 'ephyr'; // substring, letters only → not a digit sequence
        $this->assertFalse(DialerT9::isDigitSequence($q));

        $result = DialerSearch::universal($viewer, $q);

        $peopleIds = collect(collect($result['groups'])->firstWhere('key', 'people')['items'] ?? [])
            ->pluck('id')->all();
        $this->assertContains($person->id, $peopleIds, 'free-text ILIKE match on name');

        $contactIds = collect(collect($result['groups'])->firstWhere('key', 'contacts')['items'] ?? [])
            ->pluck('id')->all();
        $this->assertContains($contact->id, $contactIds, 'free-text ILIKE match on contact name');

        // A free-text query that matches nothing returns no groups for it.
        $none = DialerSearch::universal($viewer, 'no-such-name-xyz');
        $this->assertSame([], collect($none['groups'])->pluck('key')->all());
        $this->assertSame(0, $none['total']);
    }
}
