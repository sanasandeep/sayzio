<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the link-type card endpoint behind the home page's expand control.
 *
 * The endpoint takes a slug from the URL and the response embeds an iframe,
 * so the thing worth locking is that the slug is matched against the known
 * link types rather than trusted: an unknown one must 404, not render a
 * frame pointing wherever the caller asked.
 */
class HomeLinkTypeCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_known_link_type_renders_its_card(): void
    {
        $this->get(route('home.link-type', ['slug' => 'short-link']))
            ->assertOk()
            ->assertSee('Short Link', false)
            ->assertSee('How you would use it', false);
    }

    public function test_an_unknown_slug_is_not_rendered(): void
    {
        $this->get(route('home.link-type', ['slug' => 'not-a-link-type']))->assertNotFound();
    }

    public function test_the_slug_cannot_point_the_frame_somewhere_else(): void
    {
        // The route pattern rejects anything but a plain slug, so a path or a
        // host can never reach the controller in the first place.
        $this->get('/home/link-type/..%2F..%2Fevil')->assertNotFound();
        $this->get('/home/link-type/evil.test')->assertNotFound();
    }

    public function test_every_link_type_on_the_page_has_a_card(): void
    {
        $types = \App\Modules\Common\Support\SitePagesContent::homeLinkTypesDefault();
        $this->assertNotEmpty($types);

        foreach ($types as $type) {
            $slug = \Illuminate\Support\Str::slug((string) ($type['name'] ?? ''));
            $this->get(route('home.link-type', ['slug' => $slug]))
                ->assertOk()
                ->assertSee((string) $type['name'], false);
        }
    }

    public function test_usage_lines_cover_every_type(): void
    {
        foreach (\App\Modules\Common\Support\SitePagesContent::homeLinkTypesDefault() as $type) {
            $this->assertNotSame(
                '',
                \App\Modules\Common\Support\LinkTypeUsage::forName((string) $type['name']),
                'No usage line for ' . $type['name']
            );
        }
    }
}
