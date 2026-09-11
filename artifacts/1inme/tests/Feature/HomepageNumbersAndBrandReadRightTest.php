<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\SiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The homepage spells its numbers one way, and names itself once.
 *
 * Both of these are first-impression bugs found by reading the live page as a
 * stranger rather than by any test failing.
 *
 * The stats were stored with Indian grouping -- `3,75,000`, `1,05,000` -- so
 * the hero trust line said `375,000+` a few hundred pixels above a stats band
 * saying `3,75,000+`. Two spellings of one number on a single screen does not
 * read as a different convention; it reads as a mistake, and the reader then
 * discounts every other figure on the page.
 *
 * Grouping is presentation, so it is decided at render from the numeric value
 * and can no longer drift from what an admin typed.
 */
class HomepageNumbersAndBrandReadRightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(SiteStat::ACTIVE_CACHE_KEY);
    }

    public function test_a_stat_stored_with_indian_grouping_renders_international(): void
    {
        $stat = new SiteStat(['value' => '3,75,000']);

        $this->assertSame('375,000', $stat->displayValue());
    }

    public function test_grouping_is_normalised_however_it_was_typed(): void
    {
        foreach ([
            '1,05,000' => '105,000',
            '1,50,000' => '150,000',
            '375000'   => '375,000',
            '375,000'  => '375,000',
            '67'       => '67',
        ] as $stored => $expected) {
            $this->assertSame(
                $expected,
                (new SiteStat(['value' => $stored]))->displayValue(),
                "stored value {$stored} did not render as {$expected}"
            );
        }
    }

    /**
     * A value that is not a plain number is left exactly as the admin wrote it.
     *
     * `99.9%`, `24/7` and `1.5M` are deliberate formats, and reformatting them
     * would be worse than the bug this fixes.
     */
    public function test_a_deliberately_formatted_value_is_left_alone(): void
    {
        foreach (['99.9%', '24/7', '1.5M', '4.9★', ''] as $stored) {
            $this->assertSame(
                $stored,
                (new SiteStat(['value' => $stored]))->displayValue(),
                "the value {$stored} was reformatted when it should have passed through"
            );
        }
    }

    /** Decimals survive. `1.5` must not become `2`. */
    public function test_decimals_are_preserved(): void
    {
        $this->assertSame('1.5', (new SiteStat(['value' => '1.5']))->displayValue());
        $this->assertSame('12,345.67', (new SiteStat(['value' => '12345.67']))->displayValue());
    }

    /**
     * The count-up animation groups the same way the server did.
     *
     * It was hardcoded to `en-IN`, so every visitor anywhere watched the digits
     * group as 3,75,000 on the way up and then snap to the final string. Fixing
     * only the stored value would have left that.
     */
    public function test_the_count_up_animation_groups_the_same_way_the_page_does(): void
    {
        // The trust band is below the fold and arrives through /home/sections,
        // the deferred fragment the homepage fetches after first paint -- not
        // through '/'. Asserting against '/' passes vacuously.
        $html = $this->get('/home/sections')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            "toLocaleString('en-IN')",
            $html,
            'the stat count-up is grouping digits Indian-style for every visitor in the world'
        );
        $this->assertStringContainsString(
            "toLocaleString('en-US')",
            $html,
            'the stat count-up no longer pins a locale, so it will group by the viewer\'s '
            . 'and disagree with the server-rendered figure'
        );
    }

    /**
     * The old brand is introduced as a rename, not as a second company.
     *
     * "1IN.ME is Sayzio" is the second thing a first-time visitor reads. It
     * makes sense only if you already knew the old name.
     */
    public function test_the_old_brand_is_named_as_a_rename(): void
    {
        // The trust band is below the fold and arrives through /home/sections,
        // the deferred fragment the homepage fetches after first paint -- not
        // through '/'. Asserting against '/' passes vacuously.
        $html = $this->get('/home/sections')->assertOk()->getContent();

        if (! str_contains($html, '1IN.ME')) {
            $this->markTestSkipped('the brand lockup has been removed from the homepage');
        }

        // Scoped to the lockup's own element, not "somewhere after 1IN.ME".
        // The first version of this assertion was /1IN\.ME.*?is now/s, which
        // passed happily against the unfixed page because `.*?` ran on to find
        // an "is now" hundreds of lines further down. Reverting the fix did not
        // fail it, which is how I found out.
        $this->assertMatchesRegularExpression(
            '/<span class="pb-is">\s*is now\s*<\/span>/',
            $html,
            'the homepage says "1IN.ME is Sayzio", which reads as two companies to anyone '
            . 'who did not know the old name. It needs "is now".'
        );
    }
}
