<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The "Built by EEFind Private Limited" block on /about can actually be
 * edited.
 *
 * Sana could not find a way to change the phone number or the three stats on
 * that card. The editor for them exists -- Site pages -> About has inputs for
 * the eyebrow, heading, body, a repeater for the stats, and the address,
 * email, WhatsApp number and website. It renders the saved values back
 * correctly too. What was missing sat between the two: the controller's
 * `$request->validate($rules)` had no rules for `extra.eefind.*` at all, and
 * `validate()` returns only the keys it was given rules for.
 *
 * So the block was dropped from the validated payload on every save, and
 * `normalizeAboutExtra` treats an absent `eefind` key as "this row predates
 * the section" and substitutes the code defaults. The result is worse than
 * un-editable: typing a new phone number and pressing Save silently reverted
 * the whole block -- heading, body, stats, address, email, number, website --
 * to the hardcoded values, with a success message.
 *
 * These tests are written against the admin form and the public page, not
 * against the sanitiser, because the sanitiser was never the broken part.
 */
class AboutParentCompanyBlockIsEditableTest extends TestCase
{
    use RefreshDatabase;

    private function anAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function aboutPage(): SitePage
    {
        return SitePage::firstOrCreate(
            ['slug' => 'about'],
            ['title' => 'About', 'sections' => [], 'extra' => []]
        );
    }

    /** The minimum a valid About submission needs, plus whatever we're testing. */
    private function submit(array $eefind): \Illuminate\Testing\TestResponse
    {
        $page = $this->aboutPage();

        return $this->actingAs($this->anAdmin(), 'admin')->put(
            route('admin.site-pages.update', ['slug' => 'about']),
            [
                'title' => $page->title ?: 'About',
                'sections' => [],
                'extra' => ['eefind' => $eefind],
            ]
        );
    }

    public function test_the_parent_company_phone_number_can_be_changed(): void
    {
        $this->submit([
            'eyebrow' => 'Part of EEFind',
            'heading' => 'Built by EEFind Private Limited',
            'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
            'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034',
            'email' => 'support@eefind.com',
            'whatsapp' => '+91 90000 11111',
            'website' => 'eefind.com',
            'website_url' => 'https://eefind.com',
            'stats' => [],
        ]);

        $saved = (array) (SitePage::where('slug', 'about')->first()->extra['eefind'] ?? []);

        $this->assertSame(
            '+91 90000 11111',
            $saved['whatsapp'] ?? null,
            'the phone number was not saved -- the submitted value never reached the database'
        );
    }

    public function test_the_three_stats_can_be_changed(): void
    {
        $this->submit([
            'eyebrow' => 'Part of EEFind',
            'heading' => 'Built by EEFind Private Limited',
            'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
            'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034',
            'email' => 'support@eefind.com',
            'whatsapp' => '+91 81210 57755',
            'website' => 'eefind.com',
            'website_url' => 'https://eefind.com',
            'stats' => [
                ['value' => '9', 'suffix' => 'K+', 'label' => 'Products'],
                ['value' => '7', 'suffix' => 'K+', 'label' => 'Merchants'],
                ['value' => '60', 'suffix' => '+', 'label' => 'Cities live'],
            ],
        ]);

        $saved = (array) (SitePage::where('slug', 'about')->first()->extra['eefind']['stats'] ?? []);

        $this->assertCount(3, $saved, 'the stats rows were not saved');
        $this->assertSame('9', $saved[0]['value'] ?? null);
        $this->assertSame('Cities live', $saved[2]['label'] ?? null);
    }

    /**
     * And the change reaches the page a visitor sees.
     *
     * Saving to the database is only half of it: /about reads this block
     * through its own defaulting, so a value that saves but does not render
     * is the same bug from the visitor's side.
     */
    public function test_the_new_number_and_stats_show_on_the_public_page(): void
    {
        $this->submit([
            'eyebrow' => 'Part of EEFind',
            'heading' => 'Built by EEFind Private Limited',
            'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
            'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034',
            'email' => 'support@eefind.com',
            'whatsapp' => '+91 90000 11111',
            'website' => 'eefind.com',
            'website_url' => 'https://eefind.com',
            'stats' => [
                ['value' => '9', 'suffix' => 'K+', 'label' => 'Products'],
            ],
        ]);

        $page = $this->get(route('site.about'));
        $page->assertOk();

        $page->assertSee('+91 90000 11111');
        $page->assertDontSee('+91 81210 57755');
    }

    /**
     * Saving an unrelated part of the page does not wipe this block.
     *
     * This is the harsher half of the same bug and the reason it went
     * unnoticed: the block came back looking right -- as the defaults --
     * rather than disappearing, so a save that silently discarded an edit
     * looked like a save that had not been made.
     */
    public function test_saving_the_page_does_not_revert_a_customised_block(): void
    {
        $this->submit([
            'eyebrow' => 'Part of EEFind',
            'heading' => 'Built by EEFind Private Limited',
            'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
            'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034',
            'email' => 'support@eefind.com',
            'whatsapp' => '+91 90000 11111',
            'website' => 'eefind.com',
            'website_url' => 'https://eefind.com',
            'stats' => [['value' => '9', 'suffix' => 'K+', 'label' => 'Products']],
        ]);

        // A second save carrying the same block, the way the form does.
        $this->submit([
            'eyebrow' => 'Part of EEFind',
            'heading' => 'Built by EEFind Private Limited',
            'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
            'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034',
            'email' => 'support@eefind.com',
            'whatsapp' => '+91 90000 11111',
            'website' => 'eefind.com',
            'website_url' => 'https://eefind.com',
            'stats' => [['value' => '9', 'suffix' => 'K+', 'label' => 'Products']],
        ]);

        $saved = (array) (SitePage::where('slug', 'about')->first()->extra['eefind'] ?? []);
        $defaults = SitePagesContent::aboutEefindDefault();

        $this->assertSame('+91 90000 11111', $saved['whatsapp'] ?? null);
        $this->assertNotSame(
            $defaults['whatsapp'],
            $saved['whatsapp'] ?? null,
            'the block was reset to the code defaults by a save'
        );
    }
}
