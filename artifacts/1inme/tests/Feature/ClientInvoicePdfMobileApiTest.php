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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The signed PDF routes (`client-invoice.pdf` / `client-invoice.receipt-pdf`)
 * are deliberately session-less so the in-app button, emailed links AND the
 * REST/mobile clients can all share them. ClientInvoicePdfTest pins the figures
 * for the web/signed-route path; this test proves the REST/mobile surface (the
 * one the Expo app drives) can actually OBTAIN a working signed download link
 * and that the link carries the very same figures.
 *
 * The mobile app never builds the signed URL itself — it reads `pdf_url` /
 * `receipt_pdf_url` off the billing API envelope (`GET /billing/invoices/{id}`
 * and `GET /billing/invoices/{id}/receipt`), which mint a `temporarySignedRoute`
 * to the shared route. So we:
 *   1. hit the API as a real Bearer-token caller,
 *   2. assert the envelope totals equal the pinned fixture,
 *   3. follow the returned signed URL to a 200 + application/pdf download,
 *   4. assert the document the route renders carries the same pinned figures.
 *
 * The fixture is the SAME one ClientInvoicePdfTest uses, so the totals stay
 * pinned across the web and mobile/REST surfaces.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which breaks the TouchSessionToken middleware).
 */
class ClientInvoicePdfMobileApiTest extends TestCase
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

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Identical fixture to ClientInvoicePdfTest — per-line qty, a discount and
     * exclusive 20% VAT, every figure distinct:
     *   - subtotal     = 25000 (USD 250.00)
     *   - discount     =  3000 (USD 30.00)
     *   - tax          =  4400 (USD 44.00)
     *   - grand total  = 26400 (USD 264.00)
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

    /** Turn an absolute signed URL into a path+query the test client can GET. */
    private function toLocalPath(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
    }

    public function test_mobile_show_endpoint_returns_a_signed_pdf_link_with_matching_totals(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->fixtureInvoice($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->getJson("/api/v1/billing/invoices/{$invoice->id}");

        // The figures the mobile UI shows match the persisted calculator output.
        $resp->assertOk()
            ->assertJsonPath('data.invoice.subtotal_minor', 25000)
            ->assertJsonPath('data.invoice.tax_total_minor', 4400)
            ->assertJsonPath('data.invoice.grand_total_minor', 26400);

        // The envelope hands the mobile client a signed download URL (it never
        // builds one itself). An unpaid invoice has no receipt PDF yet.
        $pdfUrl = $resp->json('data.invoice.pdf_url');
        $this->assertNotNull($pdfUrl, 'show endpoint must expose a signed pdf_url');
        $this->assertNull($resp->json('data.invoice.receipt_pdf_url'));

        // Following that link (session-less, signed-only) yields the real PDF.
        $download = $this->get($this->toLocalPath($pdfUrl));
        $download->assertStatus(200);
        $download->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download->getContent());

        // And the document the route renders carries the same pinned figures as
        // the web surface (same renderer, same fixture).
        $html = app(ClientInvoicePdfRenderer::class)->invoiceHtml($invoice);
        $this->assertStringContainsString('USD 250.00', $html); // subtotal
        $this->assertStringContainsString('-USD 30.00', $html);  // discount
        $this->assertStringContainsString('USD 44.00', $html);   // VAT 20%
        $this->assertStringContainsString('USD 264.00', $html);  // grand total
    }

    public function test_mobile_can_download_the_receipt_pdf_for_a_paid_invoice(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->fixtureInvoice($u, $ws);
        app(ClientInvoiceService::class)->markPaidManual($invoice, 'bank_transfer', 'TXN-77');

        $receipt = Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame(26400, (int) $receipt->amount_minor);

        $token = 'Bearer ' . $this->token($u);

        // Once paid, the show envelope also exposes a signed receipt PDF link.
        $show = $this->withHeaders(['Authorization' => $token])
            ->getJson("/api/v1/billing/invoices/{$invoice->id}");
        $show->assertOk();
        $receiptPdfUrl = $show->json('data.invoice.receipt_pdf_url');
        $this->assertNotNull($receiptPdfUrl, 'paid invoice must expose receipt_pdf_url');

        $download = $this->get($this->toLocalPath($receiptPdfUrl));
        $download->assertStatus(200);
        $download->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download->getContent());

        // The dedicated receipt endpoint hands back the same signed link too.
        $receiptResp = $this->withHeaders(['Authorization' => $token])
            ->getJson("/api/v1/billing/invoices/{$invoice->id}/receipt");
        $receiptResp->assertOk()
            ->assertJsonPath('data.receipt.number', $receipt->number);
        $endpointPdfUrl = $receiptResp->json('data.receipt.pdf_url');
        $this->assertNotNull($endpointPdfUrl);

        $download2 = $this->get($this->toLocalPath($endpointPdfUrl));
        $download2->assertStatus(200);
        $download2->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download2->getContent());

        // The downloaded receipt carries the same paid figures + receipt number.
        $html = app(ClientInvoicePdfRenderer::class)->receiptHtml($invoice->fresh(), $receipt);
        $this->assertStringContainsString('USD 250.00', $html); // subtotal
        $this->assertStringContainsString('-USD 30.00', $html);  // discount
        $this->assertStringContainsString('USD 44.00', $html);   // VAT
        $this->assertStringContainsString('USD 264.00', $html);  // total paid
        $this->assertStringContainsString($receipt->number, $html);
    }

    public function test_signed_pdf_link_from_the_api_is_rejected_when_tampered(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->fixtureInvoice($u, $ws);

        $pdfUrl = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->getJson("/api/v1/billing/invoices/{$invoice->id}")
            ->json('data.invoice.pdf_url');
        $this->assertNotNull($pdfUrl);

        // Dropping the signature (as a stale/edited mobile link would) is blocked.
        $this->get(parse_url($pdfUrl, PHP_URL_PATH))->assertStatus(401);
        $this->get(parse_url($pdfUrl, PHP_URL_PATH) . '?signature=deadbeef')->assertStatus(401);
    }
}
