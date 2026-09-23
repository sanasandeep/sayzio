<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\StarterPageService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What a brand-new Link in Bio opens with. Sana, 2026-09-23:
 *
 *   "while creating, it shows some content.... it shouldn't be like that.
 *    while creating empty link, default it should load 5 blocks
 *    automatically, so users will understand better.
 *    also make it editable by admin panel.. admin can create multiple
 *    default templates... randomly or some logic based."
 *
 * Three claims, and the third one carries a trap. Seeding real blocks into a
 * new page means a page nobody finishes is LIVE with "My Link" pointing at
 * example.com. So the starter blocks show in the editor from the first second
 * and stay out of the public page until the owner has edited one of them.
 */
class ANewLinkInBioStartsWithBlocksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $this->user = User::factory()->create([
            'onboarded_at' => Carbon::parse('2026-01-01'),
        ]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createBiolink(array $extra = []): Link
    {
        $this->actingAs($this->user)
            ->post('/user/links', array_merge([
                'type'  => 'biolink',
                'alias' => 'starter'.fake()->unique()->numerify('####'),
                'title' => 'My page',
            ], $extra))
            ->assertRedirect();

        return Link::where('user_id', $this->user->id)->latest('id')->firstOrFail();
    }

    /** A starter template an admin has switched on. */
    private function starter(array $attrs = []): PageTemplate
    {
        return PageTemplate::create(array_merge([
            'name'           => 'Creator starter',
            'slug'           => 'creator-starter-'.fake()->unique()->numerify('####'),
            'category'       => 'general',
            'is_active'      => true,
            'sort_order'     => 0,
            'starter_weight' => 1,
            'snapshot'       => ['blocks' => [
                ['type' => 'heading', 'settings' => ['text' => 'From the admin template'], 'is_active' => true],
                ['type' => 'link', 'settings' => ['text' => 'Admin link', 'url' => 'https://example.org'], 'is_active' => true],
            ]],
        ], $attrs));
    }

    // ===== 1. The editor is no longer an empty canvas =====

    /** Creating a page seeds the built-in five when no admin starter exists. */
    public function test_a_new_page_opens_with_five_blocks(): void
    {
        $link = $this->createBiolink();

        $this->assertSame(5, $link->biolinkBlocks()->count(),
            'a brand-new page must not be a blank canvas');
        $this->assertSame(
            StarterPageService::BUILT_IN,
            $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all(),
            'picture, name, bio, socials, one link -- in that order'
        );
    }

    /** And the editor renders them instead of "No blocks yet". */
    public function test_the_editor_shows_them_rather_than_the_empty_state(): void
    {
        $link = $this->createBiolink();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/blocks')->assertOk()->getContent();

        // The empty-state panel is always in the markup (JS re-shows it when
        // the last block is deleted); what matters is that it starts hidden.
        $this->assertMatchesRegularExpression(
            '/id="blockListEmpty"[^>]*display:none;/',
            $html,
            '"No blocks yet" must not greet someone whose page has five blocks'
        );
    }

    /** Every seeded block is a real, editable row -- not a fake preview. */
    public function test_the_seeded_blocks_are_editable(): void
    {
        $link  = $this->createBiolink();
        $block = $link->biolinkBlocks()->where('type', 'heading')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/blocks/'.$block->id, [
                'settings' => ['text' => 'Sana Sandeep'],
            ])->assertOk();

        $this->assertSame('Sana Sandeep', $block->fresh()->settings['text']);
    }

    // ===== 2. A visitor sees none of the placeholder copy =====

    /** Untouched, the public page still says it is being set up. */
    public function test_an_untouched_page_is_not_published_with_placeholder_copy(): void
    {
        $link = $this->createBiolink();

        $this->assertTrue($link->isUntouchedStarterPage());

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('being set up', $html,
            'a page nobody has finished must not read as a finished page');
        $this->assertStringNotContainsString('https://example.com', $html,
            'placeholder links must never be live on someone\'s public page');
    }

    /** One real edit and the page goes live, blocks and all. */
    public function test_editing_one_block_publishes_the_page(): void
    {
        $link  = $this->createBiolink();
        $block = $link->biolinkBlocks()->where('type', 'heading')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/blocks/'.$block->id, [
                'settings' => ['text' => 'Sana Sandeep'],
            ])->assertOk();

        $this->assertFalse($link->fresh()->isUntouchedStarterPage());

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('Sana Sandeep', $html);
        $this->assertStringNotContainsString('being set up', $html);
    }

    /**
     * A block the USER added publishes the page, even unedited.
     *
     * This is the half of the rule that "every block is still a placeholder"
     * gets wrong on its own: a block dragged in from the palette also arrives
     * with placeholder content, and a page someone is actively building is
     * not an untouched starter. Only blocks the seeder put there carry
     * `_starter_seed`, so one block of your own is enough.
     */
    public function test_adding_a_block_of_your_own_publishes_the_page(): void
    {
        $link = $this->createBiolink();

        $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/user/links/'.$link->id.'/blocks', ['type' => 'paragraph_rich'])
            ->assertOk();

        $this->assertFalse($link->fresh()->isUntouchedStarterPage(),
            'a page someone is building is not an untouched starter');
        $this->assertStringNotContainsString('being set up',
            $this->get('/'.$link->alias)->assertOk()->getContent());
    }

    /** And a page built entirely by hand was never a starter at all. */
    public function test_a_hand_built_page_is_never_treated_as_a_starter(): void
    {
        $link = $this->createBiolink();
        $link->biolinkBlocks()->delete();

        $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/user/links/'.$link->id.'/blocks', ['type' => 'heading'])
            ->assertOk();

        $this->assertFalse($link->fresh()->isUntouchedStarterPage());
    }

    /** A page with no blocks at all is still the plain empty page. */
    public function test_a_page_with_no_blocks_is_not_treated_as_placeholder_only(): void
    {
        $link = $this->createBiolink();
        $link->biolinkBlocks()->delete();

        $this->assertFalse($link->fresh()->isUntouchedStarterPage(),
            'empty is empty; only a seeded-but-untouched page is "placeholder only"');
        $this->assertStringContainsString('being set up',
            $this->get('/'.$link->alias)->assertOk()->getContent());
    }

    // ===== 3. Admin control =====

    /** An admin starter wins over the built-in five. */
    public function test_an_admin_starter_replaces_the_built_in_set(): void
    {
        $this->starter();

        $link = $this->createBiolink();

        $this->assertSame(['heading', 'link'], $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all());
        $this->assertSame('From the admin template',
            $link->biolinkBlocks()->where('type', 'heading')->first()->settings['text']);
    }

    /** Weight 0 means gallery-only: the template is never drawn. */
    public function test_a_zero_weight_template_is_never_used_as_a_starter(): void
    {
        $this->starter(['starter_weight' => 0]);

        $link = $this->createBiolink();

        $this->assertSame(StarterPageService::BUILT_IN,
            $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all(),
            'a template nobody marked as a starter must stay out of new pages');
    }

    /** A hidden template is out of both the gallery and the draw. */
    public function test_an_inactive_template_is_never_used_as_a_starter(): void
    {
        $this->starter(['is_active' => false, 'starter_weight' => 9]);

        $link = $this->createBiolink();

        $this->assertSame(StarterPageService::BUILT_IN,
            $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all());
    }

    /**
     * Several starters: every one is reachable, and weight moves the odds.
     *
     * Drawn rather than asserted once, because the point of the feature is
     * that it is random. 400 draws with weights 1 and 9 puts the heavy one
     * near 360; anything under 200 or over 396 would mean the weight is
     * being ignored in one direction or the other. The odds of a correct
     * implementation failing this are far below one in a million.
     */
    public function test_the_draw_is_random_and_respects_the_weights(): void
    {
        $light = $this->starter(['name' => 'Light', 'starter_weight' => 1]);
        $heavy = $this->starter(['name' => 'Heavy', 'starter_weight' => 9]);

        $service = app(StarterPageService::class);
        $picks   = [];
        for ($i = 0; $i < 400; $i++) {
            $picks[] = $service->pickTemplate($this->user)?->id;
        }
        $counts = array_count_values(array_filter($picks));

        $this->assertArrayHasKey($light->id, $counts, 'a weight-1 starter must still come up');
        $this->assertArrayHasKey($heavy->id, $counts);
        $this->assertGreaterThan($counts[$light->id], $counts[$heavy->id],
            'weight 9 must be drawn more often than weight 1');
        $this->assertGreaterThan(200, $counts[$heavy->id],
            'a 9:1 weight is being treated as a coin flip');
    }

    /** A user is never started on a design their plan locks them out of. */
    public function test_a_starter_above_the_users_plan_is_skipped(): void
    {
        $premium = \App\Modules\Admin\Models\Plan::create([
            'name' => 'Pro', 'slug' => 'pro-starter', 'sort_order' => 90, 'price' => 0,
        ]);
        $this->starter(['plan_tier' => $premium->slug, 'starter_weight' => 5]);

        $link = $this->createBiolink();

        $this->assertSame(StarterPageService::BUILT_IN,
            $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all(),
            'a free user must not open their first page on a template they cannot edit');
    }

    /** Persona is a preference, not a filter. */
    public function test_a_persona_starter_is_preferred_but_never_the_only_option(): void
    {
        $generic  = $this->starter(['name' => 'Generic', 'recommended_personas' => []]);
        $personas = \App\Modules\User\Services\PersonaCatalog::slugs();
        $persona  = $personas[0] ?? null;

        if (! $persona) {
            $this->markTestSkipped('No personas configured to test against.');
        }

        $tagged = $this->starter(['name' => 'Tagged', 'recommended_personas' => [$persona]]);
        $this->user->update(['persona' => $persona]);

        $service = app(StarterPageService::class);
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($tagged->id, $service->pickTemplate($this->user->fresh())?->id,
                'a user with a persona must get a starter tagged for it');
        }

        // With the tagged one switched off, the generic one still serves.
        $tagged->update(['starter_weight' => 0]);
        $this->assertSame($generic->id, $service->pickTemplate($this->user->fresh())?->id,
            'no persona match must fall back to the whole set, not to nothing');
    }

    /** The admin form saves the weight, and the index badges it. */
    public function test_an_admin_can_set_the_weight_from_the_templates_screen(): void
    {
        $admin  = $this->adminUser();
        $source = $this->starter(['starter_weight' => 0]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/templates/page/'.$source->id, [
                'name'          => $source->name,
                'slug'          => $source->slug,
                'category'      => 'general',
                'is_active'     => 1,
                'starter_weight' => 4,
                'snapshot_json' => json_encode($source->snapshot),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(4, $source->fresh()->starter_weight);

        $html = $this->actingAs($admin, 'admin')->get('/admin/templates')->assertOk()->getContent();
        $this->assertStringContainsString('Starter &times;4', $html,
            'the templates list must say which templates new pages start from');
    }

    // ===== 4. It does not get in the way of anything else =====

    /** Seeding never overwrites a page that already has blocks. */
    public function test_seeding_is_a_no_op_on_a_page_that_already_has_blocks(): void
    {
        $link = $this->createBiolink();
        $link->biolinkBlocks()->delete();
        BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'heading',
            'settings' => ['text' => 'Mine'], 'sort_order' => 0, 'is_active' => true,
        ]);

        app(StarterPageService::class)->seed($link->fresh(), $this->user);

        $this->assertSame(1, $link->biolinkBlocks()->count(),
            'a second seed must never touch work that is already there');
        $this->assertSame('Mine', $link->biolinkBlocks()->first()->settings['text']);
    }

    /**
     * The template picker must not warn about "replacing your blocks" when
     * the only blocks are the untouched starter ones.
     */
    public function test_the_picker_does_not_claim_a_new_page_has_work_to_lose(): void
    {
        $tpl  = $this->starter(['starter_weight' => 0]);   // gallery only
        $link = $this->createBiolink();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/templates')->assertOk()->getContent();

        $this->assertStringContainsString('Use this template', $html);
        $this->assertStringNotContainsString('Replace with this template', $html,
            'the starter blocks are not the user\'s work; nothing is at risk yet');

        // And the server agrees: no confirm_overwrite needed.
        $this->actingAs($this->user)
            ->post('/user/links/'.$link->id.'/templates/apply-page', ['template_id' => $tpl->id])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertSame(['heading', 'link'],
            $link->biolinkBlocks()->orderBy('sort_order')->pluck('type')->all());
    }

    /** A short link is not a page and gets no blocks. */
    public function test_a_short_link_is_left_alone(): void
    {
        $this->actingAs($this->user)->post('/user/links', [
            'type' => 'url', 'alias' => 'shorty1', 'long_url' => 'https://example.org',
        ])->assertRedirect();

        $link = Link::where('alias', 'shorty1')->firstOrFail();
        $this->assertFalse($link->isBiolinkFamily());
        $this->assertSame(0, $link->biolinkBlocks()->count());
    }

    /** A super-admin on the `admin` guard, which is what CheckPermission reads. */
    private function adminUser(): \App\Modules\Admin\Models\Admin
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return \App\Modules\Admin\Models\Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin'.uniqid().'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }
}
