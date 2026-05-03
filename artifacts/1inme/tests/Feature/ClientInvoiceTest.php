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
