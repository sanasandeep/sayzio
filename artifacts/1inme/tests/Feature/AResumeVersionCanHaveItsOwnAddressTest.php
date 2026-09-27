<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "custom link and aliases need to work just like in link
 * in bio.... again... those urls are currently non editable... it should be
 * editable..."
 *
 * ---- He was standing in the resume builder ------------------------------
 *
 * And from there he was right, though not in the way the words read. A
 * resume link's address IS editable and additional aliases DO work --
 * `resume` is in the alias-capable type list and the link edit screen
 * includes the same aliases card Link in Bio uses. Two clicks away.
 *
 * The real hole is narrower and worse. LinkController::store binds every
 * new `resume` link to the owner's DEFAULT version, deliberately. So
 * pointing a URL at a tailored version meant: make a link, notice it opens
 * the wrong resume, open the link editor, change the version. Three
 * screens for one idea, and the middle step looks exactly like the bug he
 * reported first ("automatically other urls are created... why?").
 *
 * A version with no link of its own is not reachable at all -- which the
 * builder's own copy now promises it is ("every other version is only
 * reachable at its own link").
 *
 * So: create one from here, bound to THIS version, and edit the address
 * where the resume is built.
 */
class AResumeVersionCanHaveItsOwnAddressTest extends TestCase
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

    private function resume(array $attrs = []): Resume
    {
        return Resume::create(array_merge([
            'user_id'        => $this->user->id,
            'template_id'    => 'classic',
            'color_theme_id' => 'slate',
            'sections'       => [],
            'is_public'      => true,
            'is_default'     => true,
            'name'           => 'Default',
        ], $attrs));
    }

    private function editor(): string
    {
        return $this->actingAs($this->user)->get('/user/resume')->assertOk()->getContent();
    }

    // ===== 1. A version can be given its own address =====

    /** The headline: one action, from where the version lives. */
    public function test_a_version_can_be_given_its_own_link(): void
    {
        // A default has to exist for this to mean anything: binding to
        // "the owner's resume" and binding to THIS version are the same
        // row on an account with only one.
        $this->resume(['name' => 'Default']);
        $version = $this->resume(['is_default' => false, 'name' => 'Product design roles']);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => 'sana-design'])
            ->assertCreated()
            ->assertJsonPath('link.alias', 'sana-design');

        $link = Link::where('alias', 'sana-design')->first();

        $this->assertSame(Link::TYPE_RESUME, $link->type);
        $this->assertSame($version->id, $link->resume_id,
            'bound to THIS version is the entire point -- the Create Link flow binds to the default');
    }

    /**
     * And it is bound, not floating. An unbound link resolves to whichever
     * version is live, which is the confusion the builder's list spends a
     * paragraph explaining.
     */
    public function test_the_new_link_reports_itself_as_tied_to_the_version(): void
    {
        $this->resume(['name' => 'Default']);
        $version = $this->resume(['is_default' => false, 'name' => 'Growth roles']);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => 'sana-growth'])
            ->assertCreated()
            ->assertJsonPath('link.is_bound', true);
    }

    /** The public page that address serves is that version. */
    public function test_the_address_opens_the_version_it_was_made_for(): void
    {
        $default = $this->resume(['name' => 'Default']);
        $default->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => ['role' => 'Default role', 'company' => 'A'],
        ]);

        $version = $this->resume(['is_default' => false, 'name' => 'Product design roles']);
        $version->items()->create([
            'section_type' => 'experience', 'position' => 0,
            'data' => ['role' => 'Tailored role', 'company' => 'B'],
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => 'sana-design'])
            ->assertCreated();

        $html = $this->get('/sana-design')->assertOk()->getContent();

        $this->assertStringContainsString('Tailored role', $html);
        $this->assertStringNotContainsString('Default role', $html,
            'a link bound to a version that still opens the default is the bug this fixes');
    }

    // ===== 2. The address is editable where the resume is built =====

    public function test_the_builder_lets_the_address_be_edited_in_place(): void
    {
        $resume = $this->resume();
        Link::create([
            'user_id' => $this->user->id, 'type' => Link::TYPE_RESUME,
            'alias' => 'sana-resume', 'resume_id' => $resume->id, 'is_active' => true,
        ]);

        $html = $this->editor();

        $this->assertStringContainsString('x-model="rl.alias"', $html);
        $this->assertStringContainsString('saveAlias(rl)', $html);
    }

    /**
     * And it saves through the LINK module's endpoint rather than a second
     * one. That endpoint owns the per-plan length floor, the reserved and
     * banned name lists, and cross-table uniqueness; a parallel endpoint
     * would have to keep all four in step forever.
     */
    public function test_the_builder_saves_through_the_link_modules_own_endpoint(): void
    {
        $resume = $this->resume();
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => Link::TYPE_RESUME,
            'alias' => 'sana-resume', 'resume_id' => $resume->id, 'is_active' => true,
        ]);

        $html = $this->editor();

        $this->assertStringContainsString('update_alias_url', $html);
        $this->assertStringNotContainsString('resume/link-alias', $html,
            'a second alias endpoint is how two links end up claiming one address');

        // And that endpoint genuinely accepts a resume link.
        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/alias', ['alias' => 'sana-cv'])
            ->assertOk();

        $this->assertSame('sana-cv', $link->fresh()->alias);
    }

    // ===== 3. The rules the link module owns are not bypassed =====

    public function test_a_taken_address_is_refused(): void
    {
        $version = $this->resume(['is_default' => false, 'name' => 'Other']);
        Link::create([
            'user_id' => $this->user->id, 'type' => Link::TYPE_BIOLINK,
            'alias' => 'taken', 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => 'taken'])
            ->assertStatus(422);
    }

    public function test_a_reserved_name_is_refused(): void
    {
        $version = $this->resume(['is_default' => false, 'name' => 'Other']);

        $reserved = \App\Modules\User\Controllers\LinkAliasController::reservedAliases()[0] ?? 'login';

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => $reserved])
            ->assertStatus(422);
    }

    /** Somebody else's version is not a version you can hang a URL on. */
    public function test_another_owners_version_is_refused(): void
    {
        $other = User::factory()->create(['onboarded_at' => now()]);
        $theirs = Resume::create([
            'user_id' => $other->id, 'template_id' => 'classic', 'color_theme_id' => 'slate',
            'sections' => [], 'is_public' => true, 'is_default' => true, 'name' => 'Theirs',
        ]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$theirs->id.'/link', ['alias' => 'not-mine'])
            ->assertStatus(403);
    }

    // ===== 3b. Two things found while looking =====

    /**
     * The builder crashed for anyone who had a resume link.
     *
     * The short-links card referenced `$resume` and the controller never
     * passed it. The reference sat inside an `@if(!empty($resumeLinks))`
     * guard, so it only evaluated for an account that HAD one -- which is
     * why no test and no empty account ever hit it, and why Sana would
     * have, the moment he made /sana-resume.
     */
    public function test_the_builder_renders_for_an_account_that_has_a_link(): void
    {
        $resume = $this->resume();
        Link::create([
            'user_id' => $this->user->id, 'type' => Link::TYPE_RESUME,
            'alias' => 'sana-resume', 'resume_id' => $resume->id, 'is_active' => true,
        ]);

        $this->actingAs($this->user)->get('/user/resume')->assertOk();
    }

    /**
     * And a bound link that opens the wrong resume.
     *
     * `Resume::effectiveSlug()` falls back to DEFAULT_SLUG when a version
     * has no slug -- correct for the default version, quietly wrong for any
     * other: the link is tied in the database and untied on the page. The
     * normal create/duplicate paths always assign a slug, so this bites
     * versions that arrived another way.
     */
    public function test_a_version_with_no_slug_gets_one_before_a_link_is_hung_on_it(): void
    {
        $version = $this->resume(['is_default' => false, 'name' => 'Growth roles', 'slug' => null]);

        $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$version->id.'/link', ['alias' => 'sana-growth'])
            ->assertCreated();

        $slug = $version->fresh()->slug;

        $this->assertNotEmpty($slug);
        $this->assertNotSame(Resume::DEFAULT_SLUG, $slug,
            'a non-default version on the default slug resolves to the default resume');
    }

    // ===== 4. Guard =====

    /**
     * The list row and a freshly created link have to be the same shape.
     * A new link that came back without `is_bound` would render as "Any
     * version" -- the exact label this list was rewritten to stop being
     * confusing.
     */
    public function test_a_created_link_has_the_same_shape_as_a_listed_one(): void
    {
        $resume = $this->resume();
        Link::create([
            'user_id' => $this->user->id, 'type' => Link::TYPE_RESUME,
            'alias' => 'existing', 'resume_id' => $resume->id, 'is_active' => true,
        ]);

        $created = $this->actingAs($this->user)
            ->postJson('/user/resume/versions/'.$resume->id.'/link', ['alias' => 'second'])
            ->assertCreated()->json('link');

        foreach (['id', 'title', 'alias', 'public_url', 'analytics_url', 'edit_url', 'update_alias_url', 'is_bound'] as $key) {
            $this->assertArrayHasKey($key, $created, "a new link is missing '$key', so its row would render wrong");
        }
    }
}
