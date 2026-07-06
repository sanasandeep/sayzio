<?php

namespace Tests\Feature;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the receipt-email side of marking a client invoice
 * paid (ClientInvoiceService::emailReceipt + BillingController::markPaidInvoice).
 *
 * Unlike sending (markSent), emailReceipt runs AFTER the payment is recorded,
 * so a transport failure must NOT roll back the payment or receipt — but it
 * must also NOT silently claim the receipt was delivered. emailReceipt opts
 * into the central Emailer's throw_on_failure; the API catches the resulting
 * EmailDeliveryException and reports `receipt_emailed: false` (instead of
 * 500ing or pretending success) so the owner knows to re-send from the admin
 * email log.
 *
 * The failure case drives a REAL mail transport failure rather than mocking
 * the service, so it exercises the actual swallow-vs-throw path.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which breaks the TouchSessionToken middleware).
 */
class ClientInvoiceReceiptFailureApiTest extends TestCase
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

    /** A payable client invoice (status sent) in the user's active workspace. */
    private function payableInvoice(User $u, Workspace $ws): Invoice
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
            'status'                   => 'sent',
            'issued_at'                => now(),
            'recipient_email'          => 'client@ex.com',
        ]);
    }

    public function test_receipt_email_failure_keeps_payment_and_reports_not_emailed(): void
    {
        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->payableInvoice($u, $ws);

        // Drive a REAL mail transport failure on the receipt email: the resolved
        // mailer's html() throws (as a down SMTP would). markPaid itself sends no
        // mail, so only emailReceipt hits this. The central Emailer would swallow
        // it (log a `failed` row + return), but emailReceipt opts into
        // throw_on_failure so the API can report the outcome.
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('html')
            ->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('mailer')->andReturn($mailer);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/mark-paid", [
                'method'        => 'bank_transfer',
                'email_receipt' => true,
            ]);

        // The request succeeds (payment is real) but flags the email failure.
        $resp->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.receipt_emailed', false);

        // The payment + receipt row stay intact despite the email failure.
        $fresh = $invoice->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id]);

        // The Emailer still recorded the failed delivery for the admin log
        // (the owner can re-send it from there).
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.receipt',
            'recipient' => $invoice->recipient_email,
            'status'    => 'failed',
        ]);
    }

    public function test_successful_receipt_email_reports_emailed(): void
    {
        // The test mailer defaults to the in-memory `array` transport (see
        // phpunit.xml MAIL_MAILER=array), so this is a genuine successful send.
        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->payableInvoice($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/mark-paid", [
                'method'        => 'bank_transfer',
                'email_receipt' => true,
            ]);

        $resp->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.receipt_emailed', true);

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.receipt',
            'recipient' => $invoice->recipient_email,
            'status'    => 'sent',
        ]);
    }

    public function test_mark_paid_without_email_receipt_reports_null(): void
    {
        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->payableInvoice($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/mark-paid", [
                'method' => 'bank_transfer',
            ]);

        $resp->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.receipt_emailed', null);
    }
}
