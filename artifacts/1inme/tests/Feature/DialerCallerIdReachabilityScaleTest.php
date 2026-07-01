<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Support\DialerData;
use App\Modules\User\Support\DialerReachability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Performance + correctness coverage for the Dialer caller-ID reachability gate
 * on the BATCH / history renders (recents + frequent), the fourth dialer
 * surface after the single-lookup gate locked by DialerCallerIdReachabilityTest.
 *
 * The recents / frequent lists badge each row's number as a Sayzio creator via
 * the contact's attached biolink. That badge must obey the same reachability
 * rule as the single lookup — hide a creator who has since been suspended /
 * deactivated, or who has blocked the owner (.agents/memory/
 * dialer-callerid-reachability.md) — but naively calling the per-lookup gate in
 * the list loop would run one `user_blocks` query per row, an N+1 that gets slow
 * as the dialer history grows. DialerReachability::reachableMap() batches the
 * block check into a SINGLE query (status comes from the eager-loaded biolink
 * user), mirroring how the search path pre-fetches subscribers once
 * (.agents/memory/dialer-search-scaling.md). This suite asserts BOTH:
 *
 *   (1) the batch render still hides suspended / blocking creators (correctness),
 *   (2) `user_blocks` is touched at most once regardless of how many attached
 *       biolink contacts are in the recents / frequent set (no N+1).
 */
class DialerCallerIdReachabilityScaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u', string $status = 'active'): User
    {
        return User::create([
            'name'     => $prefix . Str::random(4),
            'email'    => $prefix . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => $status,
            'handle'   => strtolower($prefix) . substr(Str::random(8), 0, 8),
        ]);
    }

    private function uniqueE164(): string
    {
        return '+1' . str_pad((string) random_int(2000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    }

    /** A Sayzio creator reachable by a verified phone identifier. */
    private function makeCreatorWithPhone(string $e164, string $status = 'active'): User
    {
        $creator = $this->makeUser('creator', $status);
        LinkedIdentifier::create([
            'user_id'     => $creator->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', $e164),
            'verified_at' => now(),
        ]);
        return $creator;
    }

    /**
     * A saved contact for $owner whose phone matches $e164, already attached to
     * $creator's biolink (the state the silent auto-attach seeds), plus a recent
     * dialer lookup so the number surfaces in recents + frequent.
     */
    private function seedAttachedContactWithHistory(User $owner, string $e164, User $creator): Contact
    {
        $contact = Contact::create([
            'user_id'         => $owner->id,
            'display_name'    => 'Book ' . Str::random(4),
            'biolink_user_id' => $creator->id,
        ]);
        ContactPhone::create([
            'contact_id' => $contact->id,
            'value'      => $e164,
            'value_e164' => $e164,
            'is_primary' => true,
        ]);
        DialerLookup::create([
            'user_id'      => $owner->id,
            'number_e164'  => $e164,
            'contact_id'   => $contact->id,
            'looked_up_at' => now(),
        ]);
        return $contact;
    }

    // ===== Correctness — the batch render obeys the gate =====

    public function test_recents_hide_a_suspended_or_blocking_creators_biolink_badge(): void
    {
        $owner = $this->makeUser('owner');

        // Reachable creator → badge stays on.
        $okE164   = $this->uniqueE164();
        $okC      = $this->makeCreatorWithPhone($okE164);
        $this->seedAttachedContactWithHistory($owner, $okE164, $okC);

        // Suspended creator → badge must drop.
        $suspE164 = $this->uniqueE164();
        $suspC    = $this->makeCreatorWithPhone($suspE164, 'suspended');
        $this->seedAttachedContactWithHistory($owner, $suspE164, $suspC);

        // Creator that blocked the owner → badge must drop.
        $blkE164  = $this->uniqueE164();
        $blkC     = $this->makeCreatorWithPhone($blkE164);
        UserBlock::create(['blocker_user_id' => $blkC->id, 'blocked_user_id' => $owner->id]);
        $this->seedAttachedContactWithHistory($owner, $blkE164, $blkC);

        $badgeByNumber = collect(DialerData::groupedRecents($owner->id))
            ->mapWithKeys(fn ($r) => [$r['number_e164'] => $r['biolink']]);

        $this->assertTrue($badgeByNumber[$okE164], 'reachable creator keeps the caller-ID badge');
        $this->assertFalse($badgeByNumber[$suspE164], 'suspended creator must not badge recents');
        $this->assertFalse($badgeByNumber[$blkE164], 'blocking creator must not badge recents');
    }

    public function test_frequent_hide_a_suspended_or_blocking_creators_biolink_badge(): void
    {
        $owner = $this->makeUser('owner');

        $okE164   = $this->uniqueE164();
        $okC      = $this->makeCreatorWithPhone($okE164);
        $this->seedAttachedContactWithHistory($owner, $okE164, $okC);

        $suspE164 = $this->uniqueE164();
        $suspC    = $this->makeCreatorWithPhone($suspE164, 'suspended');
        $this->seedAttachedContactWithHistory($owner, $suspE164, $suspC);

        $blkE164  = $this->uniqueE164();
        $blkC     = $this->makeCreatorWithPhone($blkE164);
        UserBlock::create(['blocker_user_id' => $blkC->id, 'blocked_user_id' => $owner->id]);
        $this->seedAttachedContactWithHistory($owner, $blkE164, $blkC);

        $badgeByNumber = collect(DialerData::frequent($owner->id))
            ->mapWithKeys(fn ($r) => [$r['number_e164'] => $r['biolink']]);

        $this->assertTrue($badgeByNumber[$okE164], 'reachable creator keeps the caller-ID badge');
        $this->assertFalse($badgeByNumber[$suspE164], 'suspended creator must not badge frequent');
        $this->assertFalse($badgeByNumber[$blkE164], 'blocking creator must not badge frequent');
    }

    // ===== Scale — the block check is batched, never per row =====

    public function test_recents_reachability_does_not_n_plus_1_on_user_blocks(): void
    {
        $owner = $this->makeUser('owner');

        // Many attached-biolink contacts in the history. The naive path would
        // run one `user_blocks` exists() per row; the batched path runs one.
        for ($i = 0; $i < 12; $i++) {
            $e164    = $this->uniqueE164();
            $creator = $this->makeCreatorWithPhone($e164);
            // Have a few of them block the owner for a realistic mix.
            if ($i % 4 === 0) {
                UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $owner->id]);
            }
            $this->seedAttachedContactWithHistory($owner, $e164, $creator);
        }

        DB::enableQueryLog();
        DialerData::groupedRecents($owner->id);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            1,
            $blockQueries,
            'recents caller-ID gating must batch the user_blocks check, not run it per row'
        );
    }

    public function test_frequent_reachability_does_not_n_plus_1_on_user_blocks(): void
    {
        $owner = $this->makeUser('owner');

        for ($i = 0; $i < 12; $i++) {
            $e164    = $this->uniqueE164();
            $creator = $this->makeCreatorWithPhone($e164);
            if ($i % 4 === 0) {
                UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $owner->id]);
            }
            $this->seedAttachedContactWithHistory($owner, $e164, $creator);
        }

        DB::enableQueryLog();
        DialerData::frequent($owner->id);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            1,
            $blockQueries,
            'frequent caller-ID gating must batch the user_blocks check, not run it per row'
        );
    }

    // ===== Batched helper stays consistent with the per-lookup gate =====

    public function test_reachable_map_matches_per_creator_reaches_and_batches_the_query(): void
    {
        $owner = $this->makeUser('owner');

        $active    = $this->makeUser('active');
        $suspended = $this->makeUser('creator', 'suspended');
        $blocking  = $this->makeUser('creator');
        UserBlock::create(['blocker_user_id' => $blocking->id, 'blocked_user_id' => $owner->id]);

        $creators = [$active, $suspended, $blocking];

        DB::enableQueryLog();
        $map = DialerReachability::reachableMap($owner->id, $creators);
        $blockQueries = $this->countBlockQueries(DB::getQueryLog());
        DB::disableQueryLog();

        // One block query for the whole set, regardless of creator count.
        $this->assertLessThanOrEqual(1, $blockQueries, 'reachableMap must issue a single user_blocks query');

        // Result is identical to calling reaches() per creator.
        foreach ($creators as $creator) {
            $this->assertSame(
                DialerReachability::reaches($owner->id, $creator),
                $map[$creator->id],
                "reachableMap must agree with reaches() for creator {$creator->id}"
            );
        }
    }

    /** @param array<int,array{query:string}> $log */
    private function countBlockQueries(array $log): int
    {
        return collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'user_blocks'))
            ->count();
    }
}
