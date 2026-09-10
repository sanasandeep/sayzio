<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's document outline and its images.
 *
 * Audited against the live page, and the meta layer came back clean: title 55
 * characters, description 149, canonical, the og and twitter tags, lang, two
 * JSON-LD blocks, and an alt on all 64 images. Two things were not:
 *
 *   - the outline opened with six H3s BEFORE the H1, with no H2 above them.
 *     They were the sign-in modal's slide captions -- the modal renders near
 *     the top of every marketing page and stays hidden, so six slide headlines
 *     were the first six headings a crawler read;
 *   - 34 of the 64 images had no width or height, so the browser could not
 *     reserve their boxes and every row they sat in reflowed as they landed.
 *
 * Both are the kind of thing that comes back the next time someone adds a
 * card, so they are guarded rather than just fixed.
 */
class HomepageDocumentOutlineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The homepage in document order: the shell, then the fragment its loader
     * fetches into it. Neither half is the page on its own -- the H1 is in the
     * shell and every H2 is in the fragment.
     *
     * Parsed as two documents rather than one concatenated string. The shell
     * is a complete document, <head> and all, and gluing the fragment onto it
     * and handing DOMDocument the result gives a second <html> inside the
     * first; it recovers by discarding, and what it discarded was the hero --
     * so the first version of this test reported the page had no H1 at all.
     *
     * @return list<\DOMXPath>
     */
    private function documents(): array
    {
        $out = [];

        foreach ([$this->get('/'), $this->get('/home/sections')] as $response) {
            $html = $response->assertOk()->getContent();

            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $out[] = new \DOMXPath($doc);
        }

        return $out;
    }

    /**
     * Every match, both halves, in page order -- skipping anything inside a
     * <template>.
     *
     * Template content is inert: it is not in the document a crawler reads,
     * and querySelectorAll does not descend into it either. The
     * expandable-card detail panels live in templates, so counting their
     * headings would report an outline nobody has.
     *
     * Excluded with an XPath ancestor test rather than by stripping
     * `<template>...</template>` from the HTML first, which is what the first
     * version did. The shell contains a literal `<template` inside a script
     * string, so the non-greedy strip started there and ran to the next real
     * `</template>` -- taking the hero, and its H1, with it. The test then
     * reported the page had no H1 at all, which was true of what it was
     * looking at and of nothing else.
     *
     * @return list<\DOMElement>
     */
    private function nodes(string $xpath): array
    {
        $out = [];

        foreach ($this->documents() as $doc) {
            foreach ($doc->query($xpath) as $node) {
                $out[] = $node;
            }
        }

        return $out;
    }

    /** @return list<array{level:int,text:string}> in document order */
    private function headings(): array
    {
        $out = [];

        foreach ($this->nodes('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(ancestor::template)]') as $node) {
            $out[] = [
                'level' => (int) substr($node->nodeName, 1),
                'text' => trim(preg_replace('/\s+/', ' ', $node->textContent)),
            ];
        }

        return $out;
    }

    public function test_the_page_has_exactly_one_h1(): void
    {
        $h1s = array_values(array_filter($this->headings(), fn ($h) => $h['level'] === 1));

        $this->assertCount(1, $h1s, 'a page with two H1s has no main heading: ' . implode(' | ', array_column($h1s, 'text')));
    }

    /**
     * Nothing outranks the H1 by appearing before it. This is the one that was
     * broken, and by a partial that has nothing to do with the homepage.
     */
    public function test_no_heading_comes_before_the_h1(): void
    {
        $headings = $this->headings();

        $before = [];

        foreach ($headings as $h) {
            if ($h['level'] === 1) {
                break;
            }

            $before[] = 'h' . $h['level'] . ' "' . mb_substr($h['text'], 0, 46) . '"';
        }

        $this->assertSame([], $before, sprintf(
            "These headings come before the page's H1.\n\n"
            . "A heading in hidden chrome is still a heading in the outline -- the sign-in\n"
            . "modal renders near the top of the document on every marketing page, and its\n"
            . "six slide captions were the first six headings on this one. If the text is a\n"
            . "caption rather than a section title, it should not be a heading tag; the\n"
            . "slide's own aria-label already announces it.\n\n%d found:\n  %s",
            count($before),
            implode("\n  ", $before)
        ));
    }

    /**
     * No level skips: an H4 under an H2 tells a crawler there is a missing
     * section between them.
     */
    public function test_the_outline_never_skips_a_level(): void
    {
        $skips = [];
        $previous = 0;

        foreach ($this->headings() as $h) {
            if ($previous > 0 && $h['level'] > $previous + 1) {
                $skips[] = sprintf('h%d -> h%d at "%s"', $previous, $h['level'], mb_substr($h['text'], 0, 46));
            }

            $previous = $h['level'];
        }

        $this->assertSame([], $skips, sprintf(
            "The outline jumps a level here. Use the next level down, or -- if the text is\n"
            . "not a section title at all -- do not use a heading tag.\n\n%d found:\n  %s",
            count($skips),
            implode("\n  ", $skips)
        ));
    }

    /**
     * Every image reserves its box.
     *
     * The brand logo is the interesting case: it renders eight times here, and
     * its URL is host-aware, so its size is read from the file rather than
     * hardcoded (DomainBranding::intrinsicSize). A logo we cannot measure gets
     * no attributes rather than the platform logo's proportions, which would
     * reserve the wrong box -- worse than reserving none.
     */
    public function test_every_image_states_its_intrinsic_size(): void
    {
        $missing = [];

        foreach ($this->nodes('//img[not(ancestor::template)]') as $img) {
            /** @var \DOMElement $img */
            if ($img->getAttribute('width') !== '' && $img->getAttribute('height') !== '') {
                continue;
            }

            $src = $img->getAttribute('src');

            // A logo served from somewhere we cannot measure is the documented
            // exception, and only that.
            if ($src !== '' && ! str_starts_with($src, '/') && ! str_contains($src, '://' . parse_url(config('app.url'), PHP_URL_HOST))) {
                continue;
            }

            $missing[] = ($src === '' ? '(no src)' : basename($src)) . '  ' . mb_substr($img->getAttribute('alt'), 0, 30);
        }

        $this->assertSame([], $missing, sprintf(
            "These images have no width/height, so the browser cannot reserve their box and\n"
            . "the row they sit in reflows when they land. Use the file's INTRINSIC size, not\n"
            . "the rendered one -- CSS still controls how big it draws; the attributes only\n"
            . "give the aspect ratio.\n\n%d found:\n  %s",
            count($missing),
            implode("\n  ", $missing)
        ));
    }

    /** Alt on everything. It was already true; keeping it true is free. */
    public function test_every_image_has_an_alt_attribute(): void
    {
        $missing = [];

        foreach ($this->nodes('//img[not(ancestor::template)]') as $img) {
            /** @var \DOMElement $img */
            if (! $img->hasAttribute('alt')) {
                $missing[] = basename($img->getAttribute('src'));
            }
        }

        $this->assertSame([], $missing, 'images with no alt attribute: ' . implode(', ', $missing));
    }

    /**
     * The meta layer, which the audit found clean. Guarded so it stays that
     * way rather than because it is broken.
     */
    public function test_the_meta_layer_is_intact(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Every check below matches first and asserts on the RESULT, never
        // with assertMatchesRegularExpression against $html. PHPUnit puts the
        // full subject in a failing message and runs it through its exporter,
        // and the homepage is 700KB: the first version of this test did not
        // report a failure, it hung, and had to be killed. A boolean says the
        // same thing in a line.
        $has = fn (string $pattern): bool => (bool) preg_match($pattern, $html);

        preg_match('#<title>(.*?)</title>#is', $html, $t);
        $this->assertNotEmpty($t, 'no <title>');

        $title = html_entity_decode($t[1]);
        $this->assertGreaterThan(20, mb_strlen($title), "title too short to say anything: {$title}");
        $this->assertLessThanOrEqual(65, mb_strlen($title), "title will be truncated in results: {$title}");

        preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $d);
        $this->assertNotEmpty($d, 'no meta description');

        $description = html_entity_decode($d[1]);
        $this->assertGreaterThan(80, mb_strlen($description), 'the description is too short to be used');
        $this->assertLessThanOrEqual(170, mb_strlen($description), 'the description will be truncated');

        $this->assertTrue($has('/<link[^>]+rel="canonical"/i'), 'no canonical');
        $this->assertTrue($has('/<html[^>]+lang="/i'), 'no lang on <html>');

        foreach (['og:title', 'og:description', 'og:url', 'og:type'] as $property) {
            $this->assertTrue(
                $has('/<meta[^>]+property="' . preg_quote($property, '/') . '"/i'),
                "no {$property}"
            );
        }

        // og:image is conditional on a share image being configured, so it
        // cannot be required of a fresh database. What CAN be required is that
        // the card type follows it: a summary_large_image card with no image
        // renders as an empty box on every platform that reads it.
        $hasImage = $has('/<meta[^>]+property="og:image"/i');
        preg_match('/<meta[^>]+name="twitter:card"[^>]+content="([^"]*)"/i', $html, $card);

        $this->assertNotEmpty($card, 'no twitter:card');
        $this->assertSame(
            $hasImage ? 'summary_large_image' : 'summary',
            $card[1],
            'the twitter card type and the presence of og:image disagree'
        );
    }
}
