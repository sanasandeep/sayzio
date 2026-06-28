<?php

namespace Tests\Feature;

use App\Modules\Api\Controllers\BillingController;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        return User::create([
            'name'     => 'inv ' . Str::random(4),
            'email'    => 'inv' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
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

        // Simulate the send step throwing (e.g. a synchronous mail transport
        // failure). markSent must NOT have stamped the invoice when it raises.
        $svc = \Mockery::mock(ClientInvoiceService::class);
        $svc->shouldReceive('markSent')
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));
        $this->app->instance(ClientInvoiceService::class, $svc);

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
    }

    public function test_successful_send_stamps_sent_at(): void
    {
        Mail::fake();

        $u  = $this->makeUser();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->draftInvoice($u, $ws);

        // Real service this time — delivery succeeds, so the stamp is written.
        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/send");

        $resp->assertOk()
            ->assertJsonStructure(['data' => ['invoice', 'pay_url']])
            ->assertJsonPath('data.invoice.status', 'sent');

        $fresh = $invoice->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
    }
}
