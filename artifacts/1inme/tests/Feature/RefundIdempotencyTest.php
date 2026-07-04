<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Refunds must be idempotent (task-3558): a double-click, impatient retry, or
 * webhook re-delivery of the same intended refund must NOT create a second
 * Refund + second CreditNote or over-refund the client.
 *
 * Two guards are exercised here:
 *   - an explicit idempotency key (web hidden field / API body / Idempotency-Key
 *     header) collapses repeats into a no-op returning the original; and
 *   - a short dedupe window collapses an identical un-keyed repeat.
 *
 * API requests use a real personal access token (NOT Sanctum::actingAs, which
 * breaks the TouchSessionToken middleware).
 */
class RefundIdempotencyTest extends TestCase
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

    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $ws;
    }

    /** A fully-paid client invoice ready to be refunded. */
    private function paidInvoice(User $u, Workspace $ws, int $grand = 5000): Invoice
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
            'subtotal_minor'           => $grand,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => $grand,
            'discount_minor'           => 0,
            'amount_paid_minor'        => $grand,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'line_items'               => [],
            'tax_breakdown'            => [],
            'status'                   => 'paid',
            'gateway'                  => 'stripe',
            'issued_at'                => now(),
            'paid_at'                  => now(),
            'recipient_email'          => 'client@ex.com',
        ]);
    }

    // ------------------------------------------------------------------
    // Web
    // ------------------------------------------------------------------

    public function test_web_duplicate_refund_with_same_key_creates_one_refund(): void
    {
        $u  = $this->makeUser();
        $ws = $this->bind($u);
        $invoice = $this->paidInvoice($u, $ws);

        $payload = ['amount_minor' => 2000, 'reason' => 'oops', 'idempotency_key' => 'dup-key-1'];

        $this->actingAs($u)->post(route('user.client-invoices.refund', $invoice), $payload)
            ->assertStatus(302);
        // A double-click re-submits the exact same form (same key) -> no-op.
        $this->actingAs($u)->post(route('user.client-invoices.refund', $invoice), $payload)
            ->assertStatus(302);

        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());

        $fresh = $invoice->fresh();
        $this->assertSame(2000, (int) $fresh->refundedTotalMinor());
        $this->assertSame('partially_refunded', $fresh->status);
        $this->assertSame(3000, (int) $fresh->amount_paid_minor);
    }

    public function test_web_duplicate_refund_without_key_is_deduped_by_window(): void
    {
        $u  = $this->makeUser();
        $ws = $this->bind($u);
        $invoice = $this->paidInvoice($u, $ws);

        // No idempotency key: two identical POSTs within the dedupe window.
        $payload = ['amount_minor' => 2500, 'reason' => 'partial'];
        $this->actingAs($u)->post(route('user.client-invoices.refund', $invoice), $payload)
            ->assertStatus(302);
        $this->actingAs($u)->post(route('user.client-invoices.refund', $invoice), $payload)
            ->assertStatus(302);

        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());
        $this->assertSame(2500, (int) $invoice->fresh()->refundedTotalMinor());
    }

    // ------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------

    public function test_api_duplicate_refund_with_idempotency_header_creates_one_refund(): void
    {
        $u  = $this->makeUser();
        app(WorkspaceContext::class)->resolve($u);
        $ws = $this->bind($u);
        $invoice = $this->paidInvoice($u, $ws);

        $token = $u->createToken('test')->plainTextToken;
        $headers = [
            'Authorization'   => 'Bearer ' . $token,
            'Idempotency-Key' => 'api-dup-1',
        ];
        $body = ['amount_minor' => 1500, 'reason' => 'retry'];

        $first = $this->withHeaders($headers)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/refund", $body);
        $first->assertOk();
        // A retried POST (same key) must be a no-op returning the same result.
        $second = $this->withHeaders($headers)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/refund", $body);
        $second->assertOk();

        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());

        $fresh = $invoice->fresh();
        $this->assertSame(1500, (int) $fresh->refundedTotalMinor());
        $this->assertSame(3500, (int) $fresh->amount_paid_minor);
    }

    public function test_api_duplicate_refund_without_key_is_deduped_by_window(): void
    {
        $u  = $this->makeUser();
        app(WorkspaceContext::class)->resolve($u);
        $ws = $this->bind($u);
        $invoice = $this->paidInvoice($u, $ws);

        $token = $u->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];
        $body = ['amount_minor' => 1000];

        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$invoice->id}/refund", $body)->assertOk();
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$invoice->id}/refund", $body)->assertOk();

        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1000, (int) $invoice->fresh()->refundedTotalMinor());
    }

    public function test_distinct_refunds_outside_dedupe_window_both_apply(): void
    {
        $u  = $this->makeUser();
        $ws = $this->bind($u);
        $invoice = $this->paidInvoice($u, $ws);

        // Two genuinely distinct refunds (different keys) both take effect.
        app(ClientInvoiceService::class)->refund($invoice->fresh(), 2000, 'first', true, 'k1');
        app(ClientInvoiceService::class)->refund($invoice->fresh(), 1000, 'second', true, 'k2');

        $this->assertSame(2, Refund::where('invoice_id', $invoice->id)->count());
        $this->assertSame(2, CreditNote::where('invoice_id', $invoice->id)->count());
        $this->assertSame(3000, (int) $invoice->fresh()->refundedTotalMinor());
        $this->assertSame('partially_refunded', $invoice->fresh()->status);
    }
}
