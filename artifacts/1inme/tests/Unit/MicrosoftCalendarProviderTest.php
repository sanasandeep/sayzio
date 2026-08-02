<?php

namespace Tests\Unit;

use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\CalendarSyncException;
use App\Modules\User\Services\Calendar\MicrosoftCalendarProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the Microsoft Graph calendar driver: it mirrors the Google
 * driver's contract (key/isConfigured/authorizationUrl) and refuses gracefully
 * — never fatally — when OAuth credentials are absent.
 *
 * These are plain PHPUnit tests (no framework boot), so config()/env() are not
 * available; the provider is exercised through its public surface only where
 * that does not require the container.
 */
class MicrosoftCalendarProviderTest extends TestCase
{
    public function test_provider_reports_its_key(): void
    {
        $this->assertSame('microsoft', (new MicrosoftCalendarProvider())->key());
    }

    public function test_authorization_url_throws_when_unconfigured(): void
    {
        // A subclass that reports "no credentials" lets us assert the graceful
        // refusal without touching the container / env.
        $provider = new class extends MicrosoftCalendarProvider {
            public function isConfigured(): bool { return false; }
        };

        $this->expectException(CalendarSyncException::class);
        $provider->authorizationUrl('state-token', 'https://example.test/callback');
    }

    public function test_authorization_url_targets_microsoft_login_and_scopes(): void
    {
        $provider = new class extends MicrosoftCalendarProvider {
            public function isConfigured(): bool { return true; }
            public function clientId(): string { return 'client-abc'; }
            public function tenant(): string { return 'common'; }
        };

        $url = $provider->authorizationUrl('state-xyz', 'https://example.test/cb');

        $this->assertStringContainsString('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $url);
        $this->assertStringContainsString('client_id=client-abc', $url);
        $this->assertStringContainsString('state=state-xyz', $url);
        // Required scopes are URL-encoded in the query string.
        $this->assertStringContainsString(rawurlencode('offline_access'), $url);
        $this->assertStringContainsString(rawurlencode('Calendars.ReadWrite'), $url);
    }

    public function test_registry_resolves_microsoft_provider(): void
    {
        $registry = new CalendarProviderRegistry();
        $registry->register('microsoft', fn () => new MicrosoftCalendarProvider());

        $this->assertContains('microsoft', $registry->keys());
        $this->assertInstanceOf(MicrosoftCalendarProvider::class, $registry->get('microsoft'));
    }
}
