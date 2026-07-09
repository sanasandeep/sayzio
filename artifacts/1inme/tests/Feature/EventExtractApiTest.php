<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for GET /api/v1/calendars/extract-event — the server-side
 * "detect event details" endpoint the mobile Add-to-Calendar prefill uses
 * (mirrors the browser extension's in-page JSON-LD/microdata/OG scrape).
 */
class EventExtractApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }

    private function get_(string $qs)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/v1/calendars/extract-event' . $qs);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/calendars/extract-event?url=https://example.com')
            ->assertStatus(401);
    }

    public function test_extracts_json_ld_event(): void
    {
        Http::fake([
            'example.com/*' => Http::response(
                '<html><head><title>Fallback</title>' .
                '<script type="application/ld+json">' .
                json_encode([
                    '@context'  => 'https://schema.org',
                    '@type'     => 'MusicEvent',
                    'name'      => 'Summer Fest 2026',
                    'startDate' => '2026-08-15T19:30:00+00:00',
                    'endDate'   => '2026-08-15T23:00:00+00:00',
                    'location'  => ['@type' => 'Place', 'name' => 'Central Park Arena'],
                    'description' => 'Annual outdoor music festival.',
                ]) .
                '</script></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $this->get_('?url=https://example.com/fest')
            ->assertOk()
            ->assertJsonPath('data.event.title', 'Summer Fest 2026')
            ->assertJsonPath('data.event.location', 'Central Park Arena')
            ->assertJsonPath('data.event.source', 'json-ld')
            ->assertJsonPath('data.event.start_at', '2026-08-15T19:30:00+00:00');
    }

    public function test_falls_back_to_title_when_no_structured_data(): void
    {
        Http::fake([
            'example.com/*' => Http::response(
                '<html><head><title>Plain Page</title></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $this->get_('?url=https://example.com/page')
            ->assertOk()
            ->assertJsonPath('data.event.title', 'Plain Page')
            ->assertJsonPath('data.event.source', 'title')
            ->assertJsonPath('data.event.start_at', null)
            ->assertJsonPath('data.event.location', null);
    }

    public function test_rejects_missing_url(): void
    {
        $this->get_('')->assertStatus(422);
    }

    public function test_rejects_private_targets(): void
    {
        $this->get_('?url=http://localhost/admin')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'extract_failed');

        $this->get_('?url=http://169.254.169.254/latest/meta-data')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'extract_failed');
    }

    public function test_non_html_response_fails_cleanly(): void
    {
        Http::fake([
            'example.com/*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->get_('?url=https://example.com/file.pdf')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'extract_failed');
    }
}
