<?php

namespace Tests\Feature;

use App\Modules\User\Models\ExternalReview;
use App\Modules\User\Models\ReviewProvider;
use App\Modules\User\Models\User;
use App\Services\ReviewProviders\ReviewProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Live-wiring tests for the 3rd-party review adapters. Stubs the Google
 * Places Details API and the Trustpilot public Business Unit reviews API
 * with Http::fake and asserts that:
 *   - reviews are parsed + imported into external_reviews when credentials
 *     are configured (live mode),
 *   - re-syncing the same payload dedupes via dedup_key (no duplicates),
 *   - the adapter transparently falls back to a preview sample when the key
 *     is absent,
 *   - logical API failures (Google's HTTP-200 `status` envelope; a Trustpilot
 *     HTTP error) mark the connection as errored.
 */
class ReviewProviderSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function creator(): User
    {
        return User::create([
            'name'     => 'Creator ' . Str::random(4),
            'email'    => 'r' . Str::random(6) . '@e.com',
            'password' => bcrypt('secret'),
            'country'  => 'US',
        ]);
    }

    protected function connection(User $user, string $provider, ?string $ref): ReviewProvider
    {
        return ReviewProvider::create([
            'user_id'      => $user->id,
            'provider'     => $provider,
            'external_ref' => $ref,
            'status'       => ReviewProvider::STATUS_PREVIEW,
        ]);
    }

    public function test_google_live_sync_imports_and_dedupes(): void
    {
        config(['services.google_places.api_key' => 'g-test-key']);

        Http::fake([
            'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'reviews' => [
                        [
                            'time'              => 1700000000,
                            'author_name'       => 'Ada Lovelace',
                            'profile_photo_url' => 'https://lh3.googleusercontent.com/ada',
                            'rating'            => 5,
                            'text'              => 'Brilliant service.',
                            'author_url'        => 'https://maps.google.com/ada',
                        ],
                        [
                            'time'        => 1700100000,
                            'author_name' => 'Alan Turing',
                            'rating'      => 4,
                            'text'        => 'Very good.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->creator();
        $conn = $this->connection($user, 'google', 'ChIJ-place-id');

        $result = ReviewProviderRegistry::adapter('google')->sync($conn);

        $this->assertFalse($result['preview']);
        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, ExternalReview::where('user_id', $user->id)->count());

        $first = ExternalReview::where('user_id', $user->id)->where('source_id', '1700000000')->first();
        $this->assertNotNull($first);
        $this->assertSame('Ada Lovelace', $first->author_name);
        $this->assertSame(5, $first->rating);
        $this->assertSame('https://maps.google.com/ada', $first->source_url);

        $conn->refresh();
        $this->assertSame(ReviewProvider::STATUS_CONNECTED, $conn->status);

        // Re-sync the identical payload → dedupe, zero new imports.
        $again = ReviewProviderRegistry::adapter('google')->sync($conn);
        $this->assertSame(0, $again['imported']);
        $this->assertSame(2, ExternalReview::where('user_id', $user->id)->count());
    }

    public function test_google_status_error_marks_connection_errored(): void
    {
        config(['services.google_places.api_key' => 'g-test-key']);

        Http::fake([
            'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
                'status'        => 'REQUEST_DENIED',
                'error_message' => 'The provided API key is invalid.',
            ], 200),
        ]);

        $user = $this->creator();
        $conn = $this->connection($user, 'google', 'ChIJ-place-id');

        $result = ReviewProviderRegistry::adapter('google')->sync($conn);

        $this->assertSame(0, $result['imported']);
        $conn->refresh();
        $this->assertSame(ReviewProvider::STATUS_ERROR, $conn->status);
        $this->assertStringContainsString('invalid', strtolower((string) $conn->status_reason));
    }

    public function test_trustpilot_live_sync_imports_reviews(): void
    {
        config(['services.trustpilot.api_key' => 't-test-key']);

        Http::fake([
            'api.trustpilot.com/v1/business-units/*/reviews*' => Http::response([
                'reviews' => [
                    [
                        'id'        => 'tp-1',
                        'stars'     => 5,
                        'title'     => 'Great',
                        'text'      => 'Loved it.',
                        'createdAt' => '2024-01-02T10:00:00Z',
                        'consumer'  => ['displayName' => 'Grace Hopper'],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->creator();
        $conn = $this->connection($user, 'trustpilot', 'unit-123');

        $result = ReviewProviderRegistry::adapter('trustpilot')->sync($conn);

        $this->assertFalse($result['preview']);
        $this->assertSame(1, $result['imported']);

        $row = ExternalReview::where('user_id', $user->id)->where('source_id', 'tp-1')->first();
        $this->assertNotNull($row);
        $this->assertSame('Grace Hopper', $row->author_name);
        $this->assertSame(5, $row->rating);
        $this->assertSame('Loved it.', $row->body);

        // API key passed as the `apikey` query parameter.
        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'api.trustpilot.com/v1/business-units/unit-123/reviews')
            && str_contains($req->url(), 'apikey=t-test-key'));
    }

    public function test_trustpilot_http_error_marks_connection_errored(): void
    {
        config(['services.trustpilot.api_key' => 't-test-key']);

        Http::fake([
            'api.trustpilot.com/v1/business-units/*/reviews*' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $user = $this->creator();
        $conn = $this->connection($user, 'trustpilot', 'unit-123');

        $result = ReviewProviderRegistry::adapter('trustpilot')->sync($conn);

        $this->assertSame(0, $result['imported']);
        $conn->refresh();
        $this->assertSame(ReviewProvider::STATUS_ERROR, $conn->status);
    }

    public function test_preview_fallback_when_no_credentials(): void
    {
        config(['services.google_places.api_key' => null]);
        Http::fake();

        $user = $this->creator();
        $conn = $this->connection($user, 'google', 'ChIJ-place-id');

        $result = ReviewProviderRegistry::adapter('google')->sync($conn);

        $this->assertTrue($result['preview']);
        $this->assertGreaterThan(0, $result['imported']);
        $conn->refresh();
        $this->assertSame(ReviewProvider::STATUS_PREVIEW, $conn->status);

        // No live HTTP call should have been attempted in preview mode.
        Http::assertNothingSent();
    }
}
