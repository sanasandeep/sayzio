<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\CompanyIdentity;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public copy must not carry a typed-in link-type count.
 *
 * It had drifted three ways at once: the catalogue shipped 24 types while
 * SitePageController said "all 18 link types" and SitePagesContent said
 * "all 19". The count is now derived and reaches copy through the
 * {{link_type_count}} token.
 */
class TheLinkTypeCountIsNeverTypedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalogue_is_the_source_of_the_number(): void
    {
        $this->assertSame(
            count(LinkTypeCategories::types()),
            LinkTypeCategories::catalogueCount(),
            'catalogueCount() must count the catalogue, not a constant.'
        );

        // visibleCount() is what a user can pick, so it can be lower than the
        // catalogue (the Events module hides the ics type when it is off), but
        // never higher.
        $this->assertLessThanOrEqual(
            LinkTypeCategories::catalogueCount(),
            LinkTypeCategories::visibleCount()
        );
    }

    public function test_the_token_resolves_to_the_catalogue_count(): void
    {
        AppSetting::put(LinkTypeCategories::MARKETING_COUNT_SETTING, null);

        $expected = (string) LinkTypeCategories::catalogueCount();

        $this->assertSame($expected, CompanyIdentity::tokens()['link_type_count'] ?? null);
        $this->assertSame(
            "Sayzio has {$expected} link types.",
            CompanyIdentity::substitute('Sayzio has {{link_type_count}} link types.')
        );
    }

    public function test_an_admin_override_wins_but_junk_does_not(): void
    {
        AppSetting::put(LinkTypeCategories::MARKETING_COUNT_SETTING, 31);
        $this->assertSame(31, LinkTypeCategories::marketingCount());

        foreach ([null, '', '0', '-4', 'twelve'] as $junk) {
            AppSetting::put(LinkTypeCategories::MARKETING_COUNT_SETTING, $junk);
            $this->assertSame(
                LinkTypeCategories::catalogueCount(),
                LinkTypeCategories::marketingCount(),
                'A blank or nonsense override must fall back to the catalogue, not print itself.'
            );
        }
    }

    /**
     * The scan. Shipped copy must quote the token, never a literal.
     */
    public function test_no_shipped_copy_states_a_link_type_count(): void
    {
        $offenders = [];
        foreach ($this->copySources() as $label => $text) {
            foreach ($this->literalCounts($text) as $hit) {
                $offenders[] = "{$label}: \"{$hit}\"";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Public copy states a link-type count as a literal. Use {{link_type_count}} so it "
            . "cannot go stale:\n  " . implode("\n  ", $offenders)
        );
    }

    /**
     * The control. A scan that matches nothing would pass the test above for
     * the wrong reason, so prove the detector fires on a planted literal and
     * stays quiet on the token and on unrelated numbers.
     */
    public function test_the_detector_actually_detects(): void
    {
        $this->assertNotEmpty(
            $this->literalCounts('Everything you get: all 18 link types, plus analytics.'),
            'A planted literal count was not detected - the scan matches nothing.'
        );
        $this->assertNotEmpty(
            $this->literalCounts('A tour of all 19 link types.'),
            'A planted literal count was not detected.'
        );
        $this->assertSame(
            [],
            $this->literalCounts('Everything you get: all {{link_type_count}} link types.'),
            'The token must not be reported as a literal.'
        );
        $this->assertSame(
            [],
            $this->literalCounts('Design QR codes with 16 styles and 4 frames.'),
            'Counts of other things must not be reported.'
        );
    }

    /** @return array<int, string> */
    private function literalCounts(string $text): array
    {
        preg_match_all('/\b\d{1,3}\+?\s+link\s+types?\b/i', $text, $m);

        return $m[0];
    }

    /** @return array<string, string> */
    private function copySources(): array
    {
        $out = [];

        foreach (SitePagesContent::richDefaults() as $slug => $page) {
            $out["SitePagesContent[{$slug}].meta_description"] = (string) ($page['meta_description'] ?? '');
            foreach (($page['sections'] ?? []) as $i => $section) {
                $out["SitePagesContent[{$slug}].sections.{$i}"] =
                    (string) ($section['heading'] ?? '') . ' ' . (string) ($section['body'] ?? '');
            }
        }

        // The controller's own inline defaults live in source, not in an array
        // this test can call, so read the file.
        $controller = app_path('Modules/Common/Controllers/SitePageController.php');
        if (is_file($controller)) {
            $out['SitePageController source'] = (string) file_get_contents($controller);
        }

        return $out;
    }
}
