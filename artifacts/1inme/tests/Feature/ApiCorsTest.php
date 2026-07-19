<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression guard: the Laravel CORS middleware must emit
 * Access-Control-Allow-Origin on API preflight and real requests so that
 * the mobile web preview (Expo running in a browser frame on a different
 * origin) can reach /api/v1/* endpoints without "Failed to fetch".
 */
class ApiCorsTest extends TestCase
{
    public function test_api_preflight_returns_cors_headers(): void
    {
        $response = $this->options('/api/v1/auth/login', [], [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type,Authorization',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_api_request_returns_cors_header(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [], [
            'Origin' => 'https://example.com',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_assistant_preflight_still_returns_cors_headers(): void
    {
        $response = $this->options('/assistant/chat', [], [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }
}
