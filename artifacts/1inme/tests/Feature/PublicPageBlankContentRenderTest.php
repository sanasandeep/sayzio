<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web-side counterpart of the mobile blank-content render test
 * (artifacts/1inme-mobile/scripts/test-blank-content-render.mjs).
 *
 * The public biolink renderer relies on `??` fallbacks (e.g.
 * `$s['text'] ?? 'Click Here'`), which must treat an explicitly-blank
 * content key ('') as a real blank — only a *missing* key may fall back
 * to the sample label. A `?:`-style fallback would re-inject sample text
 * on blanks; the static guard (scripts/src/check-blank-content-fallbacks.ts)
 * flags that pattern, and this test proves the runtime behaviour on the
 * actual public page for representative blocks (CTA button + tip jar),
 * including the full pipeline from a blanked admin default through
 * block seeding to the public render.
 */
class PublicPageBlankContentRenderTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($u);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function biolink(User $owner): Link
    {
        // Aliases lead with a non-reserved prefix so the /{alias}
        // catch-all matcher accepts them.
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => 'zb' . Str::lower(Str::random(10)),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function block(Link $link, string $type, array $settings): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id'   => $link->id,
            'type'      => $type,
            'settings'  => $settings,
            'is_active' => true,
        ]);
    }

    private function visitPublic(string $alias)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return $this->get('/' . $alias);
    }

    /** The tip jar only renders when the creator can accept charges. */
    private function enableTips(User $owner): void
    {
        CreatorPaymentConnection::create([
            'user_id'         => $owner->id,
            'provider'        => 'stripe_connect',
            'account_id'      => 'acct_' . Str::random(8),
            'status'          => 'active',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'is_default'      => true,
        ]);
    }

    // ── CTA button ──────────────────────────────────────────────────

    public function test_cta_button_with_explicitly_blank_text_renders_no_sample_label(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        // Explicit '' — the renderer's `?? 'Click Here'` must NOT kick in.
        $this->block($link, 'cta_button', [
            'text' => '',
            'url'  => 'https://example.org/dest',
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Click Here');
    }

    public function test_cta_button_with_missing_text_key_falls_back_to_sample_label(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        // No 'text' key at all — the `??` fallback should appear.
        $this->block($link, 'cta_button', [
            'url' => 'https://example.org/dest',
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertSee('Click Here');
    }

    // ── Tip jar ─────────────────────────────────────────────────────

    public function test_tip_jar_with_explicitly_blank_title_renders_no_sample_title(): void
    {
        $owner = $this->owner();
        $this->enableTips($owner);
        $link = $this->biolink($owner);

        $this->block($link, 'tip_jar', [
            'title'       => '',
            'button_text' => '',
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Send me a tip');
        $resp->assertDontSee('Send Tip');
    }

    public function test_tip_jar_with_missing_title_key_falls_back_to_sample_title(): void
    {
        $owner = $this->owner();
        $this->enableTips($owner);
        $link = $this->biolink($owner);

        $this->block($link, 'tip_jar', []);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertSee('Send me a tip');
        $resp->assertSee('Send Tip');
    }

    // ── List blocks (array-shaped content) ─────────────────────────
    //
    // The public list renderer (common/blocks/list.blade.php) iterates
    // `$s['items'] ?? []`; an explicitly-empty items array must render
    // zero items, while a block seeded through the real store() pipeline
    // (no items provided) carries the sample items from BlockDefaults.

    public function test_list_with_explicitly_empty_items_renders_no_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'list', [
            'style' => 'clean',
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('First item');
        $resp->assertDontSee('replace with your own');
    }

    public function test_list_numbered_with_explicitly_empty_items_renders_no_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'list_numbered', [
            'style' => 'clean',
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('First step');
        $resp->assertDontSee('keep going');
    }

    public function test_list_seeded_via_store_with_no_items_shows_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        // Seed through the real store() pipeline — the missing items key
        // must fall back to the BlockDefaults sample items.
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'list']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('First item');
        $public->assertSee('replace with your own');
    }

    public function test_blanked_admin_default_items_seed_list_that_renders_blank(): void
    {
        // Admin explicitly blanks the list sample items platform-wide.
        BlockDefaults::saveAdminOverrideForType('list', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'list']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('First item');
        $public->assertDontSee('replace with your own');
    }

    // ── Socials blocks (array-shaped platform lists) ────────────────
    //
    // The public socials renderer (common/blocks/socials.blade.php)
    // iterates `$s['platforms'] ?? []`, and the profile-card renderer
    // (common/biolink-profile-card.blade.php) normalises `$s['socials']`.
    // An explicitly-empty array must render zero sample handles, while a
    // block seeded through the real store() pipeline (no socials given)
    // carries the seeded `yourhandle` sample links from BlockDefaults.

    public function test_socials_with_explicitly_empty_platforms_renders_no_sample_handles(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'socials', [
            'platforms' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('yourhandle');
    }

    public function test_socials_seeded_via_store_with_no_platforms_shows_sample_handles(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        // Seed through the real store() pipeline — the missing platforms
        // key must fall back to the BlockDefaults sample handles.
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'socials']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The sample URLs are routed through the block click tracker's
        // ?to= param, so the handle survives urlencode() verbatim.
        $public->assertSee('yourhandle');
    }

    public function test_blanked_admin_default_platforms_seed_socials_that_render_blank(): void
    {
        // Admin explicitly blanks the socials sample handles platform-wide.
        BlockDefaults::saveAdminOverrideForType('socials', [
            'content' => ['platforms' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'socials']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['platforms'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty platforms array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('yourhandle');
    }

    // ── Profile card (seeded socials list) ─────────────────────────

    public function test_profile_card_with_explicitly_empty_socials_renders_no_sample_handles(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'profile_card_v1', [
            'name'    => 'Real Name',
            'socials' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('yourhandle');
    }

    public function test_profile_card_seeded_via_store_with_no_socials_shows_sample_handles(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'profile_card_v1']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertNotEmpty($block->settings['socials'] ?? [],
            'pre-condition: the seeded block must carry the sample socials');

        // The default classic_creator layout has no socials row — switch
        // the structural layout token to one that renders it (glass), as
        // the profile_identity designs do.
        $settings = $block->settings;
        $settings['_style']['_profile_layout'] = 'glass';
        $block->update(['settings' => $settings]);

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('yourhandle');
    }

    public function test_blanked_admin_default_socials_seed_profile_card_that_renders_blank(): void
    {
        // Admin explicitly blanks the profile-card sample socials.
        BlockDefaults::saveAdminOverrideForType('profile_card_v1', [
            'content' => ['socials' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'profile_card_v1']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['socials'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty socials array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('yourhandle');
    }

    // ── Image grid / sliders (array-shaped media content) ──────────
    //
    // The public renderer iterates `$s['images'] ?? []` (grid) or
    // json_encodes it into the Alpine slider state. An explicitly-empty
    // images array must render zero placeholder media, while a block
    // seeded through the real store() pipeline carries the sample
    // `block-placeholders/*.svg` images from BlockDefaults.

    public function test_image_grid_with_explicitly_empty_images_renders_no_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'image_grid', [
            'images'  => [],
            'columns' => 3,
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('block-placeholders');
    }

    public function test_image_grid_seeded_via_store_shows_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image_grid']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('block-placeholders/image.svg');
    }

    public function test_blanked_admin_default_images_seed_image_grid_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('image_grid', [
            'content' => ['images' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image_grid']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['images'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty images array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('block-placeholders');
    }

    public function test_image_slider_with_explicitly_empty_images_renders_no_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'image_slider', [
            'images' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('block-placeholders');
    }

    public function test_image_slider_seeded_via_store_shows_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image_slider']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The slider json_encodes the images into the Alpine x-data
        // attribute; json_encode escapes '/' so match a slash-free chunk.
        $public->assertSee('block-placeholders');
    }

    public function test_image_slider_v2_with_explicitly_empty_images_renders_no_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'image_slider_v2', [
            'images' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('block-placeholders');
    }

    public function test_image_slider_v2_seeded_via_store_shows_placeholder_media(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image_slider_v2']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('block-placeholders');
    }

    // ── Card slider / scroll cards (array-shaped card content) ─────
    //
    // The public renderer iterates `$s['cards'] ?? $s['items'] ?? []`;
    // an explicitly-empty cards array must render zero sample cards.

    public function test_card_slider_with_explicitly_empty_cards_renders_no_sample_cards(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'card_slider', [
            'cards' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Card one');
        $resp->assertDontSee('Replace these placeholder cards');
    }

    public function test_card_slider_seeded_via_store_shows_sample_cards(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'card_slider']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Card one');
        $public->assertSee('Replace these placeholder cards');
    }

    public function test_blanked_admin_default_cards_seed_card_slider_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('card_slider', [
            'content' => ['cards' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'card_slider']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['cards'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty cards array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Card one');
        $public->assertDontSee('Replace these placeholder cards');
    }

    public function test_scroll_cards_with_explicitly_empty_cards_renders_no_sample_cards(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'scroll_cards', [
            'cards' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Card one');
        $resp->assertDontSee('Up to a dozen cards work nicely here');
    }

    public function test_scroll_cards_seeded_via_store_shows_sample_cards(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'scroll_cards']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Card one');
        $public->assertSee('Up to a dozen cards work nicely here');
    }

    // ── Pricing list (array-shaped items) ──────────────────────────
    //
    // common/blocks/list-pricing.blade.php normalises `$s['items'] ?? []`;
    // an explicitly-empty items array must render zero sample tiers.

    public function test_list_pricing_with_explicitly_empty_items_renders_no_sample_tiers(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'list_pricing', [
            'style' => 'classic',
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Starter');
        $resp->assertDontSee('$29');
    }

    public function test_list_pricing_seeded_via_store_shows_sample_tiers(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'list_pricing']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The seeded default 'classic' style renders name + price only.
        $public->assertSee('Starter');
        $public->assertSee('$29');
    }

    public function test_blanked_admin_default_items_seed_list_pricing_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('list_pricing', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'list_pricing']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Starter');
        $public->assertDontSee('$29');
    }

    // ── FAQ (array-shaped Q&A items) ────────────────────────────────
    //
    // common/blocks/faq.blade.php iterates `$s['items'] ?? []`; an
    // explicitly-empty items array must render zero sample questions.

    public function test_faq_with_explicitly_empty_items_renders_no_sample_questions(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'faq', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('How do I get started?');
        $resp->assertDontSee('Do you offer support?');
    }

    public function test_faq_seeded_via_store_shows_sample_questions(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'faq']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('How do I get started?');
        $public->assertSee('Do you offer support?');
    }

    public function test_blanked_admin_default_items_seed_faq_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('faq', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'faq']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('How do I get started?');
        $public->assertDontSee('Do you offer support?');
    }

    // ── Progress bars (array-shaped goal items) ─────────────────────
    //
    // common/blocks/progress.blade.php iterates `$s['items'] ?? []`;
    // an explicitly-empty items array must render zero sample goals.

    public function test_progress_with_explicitly_empty_items_renders_no_sample_goals(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'progress', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Goal one');
        $resp->assertDontSee('Goal two');
    }

    public function test_progress_seeded_via_store_shows_sample_goals(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'progress']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The renderer shows label + value; both sample labels survive.
        $public->assertSee('Goal one');
        $public->assertSee('Goal two');
    }

    public function test_blanked_admin_default_items_seed_progress_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('progress', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'progress']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Goal one');
        $public->assertDontSee('Goal two');
    }

    // ── Menu (array-shaped sections of dishes) ──────────────────────
    //
    // common/blocks/menu.blade.php normalises `$s['sections']`/`$s['items']`
    // into sections; an explicitly-empty sections array renders the empty
    // state ("No menu items yet") and zero sample dishes.

    public function test_menu_with_explicitly_empty_sections_renders_no_sample_dishes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'menu', [
            'sections' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('House focaccia');
        $resp->assertDontSee('Margherita pizza');
        $resp->assertSee('No menu items yet');
    }

    public function test_menu_seeded_via_store_shows_sample_dishes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'menu']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('House focaccia');
        $public->assertSee('Margherita pizza');
    }

    public function test_blanked_admin_default_sections_seed_menu_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('menu', [
            'content' => ['sections' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'menu']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['sections'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty sections array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('House focaccia');
        $public->assertDontSee('Margherita pizza');
    }

    // ── Menu section (array-shaped dish items) ──────────────────────
    //
    // common/blocks/menu-section.blade.php iterates a normalised
    // `$s['items']`; an explicitly-empty items array must render zero
    // sample dishes (the section name may still show).

    public function test_menu_section_with_explicitly_empty_items_renders_no_sample_dishes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'menu_section', [
            'name'  => 'Mains',
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Margherita pizza');
        $resp->assertDontSee('Cacio e pepe');
    }

    public function test_menu_section_seeded_via_store_shows_sample_dishes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'menu_section']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Margherita pizza');
        $public->assertSee('Cacio e pepe');
    }

    public function test_blanked_admin_default_items_seed_menu_section_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('menu_section', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'menu_section']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Margherita pizza');
        $public->assertDontSee('Cacio e pepe');
    }

    // ── Event list (array-shaped events) ────────────────────────────
    //
    // common/blocks/event-list.blade.php falls back through
    // `$s['events']`/`$s['items']`; an explicitly-empty events array
    // renders the empty state ("No events yet") and zero sample events.

    public function test_event_list_with_explicitly_empty_events_renders_no_sample_events(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'event_list', [
            'events' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Live Q&amp;A on YouTube', false);
        $resp->assertDontSee('Pop-up workshop');
        $resp->assertSee('No events yet');
    }

    public function test_event_list_seeded_via_store_shows_sample_events(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'event_list']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // Event titles survive every layout branch of the renderer.
        $public->assertSee('Live Q&A on YouTube');
        $public->assertSee('Pop-up workshop');
    }

    public function test_blanked_admin_default_events_seed_event_list_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('event_list', [
            'content' => ['events' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'event_list']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['events'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty events array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Live Q&amp;A on YouTube', false);
        $public->assertDontSee('Pop-up workshop');
    }

    // ── Testimonials (array-shaped items) ───────────────────────────
    //
    // common/blocks/testimonials.blade.php iterates `$s['items'] ?? []`;
    // an explicitly-empty items array must render zero sample quotes.

    public function test_testimonials_with_explicitly_empty_items_renders_no_sample_quotes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'testimonials', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('A glowing testimonial goes here');
        $resp->assertDontSee('Alex Carter');
    }

    public function test_testimonials_seeded_via_store_shows_sample_quotes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'testimonials']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('A glowing testimonial goes here');
        $public->assertSee('Alex Carter');
    }

    public function test_blanked_admin_default_items_seed_testimonials_that_render_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('testimonials', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'testimonials']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('A glowing testimonial goes here');
        $public->assertDontSee('Alex Carter');
    }

    // ── Testimonial carousel (array-shaped items) ───────────────────
    //
    // common/blocks/testimonial-carousel.blade.php normalises
    // `$s['items']`; the default carousel layout renders the "No
    // testimonials yet" empty state when the array is explicitly empty.

    public function test_testimonial_carousel_with_explicitly_empty_items_renders_no_sample_quotes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'testimonial_carousel', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Genuinely the best service');
        $resp->assertSee('No testimonials yet');
    }

    public function test_testimonial_carousel_seeded_via_store_shows_sample_quotes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'testimonial_carousel']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The quote survives every carousel/stack layout branch.
        $public->assertSee('Genuinely the best service');
    }

    // ── Timeline / staged timeline (array-shaped items) ─────────────
    //
    // The inline renderer branch iterates `$s['items'] ?? []` for both
    // timeline flavours; an explicitly-empty array renders zero entries.

    public function test_timeline_with_explicitly_empty_items_renders_no_sample_entries(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'timeline', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Got started');
        $resp->assertDontSee('Hit a milestone');
    }

    public function test_timeline_seeded_via_store_shows_sample_entries(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'timeline']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Got started');
        $public->assertSee('Hit a milestone');
    }

    public function test_blanked_admin_default_items_seed_timeline_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('timeline', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'timeline']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Got started');
        $public->assertDontSee('Hit a milestone');
    }

    public function test_timeline_staged_with_explicitly_empty_items_renders_no_sample_stages(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'timeline_staged', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Replace with your first stage.');
    }

    public function test_timeline_staged_seeded_via_store_shows_sample_stages(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'timeline_staged']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // The stage description survives the renderer (titles carry an
        // em dash that HTML-escaping would complicate matching on).
        $public->assertSee('Replace with your first stage.');
    }

    // ── Catalog / market (array-shaped product items) ───────────────
    //
    // The inline renderer branch iterates `$s['items'] ?? []` for both
    // catalog flavours; an explicitly-empty array renders zero items.

    public function test_catalog_with_explicitly_empty_items_renders_no_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'catalog', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Sample Item 1');
        $resp->assertDontSee('$19');
    }

    public function test_catalog_seeded_via_store_shows_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'catalog']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Sample Item 1');
        $public->assertSee('$19');
    }

    public function test_blanked_admin_default_items_seed_catalog_that_renders_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('catalog', [
            'content' => ['items' => []],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'catalog']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame([], $block->settings['items'] ?? null,
            'pre-condition: the seeded block must carry the explicit empty items array');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertDontSee('Sample Item 1');
        $public->assertDontSee('$19');
    }

    public function test_market_with_explicitly_empty_items_renders_no_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'market', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Another Product');
        $resp->assertDontSee('$49');
    }

    public function test_market_seeded_via_store_shows_sample_items(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'market']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Another Product');
        $public->assertSee('$49');
    }

    // ── Nav menu (array-shaped menu items) ──────────────────────────
    //
    // The inline renderer branch iterates `$s['items'] ?? []`. The
    // store() pipeline's sanitizeUrl() blanks the sample '#about' /
    // '#contact' anchors (non-http), so assert on the rendered anchor
    // labels (raw '>About</a>' markup keeps the match specific).

    public function test_nav_menu_with_explicitly_empty_items_renders_no_sample_links(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'nav_menu', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('>About</a>', false);
        $resp->assertDontSee('>Contact</a>', false);
    }

    public function test_nav_menu_seeded_via_store_shows_sample_links(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'nav_menu']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('>About</a>', false);
        $public->assertSee('>Contact</a>', false);
    }

    // ── Ticker (array of plain strings) ─────────────────────────────

    public function test_ticker_with_explicitly_empty_items_renders_no_sample_announcements(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'ticker', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Breaking news');
        $resp->assertDontSee('Replace with your own announcements');
    }

    public function test_ticker_seeded_via_store_shows_sample_announcements(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'ticker']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Breaking news');
        $public->assertSee('Replace with your own announcements');
    }

    // ── Tabs (array-shaped tab panes under the 'tabs' key) ──────────
    //
    // common/blocks/tabs.blade.php normalises `$s['tabs']`; an
    // explicitly-empty array renders the "Add tabs" empty state.

    public function test_tabs_with_explicitly_empty_tabs_renders_no_sample_panes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'tabs', [
            'tabs' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Replace with what you offer.');
        $resp->assertSee('Add tabs to get started');
    }

    public function test_tabs_seeded_via_store_shows_sample_panes(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'tabs']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Services');
        $public->assertSee('Replace with what you offer.');
    }

    // ── Accordion (array-shaped items) ──────────────────────────────

    public function test_accordion_with_explicitly_empty_items_renders_no_sample_rows(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'accordion', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('How does it work?');
        $resp->assertDontSee('Where can I learn more?');
    }

    public function test_accordion_seeded_via_store_shows_sample_rows(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'accordion']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('How does it work?');
        $public->assertSee('Where can I learn more?');
    }

    // ── Link tree group (array-shaped link items) ───────────────────

    public function test_link_tree_group_with_explicitly_empty_items_renders_no_sample_links(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'link_tree_group', [
            'title' => 'My Links',
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('My website');
        $resp->assertDontSee('Latest project');
    }

    public function test_link_tree_group_seeded_via_store_shows_sample_links(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'link_tree_group']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('My website');
        $public->assertSee('Latest project');
    }

    // ── File list (array-shaped file items) ─────────────────────────

    public function test_file_list_with_explicitly_empty_items_renders_no_sample_files(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'file_list', [
            'items' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Placeholder document.pdf');
        $resp->assertSee('No files yet');
    }

    public function test_file_list_seeded_via_store_shows_sample_files(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'file_list']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Placeholder document.pdf');
    }

    // ── Audio list (array-shaped tracks under the 'tracks' key) ─────

    public function test_audio_list_with_explicitly_empty_tracks_renders_no_sample_tracks(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $this->block($link, 'audio_list', [
            'tracks' => [],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Placeholder track');
        $resp->assertDontSee('SoundHelix');
        $resp->assertSee('No tracks yet');
    }

    public function test_audio_list_seeded_via_store_shows_sample_tracks(): void
    {
        $owner = $this->owner();
        $link  = $this->biolink($owner);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'audio_list']);
        $resp->assertOk();

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        $public->assertSee('Placeholder track');
        $public->assertSee('SoundHelix');
    }

    // Note: `insider` (settings posts) and `fan_leaderboard` (settings
    // fans) render via DB-driven community partials that never read the
    // seeded arrays on the public page, so a blanked array cannot leak
    // there and they are intentionally not covered here.

    // ── Full pipeline: blanked admin default → seeded block → render ──

    public function test_blanked_admin_default_seeds_block_that_renders_blank_on_public_page(): void
    {
        // Admin explicitly blanks the CTA sample text platform-wide.
        BlockDefaults::saveAdminOverrideForType('cta_button', [
            'content' => ['text' => ''],
        ]);

        $owner = $this->owner();
        $link  = $this->biolink($owner);

        // Seed the block through the real store() pipeline so the
        // blanked default flows through contentForType()/seededSettings().
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'cta_button']);
        $resp->assertOk();

        $block = BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
        $this->assertSame('', $block->settings['text'] ?? null,
            'pre-condition: the seeded block must carry the explicit blank');

        $public = $this->visitPublic($link->alias);
        $public->assertOk();
        // Neither the system sample ("Get started") nor the renderer
        // fallback ("Click Here") may leak onto the public page.
        $public->assertDontSee('Get started');
        $public->assertDontSee('Click Here');
    }
}
