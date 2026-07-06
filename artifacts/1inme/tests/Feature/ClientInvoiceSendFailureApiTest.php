<?php

namespace Tests\Feature;

use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the "deliver before stamping" contract of
 * client-invoice sending (ClientInvoiceService::markSent +
 * BillingController::sendInvoice).
 *
 * markSent() emails the hosted pay link FIRST and only stamps sent_at /
 * status once delivery succeeds, and the Sanctum API surfaces a transport
 * failure as a 502 carrying a fallback pay link (so the owner can still
 * share it manually) instead of silently marking the invoice "sent".
 *
 * The failure case here drives a REAL mail transport failure (the central
 * Emailer swallows transport errors and logs a `failed` row — markSent opts
 * into Emailer's throw_on_failure so the swallowed error surfaces) rather
 * than mocking the service method, so it exercises the actual no-stamp path.
 *
 * If this regressed an invoice could be flagged as sent that the client
 * never received.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the
 * TouchSessionToken middleware — every authed request would 500).
 */
class ClientInvoiceSendFailureApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** A draft client invoice in the user's active workspace, ready to send. */
    private function draftInvoice(User $u, Workspace $ws): Invoice
    {
        $fy = InvoiceService::financialYearFor(now());

        return Invoice::create([
            'number'                   => 'INV/' . $fy . '/' . Str::upper(Str::random(6)),
            'financial_year'           => $fy,
            'seq'                      => random_int(100000, 999999),
            'kind'                     => 'client',
            'workspace_id'             => $ws->id,
            'user_id'                  => $u->id,
            'currency'                 => 'USD',
            'subtotal_minor'           => 5000,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 5000,
            'discount_minor'           => 0,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'line_items'               => [],
            'tax_breakdown'            => [],
            'status'                   => 'draft',
            'issued_at'                => now(),
            'recipient_email'          => 'client@ex.com',
        ]);
    }

    public function test_email_failure_does_not_mark_sent_and_returns_pay_link(): void
    {
        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->draftInvoice($u, $ws);

        // Drive a REAL mail transport failure: the resolved mailer's html()
        // throws (as a down SMTP would). The central Emailer would normally
        // swallow this (log a `failed` row + return), but markSent opts into
        // throw_on_failure so the error surfaces and the invoice is NOT stamped.
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('html')
            ->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('mailer')->andReturn($mailer);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/send");

        // Unified error envelope, 502, with a fallback pay link in details.
        $resp->assertStatus(502)
            ->assertJsonStructure(['error' => ['message', 'details' => ['pay_url']]]);

        $payUrl = $resp->json('error.details.pay_url');
        $this->assertIsString($payUrl);
        $this->assertStringContainsString('signature=', $payUrl);

        // The invoice is untouched: still a draft, never stamped as sent.
        $fresh = $invoice->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertNull($fresh->sent_at);

        // The Emailer still recorded the failed delivery for the admin log.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.client_invoice',
            'recipient' => $invoice->recipient_email,
            'status'    => 'failed',
        ]);
    }

    public function test_successful_send_stamps_sent_at(): void
    {
        // The test mailer defaults to the in-memory `array` transport (see
        // phpunit.xml MAIL_MAILER=array), so this is a genuine successful
        // delivery — NOT Mail::fake(), whose MailFake has no html() and would
        // now (correctly) trip the throw_on_failure path.
        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->draftInvoice($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/send");

        $resp->assertOk()
            ->assertJsonStructure(['data' => ['invoice', 'pay_url']])
            ->assertJsonPath('data.invoice.status', 'sent');

        $fresh = $invoice->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);

        // Delivery succeeded, so the logged row is `sent`, not `failed`.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.client_invoice',
            'recipient' => $invoice->recipient_email,
            'status'    => 'sent',
        ]);
    }
}
