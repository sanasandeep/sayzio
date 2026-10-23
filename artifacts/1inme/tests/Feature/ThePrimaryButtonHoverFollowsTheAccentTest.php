<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The primary button's hover is derived from --accent, not typed.
 *
 * It used to be two literals -- #2342c7 in dark and #2139a1 in light --
 * which are darkened versions of an accent the product no longer uses.
 * So the button left its own hue family on hover, and an admin who
 * changed the accent got a hover that went somewhere unrelated to it.
 *
 * This is the smallest piece of the button work and the one with no
 * visual risk, which is why it ships on its own.
 */
class ThePrimaryButtonHoverFollowsTheAccentTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    public function test_the_hover_is_mixed_from_the_accent(): void
    {
        $html = $this->page();

        $ok = preg_match_all(
            '/\.btn-primary:hover \{([^}]*)\}/s',
            $html,
            $m
        );
        $this->assertGreaterThan(0, $ok, 'No .btn-primary:hover rule on the page.');

        $withBackground = array_filter($m[1], static fn ($body) => str_contains($body, 'background'));
        $this->assertNotEmpty($withBackground, 'No hover rule sets a background, so there is nothing to check.');

        foreach ($withBackground as $body) {
            $this->assertMatchesRegularExpression(
                '/background:\s*color-mix\(in srgb,\s*var\(--accent\)/',
                $body,
                'The primary button hover is not derived from --accent.'
            );
        }
    }

    /**
     * No hex literal in the hover rule at all.
     *
     * Scoped to the RULE, deliberately. The first version of this test
     * asserted that #2342c7 and #2139a1 appeared nowhere on the page --
     * and they appear in dozens of unrelated places, two of them on the
     * dashboard itself (.hero-url-text:hover and .hero-url-btn:hover).
     * It would have failed for a reason that has nothing to do with
     * buttons, which is its own kind of broken test.
     */
    public function test_the_hover_rule_carries_no_hex_literal(): void
    {
        $html = $this->page();

        preg_match_all('/\.btn-primary:hover \{([^}]*)\}/s', $html, $m);
        $this->assertNotEmpty($m[1], 'No .btn-primary:hover rule on the page.');

        foreach ($m[1] as $body) {
            $this->assertDoesNotMatchRegularExpression(
                '/background:\s*#[0-9a-fA-F]{3,8}/',
                $body,
                'A hex literal is back in the primary button hover, so it no longer follows the accent.'
            );
        }

        // The control: the search must be able to find a hex background
        // somewhere, or it is not discriminating. The page has plenty.
        $this->assertMatchesRegularExpression(
            '/background:\s*#[0-9a-fA-F]{3,8}/',
            $html,
            'No hex background anywhere on the page, so the check above proves nothing.'
        );
    }
}
