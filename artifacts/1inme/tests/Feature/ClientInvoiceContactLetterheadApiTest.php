<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the two gaps a code review found after the initial contact/receipt/
 * letterhead work: (1) a multipart request (the shape the mobile client sends
 * whenever a letterhead file rides the same request) must still pass Laravel's
 * `line_items.*.label` array validation — this only works if nested fields are
 * sent as indexed `line_items[0][label]` form fields rather than one
 * JSON-encoded string — and (2) resolveRecipient() must also prefill
 * `recipient_address` from the chosen Contact's manual profile, not just
 * name/email.
 */
class ClientInvoiceContactLetterheadApiTest extends TestCase
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

    private function contactWithAddress(User $u, Workspace $ws): Contact
    {
        return Contact::create([
            'user_id'        => $u->id,
            'workspace_id'   => $ws->id,
            'display_name'   => 'Jamie Client',
            'manual_profile' => [
                'location' => ['label' => 'HQ', 'address' => '221B Baker Street, London', 'lat' => null, 'lng' => null],
            ],
        ]);
    }

    public function test_standalone_receipt_created_via_multipart_with_letterhead_and_line_items_succeeds(): void
    {
        Storage::fake('public');

        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $contact = $this->contactWithAddress($u, $ws);

        // A tall (portrait) fake image so LetterheadValidator's dimension +
        // orientation checks pass.
        $letterhead = UploadedFile::fake()->image('letterhead.jpg', 800, 1120);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->post('/api/v1/billing/receipts', [
                'contact_id'             => $contact->id,
                'method'                 => 'cash',
                'letterhead_orientation' => 'portrait',
                'letterhead'             => $letterhead,
                // Nested arrays, as PHP's http test client (and the mobile
                // FormData bracket-notation fix) both encode them.
                'line_items' => [
                    ['label' => 'Consulting', 'amount_minor' => 12000, 'quantity' => 1],
                    ['label' => 'Materials', 'amount_minor' => 3400, 'quantity' => 2],
                ],
            ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.invoice.recipient_name', 'Jamie Client');
        $resp->assertJsonPath('data.invoice.recipient_address', '221B Baker Street, London');
        $resp->assertJsonPath('data.invoice.status', 'paid');
        $this->assertNotNull($resp->json('data.invoice.letterhead_url'), 'multipart letterhead upload must persist');

        $invoice = \App\Modules\User\Models\Invoice::findOrFail($resp->json('data.invoice.id'));
        $this->assertCount(2, $invoice->line_items, 'nested line_items must survive multipart bracket-notation encoding');
        $this->assertSame(19400, (int) $invoice->grand_total_minor);
    }

    public function test_invoice_update_via_multipart_with_letterhead_and_line_items_recomputes_totals(): void
    {
        Storage::fake('public');

        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $svc = app(\App\Services\Billing\ClientInvoiceService::class);
        $invoice = $svc->createStandalone([
            'recipient_email' => 'client@ex.com',
            'line_items'      => [['label' => 'Draft', 'amount_minor' => 1000, 'quantity' => 1]],
        ], $ws, $u->id);

        $letterhead = UploadedFile::fake()->image('letterhead.jpg', 800, 1120);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->post("/api/v1/billing/invoices/{$invoice->id}", [
                '_method'                => 'PATCH',
                'letterhead_orientation' => 'portrait',
                'letterhead'             => $letterhead,
                'line_items' => [
                    ['label' => 'Revised scope', 'amount_minor' => 5000, 'quantity' => 3],
                ],
            ]);

        $resp->assertOk();
        $this->assertNotNull($resp->json('data.invoice.letterhead_url'));
        $invoice->refresh();
        $this->assertCount(1, $invoice->line_items);
        $this->assertSame(15000, (int) $invoice->grand_total_minor);
    }

    public function test_invoice_letterhead_override_inherits_company_margins_when_unset(): void
    {
        Storage::fake('public');

        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);

        $company = \App\Modules\User\Models\BillingCompany::create([
            'user_id'                  => $u->id,
            'workspace_id'             => $ws->id,
            'name'                     => 'Acme Co',
            'letterhead_path'          => 'billing/letterheads/company.jpg',
            'letterhead_orientation'   => 'portrait',
            'letterhead_margin_top'    => 40,
            'letterhead_margin_right'  => 10,
            'letterhead_margin_bottom' => 40,
            'letterhead_margin_left'   => 10,
        ]);

        $invoice = app(\App\Services\Billing\ClientInvoiceService::class)->createStandalone([
            'recipient_email'    => 'client@ex.com',
            'billing_company_id' => $company->id,
            'line_items'         => [['label' => 'Design', 'amount_minor' => 5000, 'quantity' => 1]],
        ], $ws, $u->id);

        // Set only an invoice-level letterhead override; leave its own
        // margin_* columns null so they must fall back to the company's.
        $invoice->forceFill(['letterhead_path' => 'billing/letterheads/invoice-override.jpg'])->save();

        $html = app(\App\Services\Billing\ClientInvoicePdfRenderer::class)->invoiceHtml($invoice->fresh());

        $this->assertStringContainsString(
            'margin: 72mm 46mm 72mm 46mm;',
            $html,
            'an invoice-level letterhead override with no margins of its own must inherit the company safe-area margins (32/36 base + 40/10/40/10), not collapse to the 32/36 base'
        );
    }

    public function test_resolve_recipient_prefills_address_from_contact_manual_profile(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $contact = $this->contactWithAddress($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson('/api/v1/billing/invoices', [
                'contact_id' => $contact->id,
            ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.invoice.recipient_name', 'Jamie Client');
        $resp->assertJsonPath('data.invoice.recipient_address', '221B Baker Street, London');
    }
}
