<?php

namespace Tests\Feature;

use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoicePdfRenderer;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the client invoice / receipt PDF against the totals drifting away
 * from the on-screen (web/API) figures.
 *
 * The PDF reads the persisted line_items / tax_breakdown / subtotal_minor /
 * discount_minor / grand_total_minor that InvoiceCalculator stored. If a
 * future change renames a key or alters the stored shape, the Blade re-layout
 * would silently print numbers that disagree with the web + API surfaces.
 * These tests pin a known fixture's figures (computed by the same calculator
 * the web/API use) into the rendered PDF HTML, and cover the signed-route
 * auth contract (200 + application/pdf with a valid signature, 401 without).
 */
class ClientInvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
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

    /**
     * A standalone invoice whose totals exercise per-line qty, a discount and
     * exclusive tax — every figure distinct so an assertSee can't pass on a
     * coincidental collision:
     *   - Line "Design"  : 15000 x 1            => line total 15000 (USD 150.00)
     *   - Line "Hosting" :  5000 x 2            => line total 10000 (USD 100.00)
     *   - subtotal        = 25000               (USD 250.00)
     *   - discount        =  3000               (USD 30.00)
     *   - taxable base    = 22000, VAT 20%      => tax 4400 (USD 44.00)
     *   - grand total     = 25000 - 3000 + 4400 => 26400 (USD 264.00)
     */
    private function fixtureInvoice(User $u, Workspace $ws)
    {
        return app(ClientInvoiceService::class)->createStandalone([
            'recipient_email' => 'client@ex.com',
            'currency'        => 'USD',
            'discount_minor'  => 3000,
            'line_items'      => [
                ['label' => 'Design',  'amount_minor' => 15000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
                ['label' => 'Hosting', 'amount_minor' => 5000,  'quantity' => 2, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
            ],
        ], $ws, $u->id);
    }

    public function test_calculator_persists_the_expected_fixture_totals(): void
    {
        // Sanity-pin the stored figures the PDF reads back — these are the same
        // numbers the web edit screen and the REST API envelope expose, so a
        // calculator-shape change shows up here first.
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->fixtureInvoice($u, $ws);

        $this->assertSame(25000, (int) $invoice->subtotal_minor);
        $this->assertSame(3000, (int) $invoice->discount_minor);
        $this->assertSame(4400, (int) $invoice->tax_total_minor);
        $this->assertSame(26400, (int) $invoice->grand_total_minor);
    }

    public function test_invoice_pdf_html_shows_the_persisted_figures(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->fixtureInvoice($u, $ws);

        $html = app(ClientInvoicePdfRenderer::class)->invoiceHtml($invoice);

        // Per-line totals.
        $this->assertStringContainsString('USD 150.00', $html); // Design 15000 x 1
        $this->assertStringContainsString('USD 100.00', $html); // Hosting 5000 x 2
        // Totals block.
        $this->assertStringContainsString('USD 250.00', $html); // subtotal
        $this->assertStringContainsString('-USD 30.00', $html); // discount
        $this->assertStringContainsString('USD 44.00', $html);  // VAT 20%
        $this->assertStringContainsString('USD 264.00', $html); // grand total
        // The grouped tax row is labelled from the stored breakdown.
        $this->assertStringContainsString('VAT', $html);
    }

    public function test_invoice_pdf_route_requires_a_valid_signature(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->fixtureInvoice($u, $ws);

        $signed = URL::signedRoute('client-invoice.pdf', ['invoice' => $invoice->id]);
        $path   = parse_url($signed, PHP_URL_PATH) . '?' . parse_url($signed, PHP_URL_QUERY);

        $ok = $this->get($path);
        $ok->assertStatus(200);
        $ok->assertHeader('Content-Type', 'application/pdf');
        // dompdf always emits a %PDF- magic header.
        $this->assertStringStartsWith('%PDF-', $ok->getContent());

        // No signature => blocked.
        $this->get(parse_url($signed, PHP_URL_PATH))->assertStatus(401);
        // Tampered signature => blocked.
        $this->get(parse_url($signed, PHP_URL_PATH) . '?signature=deadbeef')->assertStatus(401);
    }

    public function test_receipt_pdf_html_shows_the_paid_figures(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->fixtureInvoice($u, $ws);
        app(ClientInvoiceService::class)->markPaidManual($invoice, 'bank_transfer', 'TXN-77');

        $receipt = Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame(26400, (int) $receipt->amount_minor);

        $html = app(ClientInvoicePdfRenderer::class)->receiptHtml($invoice->fresh(), $receipt);

        $this->assertStringContainsString('USD 250.00', $html); // subtotal
        $this->assertStringContainsString('-USD 30.00', $html); // discount
        $this->assertStringContainsString('USD 44.00', $html);  // VAT
        $this->assertStringContainsString('USD 264.00', $html); // total paid
        $this->assertStringContainsString($receipt->number, $html);
    }

    public function test_receipt_pdf_route_requires_a_valid_signature(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->fixtureInvoice($u, $ws);
        app(ClientInvoiceService::class)->markPaidManual($invoice, 'cash');

        $signed = URL::signedRoute('client-invoice.receipt-pdf', ['invoice' => $invoice->id]);
        $path   = parse_url($signed, PHP_URL_PATH) . '?' . parse_url($signed, PHP_URL_QUERY);

        $ok = $this->get($path);
        $ok->assertStatus(200);
        $ok->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $ok->getContent());

        // No signature => blocked.
        $this->get(parse_url($signed, PHP_URL_PATH))->assertStatus(401);
    }
}
