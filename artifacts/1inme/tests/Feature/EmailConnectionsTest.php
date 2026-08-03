<?php

namespace Tests\Feature;

use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\EmailConnectionMailer;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\CompanyMailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6632 — user-owned reusable SMTP connections.
 *
 * Covers the dedicated SMTP Connections page (list + test-send + recipient
 * restriction), the shared EmailConnectionMailer routing (email_logs
 * meta.transport = "connection:{id}" like the company-SMTP tests), platform
 * fallback for missing/inactive/half-configured connections, subscriber
 * settings adoption, and billing-company "fill from connection" population.
 */
class EmailConnectionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::create([
            'name'     => 'ec ' . Str::random(4),
            'email'    => 'ec' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function workspace(User $u): Workspace
    {
        return app(WorkspaceContext::class)->resolve($u);
    }

    private function connection(User $u, array $overrides = []): IntegrationConfig
    {
        return IntegrationConfig::create(array_merge([
            'user_id'     => $u->id,
            'kind'        => 'email',
            'provider'    => 'smtp',
            'name'        => 'My SMTP',
            'is_active'   => true,
            'is_default'  => false,
            'credentials' => ['password' => 's3cret-pw'],
            'meta'        => [
                'host'       => 'smtp.mine.test',
                'port'       => 2525,
                'encryption' => 'tls',
                'username'   => 'me@mine.test',
                'from_email' => 'hello@mine.test',
                'from_name'  => 'Mine',
            ],
        ], $overrides));
    }

    // ---------- page + test send ----------

    public function test_connections_page_lists_the_users_email_connections(): void
    {
        $u = $this->user();
        $c = $this->connection($u, ['name' => 'Newsletter Sender']);
        $this->connection($u, ['kind' => 'sms', 'provider' => 'twilio', 'name' => 'SMS thing', 'credentials' => ['token' => 'x'], 'meta' => []]);

        $res = $this->actingAs($u)->get(route('user.email-connections.index'));

        $res->assertOk()
            ->assertSee('SMTP Connections')
            ->assertSee('Newsletter Sender')
            ->assertSee('hello@mine.test')
            ->assertDontSee('SMS thing');
    }

    public function test_test_send_rejects_arbitrary_third_party_recipients(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u);

        $res = $this->actingAs($u)
            ->from(route('user.email-connections.index'))
            ->post(route('user.email-connections.test', $c), ['test_email' => 'victim@spam.test']);

        $res->assertSessionHasErrors('test_email');
    }

    public function test_test_send_to_own_email_succeeds(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u);

        $res = $this->actingAs($u)
            ->from(route('user.email-connections.index'))
            ->post(route('user.email-connections.test', $c), ['test_email' => $u->email]);

        $res->assertSessionHas('success');
    }

    public function test_test_send_reports_half_configured_connection_clearly(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u, ['meta' => ['host' => '', 'from_email' => 'hello@mine.test']]);

        $res = $this->actingAs($u)
            ->from(route('user.email-connections.index'))
            ->post(route('user.email-connections.test', $c), ['test_email' => $u->email]);

        $res->assertSessionHas('error');
    }

    public function test_test_send_forbids_other_users_connections(): void
    {
        Mail::fake();
        $owner = $this->user();
        $c = $this->connection($owner);
        $other = $this->user(); // rebinds current_workspace to $other

        $this->actingAs($other)
            ->post(route('user.email-connections.test', $c), ['test_email' => $other->email])
            ->assertNotFound(); // hidden by the workspace/ownership scope
    }

    public function test_integrations_store_with_return_to_lands_back_on_connections_page(): void
    {
        $u = $this->user();

        // The integrations area is behind the "coming soon" feature gate until
        // any connected app is platform-configured; mark one configured so the
        // CRUD routes behave as in a live environment.
        \App\Services\Integrations\PlatformServiceSettings::setConnectedAppClientId('salesforce', 'cid');
        \App\Services\Integrations\PlatformServiceSettings::setConnectedAppClientSecret('salesforce', 'sec');

        $res = $this->actingAs($u)->post(route('user.integrations.store', 'email'), [
            'provider'   => 'smtp',
            'name'       => 'Round-trip SMTP',
            'return_to'  => 'connections',
            'is_active'  => '1',
            'is_default' => '0',
            'fields'     => [
                'host'       => 'smtp.rt.test',
                'port'       => '587',
                'encryption' => 'tls',
                'username'   => 'rt@rt.test',
                'password'   => 'pw',
                'from_email' => 'rt@rt.test',
                'from_name'  => 'RT',
            ],
        ]);

        $res->assertRedirect(route('user.email-connections.index'));
        $this->assertDatabaseHas('integration_configs', ['user_id' => $u->id, 'name' => 'Round-trip SMTP', 'kind' => 'email']);
    }

    public function test_connections_are_account_level_across_workspaces(): void
    {
        // A connection saved while workspace A is active must stay visible,
        // manageable, and selectable after switching to workspace B — configs
        // are user-owned, not workspace-owned.
        $u = $this->user(); // binds personal workspace (A)
        $c = $this->connection($u, ['name' => 'Cross-WS SMTP']);

        // Lift the integrations coming-soon gate so the CRUD routes behave
        // like a configured live environment.
        \App\Services\Integrations\PlatformServiceSettings::setConnectedAppClientId('salesforce', 'cid');
        \App\Services\Integrations\PlatformServiceSettings::setConnectedAppClientSecret('salesforce', 'sec');
        $this->assertNotNull($c->workspace_id, 'Fixture should have been stamped with workspace A');

        $wsB = Workspace::create([
            'owner_user_id' => $u->id,
            'name'          => 'Second WS',
            'slug'          => 'ws-b-' . Str::random(6),
            'is_personal'   => false,
        ]);
        app()->instance('current_workspace', $wsB);
        app()->instance('workspace_owner', $u);

        // Listed on the SMTP Connections page.
        $this->actingAs($u)->get(route('user.email-connections.index'))
            ->assertOk()->assertSee('Cross-WS SMTP');

        // Route-model-bound actions resolve (edit page + set-default + test-send).
        $this->actingAs($u)->get(route('user.integrations.edit', $c))->assertOk();
        $this->actingAs($u)->post(route('user.integrations.set-default', $c))->assertRedirect();
        $this->assertTrue((bool) $c->fresh()->is_default);

        Mail::fake();
        $this->actingAs($u)
            ->from(route('user.email-connections.index'))
            ->post(route('user.email-connections.test', $c), ['test_email' => $u->email])
            ->assertSessionHas('success');

        // Selectable + persisted from the other workspace too.
        $this->actingAs($u)->post(route('user.subscribers.settings.update'), [
            'email_connection_id' => $c->id,
        ])->assertSessionHas('success');
        $this->assertSame($c->id, (int) ($u->fresh()->settings['subscription']['email_connection_id'] ?? 0));
    }

    // ---------- shared helper routing ----------

    public function test_helper_send_routes_through_selected_connection(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u);

        EmailConnectionMailer::send('form.notification', $u->id, $c->id, ['dest@ex.com'], [
            'subject' => 'Hi', 'body' => 'Body', 'format' => 'text', 'user' => $u->id,
        ]);

        $log = EmailLog::where('email_key', 'form.notification')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('connection:' . $c->id, $log->meta['transport'] ?? null);
        $this->assertSame(['address' => 'hello@mine.test', 'name' => 'Mine'], $log->meta['from'] ?? null);
        // Credentials purged after the send.
        $this->assertArrayNotHasKey('integ_' . $c->id, (array) config('mail.mailers'));
    }

    public function test_helper_falls_back_to_platform_when_connection_inactive_or_missing(): void
    {
        Mail::fake();
        $u = $this->user();
        $inactive = $this->connection($u, ['is_active' => false]);

        EmailConnectionMailer::send('form.notification', $u->id, $inactive->id, ['a@ex.com'], ['subject' => 's', 'body' => 'b']);
        EmailConnectionMailer::send('form.notification', $u->id, 999999, ['b@ex.com'], ['subject' => 's', 'body' => 'b']);
        EmailConnectionMailer::send('form.notification', $u->id, null, ['c@ex.com'], ['subject' => 's', 'body' => 'b']);

        $this->assertSame(3, EmailLog::where('email_key', 'form.notification')->count());
        foreach (EmailLog::where('email_key', 'form.notification')->get() as $log) {
            $this->assertStringStartsNotWith('connection:', (string) ($log->meta['transport'] ?? ''));
        }
    }

    public function test_helper_falls_back_when_connection_half_configured(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u, ['credentials' => [], 'meta' => ['host' => 'smtp.mine.test']]);

        EmailConnectionMailer::send('form.notification', $u->id, $c->id, ['a@ex.com'], ['subject' => 's', 'body' => 'b']);

        $log = EmailLog::where('email_key', 'form.notification')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringStartsNotWith('connection:', (string) ($log->meta['transport'] ?? ''));
    }

    // ---------- subscriber settings adoption ----------

    public function test_subscriber_settings_persist_selected_connection(): void
    {
        $u = $this->user();
        $c = $this->connection($u);

        $res = $this->actingAs($u)->post(route('user.subscribers.settings.update'), [
            'email_connection_id' => $c->id,
            'email_from_name'     => 'Brand',
        ]);

        $res->assertSessionHas('success');
        $this->assertSame($c->id, (int) ($u->fresh()->settings['subscription']['email_connection_id'] ?? 0));
    }

    public function test_subscriber_settings_reject_foreign_connection(): void
    {
        $owner = $this->user();
        $foreign = $this->connection($owner);
        $u = $this->user();

        $this->actingAs($u)
            ->from(route('user.subscribers.settings'))
            ->post(route('user.subscribers.settings.update'), ['email_connection_id' => $foreign->id])
            ->assertSessionHasErrors('email_connection_id');
    }

    public function test_subscriber_broadcast_routes_through_selected_connection(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u);
        $u->update(['settings' => ['subscription' => ['email_connection_id' => $c->id]]]);
        Subscriber::create([
            'user_id' => $u->id, 'type' => 'email', 'email' => 'fan@ex.com',
            'status' => 'active', 'subscribed_at' => now(),
        ]);

        $this->actingAs($u)->post(route('user.subscribers.send'), [
            'channel' => 'email', 'subject' => 'News', 'body' => 'Hello fans',
        ]);

        $log = EmailLog::where('email_key', 'subscriber.broadcast')->latest('id')->first();
        $this->assertNotNull($log, 'Expected a subscriber.broadcast email log');
        $this->assertSame('connection:' . $c->id, $log->meta['transport'] ?? null);
    }

    public function test_subscriber_broadcast_falls_back_to_platform_when_connection_disabled(): void
    {
        Mail::fake();
        $u = $this->user();
        $c = $this->connection($u, ['is_active' => false]);
        $u->update(['settings' => ['subscription' => ['email_connection_id' => $c->id]]]);
        Subscriber::create([
            'user_id' => $u->id, 'type' => 'email', 'email' => 'fan2@ex.com',
            'status' => 'active', 'subscribed_at' => now(),
        ]);

        $this->actingAs($u)->post(route('user.subscribers.send'), [
            'channel' => 'email', 'subject' => 'News', 'body' => 'Hello again',
        ]);

        $log = EmailLog::where('email_key', 'subscriber.broadcast')->latest('id')->first();
        $this->assertNotNull($log, 'Broadcast must still send via the platform mailer');
        $this->assertStringStartsNotWith('connection:', (string) ($log->meta['transport'] ?? ''));
    }

    // ---------- billing company adoption ----------

    public function test_billing_company_picker_populates_smtp_fields_from_connection(): void
    {
        $u = $this->user();
        $ws = $this->workspace($u);
        $c = $this->connection($u);
        $company = BillingCompany::create([
            'user_id' => $u->id, 'workspace_id' => $ws->id,
            'name' => 'Acme', 'email' => 'acme@ex.com',
        ]);

        $res = $this->actingAs($u)->put(route('user.billing.companies.update', $company), [
            'name'               => 'Acme',
            'email'              => 'acme@ex.com',
            'smtp_connection_id' => $c->id,
        ]);

        $company->refresh();
        $this->assertTrue((bool) $company->smtp_enabled);
        $this->assertSame('smtp.mine.test', $company->smtp_host);
        $this->assertSame(2525, (int) $company->smtp_port);
        $this->assertSame('tls', $company->smtp_encryption);
        $this->assertSame('me@mine.test', $company->smtp_username);
        $this->assertSame('hello@mine.test', $company->smtp_from_address);
        $this->assertTrue(CompanyMailSettings::for($company)->isConfigured(), 'Copied connection must satisfy the fully-configured rule');
    }

    public function test_billing_company_rejects_half_configured_connection(): void
    {
        $u = $this->user();
        $ws = $this->workspace($u);
        $c = $this->connection($u, ['credentials' => []]); // no password
        $company = BillingCompany::create([
            'user_id' => $u->id, 'workspace_id' => $ws->id,
            'name' => 'Acme2', 'email' => 'acme2@ex.com',
        ]);

        $this->actingAs($u)
            ->from(route('user.billing.companies.edit', $company))
            ->put(route('user.billing.companies.update', $company), [
                'name'               => 'Acme2',
                'email'              => 'acme2@ex.com',
                'smtp_connection_id' => $c->id,
            ])
            ->assertSessionHasErrors('smtp_connection_id');

        $this->assertFalse((bool) $company->fresh()->smtp_enabled);
    }
}
