<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings hub wears the same header as everything else.
 *
 * It had hand-rolled its own: a bare `.page-hero` holding an icon and
 * two lines of text. That skipped `.page-hero-deco` and the card
 * ribbon -- which is the entire decorated treatment -- so the one hub
 * every account passes through was the only page without the header
 * design every other page has, INCLUDING its own sub-pages. API keys
 * and Sessions sit inside this same layout and already called the
 * shared partial correctly, which is what made the gap odd rather
 * than merely missing.
 *
 * The assertions go through a real request because the header is
 * assembled from a layout, a partial and a ribbon partial, and the
 * only way to know all three arrived is to look at what came back.
 */
class TheSettingsHubWearsTheSameHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        // An older onboarded_at keeps the onboarding gate from
        // answering 302 before any header is rendered.
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    public function test_the_settings_hub_renders_the_decorated_header(): void
    {
        $html = (string) $this->actingAs($this->owner())
            ->get(route('user.profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('page-hero', $html, 'No hero at all.');
        $this->assertStringContainsString(
            'page-hero-deco',
            $html,
            'The hero rendered without its decoration, which is the part that was missing.'
        );
        $this->assertStringContainsString('Settings', $html);
    }

    /**
     * The control. Every assertion above would pass on a page that
     * never used the shared partial at all, so one page that already
     * had the decorated header has to keep it.
     */
    public function test_a_page_that_already_had_it_still_has_it(): void
    {
        // All Links has called the shared partial since before this
        // change, so it is the page that proves the assertion above is
        // about the settings hub and not about the partial having
        // quietly stopped rendering its decoration for everyone.
        $html = (string) $this->actingAs($this->owner())
            ->get(route('user.links.index'))->assertOk()->getContent();

        $this->assertStringContainsString('page-hero-deco', $html);
    }

    /**
     * The hero is the one place on the page that did not say which
     * section you were in, while the tab strip two lines below it did.
     */
    public function test_the_hero_names_the_section_you_are_in(): void
    {
        $html = (string) $this->actingAs($this->owner())
            ->get(route('user.profile.edit'))->assertOk()->getContent();

        $heroStart = strpos($html, 'page-hero');
        $this->assertNotFalse($heroStart);
        $hero = substr($html, $heroStart, 4000);

        $this->assertStringContainsString(
            'hero-chip',
            $hero,
            'The hero carries a chip naming the active settings section.'
        );
        // And it names the section the tab strip says you are on, so
        // the chip cannot drift away from the tabs.
        $active = \App\Modules\User\Support\SettingsTabs::activeKey();
        $this->assertNotNull($active, 'This route resolves to no settings tab at all.');
        $label = \App\Modules\User\Support\SettingsTabs::visibleTabs()[$active]['label'] ?? null;
        $this->assertNotNull($label);
        $this->assertStringContainsString($label, $hero);
    }
}
