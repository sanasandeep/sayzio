<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the recipient-resolution + standalone-receipt behaviors added
 * alongside the letterhead work, across BOTH surfaces (the web
 * ClientInvoiceController and the REST BillingController), so a future
 * billing refactor can't silently break:
 *   - issuing an invoice/receipt to a Contact/lead (name/email/address are
 *     prefilled from the chosen contact when not explicitly supplied), and
 *   - the standalone receipt flow (create + immediately mark-paid) against a
 *     contact, a vault client, and a plain manual recipient.
 *
 * The multipart+letterhead shape of these same endpoints is covered by
 * ClientInvoiceContactLetterheadApiTest; this file focuses on recipient
 * resolution and the paid-receipt lifecycle.
 */
class ClientInvoiceContactReceiptTest extends TestCase
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

    /** Bind the resolved workspace for the web (session) surface. */
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

    /** A contact carrying a primary email + a manual-profile street address. */
    private function contactWithEmailAndAddress(User $u, Workspace $ws): Contact
    {
        $contact = Contact::create([
            'user_id'        => $u->id,
            'display_name'   => 'Jamie Client',
            'manual_profile' => [
                'location' => ['label' => 'HQ', 'address' => '221B Baker Street, London', 'lat' => null, 'lng' => null],
            ],
        ]);
        // workspace_id is not mass-assignable (set by BelongsToWorkspace only
        // when a current_workspace is bound); force it so the API path — which
        // never binds current_workspace — can still resolve the contact.
        $contact->forceFill(['workspace_id' => $ws->id])->save();
        ContactEmail::create([
            'contact_id' => $contact->id,
            'label'      => 'work',
            'value'      => 'jamie@client.example',
            'is_primary' => true,
        ]);
        return $contact;
    }

    private function vaultClient(User $u, Workspace $ws): VaultClient
    {
        return VaultClient::create([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $u->id,
            'name'               => 'Globex LLC',
            'primary_email'      => 'ap@globex.example',
        ]);
    }

    // ---- Web: invoice to a contact --------------------------------------

    public function test_web_store_invoice_prefills_recipient_from_contact(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $contact = $this->contactWithEmailAndAddress($u, $ws);

        $resp = $this->actingAs($u)->post('/user/client-invoices', [
            'contact_id' => $contact->id,
            'line_items' => [
                ['label' => 'Consulting', 'amount_minor' => 12000, 'quantity' => 1],
            ],
        ]);
        $resp->assertStatus(302);

        $invoice = Invoice::where('kind', 'client')->latest('id')->firstOrFail();
        $this->assertSame((int) $contact->id, (int) $invoice->contact_id);
        $this->assertSame('Jamie Client', $invoice->recipient_name);
        $this->assertSame('jamie@client.example', $invoice->recipient_email);
        $this->assertSame('221B Baker Street, London', $invoice->recipient_address);
        $this->assertSame(12000, (int) $invoice->grand_total_minor);
    }

    public function test_web_update_invoice_switches_recipient_to_contact(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $contact = $this->contactWithEmailAndAddress($u, $ws);

        // Start from a plain manual-recipient invoice, then re-point it at the
        // contact via the edit screen.
        $invoice = app(ClientInvoiceService::class)->createStandalone([
            'recipient_email' => 'temp@ex.com',
            'line_items'      => [['label' => 'Draft', 'amount_minor' => 1000, 'quantity' => 1]],
        ], $ws, $u->id);

        $resp = $this->actingAs($u)->put(route('user.client-invoices.update', $invoice), [
            'contact_id' => $contact->id,
            'line_items' => [
                ['label' => 'Revised', 'amount_minor' => 8000, 'quantity' => 1],
            ],
        ]);
        $resp->assertStatus(302);

        $invoice->refresh();
        $this->assertSame((int) $contact->id, (int) $invoice->contact_id);
        $this->assertSame('Jamie Client', $invoice->recipient_name);
        $this->assertSame('jamie@client.example', $invoice->recipient_email);
        $this->assertSame('221B Baker Street, London', $invoice->recipient_address);
        $this->assertSame(8000, (int) $invoice->grand_total_minor);
    }

    // ---- Web: standalone receipts --------------------------------------

    public function test_web_standalone_receipt_against_contact_is_created_and_paid(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $contact = $this->contactWithEmailAndAddress($u, $ws);

        $resp = $this->actingAs($u)->post('/user/client-invoices/receipts', [
            'contact_id' => $contact->id,
            'method'     => 'cash',
            'line_items' => [
                ['label' => 'Photo session', 'amount_minor' => 20000, 'quantity' => 1],
            ],
        ]);
        $resp->assertStatus(302);

        $invoice = Invoice::where('kind', 'client')->latest('id')->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame((int) $contact->id, (int) $invoice->contact_id);
        $this->assertSame('Jamie Client', $invoice->recipient_name);
        $this->assertSame('221B Baker Street, London', $invoice->recipient_address);
        $this->assertSame(20000, (int) $invoice->grand_total_minor);

        $receipt = Receipt::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('manual', $receipt->method);
        $this->assertSame(20000, (int) $receipt->amount_minor);
    }

    public function test_web_standalone_receipt_against_vault_client_is_created_and_paid(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $client = $this->vaultClient($u, $ws);

        $resp = $this->actingAs($u)->post('/user/client-invoices/receipts', [
            'vault_client_id' => $client->id,
            'method'          => 'bank_transfer',
            'reference'       => 'TXN-500',
            'line_items'      => [
                ['label' => 'Retainer', 'amount_minor' => 45000, 'quantity' => 1],
            ],
        ]);
        $resp->assertStatus(302);

        $invoice = Invoice::where('kind', 'client')->latest('id')->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame((int) $client->id, (int) $invoice->vault_client_id);
        $this->assertSame(45000, (int) $invoice->grand_total_minor);

        $receipt = Receipt::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('manual', $receipt->method);
        $this->assertSame('TXN-500', $receipt->gateway_ref);
    }

    public function test_web_standalone_receipt_requires_a_recipient(): void
    {
        $u  = $this->user();
        $this->bind($u);

        // No client, contact, or recipient email → refused before creating.
        $this->actingAs($u)->post('/user/client-invoices/receipts', [
            'method'     => 'cash',
            'line_items' => [
                ['label' => 'Ghost', 'amount_minor' => 1000, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('recipient');

        $this->assertSame(0, Invoice::where('kind', 'client')->count());
    }

    // ---- API: standalone receipt against a vault client ----------------

    public function test_api_standalone_receipt_against_vault_client(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $client = $this->vaultClient($u, $ws);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson('/api/v1/billing/receipts', [
                'vault_client_id' => $client->id,
                'method'          => 'card',
                'line_items'      => [
                    ['label' => 'Setup fee', 'amount_minor' => 15000, 'quantity' => 1],
                ],
            ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.invoice.status', 'paid');

        $invoice = Invoice::findOrFail($resp->json('data.invoice.id'));
        $this->assertSame((int) $client->id, (int) $invoice->vault_client_id);
        $this->assertSame(15000, (int) $invoice->grand_total_minor);
        $this->assertNotNull(Receipt::where('invoice_id', $invoice->id)->first());
    }

    public function test_api_standalone_receipt_requires_a_recipient(): void
    {
        $u = $this->user();

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->postJson('/api/v1/billing/receipts', [
                'method'     => 'cash',
                'line_items' => [
                    ['label' => 'Ghost', 'amount_minor' => 1000, 'quantity' => 1],
                ],
            ]);

        $resp->assertStatus(422);
        $this->assertSame(0, Invoice::where('kind', 'client')->count());
    }

    // ---- API: invoice to a contact -------------------------------------

    public function test_api_update_invoice_switches_recipient_to_contact(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        $contact = $this->contactWithEmailAndAddress($u, $ws);

        $invoice = app(ClientInvoiceService::class)->createStandalone([
            'recipient_email' => 'temp@ex.com',
            'line_items'      => [['label' => 'Draft', 'amount_minor' => 1000, 'quantity' => 1]],
        ], $ws, $u->id);

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($u)])
            ->patchJson("/api/v1/billing/invoices/{$invoice->id}", [
                'contact_id' => $contact->id,
                'line_items' => [
                    ['label' => 'Revised', 'amount_minor' => 9000, 'quantity' => 1],
                ],
            ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.invoice.recipient_name', 'Jamie Client');
        $resp->assertJsonPath('data.invoice.recipient_address', '221B Baker Street, London');

        $invoice->refresh();
        $this->assertSame((int) $contact->id, (int) $invoice->contact_id);
        $this->assertSame('jamie@client.example', $invoice->recipient_email);
        $this->assertSame(9000, (int) $invoice->grand_total_minor);
    }
}
