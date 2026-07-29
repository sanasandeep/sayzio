<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BiolinkStickers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Page stickers — decorative emoji/image overlays on biolink pages:
 *
 *  - BiolinkStickers::sanitize bounds every field (kind, value, x/y,
 *    rotation, scale, layer) and caps the list at 10.
 *  - Web page-settings save persists sanitized stickers_json wholesale
 *    (shorter lists never keep stale trailing items).
 *  - Design-locked pages reject sticker changes (web + API).
 *  - API PATCH replaces the list wholesale (no numeric-key deep-merge
 *    leftovers); public biolink API payload exposes sanitized stickers.
 *  - Public page renders sticker layers; emoji values are HTML-escaped.
 */
class PageStickersTest extends TestCase
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

    // ── sanitizer ────────────────────────────────────────────────────

    public function test_sanitizer_bounds_every_field(): void
    {
        $out = BiolinkStickers::sanitize([
            ['kind' => 'emoji', 'value' => '🔥', 'x' => 250, 'y' => -10, 'rotation' => 999, 'scale' => 99, 'layer' => 'nope'],
            ['kind' => 'image', 'value' => 'javascript:alert(1)'],
            ['kind' => 'image', 'value' => 'https://example.com/a.png', 'scale' => 0.01, 'layer' => 'back'],
            ['kind' => 'emoji', 'value' => '<script>x</script>😀'],
            ['kind' => 'emoji', 'value' => ''],
            'not-an-array',
        ]);

        $this->assertCount(3, $out);
        $this->assertSame(['kind' => 'emoji', 'value' => '🔥', 'x' => 100.0, 'y' => 0.0, 'rotation' => 180, 'scale' => 3.0, 'layer' => 'front'], $out[0]);
        $this->assertSame('image', $out[1]['kind']);
        $this->assertSame(0.4, $out[1]['scale']);
        $this->assertSame('back', $out[1]['layer']);
        $this->assertSame('x😀', $out[2]['value']); // tags stripped
    }

    public function test_sanitizer_caps_at_ten_and_accepts_json_string(): void
    {
        $many = array_fill(0, 15, ['kind' => 'emoji', 'value' => '⭐']);
        $this->assertCount(10, BiolinkStickers::sanitize($many));
        $this->assertCount(10, BiolinkStickers::sanitize(json_encode($many)));
        $this->assertSame([], BiolinkStickers::sanitize('not-json'));
        $this->assertSame([], BiolinkStickers::sanitize(null));
    }

    // ── web save ─────────────────────────────────────────────────────

    public function test_web_save_persists_and_replaces_wholesale(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->actingAs($user)->post(route('user.links.page-settings', $link), [
            'stickers_json' => json_encode([
                ['kind' => 'emoji', 'value' => '🔥', 'x' => 10, 'y' => 20, 'rotation' => -15, 'scale' => 1.5, 'layer' => 'front'],
                ['kind' => 'emoji', 'value' => '⭐', 'x' => 80, 'y' => 70, 'rotation' => 10, 'scale' => 1, 'layer' => 'back'],
            ]),
        ]);

        $stickers = $link->fresh()->settings['biolink']['stickers'];
        $this->assertCount(2, $stickers);
        $this->assertSame('🔥', $stickers[0]['value']);

        // Save a SHORTER list — no stale trailing items may survive.
        $this->actingAs($user)->post(route('user.links.page-settings', $link), [
            'stickers_json' => json_encode([
                ['kind' => 'emoji', 'value' => '💎', 'x' => 50, 'y' => 50],
            ]),
        ]);

        $stickers = $link->fresh()->settings['biolink']['stickers'];
        $this->assertCount(1, $stickers);
        $this->assertSame('💎', $stickers[0]['value']);
    }

    public function test_web_save_without_stickers_field_leaves_stickers_untouched(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $link->update(['settings' => ['biolink' => ['stickers' => BiolinkStickers::sanitize([
            ['kind' => 'emoji', 'value' => '🚀'],
        ])]]]);

        $this->actingAs($user)->post(route('user.links.page-settings', $link), [
            'biolink_description' => 'hello',
        ]);

        $this->assertSame('🚀', $link->fresh()->settings['biolink']['stickers'][0]['value']);
    }

    public function test_design_locked_page_rejects_web_sticker_changes(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $link->update(['settings' => ['biolink' => ['design_locked' => ['template_id' => 1]]]]);

        $this->actingAs($user)->post(route('user.links.page-settings', $link), [
            'stickers_json' => json_encode([['kind' => 'emoji', 'value' => '🔥']]),
        ]);

        $this->assertArrayNotHasKey('stickers', $link->fresh()->settings['biolink']);
    }

    // ── API save / read parity ───────────────────────────────────────

    public function test_api_patch_replaces_sticker_list_wholesale(): void
    {
        $user  = $this->makeUser();
        $link  = $this->makeLink($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/links/{$link->id}", [
            'settings' => ['biolink' => ['stickers' => [
                ['kind' => 'emoji', 'value' => '🔥'],
                ['kind' => 'emoji', 'value' => '⭐'],
                ['kind' => 'emoji', 'value' => '💫'],
            ]]],
        ])->assertOk();

        $this->assertCount(3, $link->fresh()->settings['biolink']['stickers']);

        // Shorter patched list must not keep stale trailing items
        // (array_replace_recursive would merge numeric keys element-wise).
        $this->withToken($token)->patchJson("/api/v1/links/{$link->id}", [
            'settings' => ['biolink' => ['stickers' => [
                ['kind' => 'emoji', 'value' => '👑'],
            ]]],
        ])->assertOk();

        $stickers = $link->fresh()->settings['biolink']['stickers'];
        $this->assertCount(1, $stickers);
        $this->assertSame('👑', $stickers[0]['value']);
    }

    public function test_api_patch_on_design_locked_page_strips_stickers(): void
    {
        $user  = $this->makeUser();
        $link  = $this->makeLink($user);
        $link->update(['settings' => ['biolink' => ['design_locked' => ['template_id' => 1]]]]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/links/{$link->id}", [
            'settings' => ['biolink' => ['stickers' => [['kind' => 'emoji', 'value' => '🔥']]]],
        ])->assertOk();

        $this->assertArrayNotHasKey('stickers', $link->fresh()->settings['biolink']);
    }

    public function test_public_biolink_api_payload_includes_sanitized_stickers(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $link->update(['settings' => ['biolink' => ['stickers' => [
            ['kind' => 'emoji', 'value' => '🔥', 'x' => 10, 'y' => 20, 'rotation' => -15, 'scale' => 1.5, 'layer' => 'front'],
        ]]]]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $res = $this->getJson("/api/v1/biolinks/{$link->alias}")->assertOk();
        $stickers = $res->json('data.biolink.stickers');
        $this->assertCount(1, $stickers);
        $this->assertSame('🔥', $stickers[0]['value']);
        $this->assertSame(-15, $stickers[0]['rotation']);
    }

    // ── public render ────────────────────────────────────────────────

    public function test_public_page_renders_sticker_layers_and_escapes_values(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $link->update(['settings' => ['biolink' => ['stickers' => [
            ['kind' => 'emoji', 'value' => '🔥', 'x' => 12.5, 'y' => 20, 'rotation' => -15, 'scale' => 1, 'layer' => 'front'],
            ['kind' => 'image', 'value' => 'https://example.com/a.png', 'x' => 80, 'y' => 60, 'layer' => 'back'],
        ]]]]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('page-stickers-front', $html);
        $this->assertStringContainsString('page-stickers-back', $html);
        $this->assertStringContainsString('🔥', $html);
        $this->assertStringContainsString('https://example.com/a.png', $html);
        $this->assertStringContainsString('left:12.5%', $html);
        $this->assertStringContainsString('--st-rot:-15deg', $html);
    }

    public function test_public_page_without_stickers_renders_no_layer(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringNotContainsString('page-stickers-front', $html);
        $this->assertStringNotContainsString('page-stickers-back', $html);
    }
}
