<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every homepage band on the page's own ground carries a section divider.
 *
 * The hairline between sections is what tells a reader one band has ended
 * before they have read a word of the next. It kept going missing, and the
 * reason was structural rather than careless: which sections got one was
 * decided by an id list inside home.blade.php's <style>, and
 *
 *   - half the bands live in their own partials (create-showcase, resume,
 *     dialer-contacts, forms, notifications) and nobody adding one thought
 *     to edit a list in a different file;
 *   - the "Grow" band had no id at all, so it could not be listed;
 *   - `#compare-legacy` had been replaced by the shared _compare partial,
 *     which renders as `#compare`, so its entry had silently stopped
 *     matching anything.
 *
 * The decision now lives in the markup as a `sec-rule` class on the section,
 * and this test is what keeps the set honest: a full-bleed band in a
 * homepage view either carries the class or is named in OPTED_OUT below,
 * with a reason. A new section that is neither fails here.
 *
 * Asserting on the Blade sources rather than the rendered page on purpose.
 * The homepage's lower half is fetched after load by JS, so a server-side
 * GET returns the shell without any of the bands this is about.
 */
class HomepageSectionDividerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bands that legitimately have no divider, and why.
     *
     * The rule is calibrated for the page's white/near-black ground. A band
     * that brings a ground of its own already announces itself by changing
     * colour, and a hairline sized for the page would either vanish on it or
     * cut across it.
     */
    private const OPTED_OUT = [
        'ai-zone'                 => 'wrapper for the whole AI zone; the bands inside it are not separate zones',
        'ai-suite'                => 'inside the AI zone, which reads as one band',
        'ai-marketing-strategist' => 'inside the AI zone',
        'whatsapp-agent'          => 'inside the AI zone',
        'ai-dashboard'            => 'inside the AI zone',
        'buzz'                    => 'sits on its own tinted ground',
        'pricing'                 => 'brings its own band ground and hairlines; a divider on top of that is one separator too many',
        'compare-legacy'          => 'dead code behind @if(false); the live band is the shared _compare partial',
        'hero'                    => 'the first band on the page: a divider needs something above it to divide from',
        'ai-hero'                 => 'inside the AI zone, and its opening band',
    ];

    /** Views that contribute full-bleed bands to the homepage. */
    private function homepageBandViews(): array
    {
        $paths = [resource_path('views/home/deferred-sections.blade.php')];

        foreach (glob(resource_path('views/home/partials/*.blade.php')) as $partial) {
            $paths[] = $partial;
        }

        sort($paths);

        return $paths;
    }

    /**
     * Sections that span the page: `py-*` vertical rhythm plus `relative`,
     * which is the shape every band on this page uses (and which the
     * divider's absolutely-positioned ::before depends on).
     */
    public function test_every_full_bleed_homepage_band_has_a_divider_or_an_explicit_reason(): void
    {
        $missing = [];

        foreach ($this->homepageBandViews() as $path) {
            $source = (string) file_get_contents($path);
            $short = str_replace(resource_path('views/'), '', $path);

            preg_match_all('/<section\b[^>]*>/i', $source, $tags, PREG_OFFSET_CAPTURE);

            foreach ($tags[0] as [$tag, $offset]) {
                if (! preg_match('/class="([^"]*)"/i', $tag, $c)) {
                    continue;
                }

                $classes = $c[1];

                // Only the page's own bands: full-width, with the vertical
                // rhythm the other bands use. A card or an inner <section>
                // is not a zone boundary.
                if (! preg_match('/\b(py|pt)-(16|20|24)\b/', $classes)) {
                    continue;
                }
                if (! str_contains($classes, 'relative')) {
                    continue;
                }
                if (str_contains($classes, 'sec-rule')) {
                    continue;
                }

                preg_match('/id="([^"]*)"/i', $tag, $idMatch);
                $id = $idMatch[1] ?? '';

                if ($id !== '' && array_key_exists($id, self::OPTED_OUT)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $missing[] = sprintf(
                    '%s:%d  id="%s"',
                    $short,
                    $line,
                    $id !== '' ? $id : '(none - give it one)'
                );
            }
        }

        $this->assertSame([], $missing, sprintf(
            "These homepage bands have no section divider.\n\n"
            . "Add `sec-rule` to the <section> class list, or -- if the band brings its own\n"
            . "ground and does not want a hairline -- add its id to OPTED_OUT in this test\n"
            . "with the reason. A band with no id needs one first, so it can be opted out or\n"
            . "linked to.\n\n%d found:\n  %s",
            count($missing),
            implode("\n  ", $missing)
        ));
    }

    /**
     * The class has to actually draw something. If the rule that styles
     * `.sec-rule::before` is ever renamed or dropped, every divider on the
     * page disappears at once and the test above would still pass.
     */
    public function test_the_divider_class_is_styled(): void
    {
        $home = (string) file_get_contents(resource_path('views/home.blade.php'));

        $this->assertStringContainsString('.sec-rule::before', $home, 'nothing draws the divider');
        $this->assertMatchesRegularExpression(
            '/\.sec-rule::before\s*\{[^}]*content:/s',
            $home,
            'the ::before needs a content property or it never renders'
        );
        $this->assertStringContainsString(
            'html.light-mode .sec-rule::before',
            $home,
            'the hairline needs a light-mode colour; the dark one is invisible on white'
        );
    }

    /** The opt-out list is documentation: an entry without a reason is not. */
    public function test_every_opt_out_states_a_reason(): void
    {
        foreach (self::OPTED_OUT as $id => $reason) {
            $this->assertNotSame('', trim($reason), "opt-out for #{$id} has no reason");
        }
    }
}
