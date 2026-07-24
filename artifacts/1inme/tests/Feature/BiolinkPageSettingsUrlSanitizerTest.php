<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Route-level companion to Tests\Unit\BiolinkBlockUrlSanitizerTest: proves the
 * page-settings update flow (POST /user/links/{link}/page-settings) actually
 * runs sanitizeUrl over the page-level URL fields — custom_branding_url,
 * custom_branding_logo, favicon_url (the Link.favicon column), and the
 * touch-icon favicons map — so an unsafe value (javascript:, //evil.com,
 * /f/\evil) submitted through the real HTTP endpoint ends up blanked in the
 * persisted settings, while safe https and /f/... vault URLs round-trip
 * unchanged. Guards against a new settings field bypassing the sanitizer.
 */
class BiolinkPageSettingsUrlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(): User
    {
        $plan = $this->plan([
            'max_links' => 100, 'max_biolinks' => 5,
            'custom_branding' => true, 'custom_favicon' => true,
        ]);
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function biolink(User $u): Link
    {
        return $u->links()->create([
            'user_id' => $u->id, 'type' => 'biolink',
            'alias'   => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    private function postSettings(User $u, Link $link, array $payload)
    {
        return $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', $payload);
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript scheme'      => ['javascript:alert(1)'],
            'protocol-relative host' => ['//evil.com/x.png'],
            'backslash smuggling'    => ['/f/\\evil'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_custom_branding_urls_are_blanked(string $bad): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $resp = $this->postSettings($u, $link, [
            'custom_branding_text' => 'My Brand',
            'custom_branding_url'  => $bad,
            'custom_branding_logo' => $bad,
        ]);
        $resp->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('', $bio['custom_branding_url'] ?? null, 'custom_branding_url must be blanked');
        $this->assertSame('', $bio['custom_branding_logo'] ?? null, 'custom_branding_logo must be blanked');
        // Non-URL sibling field is untouched.
        $this->assertSame('My Brand', $bio['custom_branding_text'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_favicon_url_is_blanked_on_the_link_column(string $bad): void
    {
        $u = $this->user();
        $link = $this->biolink($u);
        $link->update(['favicon' => 'https://old.example.com/fav.ico']);

        $resp = $this->postSettings($u, $link, ['favicon_url' => $bad]);
        $resp->assertSessionMissing('error');

        $this->assertSame('', $link->refresh()->favicon, 'favicon column must be blanked, not keep the unsafe value');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_touch_icon_favicons_are_rejected_by_validation(string $bad): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        // favicons.* carry a `url` validation rule, so unsafe values are
        // rejected outright (422 redirect) before the sanitizer even runs —
        // nothing may be persisted.
        $resp = $this->postSettings($u, $link, [
            'favicons' => [
                'apple_touch_icon' => $bad,
                'icon_512'         => $bad,
            ],
        ]);
        $resp->assertSessionHasErrors(['favicons.apple_touch_icon', 'favicons.icon_512']);

        $favicons = $link->refresh()->settings['biolink']['favicons'] ?? [];
        $this->assertArrayNotHasKey('apple_touch_icon', $favicons);
        $this->assertArrayNotHasKey('icon_512', $favicons);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_video_url_is_blanked(string $bad): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $resp = $this->postSettings($u, $link, [
            'background_type' => 'video',
            'video_url'       => $bad,
        ]);
        $resp->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('', $bio['video_url'] ?? null, 'video_url must be blanked — it renders as <source src> on the public page');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_manifest_start_url_is_dropped(string $bad): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $resp = $this->postSettings($u, $link, [
            'manifest' => ['enabled' => '1', 'name' => 'My PWA', 'start_url' => $bad],
        ]);
        $resp->assertSessionMissing('error');

        $manifest = $link->refresh()->settings['biolink']['manifest'] ?? [];
        $this->assertNull($manifest['start_url'] ?? null, 'unsafe start_url must be dropped so the manifest falls back to the biolink URL');
        $this->assertSame('My PWA', $manifest['name'] ?? null);
    }

    public function test_javascript_scheme_manifest_start_url_is_dropped(): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $this->postSettings($u, $link, [
            'manifest' => ['enabled' => '1', 'start_url' => 'javascript:alert(1)'],
        ])->assertSessionMissing('error');

        $this->assertNull($link->refresh()->settings['biolink']['manifest']['start_url'] ?? null);
    }

    public function test_safe_video_and_start_urls_round_trip(): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $this->postSettings($u, $link, [
            'background_type' => 'video',
            'video_url'       => 'https://cdn.example.com/bg.mp4',
            'manifest'        => ['enabled' => '1', 'start_url' => '/my-page'],
        ])->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('https://cdn.example.com/bg.mp4', $bio['video_url'] ?? null);
        $this->assertSame('/my-page', $bio['manifest']['start_url'] ?? null);

        // Vault video paths are also allowed.
        $this->postSettings($u, $link, ['video_url' => '/f/123/clip.webm'])->assertSessionMissing('error');
        $this->assertSame('/f/123/clip.webm', $link->refresh()->settings['biolink']['video_url'] ?? null);
    }

    public function test_safe_https_and_vault_urls_round_trip_unchanged(): void
    {
        $u = $this->user();
        $link = $this->biolink($u);

        $resp = $this->postSettings($u, $link, [
            'custom_branding_text' => 'Brand',
            'custom_branding_url'  => 'https://example.com/home',
            'custom_branding_logo' => '/f/123/logo.png',
            'favicon_url'          => 'https://cdn.example.com/favicon.ico',
            // favicons.* have a `url` rule, so only absolute URLs pass here.
            'favicons'             => ['apple_touch_icon' => 'https://cdn.example.com/touch.png'],
        ]);
        $resp->assertSessionDoesntHaveErrors();

        $link->refresh();
        $bio = $link->settings['biolink'] ?? [];
        $this->assertSame('https://example.com/home', $bio['custom_branding_url'] ?? null);
        $this->assertSame('/f/123/logo.png', $bio['custom_branding_logo'] ?? null);
        $this->assertSame('https://cdn.example.com/favicon.ico', $link->favicon);
        $this->assertSame('https://cdn.example.com/touch.png', $bio['favicons']['apple_touch_icon'] ?? null);
    }
}
