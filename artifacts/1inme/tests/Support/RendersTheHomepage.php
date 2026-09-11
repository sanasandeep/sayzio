<?php

namespace Tests\Support;

/**
 * The classic homepage arrives in two responses, and most tests mean both.
 *
 * `GET /` is the initial HTML: header, hero, and — since the sections were
 * server-rendered for search engines — the proof band, the link-type
 * showcase and the audience section. `GET /home/sections` is everything
 * from "how it works" down, fetched by JavaScript after first paint.
 *
 * Which half a given section lives in is a performance and SEO decision that
 * has already moved once and may move again. A test that asserts a section's
 * COPY, or its markup, or that it renders at all, does not care which
 * response carried it — it cares what a visitor ends up looking at. Those
 * tests should call wholeHomepage(), so that moving a section between the
 * two halves is a deploy, not an afternoon of updating assertions.
 *
 * Use initialHomepageHtml() or deferredHomepageHtml() only when the test is
 * ABOUT the split: that a section is server-rendered for crawlers, that a
 * heavy demo is still deferred, that nothing renders in both halves at once.
 */
trait RendersTheHomepage
{
    /** The initial server response — what a crawler sees on its first pass. */
    protected function initialHomepageHtml(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    /** The fragment the loader fetches after first paint. */
    protected function deferredHomepageHtml(): string
    {
        return $this->get(route('home.sections'))->assertOk()->getContent();
    }

    /**
     * Both halves, in the order a visitor receives them.
     *
     * Concatenated, not merged: this is a haystack for string assertions,
     * not a parseable document (it has two <html> openings in it). Tests
     * that need a real DOM should parse one half.
     */
    protected function wholeHomepage(): string
    {
        return $this->initialHomepageHtml() . "\n" . $this->deferredHomepageHtml();
    }
}
