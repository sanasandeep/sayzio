<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Coverage for GET /user/links/{link}/blocks/og-meta
 * The endpoint fetches OG metadata for a URL on behalf of the block editor.
 * It must: require auth, scope to the biolink owner, rate-limit, guard SSRF,
 * and extract title/description/image from fetched HTML.
 */
class BiolinkBlockOgMetaTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $ws   = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $user;
    }

    private function makeLink(array $attrs = []): Link
    {
        if (!isset($attrs['user_id'])) {
            $attrs['user_id'] = $this->makeUser()->id;
        }
        return Link::create(array_merge([
            'type'      => 'short',
            'alias'     => Link::generateAlias(),
            'title'     => 'Test Link',
            'is_active' => true,
        ], $attrs));
    }


    private function makeBiolink(User $user): Link
    {
        $link = $this->makeLink(['user_id' => $user->id, 'type' => 'biolink']);
        return $link;
    }

    private function get_(User $user, Link $link, string $qs = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->get(route('user.links.blocks.ogMeta', $link) . $qs, [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => 'application/json',
            ]);
    }

    public function test_requires_authentication(): void
    {
        $link = $this->makeLink(['type' => 'biolink']);
        $this->getJson(route('user.links.blocks.ogMeta', $link))
            ->assertUnauthorized();
    }

    public function test_rejects_non_owner(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link  = $this->makeBiolink($owner);

        $this->get_($other, $link, '?url=https://example.com')
            ->assertForbidden();
    }

    public function test_rejects_non_biolink_link_type(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink(['user_id' => $user->id, 'type' => 'short']);

        $this->get_($user, $link, '?url=https://example.com')
            ->assertForbidden();
    }

    public function test_missing_url_returns_422(): void
    {
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '')
            ->assertStatus(422)
            ->assertJsonPath('error', 'Please enter a URL first.');
    }

    public function test_extracts_og_title_and_description(): void
    {
        Http::fake([
            'example.com/*' => Http::response(
                '<html><head>' .
                '<meta property="og:title" content="Test Title">' .
                '<meta property="og:description" content="A great description.">' .
                '<meta property="og:image" content="https://example.com/image.jpg">' .
                '</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '?url=https://example.com/page')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.title', 'Test Title')
            ->assertJsonPath('meta.description', 'A great description.')
            ->assertJsonPath('meta.image_url', 'https://example.com/image.jpg');
    }

    public function test_falls_back_to_html_title_and_meta_description(): void
    {
        Http::fake([
            'example.com/*' => Http::response(
                '<html><head>' .
                '<title>Fallback Title</title>' .
                '<meta name="description" content="Fallback desc.">' .
                '</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $response = $this->get_($user, $link, '?url=https://example.com/page')
            ->assertOk()
            ->assertJsonPath('meta.title', 'Fallback Title')
            ->assertJsonPath('meta.description', 'Fallback desc.');

        $this->assertNull($response->json('meta.image_url'));
        $this->assertNotNull($response->json('meta.favicon_url'));
    }

    public function test_favicon_fallback_when_no_og_image(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>T</title>' .
                '<link rel="icon" href="/favicon.png">' .
                '</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '?url=https://example.com/')
            ->assertOk()
            ->assertJsonPath('meta.favicon_url', 'https://example.com/favicon.png');
    }

    public function test_rejects_private_ip_ssrf(): void
    {
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '?url=http://192.168.1.1/secret')
            ->assertStatus(422)
            ->assertJsonPath('error', "That URL isn't allowed.");
    }

    public function test_rejects_localhost_ssrf(): void
    {
        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '?url=http://localhost/admin')
            ->assertStatus(422)
            ->assertJsonPath('error', "That URL isn't allowed.");
    }

    public function test_rate_limit_blocks_excess_requests(): void
    {
        Http::fake(['*' => Http::response('<html><head><title>T</title></head></html>', 200, ['Content-Type' => 'text/html'])]);

        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        RateLimiter::clear('og-meta:' . $user->id);

        for ($i = 0; $i < 10; $i++) {
            $this->get_($user, $link, '?url=https://example.com/')->assertOk();
        }

        $this->get_($user, $link, '?url=https://example.com/')
            ->assertStatus(429)
            ->assertJsonStructure(['error']);

        RateLimiter::clear('og-meta:' . $user->id);
    }

    public function test_upstream_error_returns_422(): void
    {
        Http::fake(['example.com/*' => Http::response('Server Error', 500)]);

        $user = $this->makeUser();
        $link = $this->makeBiolink($user);

        $this->get_($user, $link, '?url=https://example.com/bad')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }
}
