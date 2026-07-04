<?php

namespace Tests\Feature;

use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoicePdfRenderer;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the per-invoice letterhead override lifecycle end to end:
 *   - upload persists path + pixel dimensions (web + API),
 *   - an image whose orientation is flatly wrong is rejected before it can be
 *     stored (dimension/orientation validation),
 *   - removal clears the stored path (web + API), and
 *   - the PDF renderer prefers the per-invoice override image over the issuing
 *     BillingCompany's default.
 *
 * Margin inheritance (a null invoice margin falling back to the company safe
 * area) is covered by ClientInvoiceContactLetterheadApiTest; this file focuses
 * on the image itself + the upload/remove/reject paths.
 */
class ClientInvoiceLetterheadTest extends TestCase
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

    private function token(User $u): string
    {
        return $u->createToken('test')->plainTextToken;
    }

    private function draftInvoice(User $u, Workspace $ws, ?int $companyId = null): Invoice
    {
        return app(ClientInvoiceService::class)->createStandalone(array_filter([
            'recipient_email'    => 'client@ex.com',
            'billing_company_id' => $companyId,
            'line_items'         => [['label' => 'Work', 'amount_minor' => 5000, 'quantity' => 1]],
        ], fn ($v) => $v !== null), $ws, $u->id);
    }

    // ---- Upload -----------------------------------------------------------

    public function test_web_update_persists_letterhead_upload_with_dimensions(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->draftInvoice($u, $ws);

        $resp = $this->actingAs($u)->post(route('user.client-invoices.update', $invoice), [
            '_method'                => 'PUT',
            'letterhead_orientation' => 'portrait',
            'letterhead'             => UploadedFile::fake()->image('lh.jpg', 800, 1120),
            'line_items'             => [['label' => 'Work', 'amount_minor' => 5000, 'quantity' => 1]],
        ]);
        $resp->assertStatus(302);

        $invoice->refresh();
        $this->assertNotNull($invoice->letterhead_path);
        $this->assertSame(800, (int) $invoice->letterhead_width);
        $this->assertSame(1120, (int) $invoice->letterhead_height);
        $this->assertSame('portrait', $invoice->letterhead_orientation);
        Storage::disk('public')->assertExists($invoice->letterhead_path);
    }

    // ---- Reject invalid image --------------------------------------------

    public function test_web_rejects_letterhead_whose_orientation_is_wrong(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->draftInvoice($u, $ws);

        // A wide (landscape) image submitted as a portrait letterhead must be
        // rejected by the dimension/orientation validator before it is stored.
        $resp = $this->actingAs($u)->post(route('user.client-invoices.update', $invoice), [
            '_method'                => 'PUT',
            'letterhead_orientation' => 'portrait',
            'letterhead'             => UploadedFile::fake()->image('wide.jpg', 1600, 600),
            'line_items'             => [['label' => 'Work', 'amount_minor' => 5000, 'quantity' => 1]],
        ]);

        $resp->assertSessionHasErrors('letterhead');
        $invoice->refresh();
        $this->assertNull($invoice->letterhead_path);
    }

    public function test_api_rejects_letterhead_that_is_too_small(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $svc = app(ClientInvoiceService::class);
        $invoice = $this->draftInvoice($u, $ws);

        // Below LetterheadValidator::MIN_WIDTH/MIN_HEIGHT (400px) → rejected.
        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->post("/api/v1/billing/invoices/{$invoice->id}", [
                '_method'                => 'PATCH',
                'letterhead_orientation' => 'portrait',
                'letterhead'             => UploadedFile::fake()->image('tiny.jpg', 100, 140),
            ]);

        $resp->assertStatus(422);
        $invoice->refresh();
        $this->assertNull($invoice->letterhead_path);
    }

    // ---- Removal ----------------------------------------------------------

    public function test_web_update_removes_letterhead_and_deletes_file(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->draftInvoice($u, $ws);

        // Seed an existing override file + path.
        $path = 'billing/letterheads/existing.jpg';
        Storage::disk('public')->put($path, 'LETTERHEAD-BYTES');
        $invoice->forceFill([
            'letterhead_path'   => $path,
            'letterhead_width'  => 800,
            'letterhead_height' => 1120,
        ])->save();

        $resp = $this->actingAs($u)->post(route('user.client-invoices.update', $invoice), [
            '_method'           => 'PUT',
            'remove_letterhead' => '1',
            'line_items'        => [['label' => 'Work', 'amount_minor' => 5000, 'quantity' => 1]],
        ]);
        $resp->assertStatus(302);

        $invoice->refresh();
        $this->assertNull($invoice->letterhead_path);
        $this->assertNull($invoice->letterhead_width);
        $this->assertNull($invoice->letterhead_height);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_api_update_removes_letterhead_and_deletes_file(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $invoice = $this->draftInvoice($u, $ws);

        $path = 'billing/letterheads/api-existing.jpg';
        Storage::disk('public')->put($path, 'LETTERHEAD-BYTES');
        $invoice->forceFill([
            'letterhead_path'   => $path,
            'letterhead_width'  => 800,
            'letterhead_height' => 1120,
        ])->save();

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->patchJson("/api/v1/billing/invoices/{$invoice->id}", [
                'remove_letterhead' => true,
            ]);

        $resp->assertOk();
        $invoice->refresh();
        $this->assertNull($invoice->letterhead_path);
        Storage::disk('public')->assertMissing($path);
    }

    // ---- PDF prefers per-invoice override over company default ------------

    public function test_pdf_prefers_per_invoice_letterhead_over_company_default(): void
    {
        Storage::fake('public');
        $u  = $this->user();
        $ws = $this->bind($u);

        // Two distinct files: the company default vs. the per-invoice override.
        // The renderer inlines the chosen file's bytes as a base64 data URI, so
        // the override winning is provable by which bytes appear in the HTML.
        $companyPath  = 'billing/letterheads/company.jpg';
        $overridePath = 'billing/letterheads/override.jpg';
        Storage::disk('public')->put($companyPath, 'COMPANY-DEFAULT-LETTERHEAD-BYTES');
        Storage::disk('public')->put($overridePath, 'INVOICE-OVERRIDE-LETTERHEAD-BYTES');

        $company = BillingCompany::create([
            'user_id'                => $u->id,
            'workspace_id'           => $ws->id,
            'name'                   => 'Acme Co',
            'letterhead_path'        => $companyPath,
            'letterhead_orientation' => 'portrait',
        ]);

        $invoice = $this->draftInvoice($u, $ws, $company->id);

        // No override yet → the company default bytes render.
        $htmlDefault = app(ClientInvoicePdfRenderer::class)->invoiceHtml($invoice->fresh());
        $this->assertStringContainsString(base64_encode('COMPANY-DEFAULT-LETTERHEAD-BYTES'), $htmlDefault);

        // Set a per-invoice override → its bytes must win, company bytes gone.
        $invoice->forceFill(['letterhead_path' => $overridePath])->save();
        $htmlOverride = app(ClientInvoicePdfRenderer::class)->invoiceHtml($invoice->fresh());

        $this->assertStringContainsString(base64_encode('INVOICE-OVERRIDE-LETTERHEAD-BYTES'), $htmlOverride);
        $this->assertStringNotContainsString(base64_encode('COMPANY-DEFAULT-LETTERHEAD-BYTES'), $htmlOverride);
    }
}
