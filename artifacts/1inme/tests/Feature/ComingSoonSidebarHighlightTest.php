<?php

namespace Tests\Feature;

use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * When a soon-gated feature redirects to /user/coming-soon/{feature}, the
 * sidebar item for that feature must still be highlighted as active (and its
 * collapsible group auto-opened) in BOTH the desktop sidebar and the mobile
 * drawer — otherwise the user loses their place in the nav. nav_route_is()
 * provides this by mapping the coming-soon page back onto the gated
 * feature's own route patterns.
 */
class ComingSoonSidebarHighlightTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'onboarded_at' => now(),
        ]);
    }

    public function test_coming_soon_page_highlights_the_features_sidebar_link_and_opens_its_group(): void
    {
        FeatureAvailability::setForced('payouts', true);
        $this->assertTrue(FeatureAvailability::isComingSoon('payouts'));

        $resp = $this->actingAs($this->user())
            ->get(route('user.coming-soon.show', ['feature' => 'payouts'], false));

        $resp->assertOk();
        $html = $resp->getContent();

        // The payouts anchor(s) — desktop sidebar + mobile drawer — must both
        // carry the `active` class.
        preg_match_all(
            '/<a[^>]*href="[^"]*\/user\/payouts"[^>]*class="sidebar-link[^"]*"/',
            $html,
            $anchors
        );
        $this->assertNotEmpty($anchors[0], 'Expected payouts sidebar links in the layout.');
        foreach ($anchors[0] as $anchor) {
            $this->assertStringContainsString(
                'active',
                $anchor,
                'Payouts sidebar link should be highlighted on its coming-soon page: ' . $anchor
            );
        }

        // Its collapsible "Links & Pages" group must auto-open (desktop +
        // mobile drawer both seed Alpine `open: true`).
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'open: true'),
            'The payouts group should auto-open in both navs on the coming-soon page.'
        );
    }

    public function test_non_gated_pages_are_unchanged(): void
    {
        FeatureAvailability::setForced('payouts', false);

        $resp = $this->actingAs($this->user())->get(route('user.links.index', [], false));

        $resp->assertOk();
        $html = $resp->getContent();

        // Dashboard link active, payouts link NOT active.
        preg_match_all(
            '/<a[^>]*href="[^"]*\/user\/payouts"[^>]*class="sidebar-link([^"]*)"/',
            $html,
            $anchors
        );
        foreach ($anchors[1] as $classes) {
            $this->assertStringNotContainsString('active', $classes);
        }
        $this->assertStringContainsString('sidebar-link active', $html);
    }
}
