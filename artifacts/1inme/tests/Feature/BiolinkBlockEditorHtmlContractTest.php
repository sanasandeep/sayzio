<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the in-place editor's rendered-HTML contract. The drag-and-drop
 * editor never reloads the page — it splices server-rendered block markup
 * straight into the DOM, so it depends on the controllers returning that
 * markup under the right key:
 *
 *   - store() (top level)   -> `html`
 *   - store() into a card   -> `child_html` + `parent_id`
 *   - moveBlock() into card -> `child_html`
 *   - applyCard()           -> `html`
 *
 * All four endpoints render that markup only inside an `if ($request->ajax())`
 * branch (a plain request falls through to a 302 redirect), so every request
 * here sends `X-Requested-With: XMLHttpRequest` exactly as the editor's
 * front-end fetch calls do.
 */
class BiolinkBlockEditorHtmlContractTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeBiolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    /**
     * POST to the block store endpoint as the owner, mimicking the editor's
     * ajax fetch. Returns the TestResponse so callers can assert on the JSON.
     */
    private function storeBlock(User $owner, Link $link, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", $payload);
    }

    public function test_store_top_level_block_returns_rendered_html(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        $resp = $this->storeBlock($owner, $link, ['type' => 'link']);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        // Top-level adds return their markup under `html` — never `child_html`.
        $html = $resp->json('html');
        $this->assertIsString($html, 'store() of a top-level block must return rendered `html`');
        $this->assertNotEmpty($html);
        $this->assertNull($resp->json('child_html'),
            'top-level store() must not return `child_html`');
        $this->assertNull($resp->json('parent_id'),
            'top-level store() must not report a parent_id');

        // The markup references the block that was just created.
        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertNull($block->parent_id);
        $this->assertStringContainsString((string) $block->id, $html);
    }

    public function test_store_into_card_returns_child_html_and_parent_id(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        // A card container to nest the new block inside.
        $cardResp = $this->storeBlock($owner, $link, ['type' => 'card']);
        $cardResp->assertOk();
        $card = BiolinkBlock::where('link_id', $link->id)->where('type', 'card')->latest('id')->firstOrFail();

        $resp = $this->storeBlock($owner, $link, [
            'type'      => 'link',
            'parent_id' => $card->id,
        ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        // Nested adds return `child_html` + the owning card's id, and must
        // NOT return the top-level `html` key.
        $childHtml = $resp->json('child_html');
        $this->assertIsString($childHtml, 'store() into a card must return `child_html`');
        $this->assertNotEmpty($childHtml);
        $this->assertSame($card->id, $resp->json('parent_id'),
            'store() into a card must report the parent card id');
        $this->assertNull($resp->json('html'),
            'nested store() must not return the top-level `html` key');

        // The new block is actually parented to the card.
        $child = BiolinkBlock::where('link_id', $link->id)
            ->where('parent_id', $card->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($card->id, $child->parent_id);
        $this->assertStringContainsString((string) $child->id, $childHtml);
    }

    public function test_move_block_into_card_returns_child_html(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        // A card and a separate top-level block to drag into it.
        $this->storeBlock($owner, $link, ['type' => 'card'])->assertOk();
        $card = BiolinkBlock::where('link_id', $link->id)->where('type', 'card')->latest('id')->firstOrFail();

        $this->storeBlock($owner, $link, ['type' => 'link'])->assertOk();
        $block = BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'link')
            ->whereNull('parent_id')
            ->latest('id')
            ->firstOrFail();

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/move", [
                'parent_id' => $card->id,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        // Moving into a card returns the nested markup under `child_html`
        // plus the new parent id, never the top-level `html` key.
        $childHtml = $resp->json('child_html');
        $this->assertIsString($childHtml, 'moveBlock() into a card must return `child_html`');
        $this->assertNotEmpty($childHtml);
        $this->assertSame($card->id, $resp->json('parent_id'),
            'moveBlock() into a card must report the new parent id');
        $this->assertNull($resp->json('html'),
            'moveBlock() into a card must not return the top-level `html` key');

        // The block is now parented to the card on disk.
        $this->assertSame($card->id, $block->fresh()->parent_id);
        $this->assertStringContainsString((string) $block->id, $childHtml);
    }

    /**
     * Guards the new icon/image button-style layouts. The "Designs" gallery
     * persists the chosen placement via `_style.link_layout`; if the value
     * isn't whitelisted in `sanitizeBlockStyle()` it gets silently stripped
     * on save and the layout never renders. This asserts every new token
     * survives a round-trip through update().
     */
    public function test_new_link_layout_tokens_survive_sanitizer(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        $this->storeBlock($owner, $link, ['type' => 'link'])->assertOk();
        $block = BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'link')
            ->latest('id')
            ->firstOrFail();

        $layouts = [
            'icon_left', 'icon_right', 'icon_both', 'icon_only',
            'icon_circle_left', 'icon_circle_right', 'icon_box',
            'image_left', 'image_right', 'image_top',
            'image_icon_rounded', 'image_icon_square', 'image_icon_circle',
        ];

        foreach ($layouts as $layout) {
            $resp = $this->actingAs($owner)
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->put("/user/links/{$link->id}/blocks/{$block->id}", [
                    'style' => ['link_layout' => $layout],
                ]);

            $resp->assertOk();

            $saved = $block->fresh()->settings['_style']['link_layout'] ?? null;
            $this->assertSame($layout, $saved,
                "link_layout `{$layout}` must survive sanitizeBlockStyle()");
        }
    }

    public function test_apply_card_template_returns_rendered_html(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        // A minimal active card template. The snapshot only needs the
        // `card` shape — TemplateService re-runs every child through the
        // sanitizer, so a single bare link child is enough.
        $template = CardTemplate::create([
            'name'      => 'Test Card',
            'slug'      => 'test-card-' . Str::random(6),
            'category'  => 'general',
            'is_active' => true,
            'plan_tier' => null,
            'snapshot'  => [
                'type'      => 'card',
                'settings'  => ['title' => 'Hello'],
                'is_active' => true,
                'children'  => [
                    ['type' => 'link', 'settings' => ['text' => 'Click', 'url' => 'https://example.com'], 'is_active' => true],
                ],
            ],
        ]);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/templates/apply-card", [
                'template_id' => $template->id,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        // applyCard() returns the whole card subtree under `html` and the
        // id of the new card block.
        $html = $resp->json('html');
        $this->assertIsString($html, 'applyCard() must return rendered `html`');
        $this->assertNotEmpty($html);

        $blockId = $resp->json('block_id');
        $this->assertNotNull($blockId, 'applyCard() must report the new card block id');

        $card = BiolinkBlock::where('link_id', $link->id)->where('type', 'card')->latest('id')->firstOrFail();
        $this->assertSame($card->id, $blockId);
        $this->assertStringContainsString((string) $card->id, $html);
    }
}
