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
 * A site-page editor saves the payload its own form posts.
 *
 * This is the test that was missing, and the reason a broken page survived a
 * round of "fixes" aimed at it.
 *
 * /about could not be saved at all. Not the parent-company block -- the page.
 * `extra.section_order` is a hidden field carrying the render order of the
 * lower sections, and its rule said `max:5`, written when there were five of
 * them. `eefind` made six. The editor rendered six rows, the form posted six
 * values, validation rejected the request, and the admin layout had no block
 * for validation errors, so Laravel redirected back with the errors in the
 * session where nothing read them. Press Save: no success message, no error,
 * old values. Every edit to /about had been discarded since the sixth section
 * was added.
 *
 * My earlier test for the EEFind block passed against this, because it posted
 * a hand-written minimal payload -- title, sections, one `extra` key -- and
 * never included `section_order`. It proved the rules I had just added worked
 * in isolation, which was true and useless: the form those fields live on
 * could not be submitted.
 *
 * So these build the payload from what the editor actually renders, not from
 * what is convenient to assert, and check the thing the admin cares about:
 * that pressing Save saves.
 */
class SitePageEditorSavesWhatTheFormPostsTest extends TestCase
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

    /**
     * Saving /about with every section in the order field succeeds.
     *
     * The order list is taken from the same source the editor renders from,
     * so this cannot drift back out of step the way the hardcoded cap did.
     */
    public function test_the_about_page_saves_with_the_full_section_order_its_editor_renders(): void
    {
        $this->aboutPage();

        $slugs = SitePagesContent::aboutLowerSectionSlugs();
        $this->assertGreaterThan(1, count($slugs), 'the section list is empty, so this proves nothing');

        $response = $this->actingAs($this->anAdmin(), 'admin')->put(
            route('admin.site-pages.update', ['slug' => 'about']),
            [
                'title' => 'About Sayzio',
                'sections' => [],
                'extra' => [
                    'section_order' => $slugs,
                    'section_visibility' => array_fill_keys($slugs, '1'),
                    'eefind' => [
                        'eyebrow' => 'Part of EEFind',
                        'heading' => 'Built by EEFind Private Limited',
                        'body' => 'Sayzio is a brand and product of EEFIND PVT LTD.',
                        'address' => '8 Amrutha Nilayam, Banjara Hills, Hyderabad',
                        'email' => 'support@eefind.com',
                        'whatsapp' => '+91 90000 11111',
                        'website' => 'eefind.com',
                        'website_url' => 'https://eefind.com',
                        'stats' => [
                            ['value' => '9', 'suffix' => 'K+', 'label' => 'Products'],
                        ],
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $saved = (array) (SitePage::where('slug', 'about')->first()->extra['eefind'] ?? []);
        $this->assertSame(
            '+91 90000 11111',
            $saved['whatsapp'] ?? null,
            'the save reported success but the page was not written'
        );
    }

    /**
     * The cap on the order field is the length of the list, not a number.
     *
     * A literal here is a time bomb: it is correct on the day it is written
     * and silently wrong the next time a section is added, with no symptom
     * except that the whole page stops saving.
     */
    public function test_the_section_order_cap_is_derived_from_the_section_list(): void
    {
        $source = file_get_contents(base_path('app/Modules/Admin/Controllers/SitePageController.php'));

        $this->assertMatchesRegularExpression(
            "/extra\.section_order'\]\s*=\s*'nullable\|array\|max:'\s*\.\s*count\(/",
            $source,
            "the section_order cap is a hardcoded number again. It must be "
            . 'count(SitePagesContent::aboutLowerSectionSlugs()), or adding a seventh section '
            . 'silently makes the whole /about editor unsaveable.'
        );
    }

    /**
     * A save that fails tells the admin it failed.
     *
     * Without this the page is indistinguishable from one that saved: the
     * redirect lands back on the editor showing the stored values, which is
     * also what a successful save looks like from the admin's chair.
     */
    public function test_a_rejected_save_is_reported_to_the_admin(): void
    {
        $this->aboutPage();

        $rejected = $this->actingAs($this->anAdmin(), 'admin')
            ->from(route('admin.site-pages.edit', ['slug' => 'about']))
            ->put(route('admin.site-pages.update', ['slug' => 'about']), [
                'title' => '',   // required
                'sections' => [],
            ]);

        // Following the redirect BEFORE asserting on the session. Reading the
        // session off the response first makes the flashed errors unavailable
        // to the followed request, and the banner then renders empty -- which
        // cost me a while, because the assertion that failed was the one about
        // the page, not the one that broke it.
        $page = $this->followRedirects($rejected);
        $page->assertOk();

        $page->assertSee('Nothing was saved');
        $page->assertSee('title');
    }
}
