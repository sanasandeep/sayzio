<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\ShareButton;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "for all link in bio or any other pages or link
 * types where display is there, i want share button with qr code to
 * show same page as well as share link options like to whatsapp,
 * telegrams and others..... share button should be visible or not,
 * customizable options should be there in settings on that link.
 * default active".
 *
 * Most of this already existed -- inline in common/biolink.blade.php,
 * off by default, with its QR fetched from api.qrserver.com. Three
 * things follow, and this file is organised around them:
 *
 *  1. It only existed on Link in Bio. A menu, a resume, an event page
 *     and a review wall each render their own template, so a button
 *     living inside one of them could never appear on the others.
 *  2. Its QR came from a third party. Every view of a page carrying the
 *     button handed that page's URL to api.qrserver.com, in exchange for
 *     an image Sayzio already knows how to draw -- and a scan routed
 *     through someone else's host can never be counted.
 *  3. It was off unless switched on. "Default active" means an ABSENT
 *     setting is on. It does not mean overriding a creator who went and
 *     turned it off, and the difference between those two is the test
 *     that matters most here.
 */
class EveryPageWithADisplayCanBeSharedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'handle'       => 'h'.fake()->unique()->numerify('########'),
            'onboarded_at' => now(),
        ]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function link(string $type, array $settings = []): Link
    {
        return Link::create([
            'user_id'   => $this->user->id,
            'type'      => $type,
            'alias'     => 'a'.fake()->unique()->numerify('######'),
            'title'     => 'A page',
            'is_active' => true,
            'settings'  => $settings,
        ]);
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    /**
     * Whether the widget is actually ON the page.
     *
     * Not a bare search for "sz-share": the page-background CSS names
     * the class in a :not() so the button keeps its fixed position, and
     * that string is present whether the button renders or not.
     */
    private function hasButton(string $html): bool
    {
        return str_contains($html, 'class="sz-share at-');
    }

    // ===== 1. Default active =====

    /** A page nobody has configured shows the button. */
    public function test_a_page_that_was_never_configured_shows_the_button(): void
    {
        $this->assertTrue($this->hasButton($this->page($this->link('biolink'))));
    }

    /**
     * The one that matters. Someone who turned this off before today
     * must not find it switched back on.
     */
    public function test_a_creator_who_turned_it_off_keeps_it_off(): void
    {
        $link = $this->link('biolink', ['biolink' => ['share_button' => ['enabled' => false]]]);

        $this->assertFalse($this->hasButton($this->page($link)),
            '"default active" means an absent setting, not an override of an explicit no');
    }

    /** And resolve() is where that distinction lives. */
    public function test_absent_and_false_are_different_states(): void
    {
        $this->assertTrue(ShareButton::resolve([])['enabled']);
        $this->assertTrue(ShareButton::resolve(['share_button' => []])['enabled']);
        $this->assertFalse(ShareButton::resolve(['share_button' => ['enabled' => false]])['enabled']);
    }

    // ===== 2. Every page type with a display =====

    /**
     * The headline. Each of these renders its own template, and before
     * this none of them could carry the button.
     */
    public function test_the_menu_pages_carry_it(): void
    {
        foreach (['restaurant_menu', 'store_menu'] as $type) {
            $link = $this->link($type);
            $link->biolinkBlocks()->delete();

            if ($type === 'restaurant_menu') {
                RestaurantMenu::create([
                    'link_id' => $link->id, 'user_id' => $this->user->id,
                    'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
                ]);
            } else {
                \App\Modules\User\Models\StoreMenu::create([
                    'link_id' => $link->id, 'user_id' => $this->user->id,
                    'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
                ]);
            }

            $this->assertTrue($this->hasButton($this->page($link)),
                $type.' renders its own template and must still carry the button');
        }
    }

    public function test_a_resume_page_carries_it(): void
    {
        Resume::create([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => ['header' => ['name' => 'Sana Sandeep']],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
        ]);

        $html = $this->get('/'.$this->user->handle.'/resume')->assertOk()->getContent();

        $this->assertTrue($this->hasButton($html));
    }

    /**
     * A resume has two public URLs and only one of them has a Link in
     * scope, which is why its setting lives on the resume. Turning it
     * off must hold at the handle URL too.
     */
    public function test_a_resume_setting_applies_at_its_handle_url(): void
    {
        Resume::create([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => ['header' => ['name' => 'Sana Sandeep']],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
            'share_button'   => ['enabled' => false],
        ]);

        $html = $this->get('/'.$this->user->handle.'/resume')->assertOk()->getContent();

        $this->assertFalse($this->hasButton($html));
    }

    /** Redirects and downloads have no page to put a button on. */
    public function test_a_short_link_and_a_download_are_not_shareable(): void
    {
        foreach (['url', 'file', 'vcf'] as $type) {
            $this->assertFalse(ShareButton::supports($type),
                $type.' has no display, so there is nothing to attach a button to');
        }
    }

    // ===== 3. The QR is ours, and it is countable =====

    public function test_the_qr_is_rendered_by_sayzio_not_a_third_party(): void
    {
        $html = $this->page($this->link('biolink'));

        $this->assertStringNotContainsString('qrserver.com', $html,
            'every view was handing this page URL to a third party for an image we draw ourselves');
        $this->assertStringContainsString('/qr/render', $html);
    }

    /** A scan is distinguishable from a normal visit. */
    public function test_the_qr_carries_its_own_source_tag(): void
    {
        $html = $this->page($this->link('biolink'));

        $this->assertStringContainsString('src%3Dshare_qr', $html,
            'the QR target is URL-encoded inside the /qr/render link');
    }

    /** And so is each way of sharing it. */
    public function test_each_share_link_carries_its_own_tag(): void
    {
        $html = $this->page($this->link('biolink'));

        foreach (['share_whatsapp', 'share_telegram'] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
    }

    /**
     * The tag has to survive the round trip: a visit arriving with it is
     * recorded under that source, so the owner's stats separate a scan
     * from a tap.
     */
    public function test_a_visit_from_the_qr_is_recorded_as_a_scan(): void
    {
        $link = $this->link('biolink');

        $this->get('/'.$link->alias.'?src=share_qr')->assertOk();

        $this->assertDatabaseHas('link_clicks', [
            'link_id' => $link->id,
            'source'  => 'share_qr',
        ]);
    }

    /**
     * But only tags this feature actually issues. The source column is
     * one owners group by, so an open field would let a visitor invent
     * categories inside someone else's analytics.
     */
    public function test_an_invented_source_is_ignored(): void
    {
        $link = $this->link('biolink');

        $this->get('/'.$link->alias.'?src=totally_made_up')->assertOk();

        $this->assertDatabaseHas('link_clicks', ['link_id' => $link->id, 'source' => 'web']);
        $this->assertDatabaseMissing('link_clicks', ['link_id' => $link->id, 'source' => 'totally_made_up']);
    }

    /** Those tags read as words on the stats screen, not as slugs. */
    public function test_the_tags_have_readable_labels(): void
    {
        $labels = ShareButton::sourceLabels();

        $this->assertSame('QR scan', $labels['share_qr']);
        $this->assertSame('Shared via WhatsApp', $labels['share_whatsapp']);
        $this->assertSame(
            ShareButton::sourceTags(),
            array_keys($labels),
            'every tag this feature can write needs a label, or it renders as a slug'
        );
    }

    // ===== 4. The settings =====

    public function test_the_settings_card_is_on_the_link_settings_screen(): void
    {
        $link = $this->link('biolink');

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/advanced')->assertOk()->getContent();

        $this->assertStringContainsString('share_button[enabled]', $html);
        $this->assertStringContainsString('share_button[networks][]', $html);
    }

    public function test_the_settings_save(): void
    {
        $link = $this->link('biolink');

        $this->actingAs($this->user)->post('/user/links/'.$link->id.'/page-settings', [
            'share_button' => [
                'enabled'  => '1',
                'show_qr'  => '0',
                'style'    => 'bar',
                'position' => 'bottom-left',
                'label'    => 'Send this',
                'networks' => ['whatsapp', 'telegram'],
            ],
        ]);

        $sb = ShareButton::resolve($link->fresh()->settings['biolink'] ?? []);

        $this->assertTrue($sb['enabled']);
        $this->assertFalse($sb['show_qr']);
        $this->assertSame('bar', $sb['style']);
        $this->assertSame('Send this', $sb['label']);
        $this->assertSame(['whatsapp', 'telegram'], $sb['networks']);
    }

    /**
     * Unticking every network is a real choice -- QR and copy-link only.
     * If an empty list read as "unset" it would silently restore all of
     * them, and the control would appear to do nothing.
     */
    public function test_unticking_every_network_means_none_not_all(): void
    {
        $link = $this->link('biolink');

        $this->actingAs($this->user)->post('/user/links/'.$link->id.'/page-settings', [
            'share_button' => ['enabled' => '1', 'networks' => ['']],
        ]);

        $sb = ShareButton::resolve($link->fresh()->settings['biolink'] ?? []);

        $this->assertSame([], $sb['networks']);
    }

    /**
     * The endpoint's gate used to be "can this type have a background?".
     * A share button reaches more types than a background does, so a
     * type that can carry one has to be able to save it.
     */
    public function test_every_shareable_type_can_save_its_share_button(): void
    {
        foreach (ShareButton::SHAREABLE as $type) {
            if ($type === 'resume') {
                continue; // stored on the resume, tested separately below
            }

            $link = $this->link($type);

            $this->actingAs($this->user)
                ->post('/user/links/'.$link->id.'/page-settings', [
                    'share_button' => ['enabled' => '1', 'label' => 'Share'],
                ]);

            $this->assertTrue(
                ShareButton::resolve($link->fresh()->settings['biolink'] ?? [])['enabled'],
                $type.' can show a share button but could not save one'
            );
        }
    }

    public function test_a_resume_saves_its_share_button_on_the_resume(): void
    {
        $resume = Resume::create([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => [],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
        ]);

        $this->actingAs($this->user)->post('/user/resume/share-button', [
            'share_button' => ['enabled' => '0'],
        ]);

        $this->assertFalse((bool) ($resume->fresh()->share_button['enabled'] ?? true));
    }

    // ===== 5. What the panel must not do =====

    /**
     * The old panel set white text on a translucent white fill: it
     * assumed a dark page, and was unreadable on every light one. The
     * replacement paints its own surface.
     */
    public function test_the_panel_paints_its_own_surface(): void
    {
        $html = $this->page($this->link('biolink'));

        $this->assertStringContainsString('.sz-share-panel', $html);
        $this->assertStringContainsString('background: #ffffff', $html,
            'the panel must not inherit a page background it cannot predict');
    }

    /**
     * A URL can contain an apostrophe. The old copy button interpolated
     * the URL straight into an onclick attribute, where one quote is a
     * dead button; the address is read from a data attribute now.
     */
    public function test_the_copy_button_reads_the_address_from_an_attribute(): void
    {
        $html = $this->page($this->link('biolink'));

        $this->assertStringContainsString('data-sz-copy', $html);
        $this->assertStringNotContainsString("navigator.clipboard.writeText('http", $html);
    }
}
