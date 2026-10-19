<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\HomeController;
use App\Modules\Common\Support\HomeFaq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page's FAQ schema has to be in the response a crawler gets.
 *
 * The bug this guards: the FAQPage node was built correctly and emitted
 * inside home/partials/compact-conversion, which is part of the DEFERRED
 * fragment fetched after first paint. Everything about it was right except
 * the document it landed in, and nothing could see the difference -- the
 * schema validated, the questions were accurate, and a test that fetched
 * the fragment would have passed.
 *
 * So this asserts the thing that actually matters: GET / -- the initial
 * HTML, before any JS runs -- carries the FAQPage node, and the fragment
 * does not carry a second copy.
 */
class HomeFaqSchemaIsCrawlableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_initial_html_carries_the_faq_schema(): void
    {
        $nodes = $this->schemaNodesFrom($this->get('/')->assertOk()->getContent());

        $faq = collect($nodes)->firstWhere('@type', 'FAQPage');
        $this->assertNotNull(
            $faq,
            'the home page ships no FAQPage node in its initial HTML, so the FAQ cannot earn a rich result'
        );

        $asked = array_column($faq['mainEntity'] ?? [], 'name');
        $this->assertSame(
            array_column(HomeFaq::CLASSIC, 'q'),
            $asked,
            'the schema questions must be the questions the page renders'
        );

        foreach ($faq['mainEntity'] as $entity) {
            $this->assertNotEmpty(
                trim((string) ($entity['acceptedAnswer']['text'] ?? '')),
                'every question needs a non-empty answer or Google drops the whole node'
            );
        }
    }

    /** Two FAQPage nodes on one URL is worse than one in the wrong place. */
    public function test_the_deferred_fragment_does_not_emit_a_second_copy(): void
    {
        $fragment = $this->get(route('home.sections'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'FAQPage',
            $fragment,
            'the deferred fragment still emits its own FAQPage; the head already has one'
        );
    }

    /** The visible list and the schema read from one source, so they cannot drift. */
    public function test_the_rendered_questions_match_the_schema(): void
    {
        $fragment = $this->get(route('home.sections'))->assertOk()->getContent();

        foreach (HomeFaq::CLASSIC as $row) {
            $this->assertStringContainsString(
                e($row['q']),
                $fragment,
                "the page does not render a question its schema claims: {$row['q']}"
            );
        }
    }

    /**
     * Only a design whose questions are registered gets head schema. The
     * short keyword designs carry their own FAQ copy, and attaching the
     * classic questions to them would describe content they never render.
     */
    public function test_a_design_with_its_own_faq_gets_no_head_schema(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'ai');
        $this->assertSame([], HomeFaq::forActiveDesign());

        $nodes = $this->schemaNodesFrom($this->get('/')->assertOk()->getContent());
        $this->assertNull(
            collect($nodes)->firstWhere('@type', 'FAQPage'),
            'the AI design keeps its own questions, so the head must not claim the classic ones'
        );
    }

    /** @return array<int,array> every node across every ld+json block */
    private function schemaNodesFrom(string $html): array
    {
        preg_match_all(
            '#<script[^>]+application/ld\+json[^>]*>([\s\S]*?)</script>#i',
            $html,
            $m
        );

        $nodes = [];
        foreach ($m[1] as $json) {
            $data = json_decode(trim($json), true);
            if (! is_array($data)) {
                continue;
            }
            foreach ($data['@graph'] ?? [$data] as $node) {
                if (is_array($node)) {
                    $nodes[] = $node;
                }
            }
        }

        return $nodes;
    }
}
