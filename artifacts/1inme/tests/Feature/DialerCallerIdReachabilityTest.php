<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\DialerIdentity;
use App\Modules\User\Support\DialerReachability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reachability boundary coverage for the Dialer's caller-ID enrichment surface
 * (.agents/memory/dialer-everyday.md). Separate from the universal-finder
 * search gate (DialerSearchVisibilityTest), this locks the THIRD dialer
 * surface: resolving a phone number to a Sayzio creator's name / handle /
 * biolink for caller-ID (`/dialer/lookup` + `/dialer/profile`, backed by
 * `linked_identifiers` phone resolution and the silent biolink auto-attach).
 *
 * Neither `contact.biolink_user_id` nor `LinkedIdentifier::resolveUser()`
 * checks whether the searcher can still reach the matched creator, so a single
 * gate — DialerReachability — keeps caller-ID from naming an account that has
 * since been suspended/deactivated (`status != active`), or that has blocked
 * the searcher (`UserBlock` where `blocked_user_id` = searcher). This suite
 * locks that gate on:
 *
 *   (1) the shared contract — DialerReachability directly + DialerIdentity::resolve
 *   (2) the silent auto-attach that SEEDS the surface (BiolinkAttachResolver)
 *   (3) GET/POST /api/v1/dialer/{lookup,profile} with a REAL Bearer token
 *   (4) GET /user/dialer/profile (session + bound workspace)
 */
class DialerCallerIdReachabilityTest extends TestCase
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

    /** A unique valid E.164 number for each test creator. */
    private function uniqueE164(): string
    {
        return '+1' . str_pad((string) random_int(2000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    }

    /** A Sayzio creator reachable by a verified phone identifier + a biolink. */
    private function makeCreatorWithPhone(string $e164, string $status = 'active'): User
    {
        $creator = $this->makeUser('creator', $status);
        LinkedIdentifier::create([
            'user_id'     => $creator->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', $e164),
            'verified_at' => now(),
        ]);
        $creator->links()->create([
            'user_id'    => $creator->id,
            'type'       => 'biolink',
            'alias'      => 'a' . substr(Str::random(10), 0, 10),
            'title'      => 'Creator bio',
            'is_active'  => true,
            'visibility' => 'public',
        ]);
        return $creator;
    }

    /** A saved contact for $owner whose phone matches $e164. */
    private function makeContactWithPhone(User $owner, string $e164): Contact
    {
        $contact = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Book ' . Str::random(4),
        ]);
        ContactPhone::create([
            'contact_id' => $contact->id,
            'value'      => $e164,
            'value_e164' => $e164,
            'is_primary' => true,
        ]);
        return $contact->fresh('phones');
    }

    private function asUser(User $user): self
    {
        // Real Sanctum token (see .agents/memory/sanctum-api-tests.md):
        // Sanctum::actingAs breaks the TouchSessionToken middleware.
        $this->withToken($user->createToken('dialer-callerid-test')->plainTextToken);
        return $this;
    }

    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    // ===== (1) Shared contract — DialerReachability =====

    public function test_gate_allows_an_active_non_blocking_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $creator  = $this->makeUser('creator');

        $this->assertTrue(DialerReachability::reaches($searcher->id, $creator));
        $this->assertSame($creator->id, DialerReachability::enrichableCreator($searcher->id, $creator)?->id);
    }

    public function test_gate_blocks_a_suspended_or_deactivated_creator(): void
    {
        $searcher    = $this->makeUser('viewer');
        $suspended   = $this->makeUser('creator', 'suspended');
        $deactivated = $this->makeUser('creator', 'deactivated');

        $this->assertFalse(DialerReachability::reaches($searcher->id, $suspended));
        $this->assertFalse(DialerReachability::reaches($searcher->id, $deactivated));
        $this->assertNull(DialerReachability::enrichableCreator($searcher->id, $suspended));
        $this->assertNull(DialerReachability::enrichableCreator($searcher->id, $deactivated));
    }

    public function test_gate_blocks_a_creator_that_blocked_the_searcher(): void
    {
        $searcher = $this->makeUser('viewer');
        $creator  = $this->makeUser('creator');
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $searcher->id]);

        $this->assertFalse(DialerReachability::reaches($searcher->id, $creator));
        $this->assertNull(DialerReachability::enrichableCreator($searcher->id, $creator));
    }

    public function test_gate_is_directional_a_searcher_side_block_still_enriches(): void
    {
        // The searcher blocking a creator hides that account elsewhere but must
        // not change caller-ID resolution — only "they blocked me" removes it.
        $searcher = $this->makeUser('viewer');
        $creator  = $this->makeUser('creator');
        UserBlock::create(['blocker_user_id' => $searcher->id, 'blocked_user_id' => $creator->id]);

        $this->assertTrue(DialerReachability::reaches($searcher->id, $creator));
    }

    public function test_gate_exempts_self_from_the_status_check(): void
    {
        // A user can always resolve their own number, even if their own account
        // is suspended (status is exempt for self; blocks can't apply to self).
        $self = $this->makeUser('self', 'suspended');

        $this->assertTrue(DialerReachability::reaches($self->id, $self));
    }

    // ===== (2) Shared contract — DialerIdentity::resolve =====

    public function test_resolve_enriches_a_reachable_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);

        $resolved = DialerIdentity::resolve($searcher, null, $e164);
        $this->assertNotNull($resolved['matchedUser']);
        $this->assertSame($creator->id, $resolved['matchedUser']->id);
    }

    public function test_resolve_drops_a_suspended_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $this->makeCreatorWithPhone($e164, 'suspended');

        $resolved = DialerIdentity::resolve($searcher, null, $e164);
        $this->assertNull($resolved['matchedUser'], 'a suspended creator must not enrich caller-ID');
        $this->assertNull($resolved['bio']);
    }

    public function test_resolve_drops_a_creator_that_blocked_the_searcher(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $searcher->id]);

        $resolved = DialerIdentity::resolve($searcher, null, $e164);
        $this->assertNull($resolved['matchedUser'], 'a blocking creator must not enrich caller-ID');
    }

    public function test_resolve_drops_a_stale_contact_attachment_to_a_suspended_creator(): void
    {
        // Even when the contact carries a (now-stale) biolink_user_id pointing
        // at a since-suspended creator, the read-side gate must hide it.
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164, 'suspended');

        $contact = $this->makeContactWithPhone($searcher, $e164);
        $contact->forceFill(['biolink_user_id' => $creator->id])->save();

        $resolved = DialerIdentity::resolve($searcher, $contact->id, null);
        $this->assertNull($resolved['matchedUser'], 'a stale attachment to a suspended creator must not enrich');
    }

    // ===== (3) Silent auto-attach — BiolinkAttachResolver =====

    public function test_auto_attach_links_a_reachable_creator(): void
    {
        $owner   = $this->makeUser('owner');
        $e164    = $this->uniqueE164();
        $creator = $this->makeCreatorWithPhone($e164);
        $contact = $this->makeContactWithPhone($owner, $e164);

        app(BiolinkAttachResolver::class)->resolveFor($contact);

        $this->assertSame($creator->id, $contact->fresh()->biolink_user_id);
    }

    public function test_auto_attach_skips_a_suspended_creator(): void
    {
        $owner = $this->makeUser('owner');
        $e164  = $this->uniqueE164();
        $this->makeCreatorWithPhone($e164, 'suspended');
        $contact = $this->makeContactWithPhone($owner, $e164);

        app(BiolinkAttachResolver::class)->resolveFor($contact);

        $this->assertNull($contact->fresh()->biolink_user_id, 'a suspended creator must not be auto-attached');
    }

    public function test_auto_attach_skips_a_creator_that_blocked_the_owner(): void
    {
        $owner   = $this->makeUser('owner');
        $e164    = $this->uniqueE164();
        $creator = $this->makeCreatorWithPhone($e164);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $owner->id]);
        $contact = $this->makeContactWithPhone($owner, $e164);

        app(BiolinkAttachResolver::class)->resolveFor($contact);

        $this->assertNull($contact->fresh()->biolink_user_id, 'a blocking creator must not be auto-attached');
    }

    // ===== (4) API surface — /api/v1/dialer/lookup + /profile =====

    public function test_api_lookup_enriches_a_reachable_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);

        $resp = $this->asUser($searcher)->postJson('/api/v1/dialer/lookup', ['number_e164' => $e164]);
        $resp->assertOk();
        $this->assertSame($creator->id, $resp->json('data.biolink.user_id'));
    }

    public function test_api_lookup_hides_a_suspended_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $this->makeCreatorWithPhone($e164, 'suspended');

        $resp = $this->asUser($searcher)->postJson('/api/v1/dialer/lookup', ['number_e164' => $e164]);
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'API lookup must not name a suspended creator');
    }

    public function test_api_lookup_hides_a_creator_that_blocked_the_searcher(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $searcher->id]);

        $resp = $this->asUser($searcher)->postJson('/api/v1/dialer/lookup', ['number_e164' => $e164]);
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'API lookup must not name a blocking creator');
    }

    public function test_api_profile_hides_a_suspended_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $this->makeCreatorWithPhone($e164, 'suspended');

        $resp = $this->asUser($searcher)->getJson('/api/v1/dialer/profile?number=' . urlencode($e164));
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'API profile must not name a suspended creator');
    }

    public function test_api_profile_hides_a_creator_that_blocked_the_searcher(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $searcher->id]);

        $resp = $this->asUser($searcher)->getJson('/api/v1/dialer/profile?number=' . urlencode($e164));
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'API profile must not name a blocking creator');
    }

    // ===== (5) Web surface — /user/dialer/profile =====

    public function test_web_profile_enriches_a_reachable_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);

        $resp = $this->actingAsWeb($searcher)
            ->getJson(route('user.dialer.profile', ['number' => $e164]));
        $resp->assertOk();
        $this->assertSame($creator->id, $resp->json('data.biolink.user_id'));
    }

    public function test_web_profile_hides_a_suspended_creator(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $this->makeCreatorWithPhone($e164, 'suspended');

        $resp = $this->actingAsWeb($searcher)
            ->getJson(route('user.dialer.profile', ['number' => $e164]));
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'web profile must not name a suspended creator');
    }

    public function test_web_profile_hides_a_creator_that_blocked_the_searcher(): void
    {
        $searcher = $this->makeUser('viewer');
        $e164     = $this->uniqueE164();
        $creator  = $this->makeCreatorWithPhone($e164);
        UserBlock::create(['blocker_user_id' => $creator->id, 'blocked_user_id' => $searcher->id]);

        $resp = $this->actingAsWeb($searcher)
            ->getJson(route('user.dialer.profile', ['number' => $e164]));
        $resp->assertOk();
        $this->assertNull($resp->json('data.biolink'), 'web profile must not name a blocking creator');
    }
}
