<?php

namespace Tests\Feature;

use App\Modules\User\Models\ReviewProvider;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extension-initiated review-source capture:
 *
 *   POST /api/v1/me/reviews/capture-source
 *
 * The controller registers (or re-activates) a review_providers row and
 * queues a sync via the adapter registry. Both providers exist in the
 * registry, but with no platform API keys configured (the test
 * environment) the adapter's credentialsConfigured() check reports
 * false, so the connection lands in preview mode immediately —
 * identical to the scheduled reviews:sync behaviour.
 *
 * We use a real Bearer token (not Sanctum::actingAs) because the API
 * path runs the TouchSessionToken middleware.
 */
class ReviewCaptureControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_valid_google_capture_returns_200_and_creates_connection(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $res = $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'google',
            'external_ref' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'name'         => 'Test Business',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonStructure(['data' => ['connection_id', 'provider', 'status', 'preview']]);

        $connection = ReviewProvider::find($res->json('data.connection_id'));
        $this->assertNotNull($connection);
        $this->assertSame($user->id, $connection->user_id);
        $this->assertSame('google', $connection->provider);
        $this->assertSame('ChIJN1t_tDeuEmsRUsoyG83frY4', $connection->external_ref);
        $this->assertSame('Test Business', $connection->settings['name'] ?? null);

        // No platform API keys are configured in the test environment, so
        // the immediate response must honestly report a preview capture
        // (rather than a "connected" state the queued sync would downgrade).
        $this->assertTrue($res->json('data.preview'));
        $this->assertSame(ReviewProvider::STATUS_PREVIEW, $res->json('data.status'));
        $this->assertSame(ReviewProvider::STATUS_PREVIEW, $connection->status);
    }

    public function test_valid_trustpilot_capture_returns_200(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $res = $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'trustpilot',
            'external_ref' => 'example.com',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.provider', 'trustpilot');

        $this->assertDatabaseHas('review_providers', [
            'user_id'      => $user->id,
            'provider'     => 'trustpilot',
            'external_ref' => 'example.com',
        ]);
    }

    public function test_capture_is_idempotent_per_provider_and_ref(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $first = $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'google',
            'external_ref' => 'place-123',
        ])->assertOk();

        $second = $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'google',
            'external_ref' => 'place-123',
        ])->assertOk();

        $this->assertSame(
            $first->json('data.connection_id'),
            $second->json('data.connection_id'),
        );
        $this->assertSame(1, ReviewProvider::where('user_id', $user->id)->count());
    }

    public function test_missing_provider_returns_422(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/me/reviews/capture-source', [
            'external_ref' => 'place-123',
        ])->assertStatus(422)
            ->assertJsonPath('error.details.provider.0', fn ($msg) => is_string($msg));
    }

    public function test_unknown_provider_returns_422(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'yelp',
            'external_ref' => 'place-123',
        ])->assertStatus(422);
    }

    public function test_missing_external_ref_returns_422(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider' => 'google',
        ])->assertStatus(422)
            ->assertJsonPath('error.details.external_ref.0', fn ($msg) => is_string($msg));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/reviews/capture-source', [
            'provider'     => 'google',
            'external_ref' => 'place-123',
        ])->assertUnauthorized();
    }
}
