<?php

namespace Tests\Feature;

use App\Services\AI\AiCostEstimator;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiFeatureCatalog;
use App\Services\AI\CompetitorPageFetcher;
use App\Services\AI\CompetitorTeardownService;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The teardown's coin estimate has to track the job it is quoting for.
 *
 * AiCostEstimator's whole contract is that "every estimate mirrors the real
 * charge path". For competitor_teardown that estimate is a fixed worst case
 * built from two things it does not own: the service's output ceiling, and the
 * caps CompetitorPageFetcher puts on the page content it sends.
 *
 * Both live in other classes. If either moves -- a bigger text excerpt, more
 * CTA strings, a higher max_tokens -- the quoted price silently stops matching
 * the charge, and the first person to notice is a creator whose wallet went
 * down by more than the badge said. So the numbers are pinned here, with the
 * arithmetic written out, and this test fails when the inputs change rather
 * than when the output is wrong.
 */
class CompetitorTeardownEstimateTest extends TestCase
{
    use RefreshDatabase;

    /** The overhead constant used in AiCostEstimator for this feature. */
    private const QUOTED_OVERHEAD_TOKENS = 3000;

    private const QUOTED_MAX_OUTPUT_TOKENS = 1400;

    public function test_the_feature_is_one_the_estimator_actually_handles(): void
    {
        $this->assertContains(
            CompetitorTeardownService::FEATURE,
            AiFeatureCatalog::FEATURES,
            'competitor_teardown is not a known AI feature'
        );

        $this->assertContains(
            CompetitorTeardownService::FEATURE,
            AiEngineSettings::FEATURES,
            'competitor_teardown has no model mapping, so featureModel() would fall back'
        );
    }

    /**
     * An unhandled feature falls through AiCostEstimator::estimate() to
     * ['coins' => 0], and the shared badge hides itself on a zero. That is the
     * state this feature was in: a cost endpoint that answered "nothing".
     *
     * This asserts the CALL rather than the coins. estimateChatCoinsForText()
     * returns 0 whenever AiEngineSettings::isEnabled() is false, which it is
     * in a fresh test database -- so "coins > 0" cannot tell a handled feature
     * from an unhandled one, and an earlier version of this test failed for
     * exactly that reason. Observing the arguments pins the branch and the two
     * numbers at once, whatever the engine is set to.
     */
    public function test_the_estimator_prices_the_teardown_with_the_documented_budget(): void
    {
        $user = $this->teardownUser();

        $this->mock(OpenAiService::class, function ($m) {
            $m->shouldReceive('estimateChatCoinsForText')
                ->once()
                ->withArgs(function ($model, $text, $overhead, $maxOut, $u) {
                    // The URL field must not feed the quote: a fixed price is
                    // the whole point, so the text argument stays empty.
                    return $text === ''
                        && $overhead === self::QUOTED_OVERHEAD_TOKENS
                        && $maxOut === self::QUOTED_MAX_OUTPUT_TOKENS;
                })
                ->andReturn(37);
        });

        $result = app(AiCostEstimator::class)->estimate($user, CompetitorTeardownService::FEATURE, 'https://example.com/@someone');

        $this->assertSame('fixed', $result['mode'], 'the teardown price must not be driven by the URL field');
        $this->assertSame(37, $result['coins'], 'the estimator did not take the competitor_teardown branch');
    }

    /** The service's output ceiling is what the estimate quotes against. */
    public function test_the_output_ceiling_still_matches_the_service(): void
    {
        $actual = (new ReflectionClass(CompetitorTeardownService::class))
            ->getConstant('MAX_OUTPUT_TOKENS');

        $this->assertSame(
            self::QUOTED_MAX_OUTPUT_TOKENS,
            $actual,
            'CompetitorTeardownService::MAX_OUTPUT_TOKENS changed; update the estimator and this test together'
        );
    }

    /**
     * The prompt overhead is the fetcher's own caps added up. Written out so a
     * change to any cap shows which line moved.
     */
    public function test_the_prompt_overhead_still_covers_the_fetcher_caps(): void
    {
        $this->assertSame(1_500_000, CompetitorPageFetcher::MAX_BYTES, 'fetcher byte cap changed');

        // Caps the fetcher applies before the payload is built, in characters.
        $worstCaseChars = 1455   // system prompt
            + 200                // final_url
            + 300                // title
            + 500                // meta_description
            + (6 * 160)          // h1, collectText limit 6
            + (10 * 160)         // h2, collectText limit 10
            + (15 * 160)         // cta_texts, array_slice 15
            + 3000               // text_excerpt
            + 360;               // JSON keys, counts, lead-in sentence

        // Roughly four characters to a token.
        $impliedTokens = (int) ceil($worstCaseChars / 4);

        $this->assertLessThanOrEqual(
            self::QUOTED_OVERHEAD_TOKENS,
            $impliedTokens,
            "the fetcher now sends more than the estimate budgets for ({$impliedTokens} tokens implied vs ".self::QUOTED_OVERHEAD_TOKENS.' quoted); raise the overhead in AiCostEstimator'
        );
    }

    private function teardownUser()
    {
        $user = \App\Modules\User\Models\User::factory()->create();

        return $user;
    }
}
