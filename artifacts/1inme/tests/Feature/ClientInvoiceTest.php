<?php

namespace Tests\Feature;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientInvoiceTest extends TestCase
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

    public function test_draft_builds_line_items_from_hourly_and_flat_cards(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'Client A', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'Client A')->first();
        $col   = $board->columns()->orderBy('position')->first();

        $hourly = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Design work', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'hourly', 'rate_amount_minor' => 10000, // $100/hr
        ]);
        TaskTimeEntry::create([
            'workspace_id' => $ws->id, 'card_id' => $hourly->id, 'user_id' => $u->id,
            'started_at' => now()->subMinutes(90), 'ended_at' => now(),
            'minutes' => 90, 'source' => 'manual',
        ]);
        $flat = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Logo', 'position' => 2, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 50000,
        ]);

        $invoice = app(ClientInvoiceService::class)
            ->draftFromCards([$hourly->id, $flat->id], $ws, $u->id);

        $this->assertSame('client', $invoice->kind);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame((int) $ws->id, (int) $invoice->workspace_id);
        // hourly: 1.5h × $100 = $150 (15000 minor) ; flat: $500 (50000 minor)
        $this->assertSame(15000 + 50000, (int) $invoice->subtotal_minor);
        $this->assertSame(15000 + 50000, (int) $invoice->grand_total_minor);
        $this->assertSame(2, count($invoice->line_items));
        $this->assertSame(2, $invoice->sourceCards()->count());
    }

    public function test_mark_paid_syncs_cards_and_locks_time_entries(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'B', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'B')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $billed = $board->columns()->where('is_done', true)->first();
        $board->update(['billed_column_id' => $billed->id]);

        $card = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Build feature', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 20000,
        ]);
        $entry = TaskTimeEntry::create([
            'workspace_id' => $ws->id, 'card_id' => $card->id, 'user_id' => $u->id,
            'started_at' => now()->subMinutes(30), 'ended_at' => now(), 'minutes' => 30, 'source' => 'manual',
        ]);

        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);
        app(ClientInvoiceService::class)->markPaid($invoice->fresh(), 'stripe', 'evt_123');

        $card->refresh(); $entry->refresh(); $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame((int) $invoice->id, (int) $card->client_invoice_id);
        // card auto-moved to the billed column
        $this->assertSame((int) $billed->id, (int) $card->column_id);
        $this->assertSame((int) $invoice->id, (int) $entry->client_invoice_id);
    }

    public function test_draft_endpoint_creates_invoice_and_redirects_to_editor(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'C', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'C')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Flat work', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 12345,
        ]);

        $resp = $this->actingAs($u)->post('/user/client-invoices/drafts', ['card_ids' => [$card->id]]);
        $resp->assertStatus(302);
        $invoice = Invoice::where('kind', 'client')->latest('id')->first();
        $this->assertNotNull($invoice);
        $resp->assertRedirect(route('user.client-invoices.edit', $invoice));
    }

    public function test_dashboard_lists_workspace_client_invoices_only(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'D', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'D')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'X', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 1000,
        ]);
        app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);

        $this->actingAs($u)->get('/user/client-invoices')
            ->assertStatus(200)
            ->assertSee('Client Invoices');
    }

    public function test_edit_view_renders_with_vault_client_picker(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'E', 'scope' => 'team']);
        $board = \App\Modules\User\Models\TaskBoard::where('name', 'E')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Z', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 5000,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);

        $this->actingAs($u)->get(route('user.client-invoices.edit', $invoice))
            ->assertStatus(200)
            ->assertSee('Recipient')
            ->assertSee('Vault Client');
    }

    public function test_signed_pay_page_renders_and_post_passes_signature(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'P', 'scope' => 'team']);
        $board = \App\Modules\User\Models\TaskBoard::where('name', 'P')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'P', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 9900,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);

        $signed = \Illuminate\Support\Facades\URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        $path   = parse_url($signed, PHP_URL_PATH) . '?' . parse_url($signed, PHP_URL_QUERY);

        // GET with signature: page renders.
        $this->get($path)->assertStatus(200)->assertSee('Pay with Stripe');
        // GET without signature: blocked.
        $this->get(parse_url($signed, PHP_URL_PATH))->assertStatus(401);

        // POST to the same signed URL must NOT 401 — signature is preserved
        // by url()->full() in the form action. Stripe isn't configured in
        // tests so the controller's NotImplementedException catch sends us
        // back with a flash error (302), which is the success criterion.
        $resp = $this->withHeaders(['Accept' => 'text/html'])->post($path);
        $this->assertNotSame(401, $resp->status(), 'Signed POST should not be 401.');
    }

    public function test_send_reminder_emails_unpaid_sent_invoice(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'R', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'R')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'R', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 7700,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);
        $invoice->forceFill([
            'recipient_email' => 'client@ex.com',
            'sent_at'         => now(),
            'status'          => 'sent',
        ])->save();

        $this->actingAs($u)
            ->post(route('user.client-invoices.remind', $invoice))
            ->assertStatus(302);

        // Emailer always writes an email_logs row (Mail::fake can't observe
        // raw/html sends), so assert delivery via the log under the new key.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.payment_reminder',
            'recipient' => 'client@ex.com',
        ]);
    }

    public function test_send_reminder_blocked_before_invoice_is_sent(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'RB', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'RB')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'RB', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 5000,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);
        $invoice->forceFill(['recipient_email' => 'client@ex.com'])->save();

        // Draft (never sent) → reminder is refused and no email is logged.
        $this->actingAs($u)
            ->post(route('user.client-invoices.remind', $invoice))
            ->assertStatus(302);

        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'billing.payment_reminder',
            'recipient' => 'client@ex.com',
        ]);
    }

    /** Build a draft client invoice with a recipient set, ready to send. */
    private function sendableDraft(User $u, Workspace $ws, string $name): Invoice
    {
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => $name, 'scope' => 'team']);
        $board = TaskBoard::where('name', $name)->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => $name, 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 4200,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);
        $invoice->forceFill(['recipient_email' => 'client@ex.com'])->save();
        return $invoice->fresh();
    }

    // ------------------------------------------------------------------
    // Send: delivery-first + failure-fallback (must not silently succeed)
    // ------------------------------------------------------------------

    public function test_send_stamps_sent_at_and_emails_recipient(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->sendableDraft($u, $ws, 'SendOk');

        // MAIL_MAILER=array in phpunit.xml -> a genuine successful delivery.
        $this->actingAs($u)
            ->post(route('user.client-invoices.send', $invoice))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $fresh = $invoice->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at, 'a successful send must stamp sent_at');

        // The Emailer logs the delivery under the client-invoice key.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.client_invoice',
            'recipient' => 'client@ex.com',
            'status'    => 'sent',
        ]);
    }

    public function test_send_transport_failure_does_not_stamp_sent_at_and_returns_pay_link(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->sendableDraft($u, $ws, 'SendFail');

        // Drive a REAL mail transport failure: the resolved mailer throws when
        // the message is dispatched (as a down SMTP would). markSent opts into
        // the Emailer's throw_on_failure, so the send raises instead of silently
        // stamping the invoice "sent".
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('html')->andThrow(new \RuntimeException('smtp down'));
        $mailer->shouldReceive('raw')->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('mailer')->andReturn($mailer);

        $resp = $this->actingAs($u)
            ->post(route('user.client-invoices.send', $invoice));

        // The controller catches the failure and surfaces the signed pay link
        // for the owner to share manually, instead of 500ing.
        $resp->assertStatus(302)
            ->assertSessionHas('error')
            ->assertSessionHas('pay_url');

        // Crucially, the invoice was NOT marked sent.
        $fresh = $invoice->fresh();
        $this->assertNull($fresh->sent_at, 'a failed send must NOT stamp sent_at');
        $this->assertSame('draft', $fresh->status);

        // The failed delivery is still recorded for the admin log + resend.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'billing.client_invoice',
            'recipient' => 'client@ex.com',
            'status'    => 'failed',
        ]);
    }

    public function test_send_blocked_without_recipient_email(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'SendNoRcpt', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'SendNoRcpt')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'NR', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 3000,
        ]);
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);

        $this->actingAs($u)
            ->post(route('user.client-invoices.send', $invoice))
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->assertNull($invoice->fresh()->sent_at);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'billing.client_invoice',
        ]);
    }

    public function test_api_send_transport_failure_returns_pay_link_and_keeps_unsent(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        $invoice = $this->sendableDraft($u, $ws, 'ApiSendFail');

        $mailer = \Mockery::mock();
        $mailer->shouldReceive('html')->andThrow(new \RuntimeException('smtp down'));
        $mailer->shouldReceive('raw')->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('mailer')->andReturn($mailer);

        $token = $u->createToken('test')->plainTextToken;
        $resp  = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/send");

        // A transport failure returns an error envelope carrying the pay link,
        // not a success — and the invoice stays unsent.
        $resp->assertStatus(502)
            ->assertJsonPath('error.details.pay_url', fn ($v) => is_string($v) && $v !== '');

        $this->assertNull($invoice->fresh()->sent_at, 'API failed send must NOT stamp sent_at');
    }

    public function test_api_send_success_stamps_sent_at(): void
    {
        $u  = $this->user();
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        $invoice = $this->sendableDraft($u, $ws, 'ApiSendOk');

        $token = $u->createToken('test')->plainTextToken;
        $resp  = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/send");

        $resp->assertOk()
            ->assertJsonPath('data.invoice.status', 'sent')
            ->assertJsonPath('data.pay_url', fn ($v) => is_string($v) && $v !== '');

        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    // ------------------------------------------------------------------
    // Reminder guardrails
    // ------------------------------------------------------------------

    public function test_send_reminder_blocked_without_recipient_email(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'RemNoRcpt', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'RemNoRcpt')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'RNR', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 6000,
        ]);
        // Sent, but no recipient set -> reminder refused, nothing emailed.
        $invoice = app(ClientInvoiceService::class)->draftFromCards([$card->id], $ws, $u->id);
        $invoice->forceFill(['sent_at' => now(), 'status' => 'sent'])->save();

        $this->actingAs($u)
            ->post(route('user.client-invoices.remind', $invoice))
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'billing.payment_reminder',
        ]);
    }

    public function test_send_reminder_blocked_when_already_settled(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = $this->sendableDraft($u, $ws, 'RemPaid');
        // Already paid -> settled -> reminder refused.
        $invoice->forceFill(['sent_at' => now(), 'status' => 'paid', 'paid_at' => now()])->save();

        $this->actingAs($u)
            ->post(route('user.client-invoices.remind', $invoice))
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'billing.payment_reminder',
            'recipient' => 'client@ex.com',
        ]);
    }

    public function test_timer_start_and_stop_logs_minutes(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'T', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'T')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $card  = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Y', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'hourly', 'rate_amount_minor' => 12000,
        ]);

        $this->actingAs($u)->post("/user/tasks/cards/{$card->id}/timer/start")->assertOk();
        $running = TaskTimeEntry::where('card_id', $card->id)->whereNull('ended_at')->first();
        $this->assertNotNull($running);
        $this->actingAs($u)->post("/user/tasks/cards/{$card->id}/timer/stop")->assertOk();
        $this->assertNotNull($running->fresh()->ended_at);
        $this->assertGreaterThanOrEqual(1, (int) $running->fresh()->minutes);
    }
}
