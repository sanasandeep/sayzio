<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BrandKitPageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the standalone Brand / Press Kit link type (Task #2663). Asserts:
 *   (a) creating a brand_kit link seeds settings['brand_kit'] from the owner's
 *       saved AI Brand Kit and defaults the page to public;
 *   (b) the public render returns 200 for a public page (showing brand assets)
 *       and enforces sign-in (gated 401) for a registered-visibility one;
 *   (c) the dedicated editor persists config (template, brand name, colours,
 *       sections) and flips page-level visibility (public <-> registered);
 *   (d) plan gating: the module toggle / cap blocks creation when off.
 *
 * Aliases are prefixed with a non-reserved letter ("zb"): the GET catch-all
 * /{alias} matcher rejects aliases that start with reserved single-letter
 * tokens like p/u/c/m/f.
 */
class BrandKitPageTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function alias(): string
    {
        return 'zb' . substr(Str::random(8), 0, 8);
    }

    private function visitPublic(string $alias, ?User $as = null)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $req = $as ? $this->actingAs($as) : $this;
        return $req->get('/' . $alias);
    }

    /** Save a representative AI Brand Kit for the user. */
    private function savedKit(User $u, bool $default = true): BrandKit
    {
        return BrandKit::create([
            'user_id'    => $u->id,
            'name'       => 'Acme Brand',
            'slug'       => 'acme' . Str::random(4),
            'is_default' => $default,
            'config'     => [
                'palette'  => ['primary' => '#112233', 'secondary' => '#445566', 'accent' => '#ff8800', 'neutrals' => ['#eeeeee', '#222222']],
                'fonts'    => ['heading' => 'Poppins', 'body' => 'Inter'],
                'voice'    => ['tone' => 'Confident and warm', 'descriptors' => ['bold', 'friendly']],
                'taglines' => ['Build it better', 'Ship with confidence'],
                'bio'      => 'Acme makes delightful tools for makers.',
            ],
        ]);
    }

    /** Create a brand_kit link directly (mirrors the seeding done by store()). */
    private function brandKitLink(User $u, string $visibility = 'public', array $config = []): Link
    {
        return $u->links()->create([
            'user_id'    => $u->id,
            'type'       => Link::TYPE_BRAND_KIT,
            'alias'      => $this->alias(),
            'is_active'  => true,
            'visibility' => $visibility,
            'settings'   => ['brand_kit' => BrandKitPageTemplates::normalize($config)],
        ]);
    }

    // ===== (a) creation seeds config from the saved kit + public default =====

    public function test_creating_brand_kit_seeds_config_from_saved_kit_and_public_visibility(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'module_brand_kit' => true, 'max_brand_kit_pages' => 5]));
        $kit = $this->savedKit($u);

        $this->actingAs($u)->post('/user/links', [
            'type'         => 'brand_kit',
            'alias'        => $this->alias(),
            'title'        => 'My Press Kit',
            'brand_kit_id' => $kit->id,
        ]);

        $link = $u->links()->where('type', 'brand_kit')->latest('id')->first();
        $this->assertNotNull($link);

        $cfg = $link->settings['brand_kit'] ?? null;
        $this->assertIsArray($cfg);
        // Seeded from the kit.
        $this->assertSame('Acme Brand', $cfg['brand_name']);
        $this->assertSame('#112233', $cfg['palette']['primary']);
        $this->assertSame('Poppins', $cfg['fonts']['heading']);
        $this->assertSame('Confident and warm', $cfg['voice']['tone']);
        $this->assertContains('Build it better', $cfg['taglines']);
        // Default template + public visibility.
        $this->assertSame(BrandKitPageTemplates::DEFAULT_ID, $cfg['template']);
        $this->assertSame('public', $link->visibility);
    }

    public function test_creating_brand_kit_without_saved_kit_still_seeds_a_shell(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'module_brand_kit' => true, 'max_brand_kit_pages' => 5]));

        $this->actingAs($u)->post('/user/links', [
            'type'  => 'brand_kit',
            'alias' => $this->alias(),
        ]);

        $link = $u->links()->where('type', 'brand_kit')->latest('id')->first();
        $this->assertNotNull($link);
        $cfg = $link->settings['brand_kit'] ?? null;
        $this->assertIsArray($cfg);
        // A coherent default shell so the editor/public render never blow up.
        $this->assertSame(BrandKitPageTemplates::DEFAULT_ID, $cfg['template']);
        $this->assertNotSame('', $cfg['brand_name']);
        $this->assertSame('public', $link->visibility);
    }

    // ===== (b) public render: 200 public, gated for registered =====

    public function test_public_brand_kit_renders_for_guest(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'public', [
            'brand_name' => 'VISIBLE-BRAND-NAME',
            'palette'    => ['primary' => '#abcdef'],
        ]);

        $resp = $this->visitPublic($link->alias);
        $resp->assertOk();
        $resp->assertSee('VISIBLE-BRAND-NAME', false);
        // Copy-able hex swatch appears upper-cased.
        $resp->assertSee('#ABCDEF', false);
    }

    public function test_registered_brand_kit_blocks_guest_with_gated_view(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'registered');

        $resp = $this->get('/' . $link->alias);
        $resp->assertStatus(401);
    }

    public function test_registered_brand_kit_renders_for_signed_in_visitor(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'registered', ['brand_name' => 'GATED-BRAND']);

        $visitor = $this->user($this->plan(['max_links' => 100]));
        $resp = $this->visitPublic($link->alias, $visitor);
        $resp->assertOk();
        $resp->assertSee('GATED-BRAND', false);
    }

    public function test_owner_can_view_their_own_registered_brand_kit(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'registered');

        $resp = $this->visitPublic($link->alias, $u);
        $resp->assertOk();
    }

    // ===== (c) editor persists config + flips visibility =====

    public function test_editor_update_persists_config_and_flips_visibility(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'public');

        $explicit = collect(BrandKitPageTemplates::ids())
            ->first(fn ($id) => $id !== BrandKitPageTemplates::DEFAULT_ID);

        $this->actingAs($u)->post('/user/links/' . $link->id . '/brand-kit', [
            'template'      => $explicit,
            'is_public'     => 0,
            'brand_name'    => 'Renamed Brand',
            'tagline'       => 'New tagline',
            'palette'       => ['primary' => '#010203', 'accent' => '#0a0b0c'],
            'fonts'         => ['heading' => 'Lora', 'body' => 'Roboto'],
            'voice'         => ['tone' => 'Playful', 'descriptors' => ['fun', 'quirky']],
            'taglines'      => ['One', 'Two'],
            'logos'         => [['label' => 'PNG', 'url' => 'https://cdn.example.com/logo.png']],
            'socials'       => [['label' => 'X', 'url' => 'https://x.com/acme']],
            'contact_email' => 'press@acme.com',
            'sections'      => ['logos' => 1, 'colors' => 1],
        ])->assertRedirect();

        $link->refresh();
        $cfg = $link->settings['brand_kit'];
        $this->assertSame($explicit, $cfg['template']);
        $this->assertSame('Renamed Brand', $cfg['brand_name']);
        $this->assertSame('#010203', $cfg['palette']['primary']);
        $this->assertSame('Lora', $cfg['fonts']['heading']);
        $this->assertSame('Playful', $cfg['voice']['tone']);
        $this->assertSame('https://cdn.example.com/logo.png', $cfg['logos'][0]['url']);
        $this->assertSame('press@acme.com', $cfg['contact_email']);
        // Unchecked sections normalise to false.
        $this->assertFalse($cfg['sections']['voice']);
        $this->assertSame('registered', $link->visibility);

        // Flip back registered -> public.
        $this->actingAs($u)->post('/user/links/' . $link->id . '/brand-kit', [
            'template'  => BrandKitPageTemplates::DEFAULT_ID,
            'is_public' => 1,
        ])->assertRedirect();

        $link->refresh();
        $this->assertSame('public', $link->visibility);
    }

    public function test_editor_update_rejects_unknown_template(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($u, 'public');

        $this->actingAs($u)->post('/user/links/' . $link->id . '/brand-kit', [
            'template'  => 'not-a-real-template',
            'is_public' => 1,
        ])->assertSessionHasErrors('template');
    }

    public function test_editor_rejects_non_owner(): void
    {
        $owner = $this->user($this->plan(['max_links' => 100]));
        $link = $this->brandKitLink($owner, 'public');

        $other = $this->user($this->plan(['max_links' => 100]));
        $resp = $this->actingAs($other)->post('/user/links/' . $link->id . '/brand-kit', [
            'template'  => BrandKitPageTemplates::DEFAULT_ID,
            'is_public' => 1,
        ]);
        $this->assertContains($resp->status(), [403, 404]);
    }

    // ===== (d) plan gating =====

    public function test_creation_blocked_when_module_off(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'module_brand_kit' => false]));

        $this->actingAs($u)->post('/user/links', [
            'type'  => 'brand_kit',
            'alias' => $this->alias(),
        ]);

        $this->assertNull($u->links()->where('type', 'brand_kit')->first());
    }

    public function test_creation_blocked_when_cap_zero(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'module_brand_kit' => true, 'max_brand_kit_pages' => 0]));

        $this->actingAs($u)->post('/user/links', [
            'type'  => 'brand_kit',
            'alias' => $this->alias(),
        ]);

        $this->assertNull($u->links()->where('type', 'brand_kit')->first());
    }
}
