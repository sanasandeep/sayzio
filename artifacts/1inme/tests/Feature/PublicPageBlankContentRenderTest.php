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
