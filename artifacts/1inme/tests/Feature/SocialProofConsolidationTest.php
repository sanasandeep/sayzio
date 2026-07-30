<?php

namespace Tests\Feature;

use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Buzz notification-type consolidation:
 *   visitor_count / conversion_count  → counter        (settings.mode)
 *   email_signup  / exit_offer        → capture_prompt (settings.trigger)
 *
 * Legacy widgets must keep working transparently (no DB rewrite), the
 * public config payload should emit consolidated types, and the subscribe
 * endpoint must accept both legacy and consolidated capture types.
 */
class SocialProofConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Buzz Owner',
            'email'    => 'buzz-' . Str::lower(Str::random(8)) . '@example.test',
            'password' => bcrypt('secret-password'),
        ]);
    }

    /**
     * The public config endpoint hides live-counter types unless the owner's
     * primary biolink explicitly disables hide_public_visitor_counts.
     */
    private function allowPublicCounts(User $user): void
    {
        \App\Modules\User\Models\Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => 'bz-' . Str::lower(Str::random(10)),
            'url'       => null,
            'is_active' => true,
            'settings'  => ['biolink' => ['privacy' => ['hide_public_visitor_counts' => false]]],
        ]);
    }

    private function makeProof(User $user, array $notifications): SocialProof
    {
        return SocialProof::create([
            'user_id'       => $user->id,
            'uuid'          => (string) Str::uuid(),
            'name'          => 'Consolidation Test',
            'type'          => $notifications[0]['type'] ?? 'counter',
            'is_active'     => true,
            'notifications' => $notifications,
            'settings'      => [],
            'design'        => [],
            'targeting'     => [],
        ]);
    }

    public function test_normalize_maps_legacy_types_to_consolidated(): void
    {
        $a = SocialProof::normalizeNotification(['type' => 'visitor_count', 'settings' => ['min' => 3, 'max' => 9]]);
        $this->assertSame('counter', $a['type']);
        $this->assertSame('live_visitors', $a['settings']['mode']);
        $this->assertSame(3, $a['settings']['min']);

        $b = SocialProof::normalizeNotification(['type' => 'conversion_count', 'settings' => ['count' => 12]]);
        $this->assertSame('counter', $b['type']);
        $this->assertSame('conversions', $b['settings']['mode']);
        $this->assertSame(12, $b['settings']['count']);

        $c = SocialProof::normalizeNotification(['type' => 'email_signup']);
        $this->assertSame('capture_prompt', $c['type']);
        $this->assertSame('always', $c['settings']['trigger']);

        $d = SocialProof::normalizeNotification(['type' => 'exit_offer', 'settings' => ['cta_url' => 'https://x.test']]);
        $this->assertSame('capture_prompt', $d['type']);
        $this->assertSame('exit_intent', $d['settings']['trigger']);
        $this->assertSame('https://x.test', $d['settings']['cta_url']);

        // Consolidated types pass through untouched.
        $e = SocialProof::normalizeNotification(['type' => 'counter', 'settings' => ['mode' => 'conversions']]);
        $this->assertSame('counter', $e['type']);
        $this->assertSame('conversions', $e['settings']['mode']);
    }

    public function test_public_config_emits_consolidated_types_for_legacy_rows(): void
    {
        $user = $this->makeUser();
        $this->allowPublicCounts($user);
        $proof = $this->makeProof($user, [
            ['id' => 'n1', 'type' => 'visitor_count', 'settings' => ['min' => 5, 'max' => 10], 'is_active' => true],
            ['id' => 'n2', 'type' => 'exit_offer', 'settings' => ['title' => 'Wait'], 'is_active' => true],
        ]);

        $res = $this->getJson('/sp/' . $proof->uuid . '.json');
        $res->assertOk();

        $types = collect($res->json('notifications'))->pluck('type')->all();
        $this->assertContains('counter', $types);
        $this->assertContains('capture_prompt', $types);
        $this->assertNotContains('visitor_count', $types);
        $this->assertNotContains('exit_offer', $types);

        $counter = collect($res->json('notifications'))->firstWhere('type', 'counter');
        $this->assertSame('live_visitors', $counter['settings']['mode']);
        $capture = collect($res->json('notifications'))->firstWhere('type', 'capture_prompt');
        $this->assertSame('exit_intent', $capture['settings']['trigger']);
    }

    public function test_subscribe_accepts_consolidated_and_legacy_capture_types(): void
    {
        $user = $this->makeUser();

        $proofNew = $this->makeProof($user, [
            ['id' => 'cap1', 'type' => 'capture_prompt', 'settings' => ['trigger' => 'always'], 'is_active' => true],
        ]);
        $this->postJson('/sp/' . $proofNew->uuid . '/subscribe', [
            'email' => 'new-type@example.test',
            'notification_id' => 'cap1',
        ])->assertOk()->assertJson(['ok' => true]);

        $proofLegacy = $this->makeProof($user, [
            ['id' => 'leg1', 'type' => 'email_signup', 'settings' => [], 'is_active' => true],
        ]);
        $this->postJson('/sp/' . $proofLegacy->uuid . '/subscribe', [
            'email' => 'legacy-type@example.test',
            'notification_id' => 'leg1',
        ])->assertOk()->assertJson(['ok' => true]);

        // Non-capture types are still rejected.
        $proofOther = $this->makeProof($user, [
            ['id' => 'cnt1', 'type' => 'counter', 'settings' => ['mode' => 'live_visitors'], 'is_active' => true],
        ]);
        $this->postJson('/sp/' . $proofOther->uuid . '/subscribe', [
            'email' => 'reject@example.test',
            'notification_id' => 'cnt1',
        ])->assertStatus(422)->assertJson(['ok' => false, 'error' => 'not_capture']);
    }

    public function test_live_visitor_count_present_for_counter_live_mode(): void
    {
        $user = $this->makeUser();
        $this->allowPublicCounts($user);
        $proof = $this->makeProof($user, [
            ['id' => 'n1', 'type' => 'counter', 'settings' => ['mode' => 'live_visitors', 'min' => 7, 'max' => 7], 'is_active' => true],
        ]);

        $res = $this->getJson('/sp/' . $proof->uuid . '.json');
        $res->assertOk();
        $this->assertSame(7, $res->json('live_visitors'));
    }
}
