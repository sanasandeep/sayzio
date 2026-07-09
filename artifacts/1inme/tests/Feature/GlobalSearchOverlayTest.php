<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the global search overlay endpoint (`user.dialer.search`) as
 * consumed by the new full-screen overlay. Covers authentication, response
 * structure, pagination (`page` / `per_group`), and that the Dialer page
 * itself is unaffected.
 */
class GlobalSearchOverlayTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth ──────────────────────────────────────────────────────────────

    public function test_search_requires_authentication(): void
    {
        $response = $this->getJson(route('user.dialer.search', ['q' => 'test']));
        $response->assertStatus(401);
    }

    // ── Basic response contract ───────────────────────────────────────────

    public function test_search_returns_grouped_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', ['q' => 'a']));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'q',
                    'filter',
                    'total',
                    'page',
                    'per_group',
                    'groups',
                ],
            ]);

        $this->assertSame('a', $response->json('data.q'));
        $this->assertSame(0, $response->json('data.page'));
        $this->assertSame(12, $response->json('data.per_group'));
    }

    public function test_empty_query_returns_empty_groups(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', ['q' => '']));

        $response->assertOk();
        $this->assertCount(0, $response->json('data.groups'));
        $this->assertSame(0, $response->json('data.total'));
    }

    // ── Group contract ────────────────────────────────────────────────────

    public function test_each_group_has_has_more_flag(): void
    {
        $user = User::factory()->create();

        // Create a link so the my_links group has at least one result.
        Link::factory()->for($user)->create([
            'alias'    => 'alpha-link-one',
            'type'     => 'url',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', ['q' => 'alpha']));

        $response->assertOk();
        $groups = $response->json('data.groups');
        $this->assertNotEmpty($groups);

        foreach ($groups as $group) {
            $this->assertArrayHasKey('has_more', $group, "Group '{$group['key']}' is missing has_more");
            $this->assertIsBool($group['has_more']);
            $this->assertArrayHasKey('key', $group);
            $this->assertArrayHasKey('label', $group);
            $this->assertArrayHasKey('items', $group);
        }
    }

    // ── Pagination ────────────────────────────────────────────────────────

    public function test_per_group_param_is_honoured(): void
    {
        $user = User::factory()->create();

        // Create 5 links so there's something to page through.
        for ($i = 1; $i <= 5; $i++) {
            Link::factory()->for($user)->create([
                'alias'     => "paginatelink{$i}",
                'type'      => 'url',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', [
                'q'         => 'paginatelink',
                'per_group' => 2,
            ]));

        $response->assertOk();
        $this->assertSame(2, $response->json('data.per_group'));

        $myLinks = collect($response->json('data.groups'))->firstWhere('key', 'my_links');
        if ($myLinks) {
            $this->assertCount(2, $myLinks['items']);
            $this->assertTrue($myLinks['has_more'], 'has_more should be true when more results exist');
        }
    }

    public function test_page_1_returns_next_items(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            Link::factory()->for($user)->create([
                'alias'     => "pagelink{$i}",
                'type'      => 'url',
                'is_active' => true,
            ]);
        }

        $page0 = $this->actingAs($user)
            ->getJson(route('user.dialer.search', [
                'q'         => 'pagelink',
                'per_group' => 2,
                'page'      => 0,
            ]));

        $page1 = $this->actingAs($user)
            ->getJson(route('user.dialer.search', [
                'q'         => 'pagelink',
                'per_group' => 2,
                'page'      => 1,
            ]));

        $page0->assertOk();
        $page1->assertOk();

        $links0 = collect($page0->json('data.groups'))->firstWhere('key', 'my_links');
        $links1 = collect($page1->json('data.groups'))->firstWhere('key', 'my_links');

        if ($links0 && $links1) {
            $ids0 = collect($links0['items'])->pluck('id')->all();
            $ids1 = collect($links1['items'])->pluck('id')->all();
            // Pages must not overlap
            $this->assertEmpty(
                array_intersect($ids0, $ids1),
                'Page 0 and page 1 share overlapping items'
            );
        }
    }

    public function test_per_group_is_capped_at_60(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', [
                'q'         => 'anything',
                'per_group' => 999,
            ]));

        $response->assertOk();
        $this->assertSame(60, $response->json('data.per_group'));
    }

    public function test_page_param_defaults_to_0(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('user.dialer.search', ['q' => 'test']));

        $response->assertOk();
        $this->assertSame(0, $response->json('data.page'));
    }

    // ── Dialer page is unaffected ─────────────────────────────────────────

    public function test_dialer_index_still_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.dialer.index'));

        // Should still render the dialer page (not broken by our changes).
        $response->assertStatus(200);
    }
}
