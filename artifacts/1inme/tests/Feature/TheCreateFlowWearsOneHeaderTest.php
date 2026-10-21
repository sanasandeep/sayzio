<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every other page in the product wears the page-hero card. None of the
 * twelve Create Link type pages did. Each hand-rolled its own header --
 * a back arrow, an <h1>, and the Choose/Set up row -- and ten of those
 * were byte-identical copies of each other. The other two had drifted to
 * a different pattern entirely, with breadcrumbs and no step row at all.
 *
 * They also disagreed about width: eight at max-w-2xl, three at
 * max-w-3xl, and the type picker at none.
 *
 * These tests render all twelve rather than reading their source, because
 * the interesting failure is a runtime one. A shared partial that throws
 * on an undefined variable, or silently drops its chips, still looks
 * perfectly correct in the Blade.
 */
class TheCreateFlowWearsOneHeaderTest extends TestCase
{
    use RefreshDatabase;

    /** Route name per create page. A thirteenth type belongs here too. */
    public static function createPages(): array
    {
        return [
            'short link'   => ['user.links.url.create'],
            'event'        => ['user.links.ics.create'],
            'contact card' => ['user.links.vcf.create'],
            'bio page'     => ['user.links.biolink.create'],
            'file share'   => ['user.links.file.create'],
            'reviews'      => ['user.links.reviews.create'],
            'resume'       => ['user.links.resume.create'],
            'paid page'    => ['user.links.paid-page.create'],
            'brand kit'    => ['user.links.brand-kit.create'],
            'updates'      => ['user.links.updates.create'],
            'text page'    => ['user.links.text.create'],
            'calendar'     => ['user.calendars.create'],
        ];
    }

    private function owner(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    #[DataProvider('createPages')]
    public function test_the_page_wears_the_header_card(string $route): void
    {
        $html = (string) $this->actingAs($this->owner())
            ->get(route($route, [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('page-hero', $html, 'no hero card on this create page');
        $this->assertStringContainsString('hero-chip', $html, 'the hero rendered without its chips');
    }

    #[DataProvider('createPages')]
    public function test_the_page_still_says_which_step_you_are_on(string $route): void
    {
        // The step state moved from a hand-written row into the hero's
        // chip slot. Moving it must not mean losing it: knowing you are
        // half way through a two-step flow is the whole point of it.
        $html = (string) $this->actingAs($this->owner())
            ->get(route($route, [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Step 2 of 2', $html, 'the step state is gone from this page');
        $this->assertStringContainsString('Change type', $html, 'no way back to the type picker');
    }

    #[DataProvider('createPages')]
    public function test_the_old_hand_rolled_header_is_gone(string $route): void
    {
        $html = (string) $this->actingAs($this->owner())
            ->get(route($route, [], false))
            ->assertOk()
            ->getContent();

        // Match the MARKUP, not the bare class name. The first version of
        // this assertion looked for "create-progress" anywhere in the
        // response and failed on ten of twelve pages -- not because the
        // old header survived, but because `.create-progress` is a CSS
        // rule in create-premium-style, inlined as a <style> block on
        // exactly those ten. The two that passed were the two that do not
        // include that partial, which is what gave the mistake away.
        //
        // Leaving the old row in place alongside the hero would show the
        // step state twice, which is how a half-finished migration ships.
        $this->assertStringNotContainsString('class="create-progress"', $html, 'this page still renders the old step row');
        $this->assertStringNotContainsString('class="step-dot"', $html, 'this page still renders the old step dots');
    }

    public function test_every_create_view_uses_the_shared_header(): void
    {
        // Source-level, and deliberately so: this is the one that catches
        // a THIRTEENTH type page being added without the header, which no
        // amount of rendering the current twelve can do.
        foreach (glob(resource_path('views/user/links/create-*.blade.php')) as $file) {
            $this->assertStringContainsString(
                "user.partials.create-step-header",
                file_get_contents($file),
                basename($file) . ' does not use the shared create header'
            );
        }
    }

    public function test_no_create_page_pins_its_own_width(): void
    {
        // They disagreed three ways before. The container is now the
        // layout's business, not each page's.
        foreach (glob(resource_path('views/user/links/create-*.blade.php')) as $file) {
            $src = file_get_contents($file);
            $at  = strpos($src, "@section('content')");
            $this->assertNotFalse($at, basename($file) . ' has no content section');

            // The page container is the first div after the content
            // section opens; anything deeper may legitimately constrain
            // itself.
            $this->assertTrue(
                (bool) preg_match('~<div\b[^>]*>~', $src, $m, 0, $at),
                basename($file) . ' has no container div'
            );
            $this->assertStringNotContainsString(
                'max-w-',
                $m[0],
                basename($file) . ' still pins its own page width: ' . $m[0]
            );
        }
    }

    public function test_the_header_icons_all_exist_in_this_font_awesome_build(): void
    {
        // The icons here are passed as Blade array values, not class
        // attributes, so the project-wide icon guard does not see them --
        // and a Pro-only name renders as blank space with no error.
        $sheets = glob(public_path('css/vendor/fontawesome-free-*/css/all.min.css'));
        $this->assertNotEmpty($sheets, 'no bundled Font Awesome stylesheet found');
        preg_match_all('~\.(fa-[a-z0-9-]+):before~', file_get_contents($sheets[0]), $m);
        $available = array_unique($m[1]);
        $this->assertGreaterThan(1000, count($available));

        $files = array_merge(
            glob(resource_path('views/user/links/create-*.blade.php')),
            [resource_path('views/user/partials/create-step-header.blade.php')]
        );

        $checked = 0;

        foreach ($files as $file) {
            preg_match_all("~'icon'\s*=>\s*'(fa-[a-z0-9-]+)'~", file_get_contents($file), $icons);

            foreach (array_unique($icons[1]) as $icon) {
                $checked++;
                $this->assertContains(
                    $icon,
                    $available,
                    basename($file) . " asks for {$icon}, which this build cannot draw"
                );
            }
        }

        $this->assertGreaterThanOrEqual(12, $checked, 'parsed too few icons to be checking anything');
    }
}
