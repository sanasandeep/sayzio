<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Services\LinkPerformanceCoach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Asserts that the three coach rules that support one-click remediation
 * (dead-blocks, top-heavy, disabled-socials) attach an `action` payload the
 * frontend can POST back to /user/links/{link}/coach-action.
 */
class LinkPerformanceCoachActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLink(): Link
    {
        $user = \App\Modules\User\Models\User::create([
            'name' => 'Coach User',
            'email' => 'coach-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => bcrypt('pw'),
            'status' => 'active',
            'timezone' => 'UTC',
            'language' => 'en',
        ]);
        $link = Link::create([
            'user_id' => $user->id,
            'type' => 'biolink',
            'alias' => 'cl' . bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        // The rules inspect `$link->pixels` via relationLoaded; preload an
        // empty collection so ruleMissingPixel is deterministic.
        $link->setRelation('pixels', new Collection());
        return $link;
    }

    /**
     * Build a realistic coach context with enough traffic to keep the
     * low-traffic suppressors out of the way.
     */
    private function baseContext(Link $link, array $overrides = []): array
    {
        return array_merge([
            'link'               => $link,
            'totalInRange'       => 200,
            'uniqueInRange'      => 150,
            'blockClicksInRange' => 150,
            'pageVisitsInRange'  => 200,
            'totalSessions'      => 150,
            'avgSessionSeconds'  => 30,
            'bounceRate'         => 20.0,
            'blockStats'         => collect(),
            'topReferrers'       => collect(),
            'totalInRangePrev'   => 180,
            'blockInventory'     => [
                'clickable' => [], 'has_socials' => false, 'has_qr' => false,
                'active_count' => 0, 'top_level_active_count' => 0,
                'disabled_socials_block_id' => null,
            ],
            'period' => '30d',
        ], $overrides);
    }

    public function test_dead_blocks_rule_attaches_deactivate_blocks_action(): void
    {
        $link = $this->makeLink();

        // Three clickable active blocks — only id=10 actually got clicks.
        $ctx = $this->baseContext($link, [
            'blockStats' => collect([
                (object) ['block_id' => 10, 'count' => 50, 'unique_count' => 40],
            ]),
            'blockInventory' => [
                'clickable' => [10, 11, 12], // 11 + 12 are dead
                'has_socials' => false, 'has_qr' => false,
                'active_count' => 3, 'top_level_active_count' => 3,
                'disabled_socials_block_id' => null,
            ],
            'totalInRange' => 100, // above dead_block_min_total_clicks
        ]);

        $payload = LinkPerformanceCoach::build($ctx);

        $dead = $this->findInsight($payload['insights'], fn ($i) => ($i['action']['type'] ?? null) === 'deactivate_blocks');
        $this->assertNotNull($dead, 'dead-blocks rule should attach a deactivate_blocks action');
        $this->assertSame('warning', $dead['severity']);
        $this->assertEqualsCanonicalizing([11, 12], $dead['action']['block_ids']);
        $this->assertArrayHasKey('confirm', $dead['action']);
    }

    public function test_top_heavy_rule_attaches_promote_block_action(): void
    {
        $link = $this->makeLink();

        // One block is eating 80% of clicks (>70% threshold).
        $ctx = $this->baseContext($link, [
            'blockClicksInRange' => 100,
            'blockStats' => collect([
                (object) ['block_id' => 42, 'count' => 80, 'unique_count' => 60],
                (object) ['block_id' => 43, 'count' => 10, 'unique_count' => 8],
                (object) ['block_id' => 44, 'count' => 10, 'unique_count' => 8],
            ]),
            'blockInventory' => [
                'clickable' => [42, 43, 44],
                'has_socials' => true, 'has_qr' => false,
                'active_count' => 3, 'top_level_active_count' => 3,
                'disabled_socials_block_id' => null,
            ],
        ]);

        $payload = LinkPerformanceCoach::build($ctx);

        $top = $this->findInsight($payload['insights'], fn ($i) => ($i['action']['type'] ?? null) === 'promote_block');
        $this->assertNotNull($top, 'top-heavy rule should attach a promote_block action');
        $this->assertSame(42, $top['action']['block_id']);
        $this->assertArrayHasKey('confirm', $top['action']);
    }

    public function test_disabled_socials_rule_attaches_enable_block_action(): void
    {
        $link = $this->makeLink();

        $ctx = $this->baseContext($link, [
            'blockInventory' => [
                'clickable' => [1, 2],
                'has_socials' => false,                  // no ACTIVE socials
                'has_qr' => false,
                'active_count' => 2, 'top_level_active_count' => 2,
                'disabled_socials_block_id' => 77,       // …but a hidden one exists
            ],
        ]);

        $payload = LinkPerformanceCoach::build($ctx);

        $social = $this->findInsight($payload['insights'], fn ($i) => ($i['action']['type'] ?? null) === 'enable_block');
        $this->assertNotNull($social, 'disabled-socials rule should attach an enable_block action');
        $this->assertSame(77, $social['action']['block_id']);
        $this->assertSame('tip', $social['severity']);
    }

    public function test_disabled_socials_rule_suppressed_when_active_socials_present(): void
    {
        $link = $this->makeLink();

        $ctx = $this->baseContext($link, [
            'blockInventory' => [
                'clickable' => [1, 2],
                'has_socials' => true,               // user already has an active socials block
                'has_qr' => false,
                'active_count' => 2, 'top_level_active_count' => 2,
                'disabled_socials_block_id' => 77,   // a duplicate hidden one exists
            ],
        ]);

        $payload = LinkPerformanceCoach::build($ctx);

        $social = $this->findInsight($payload['insights'], fn ($i) => ($i['action']['type'] ?? null) === 'enable_block');
        $this->assertNull($social, 'disabled-socials should not fire when another socials block is already active');
    }

    /**
     * @param  callable(array):bool  $predicate
     */
    private function findInsight(array $insights, callable $predicate): ?array
    {
        foreach ($insights as $ins) {
            if ($predicate($ins)) return $ins;
        }
        return null;
    }
}
