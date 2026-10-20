<?php

namespace Tests\Feature\AI;

use App\Modules\User\Models\CompetitorTeardown;
use App\Services\AI\CompetitorTeardownService;
use App\Services\Biolink\AiBiolinkBuilderService;
use Tests\TestCase;

/**
 * "Build me a better version" quotes a price before it is pressed.
 *
 * It did not. The card showed the coin balance and no cost, which is the
 * half that does not decide anything -- the same gap PR #464 closed on the
 * teardown itself.
 *
 * The fix is deliberately not another AiCostEstimator case. That class's own
 * docblock says features owning an accurate input-specific estimate keep
 * using theirs, and the builder does: estimateCredits() composes the real
 * prompt (block catalogue, plan multiplier) and adds the image-generation
 * fallback, which applies here because buildBetterVersion() never supplies
 * images. So estimateBuild() forwards to it with the exact description the
 * build will send.
 *
 * These assert the forwarding, not the arithmetic. Asserting a coin figure
 * would assert the test environment: estimateChatCoins() returns 0 whenever
 * AiEngineSettings is disabled, which it is on a fresh test database, so
 * "coins > 0" cannot tell a working estimate from an absent one. That trap
 * already cost a rewrite once in CompetitorTeardownEstimateTest.
 */
class CompetitorTeardownBuildEstimateTest extends TestCase
{
    private function teardown_(array $extracted = []): CompetitorTeardown
    {
        $t = new CompetitorTeardown();
        $t->status    = 'completed';
        $t->competitor_url = 'https://example.com/@rival';
        $t->analysis  = [
            'summary'          => 'Decent but unfocused.',
            'weaknesses'       => ['No clear primary CTA'],
            'missing_elements' => ['No social proof'],
        ];
        $t->extracted = $extracted;

        return $t;
    }

    private function palette(): array
    {
        return ['palette' => [
            'theme_color' => '#eceef1',
            'colors'      => ['#000000', '#ffffff'],
            'roles'       => ['background' => '#eceef1', 'button_bg' => '#ffffff'],
        ]];
    }

    public function test_the_original_style_is_priced_with_no_brand_direction(): void
    {
        $this->mock(AiBiolinkBuilderService::class, function ($mock) {
            $mock->shouldReceive('estimateCredits')
                ->once()
                ->withArgs(function ($user, $description, $links, $images, $files, $grounding, $brandDirectives) {
                    // The real description the build will send...
                    $this->assertStringContainsString('https://example.com/@rival', $description);
                    $this->assertStringContainsString('No clear primary CTA', $description);
                    // ...with nothing supplied, which is what makes the
                    // builder's image-generation fallback part of the quote.
                    $this->assertSame([], $links);
                    $this->assertSame([], $images);
                    $this->assertSame([], $files);
                    $this->assertSame('', $brandDirectives);

                    return true;
                })
                ->andReturn(31);
        });

        $service = $this->app->make(CompetitorTeardownService::class);

        $this->assertSame(31, $service->estimateBuild(
            new \App\Modules\User\Models\User(),
            $this->teardown_($this->palette()),
            CompetitorTeardownService::STYLE_ORIGINAL,
        ));
    }

    public function test_the_match_style_is_priced_with_the_palette_included(): void
    {
        $this->mock(AiBiolinkBuilderService::class, function ($mock) {
            $mock->shouldReceive('estimateCredits')
                ->once()
                ->withArgs(function ($user, $description, $links, $images, $files, $grounding, $brandDirectives) {
                    $this->assertStringContainsString('#eceef1', $brandDirectives);
                    $this->assertStringContainsString('Page background', $brandDirectives);

                    return true;
                })
                ->andReturn(34);
        });

        $service = $this->app->make(CompetitorTeardownService::class);

        $this->assertSame(34, $service->estimateBuild(
            new \App\Modules\User\Models\User(),
            $this->teardown_($this->palette()),
            CompetitorTeardownService::STYLE_MATCH,
        ));
    }

    public function test_an_unfinished_teardown_is_not_priced_at_all(): void
    {
        // describeForBuilder() needs an analysis; quoting from a teardown
        // without one would price an empty prompt.
        $this->mock(AiBiolinkBuilderService::class, function ($mock) {
            $mock->shouldNotReceive('estimateCredits');
        });

        $t = $this->teardown_();
        $t->status = 'processing';

        $service = $this->app->make(CompetitorTeardownService::class);

        $this->assertSame(0, $service->estimateBuild(new \App\Modules\User\Models\User(), $t));
    }
}
