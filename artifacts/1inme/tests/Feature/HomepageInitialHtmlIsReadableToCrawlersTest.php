<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\HomeController;
use App\Modules\Common\Support\HomePageCache;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RendersTheHomepage;
use Tests\TestCase;

/**
 * The homepage's FIRST response says what Sayzio is.
 *
 * Measured on the live site on 2026-09-11, `GET /` returned zero h2 headings
 * and zero h3 headings: one <h1>, and then the whole page behind the
 * JavaScript fetch of /home/sections. Google does render JavaScript, but as
 * a queued second pass -- so the first look at the homepage of a
 * 375,000-user product was a headline and a spinner.
 *
 * The same measurement also reported "1 internal link". That was wrong --
 * re-measuring found 64, because the header and footer navigation are
 * server-rendered and always have been. The heading and body-copy findings
 * held up; the link one did not, and it is written down here so nobody
 * rediscovers it as a fact.
 *
 * The fix moved three sections into the initial HTML. This suite exists
 * because that fix is invisible: the page looks identical in a browser
 * whether it is server-rendered or fetched, so nothing but a test will
 * notice it being undone. Deferring one of these sections again -- to shave
 * a page-speed number, or while refactoring the fragment -- reverts the SEO
 * work silently and with a plausible-sounding reason attached.
 *
 * The counts below are floors, not targets. They are set at roughly what the
 * three sections carry, so ADDING to the initial HTML never fails this and
 * removing a section does.
 */
class HomepageInitialHtmlIsReadableToCrawlersTest extends TestCase
{
    use RefreshDatabase;
    use RendersTheHomepage;

    /** Tag occurrences in a raw HTML string (`h2` -> count of `<h2 ...>`). */
    private function countTags(string $html, string $tag): int
    {
        return preg_match_all('/<' . $tag . '(\s[^>]*)?>/i', $html);
    }

    public function test_the_first_response_has_a_heading_outline(): void
    {
        $html = $this->initialHomepageHtml();

        $this->assertGreaterThanOrEqual(
            1,
            $this->countTags($html, 'h1'),
            'the homepage has no h1 in its initial HTML'
        );
        $this->assertGreaterThanOrEqual(
            3,
            $this->countTags($html, 'h2'),
            'the homepage is back to shipping its section headings behind a JavaScript '
            . 'fetch -- a crawler\'s first pass sees the hero and nothing else'
        );
    }

    /**
     * The first response contains the page's actual prose.
     *
     * This is the measurement that matters, and it is not the one I reported
     * first. I told Sana the initial HTML had "1 internal link"; re-measuring
     * the live page found 64 unique internal links -- the header and footer
     * navigation are fully server-rendered and always were. That figure was
     * a bug in my instrument, not a finding, and nothing should be built on
     * it. Counting links would not guard this change anyway: removing the
     * three sections leaves 53 internal links behind, so the assertion would
     * pass against the unfixed page.
     *
     * Words of indexable text is what actually moves. Measured in this
     * suite's own environment, with and without the change:
     *
     *     before   1,302 words   (almost entirely nav labels)
     *     after    2,659 words
     *
     * The floor is set between the two so that removing a section fails and
     * adding copy does not.
     */
    public function test_the_first_response_contains_the_pages_prose(): void
    {
        $html = $this->initialHomepageHtml();

        // Script and style content is not prose, and this page carries a lot
        // of both -- counting it would swamp the signal entirely.
        $body = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#si', ' ', $html);
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $body))));

        $this->assertGreaterThanOrEqual(
            2000,
            str_word_count($text),
            'the homepage\'s initial HTML is back to nav labels and a headline -- the '
            . 'product copy has moved behind the JavaScript fetch again'
        );
    }

    /**
     * The three sections that carry the product's keyword surface.
     *
     * Asserted by section id rather than by copy: the wording is marketing's
     * to change, the presence is not.
     */
    public function test_the_sections_that_say_what_sayzio_is_are_server_rendered(): void
    {
        $html = $this->initialHomepageHtml();

        foreach ([
            'id="create"' => 'the "what you can create" showcase -- the eighteen link '
                . 'types, which is the page\'s whole keyword surface',
            'id="audience"' => 'the audience section',
        ] as $marker => $what) {
            $this->assertStringContainsString(
                $marker,
                $html,
                $what . ' is no longer in the homepage\'s initial HTML'
            );
        }

        // The proof band has no id of its own; it is identified by its class.
        $this->assertStringContainsString(
            'pb-band',
            $html,
            'the proof band (user count, the 1IN.ME rebrand) is no longer in the '
            . 'homepage\'s initial HTML'
        );
    }

    /**
     * Moved, not copied.
     *
     * Rendering a section in both responses would put two of every heading on
     * the finished page -- and duplicate headings are worse for search than
     * the deferred version this change replaced.
     */
    public function test_the_server_rendered_sections_are_not_also_in_the_fragment(): void
    {
        $fragment = $this->deferredHomepageHtml();

        foreach (['id="create"', 'id="audience"', 'pb-band'] as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $fragment,
                "{$marker} renders in BOTH homepage responses -- every heading in that "
                . 'section now appears twice on the finished page'
            );
        }
    }

    /**
     * The rest of the page is still deferred.
     *
     * The change is worthless if it becomes the thin end of moving the whole
     * 2,000-line fragment into the first response. These are the heavy
     * sections -- live demos, the plan teaser, the FAQ -- and they stay
     * behind the fetch.
     */
    public function test_the_heavy_sections_are_still_deferred(): void
    {
        $initial = $this->initialHomepageHtml();

        foreach ([
            'id="faq"' => 'the FAQ',
            'id="grow"' => 'the analytics section',
            'id="workspace-team"' => 'the teams section',
        ] as $marker => $what) {
            $this->assertStringNotContainsString(
                $marker,
                $initial,
                $what . ' has been moved into the initial HTML. The reason the rest of '
                . 'this page is deferred has not gone away -- weigh it against first paint.'
            );
        }
    }

    /**
     * The first response still does not price anything.
     *
     * The showcase shows no prices, so it must not drag the plan matrix into
     * the homepage's first response. It reads its own small cache key for
     * exactly this reason; reaching for the per-currency payload instead
     * would work in every test and cost a dozen queries on a cold key in
     * production, where the database is a cross-region round trip.
     */
    public function test_the_first_response_does_not_query_the_plan_catalogue(): void
    {
        // Warm the key the way the scheduled warmer does, so this measures a
        // normal request rather than a cold boot.
        HomePageCache::linkTypes();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->get('/')->assertOk();

        $planQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, '"plans"') || str_contains($sql, '`plans`')
        ));

        $this->assertSame(
            [],
            $planQueries,
            'the homepage\'s first response is querying the plan catalogue for a '
            . 'section that shows no prices: ' . implode(' | ', $planQueries)
        );
    }

    /**
     * Only the classic design server-renders. The other six defer everything
     * below the hero, as they always have.
     *
     * Each of those designs tells a shorter, keyword-focused story of its
     * own. Prepending the classic page's three sections to one of them would
     * put two openings on one page.
     */
    public function test_the_other_designs_still_defer_everything(): void
    {
        foreach (array_keys(HomeController::DESIGNS) as $design) {
            if ($design === 'classic') {
                continue;
            }

            AppSetting::put(HomeController::DESIGN_SETTING_KEY, $design);

            $this->assertNull(
                HomeController::activeDesignAboveFold(),
                "the '{$design}' design has acquired server-rendered sections"
            );

            $this->assertStringNotContainsString(
                'id="create"',
                $this->initialHomepageHtml(),
                "the '{$design}' design is rendering the classic page's showcase above "
                . 'its own opening'
            );
        }

        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'classic');
    }

    /**
     * A cache miss on the link-type key does not empty the showcase.
     *
     * Before this change the list came from the deferred fragment's payload,
     * where an empty result cost a section nobody had scrolled to yet. It is
     * in the first response now, so the fallback has to hold.
     */
    public function test_the_showcase_falls_back_to_defaults_rather_than_rendering_empty(): void
    {
        $this->assertNotEmpty(
            HomePageCache::linkTypes(),
            'the link-type accessor can return an empty list, which renders the '
            . 'homepage\'s main grid as a heading above nothing'
        );

        // The card's DESCRIPTION, not its name: "Short Link" also appears in
        // the header navigation, so asserting the name passes against a page
        // with no showcase on it at all.
        $this->assertStringContainsString(
            SitePagesContent::homeLinkTypesDefault()[0]['desc'],
            $this->initialHomepageHtml(),
            'the showcase is not rendering its default cards'
        );
    }
}
