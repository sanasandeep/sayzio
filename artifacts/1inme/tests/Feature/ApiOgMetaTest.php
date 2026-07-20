<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Coverage for GET /api/v1/og-meta — the mobile block editor's "Fetch
 * details" endpoint (parity for the web editor's
 * user/links/{link}/blocks/og-meta). It must: require a bearer token,
 * validate the url param, rate-limit per user, guard SSRF via the shared
 * OgMetadataService, and return the extracted metadata under the standard
 * `{data:{meta}}` envelope.
 */
class ApiOgMetaTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/og-meta?url=https://example.com')
            ->assertStatus(401);
    }

    public function test_missing_url_returns_422(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/og-meta')
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Please enter a URL first.');
    }

    public function test_extracts_og_title_description_and_image(): void
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

        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/og-meta?url=' . urlencode('https://example.com/page'))
            ->assertOk()
            ->assertJsonPath('data.meta.title', 'Test Title')
            ->assertJsonPath('data.meta.description', 'A great description.')
            ->assertJsonPath('data.meta.image_url', 'https://example.com/image.jpg');
    }

    public function test_blocks_private_hosts(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/og-meta?url=' . urlencode('http://127.0.0.1/internal'))
            ->assertStatus(422);
    }

    public function test_rate_limited_after_ten_requests(): void
    {
        $user  = User::factory()->create();
        $token = $this->tokenFor($user);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('og-meta:' . $user->id, 60);
        }

        $this->withToken($token)
            ->getJson('/api/v1/og-meta?url=' . urlencode('https://example.com'))
            ->assertStatus(429);
    }
}
