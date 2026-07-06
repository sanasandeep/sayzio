<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the mobile page-template apply flow exposed over /api/v1:
 *
 *   GET  /api/v1/links/{id}/page-templates
 *   POST /api/v1/links/{id}/page-templates/apply
 *
 * Unlike card templates (which insert a single grouped sub-tree),
 * applying a page template REPLACES the link's blocks, so apply carries
 * an overwrite-confirmation guard (409) on top of the plan-lock (403)
 * and validation surfaces. These tests pin the index shape (items,
 * categories, persona ordering, plan-lock flags) and every apply branch.
 *
 * Authenticated with a REAL Sanctum bearer token — `Sanctum::actingAs`
 * injects a mock current-access-token that the TouchSessionToken
 * middleware then tries to ->save(), 500-ing every request.
 */
class PageTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $slug, int $sortOrder): Plan
    {
        return Plan::create([
            'name'          => ucfirst($slug),
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => $sortOrder,
            'features'      => [
                'max_links'    => 100,
                'max_biolinks' => 100,
            ],
        ]);
    }

    private function makeUser(?Plan $plan = null, ?string $persona = null): User
    {
        return User::factory()->create([
            'plan_id' => $plan?->id,
            'persona' => $persona,
        ])->fresh();
    }

    private function makeLink(User $user, string $type = 'biolink'): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => $type,
            'alias'   => Link::generateAlias(),
            'title'   => 'My Bio',
        ]);
    }

    private function makeTemplate(array $overrides = []): PageTemplate
    {
        return PageTemplate::create(array_merge([
            'slug'                 => 'tpl-' . Str::random(8),
            'name'                 => 'Starter ' . Str::random(4),
            'category'             => 'general',
            'description'          => 'A simple starter page.',
            'thumbnail_url'        => null,
            'plan_tier'            => null,
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => [
                'biolink' => [],
                'blocks'  => [
                    ['type' => 'heading', 'settings' => ['text' => 'Welcome'], 'is_active' => true],
                    ['type' => 'paragraph', 'settings' => ['text' => 'Hello there'], 'is_active' => true],
                    ['type' => 'link', 'settings' => ['text' => 'Visit', 'url' => 'https://example.com'], 'is_active' => true],
                ],
                'meta' => [],
            ],
        ], $overrides));
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ── index ────────────────────────────────────────────────────────

    public function test_index_returns_items_and_categories_with_lock_flags(): void
    {
        $free = $this->plan('free', 0);
        $pro  = $this->plan('pro', 10);
        $user = $this->makeUser($free);
        $link = $this->makeLink($user);

        $open   = $this->makeTemplate(['name' => 'Open Template', 'plan_tier' => null]);
        $locked = $this->makeTemplate(['name' => 'Locked Template', 'plan_tier' => 'pro']);

        $this->withToken($this->token($user));
        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates");

        $resp->assertOk();
        $resp->assertJsonStructure([
            'data' => [
                'items' => [
                    ['id', 'name', 'category', 'category_label', 'description',
                     'plan_tier', 'locked', 'recommended', 'blocks_count',
                     'content', 'preview_layout'],
                ],
                'categories',
            ],
        ]);

        $items = collect($resp->json('data.items'));
        $this->assertSame(false, $items->firstWhere('id', $open->id)['locked']);
        $this->assertSame(true, $items->firstWhere('id', $locked->id)['locked']);

        // categories is the shared shape/persona map and is never empty.
        $this->assertNotEmpty($resp->json('data.categories'));
        // blocks_count reflects the snapshot's blocks.
        $this->assertSame(3, $items->firstWhere('id', $open->id)['blocks_count']);
    }

    public function test_index_orders_persona_recommended_templates_first(): void
    {
        $persona = PersonaCatalog::slugs()[0];
        $free    = $this->plan('free', 0);
        $user    = $this->makeUser($free, $persona);
        $link    = $this->makeLink($user);

        // Plain template sorts before the recommended one by sort_order,
        // but persona ordering must lift the recommended one to the top.
        $plain = $this->makeTemplate(['name' => 'Plain', 'sort_order' => 0, 'recommended_personas' => []]);
        $reco  = $this->makeTemplate(['name' => 'Recommended', 'sort_order' => 99, 'recommended_personas' => [$persona]]);

        $this->withToken($this->token($user));
        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates");

        $resp->assertOk();
        $ids = array_column($resp->json('data.items'), 'id');
        $this->assertSame($reco->id, $ids[0], 'persona-recommended template must sort first');
        $this->assertTrue(collect($resp->json('data.items'))->firstWhere('id', $reco->id)['recommended']);
    }

    public function test_index_rejects_non_biolink_family_links(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user, 'short');

        $this->withToken($this->token($user));
        $this->getJson("/api/v1/links/{$link->id}/page-templates")->assertStatus(403);
    }

    public function test_index_requires_authentication(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);

        $this->getJson("/api/v1/links/{$link->id}/page-templates")->assertStatus(401);
    }

    // ── apply ────────────────────────────────────────────────────────

    public function test_apply_succeeds_on_an_empty_link(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate();

        $this->withToken($this->token($user));
        $resp = $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id' => $tpl->id,
        ]);

        $resp->assertOk();
        $blocks = $resp->json('data.blocks');
        $this->assertCount(3, $blocks);
        $this->assertSame(['heading', 'paragraph', 'link'], array_column($blocks, 'type'));
        $this->assertSame(3, BiolinkBlock::where('link_id', $link->id)->count());
    }

    public function test_apply_returns_409_when_blocks_exist_without_confirm(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate();

        BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'heading',
            'sort_order' => 0,
            'settings'   => ['text' => 'Existing'],
        ]);

        $this->withToken($this->token($user));
        $resp = $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id' => $tpl->id,
        ]);

        $resp->assertStatus(409);
        $resp->assertJsonPath('error.code', 'confirm_overwrite');
        // The existing block must be untouched (no replacement happened).
        $this->assertSame(1, BiolinkBlock::where('link_id', $link->id)->count());
    }

    public function test_apply_with_confirm_overwrite_replaces_existing_blocks(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate();

        $old = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'heading',
            'sort_order' => 0,
            'settings'   => ['text' => 'Existing'],
        ]);

        $this->withToken($this->token($user));
        $resp = $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id'       => $tpl->id,
            'confirm_overwrite' => true,
        ]);

        $resp->assertOk();
        $this->assertCount(3, $resp->json('data.blocks'));
        // The pre-existing block is gone — replaced by the 3 template blocks.
        $this->assertNull(BiolinkBlock::find($old->id));
        $this->assertSame(3, BiolinkBlock::where('link_id', $link->id)->count());
        $this->assertSame(
            ['heading', 'paragraph', 'link'],
            BiolinkBlock::where('link_id', $link->id)->orderBy('sort_order')->pluck('type')->all()
        );
    }

    public function test_apply_returns_403_when_template_is_plan_locked(): void
    {
        $this->plan('pro', 10);
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);
        $tpl  = $this->makeTemplate(['plan_tier' => 'pro']);

        $this->withToken($this->token($user));
        $resp = $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id' => $tpl->id,
        ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.code', 'plan_required');
        // No blocks were created despite the lock.
        $this->assertSame(0, BiolinkBlock::where('link_id', $link->id)->count());
    }

    public function test_apply_validates_template_id(): void
    {
        $user = $this->makeUser($this->plan('free', 0));
        $link = $this->makeLink($user);

        $this->withToken($this->token($user));
        $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [])
            ->assertStatus(422);

        $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id' => 999999,
        ])->assertStatus(422);
    }

    public function test_apply_rejects_links_owned_by_another_user(): void
    {
        $owner = $this->makeUser($this->plan('free', 0));
        $other = $this->makeUser($this->plan('free2', 0));
        $link  = $this->makeLink($owner);
        $tpl   = $this->makeTemplate();

        $this->withToken($this->token($other));
        $this->postJson("/api/v1/links/{$link->id}/page-templates/apply", [
            'template_id' => $tpl->id,
        ])->assertStatus(404);
    }
}
