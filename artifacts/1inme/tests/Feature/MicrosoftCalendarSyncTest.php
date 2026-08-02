<?php

namespace Tests\Feature;

use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\MicrosoftCalendarProvider;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature coverage for the Microsoft Outlook push calendar sync + the polished
 * Apple (ICS/webcal) subscribe path on the Calendar Sync settings page.
 *
 * The Microsoft OAuth/connect routes are gated by `workspace.can:settings.*`,
 * so each request binds an active workspace in the session (see
 * .agents/memory/api-workspace-scope.md); the user owns the resolved
 * workspace, so the permission is granted.
 *
 * Graph HTTP calls are mocked with Http::fake — no real network.
 */
class MicrosoftCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name'  => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    private function configureMicrosoft(): void
    {
        config([
            'services.microsoft_calendar.client_id'     => 'test-client-id',
            'services.microsoft_calendar.client_secret' => 'test-client-secret',
            'services.microsoft_calendar.tenant'        => 'common',
        ]);
    }

    // ── Provider registration ─────────────────────────────────────────────

    public function test_microsoft_provider_is_registered_in_the_app_registry(): void
    {
        $registry = app(CalendarProviderRegistry::class);

        $this->assertContains('microsoft', $registry->keys());
        $this->assertInstanceOf(MicrosoftCalendarProvider::class, $registry->get('microsoft'));
    }

    // ── Graceful unconfigured state (never 500) ────────────────────────────

    public function test_connect_reports_unconfigured_gracefully_when_credentials_absent(): void
    {
        config([
            'services.microsoft_calendar.client_id'     => null,
            'services.microsoft_calendar.client_secret' => null,
        ]);

        $user = $this->makeUser();

        $resp = $this->actingAsWeb($user)
            ->from(route('user.calendar.index'))
            ->get(route('user.calendar.connect', 'microsoft'));

        // Bounces back with an error flash rather than a 500 or an OAuth redirect.
        $resp->assertRedirect(route('user.calendar.index'));
        $resp->assertSessionHas('error');
    }

    public function test_connect_redirects_to_microsoft_oauth_when_configured(): void
    {
        $this->configureMicrosoft();
        $user = $this->makeUser();

        $resp = $this->actingAsWeb($user)->get(route('user.calendar.connect', 'microsoft'));

        $resp->assertStatus(302);
        $this->assertStringContainsString(
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            (string) $resp->headers->get('Location')
        );
    }

    // ── OAuth callback route auth ──────────────────────────────────────────

    public function test_callback_route_requires_authentication(): void
    {
        // No auth / no workspace => the permission middleware must not allow it
        // through to the controller (redirect to login or 403, never 200).
        $resp = $this->get(route('user.calendar.callback', 'microsoft'));

        $this->assertNotSame(200, $resp->getStatusCode());
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->configureMicrosoft();
        $user = $this->makeUser();

        // No stored oauth state in session => invalid/expired.
        $resp = $this->actingAsWeb($user)
            ->get(route('user.calendar.callback', 'microsoft') . '?state=bogus&code=abc');

        $resp->assertRedirect(route('user.calendar.index'));
        $resp->assertSessionHas('error');
    }

    // ── OAuth token exchange against a faked Graph API ─────────────────────

    public function test_exchange_code_persists_account_from_faked_graph(): void
    {
        $this->configureMicrosoft();
        $user = $this->makeUser();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token'  => 'gr_access',
                'refresh_token' => 'gr_refresh',
                'expires_in'    => 3600,
                'scope'         => 'offline_access Calendars.ReadWrite',
            ], 200),
            'graph.microsoft.com/v1.0/me' => Http::response([
                'id'                => 'ms-user-123',
                'mail'              => 'creator@contoso.com',
                'displayName'       => 'Creator Contoso',
                'userPrincipalName' => 'creator@contoso.com',
            ], 200),
        ]);

        $provider = app(CalendarProviderRegistry::class)->get('microsoft');
        $account  = $provider->exchangeCode($user->id, 'auth-code', 'https://example.test/cb');

        $this->assertInstanceOf(CalendarAccount::class, $account);
        $this->assertSame('microsoft', $account->provider);
        $this->assertSame((int) $user->id, (int) $account->user_id);
        $this->assertSame('creator@contoso.com', $account->account_email);
        $this->assertSame('ms-user-123', $account->external_account_id);
        $this->assertNotNull($account->access_token);

        $this->assertDatabaseHas('calendar_accounts', [
            'user_id'  => $user->id,
            'provider' => 'microsoft',
        ]);
    }

    // ── Settings page renders Microsoft + Apple sections ───────────────────

    public function test_settings_page_renders_microsoft_and_apple_sections(): void
    {
        $this->configureMicrosoft();
        $user = $this->makeUser();

        $resp = $this->actingAsWeb($user)->get(route('user.calendar.index'));

        $resp->assertOk();
        // Microsoft connect option present + enabled (configured).
        $resp->assertSee('Microsoft 365 / Outlook', false);
        $resp->assertSee(route('user.calendar.connect', 'microsoft'), false);
        // Apple Calendar subscribe section present with a webcal:// link.
        $resp->assertSee('Apple Calendar', false);
        $resp->assertSee('webcal://', false);
        $resp->assertSee('Subscribe in Apple Calendar', false);
    }

    public function test_settings_page_shows_microsoft_unavailable_when_unconfigured(): void
    {
        config([
            'services.microsoft_calendar.client_id'     => null,
            'services.microsoft_calendar.client_secret' => null,
        ]);
        $user = $this->makeUser();

        $resp = $this->actingAsWeb($user)->get(route('user.calendar.index'));

        $resp->assertOk();
        $resp->assertSee('Unavailable', false);
        // The connect link must NOT be offered when unconfigured.
        $resp->assertDontSee('href="' . route('user.calendar.connect', 'microsoft') . '"', false);
    }
}
