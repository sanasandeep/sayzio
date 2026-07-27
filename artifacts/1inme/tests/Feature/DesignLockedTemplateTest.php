<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Design-locked page templates:
 *
 *  - Applying a design_locked template stamps the biolink with
 *    settings.biolink.design_locked (template identity + block_styles map);
 *    applying an unlocked template clears any prior stamp.
 *  - While locked, web page-settings saves strip design keys (background,
 *    fonts, buttons, custom CSS/JS) but content keys still persist.
 *  - While locked, per-block style mutations 403 and new blocks are seeded
 *    with the template's `_style` for their type (client `_style` ignored).
 *  - "Detach from template" (web + API) clears the stamp.
 *  - API parity: LinkResource exposes design_locked/design_lock and the
 *    API PATCH strips design keys while locked.
 *  - Seeder: curated persona "starter" blueprints are seeded design_locked.
 */
class DesignLockedTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name' => 'P', 'slug' => 'p-' . Str::lower(Str::random(6)),
            'monthly_price' => 0, 'annual_price' => 0, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 0,
            'features' => ['max_links' => 100, 'max_biolinks' => 100],
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id])->fresh();

        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeLink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => Link::generateAlias(),
            'title'   => 'My Bio',
        ]);
    }

    private function makeTemplate(bool $locked): PageTemplate
    {
        return PageTemplate::create([
            'slug'          => 'tpl-' . Str::random(8),
            'name'          => $locked ? 'Locked Designer' : 'Open Starter',
            'category'      => 'general',
            'description'   => 'Test template.',
            'is_active'     => true,
            'sort_order'    => 0,
            'design_locked' => $locked,
            'recommended_personas' => [],
            'snapshot' => [
                'biolink' => ['background_type' => 'color', 'background_color' => '#111111'],
                'blocks'  => [
                    ['type' => 'heading', 'settings' => [
                        'text' => 'Welcome',
                        '_style' => ['text_color' => '#ff0000', 'font_size' => '24'],
                    ], 'is_active' => true],
                    ['type' => 'paragraph', 'settings' => ['text' => 'Hello'], 'is_active' => true],
                ],
                'meta' => [],
            ],
        ]);
    }

    // ── apply stamps / clears the lock ───────────────────────────────

    public function test_applying_locked_template_stamps_design_lock(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate(true);

        $this->actingAs($user)
            ->post(route('user.links.templates.apply-page', $link), ['template_id' => $tpl->id])
            ->assertRedirect();

        $link->refresh();
        $this->assertTrue($link->isDesignLocked());
        $info = $link->designLockInfo();
        $this->assertSame($tpl->id, $info['template_id']);
        $this->assertSame($tpl->name, $info['template_name']);
        $this->assertSame('#ff0000', $info['block_styles']['heading']['text_color'] ?? null);
        $style = $link->designLockStyleFor('heading');
        $this->assertSame('#ff0000', $style['text_color'] ?? null);
        $this->assertNull($link->designLockStyleFor('video'));
    }

    public function test_applying_unlocked_template_clears_prior_lock(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.links.templates.apply-page', $link), ['template_id' => $this->makeTemplate(true)->id]);
        $this->assertTrue($link->refresh()->isDesignLocked());

        $this->post(route('user.links.templates.apply-page', $link), [
            'template_id' => $this->makeTemplate(false)->id,
            'confirm_overwrite' => 1,
        ]);
        $this->assertFalse($link->refresh()->isDesignLocked());
    }

    // ── web enforcement while locked ─────────────────────────────────

    private function lockedLink(User $user): Link
    {
        $link = $this->makeLink($user);
        $this->actingAs($user)
            ->post(route('user.links.templates.apply-page', $link), ['template_id' => $this->makeTemplate(true)->id]);
        return $link->refresh();
    }

    public function test_page_settings_save_strips_design_keys_but_keeps_content(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $this->post(route('user.links.page-settings', $link), [
            'biolink_title'    => 'New Title',
            'background_type'  => 'color',
            'background_color' => '#00ff00',
            'font_color'       => '#123456',
            'button_style'     => 'pill',
        ]);

        $link->refresh();
        $biolink = ($link->settings ?? [])['biolink'] ?? [];
        // Content key persisted.
        $this->assertSame('New Title', $biolink['biolink_title'] ?? null);
        // Design keys stayed as the template set them.
        $this->assertSame('#111111', $biolink['background_color'] ?? null);
        $this->assertNotSame('#123456', $biolink['font_color'] ?? null);
        $this->assertContains('font_color', BiolinkBlockController::DESIGN_LOCKED_PAGE_KEYS);
        // Lock survives the save.
        $this->assertTrue($link->isDesignLocked());
    }

    public function test_block_style_mutations_are_403_while_locked(): void
    {
        $user  = $this->makeUser();
        $link  = $this->lockedLink($user);
        $block = $link->biolinkBlocks()->where('type', 'heading')->firstOrFail();

        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->postJson(route('user.links.blocks.applyVariant', [$link, $block]), ['variant' => 'default'], $ajax)
            ->assertStatus(403);
        $this->postJson(route('user.links.blocks.resetStyle', [$link, $block]), [], $ajax)
            ->assertStatus(403);
    }

    public function test_new_block_inherits_template_style_and_ignores_client_style(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $resp = $this->post(route('user.links.blocks.store', $link), [
            'type' => 'heading',
            'settings' => ['text' => 'Another heading', '_style' => ['text_color' => '#0000ff']],
        ], ['X-Requested-With' => 'XMLHttpRequest']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'heading')->orderByDesc('id')->first();
        $this->assertSame('#ff0000', $block->settings['_style']['text_color'] ?? null,
            'new block must inherit the template lock style, not the client-sent one');
    }

    public function test_block_content_update_stays_allowed_while_locked(): void
    {
        $user  = $this->makeUser();
        $link  = $this->lockedLink($user);
        $block = $link->biolinkBlocks()->where('type', 'paragraph')->firstOrFail();

        $this->putJson(route('user.links.blocks.update', [$link, $block]), [
            'settings' => ['text' => 'Edited copy'],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertSame('Edited copy', $block->fresh()->settings['text'] ?? null);
    }

    public function test_web_detach_clears_the_lock(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $this->postJson(route('user.links.templates.detach-design', $link), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertFalse($link->refresh()->isDesignLocked());
    }

    // ── API parity ───────────────────────────────────────────────────

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_api_apply_stamps_and_link_resource_exposes_lock(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate(true);

        $this->withToken($this->token($user));
        $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", ['template_id' => $tpl->id])
            ->assertOk();

        $resp = $this->getJson("/api/v1/links/{$link->id}");
        $resp->assertOk();
        $this->assertTrue($resp->json('data.link.design_locked'));
        $this->assertSame($tpl->id, $resp->json('data.link.design_lock.template_id'));
    }

    public function test_api_update_strips_design_keys_while_locked(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $this->withToken($this->token($user));
        $this->patchJson("/api/v1/links/{$link->id}", [
            'biolink' => ['background_color' => '#00ff00', 'title' => 'API Title'],
        ])->assertOk();

        $biolink = ($link->refresh()->settings ?? [])['biolink'] ?? [];
        $this->assertSame('#111111', $biolink['background_color'] ?? null);
        $this->assertTrue($link->isDesignLocked());
    }

    public function test_api_block_create_inherits_lock_style(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $this->withToken($this->token($user));
        $this->postJson("/api/v1/links/{$link->id}/blocks", [
            'type' => 'heading',
            'settings' => ['text' => 'From mobile', '_style' => ['text_color' => '#00ff00']],
        ])->assertCreated();

        $block = BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'heading')->orderByDesc('id')->first();
        $this->assertSame('#ff0000', $block->settings['_style']['text_color'] ?? null);
    }

    public function test_api_detach_clears_the_lock(): void
    {
        $user = $this->makeUser();
        $link = $this->lockedLink($user);

        $this->withToken($this->token($user));
        $this->postJson("/api/v1/links/{$link->id}/page-templates/detach")
            ->assertOk()
            ->assertJsonPath('data.detached', true)
            ->assertJsonPath('data.design_locked', false);

        $this->assertFalse($link->refresh()->isDesignLocked());
    }

    // ── seeder ───────────────────────────────────────────────────────

    public function test_seeder_marks_curated_starter_blueprints_design_locked(): void
    {
        $seeder = new ExpandedPageTemplateLibrarySeeder();

        $method = new \ReflectionMethod($seeder, 'createBlueprintRow');
        $method->setAccessible(true);

        $mk = fn(string $key) => [
            'key' => $key, 'name' => 'X', 'description' => 'Y', 'thumb' => '',
            'snapshot' => ['biolink' => [], 'blocks' => [], 'meta' => []],
        ];

        // Curated persona + starter blueprint → locked.
        $method->invoke($seeder, 'creator', $mk('starter'), 0);
        $this->assertTrue(PageTemplate::where('slug', 'persona-creator-starter')->firstOrFail()->design_locked);

        // Same persona, non-starter blueprint → unlocked.
        $method->invoke($seeder, 'creator', $mk('gallery'), 1);
        $this->assertFalse(PageTemplate::where('slug', 'persona-creator-gallery')->firstOrFail()->design_locked);

        // Non-curated persona starter → unlocked.
        $method->invoke($seeder, 'cafe', $mk('starter'), 0);
        $this->assertFalse(PageTemplate::where('slug', 'persona-cafe-starter')->firstOrFail()->design_locked);
    }
}
