<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\TestCase;

/**
 * The code on /docs/api is code, not a picture of code.
 *
 * Every sample on that page exists to be copied into a terminal. They were
 * being escaped twice -- once by the endpoint component echoing its slot, then
 * again by x-doc-code echoing the slot it was handed -- so the page printed
 * `&#039;` and `&quot;` where the quotes belong, and the Copy button put that
 * on the clipboard. A curl command pasted from it does not run.
 *
 * This is the second guard, because the first fix was only half of one. The
 * custom request slots were corrected and confirmed with a grep for
 * `&amp;#039;` -- the single-quote form -- which came back empty and looked
 * like proof. The fifteen endpoints with no sample of their own take a
 * fallback whose Authorization header uses DOUBLE quotes, and those were still
 * doubled, live, for as long as it took someone to load the page and look.
 *
 * So this checks the rendered page for the escaped form of both quote
 * characters, and for the ampersand itself, which is what doubling actually
 * produces and what any future variant will produce too.
 */
class ApiDocsCodeSamplesAreCopyableTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;

    private function docsHtml(): string
    {
        return (string) $this->get('/docs/api')->assertOk()->getContent();
    }

    public function test_no_code_sample_shows_a_doubly_escaped_entity(): void
    {
        $html = $this->docsHtml();

        // `&amp;` followed by an entity name is the signature of a value that
        // went through htmlspecialchars twice. Nothing on a correctly rendered
        // page produces it.
        preg_match_all('/&amp;(#\d+|quot|apos|lt|gt|amp);/', $html, $found);

        $counts = array_count_values($found[1]);
        $report = [];

        foreach ($counts as $entity => $n) {
            $report[] = "&amp;{$entity};  ({$n})";
        }

        $this->assertSame([], $report, sprintf(
            "/docs/api is double-escaping its code samples.\n\n"
            . "Something echoed a value with `{{ }}` and then handed it to x-doc-code,\n"
            . "which escapes what it is given. Use `{!! !!}` inside a doc-code slot and let\n"
            . "doc-code do the single escape the markup needs.\n\n"
            . "Found:\n  %s",
            implode("\n  ", $report)
        ));
    }

    /**
     * And the samples have to be there to be judged.
     *
     * Every assertion above passes against a page with no code on it, and a
     * page with no code on it is its own failure.
     */
    public function test_the_page_actually_carries_code_samples(): void
    {
        $html = $this->docsHtml();

        $this->assertGreaterThan(
            20,
            substr_count($html, 'class="doc-code'),
            'the API docs page is not rendering its code blocks any more'
        );

        $this->assertSubjectContains(
            'curl ',
            $html,
            'the API docs page has no curl examples on it at all'
        );
    }

    /**
     * The fallback sample specifically, because that is the one that stayed
     * broken: fifteen endpoints declare no request example and take it.
     */
    public function test_the_default_curl_example_carries_real_quotes(): void
    {
        $html = $this->docsHtml();

        $this->assertSubjectContains(
            'Authorization: Bearer YOUR_TOKEN',
            $html,
            'the default curl example is gone from the API docs'
        );

        $this->assertSubjectDoesNotContain(
            '&amp;quot;Authorization',
            $html,
            'the default curl example is showing escaped quotes around the Authorization header'
        );
    }
}
