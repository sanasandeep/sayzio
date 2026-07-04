<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\RecurringInvoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\RecurringInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end money-path coverage for the Invoicing & Accounting Suite:
 * create -> recalculate (inclusive / exclusive / multi-rate tax) ->
 * markPaidManual + gap-free receipt -> partial + full refund state machine
 * (paid -> partially_refunded -> refunded), the kanban draftFromCards()
 * totals, and recurring template generation (no double-billing).
 *
 * The REST parity tests drive the real stateless Sanctum HTTP path with a
 * real personal access token (NOT Sanctum::actingAs, which injects a mock
 * that breaks the TouchSessionToken middleware — see memory:
 * sanctum-api-tests) and assert the unified `{data}` / `{error}` envelope.
 */
class InvoiceMoneyFlowTest extends TestCase
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

    /** @return array<string,string> */
    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
    }

    // ------------------------------------------------------------------
    // InvoiceCalculator — tax math
    // ------------------------------------------------------------------

    public function test_calculator_exclusive_tax_adds_on_top(): void
    {
        $calc = app(InvoiceCalculator::class)->compute([
            ['label' => 'Design', 'amount_minor' => 10000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
        ]);

        $this->assertSame(10000, $calc['subtotal_minor']);
        $this->assertSame(0, $calc['discount_minor']);
        $this->assertSame(2000, $calc['tax_total_minor']);     // 20% of 10000
        $this->assertSame(12000, $calc['grand_total_minor']);
        $this->assertCount(1, $calc['tax_breakdown']);
        $this->assertSame('VAT', $calc['tax_breakdown'][0]['name']);
        $this->assertSame(2000, $calc['tax_breakdown'][0]['amount_minor']);
    }

    public function test_calculator_inclusive_tax_backs_out_of_gross(): void
    {
        $calc = app(InvoiceCalculator::class)->compute([
            ['label' => 'Consulting', 'amount_minor' => 12000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_inclusive' => true, 'tax_name' => 'GST'],
        ]);

        // 12000 gross at 20% inclusive => 10000 net + 2000 tax.
        $this->assertSame(10000, $calc['subtotal_minor']);
        $this->assertSame(2000, $calc['tax_total_minor']);
        $this->assertSame(12000, $calc['grand_total_minor']);
    }

    public function test_calculator_multiple_rates_group_into_breakdown(): void
    {
        // "Compound" books: distinct tax rules accumulate into a grouped
        // breakdown rather than collapsing into a single bucket.
        $calc = app(InvoiceCalculator::class)->compute([
            ['label' => 'A', 'amount_minor' => 10000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_name' => 'GST'],
            ['label' => 'B', 'amount_minor' => 5000,  'quantity' => 1, 'tax_rate_bps' => 1000, 'tax_name' => 'PST'],
        ]);

        $this->assertSame(15000, $calc['subtotal_minor']);
        $this->assertSame(2500, $calc['tax_total_minor']);     // 2000 + 500
        $this->assertSame(17500, $calc['grand_total_minor']);
        $this->assertCount(2, $calc['tax_breakdown']);

        $byName = collect($calc['tax_breakdown'])->keyBy('name');
        $this->assertSame(2000, $byName['GST']['amount_minor']);
        $this->assertSame(500, $byName['PST']['amount_minor']);
    }

    public function test_calculator_discount_applies_proportionally_before_tax(): void
    {
        $calc = app(InvoiceCalculator::class)->compute([
            ['label' => 'X', 'amount_minor' => 10000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
        ], 2000); // $20 discount

        // taxable base = 8000, tax = 1600, grand = 9600.
        $this->assertSame(10000, $calc['subtotal_minor']);
        $this->assertSame(2000, $calc['discount_minor']);
        $this->assertSame(1600, $calc['tax_total_minor']);
        $this->assertSame(9600, $calc['grand_total_minor']);
    }

    // ------------------------------------------------------------------
    // Standalone create -> recalculate
    // ------------------------------------------------------------------

    public function test_standalone_create_costs_via_calculator(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);

        $invoice = app(ClientInvoiceService::class)->createStandalone([
            'recipient_email' => 'client@ex.com',
            'line_items'      => [
                ['label' => 'Build', 'amount_minor' => 10000, 'quantity' => 2, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
            ],
        ], $ws, $u->id);

        $this->assertSame('client', $invoice->kind);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(20000, (int) $invoice->subtotal_minor);  // 10000 x 2
        $this->assertSame(4000, (int) $invoice->tax_total_minor);  // 20%
        $this->assertSame(24000, (int) $invoice->grand_total_minor);
    }

    public function test_recalculate_keeps_stored_tax_and_discount(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $invoice = app(ClientInvoiceService::class)->createStandalone([
            'line_items' => [['label' => 'Seed', 'amount_minor' => 5000, 'quantity' => 1]],
        ], $ws, $u->id);

        $invoice->forceFill(['discount_minor' => 1000, 'tax_total_minor' => 500])->save();

        app(ClientInvoiceService::class)->recalculate($invoice, [
            ['label' => 'New', 'amount_minor' => 8000, 'quantity' => 1, 'meta' => ['kind' => 'manual']],
        ]);

        $invoice->refresh();
        $this->assertSame(8000, (int) $invoice->subtotal_minor);
        $this->assertSame(1000, (int) $invoice->discount_minor);
        // grand = subtotal - discount + stored tax = 8000 - 1000 + 500.
        $this->assertSame(7500, (int) $invoice->grand_total_minor);
    }

    // ------------------------------------------------------------------
    // Kanban draftFromCards totals
    // ------------------------------------------------------------------

    public function test_draft_from_cards_totals_hourly_plus_flat(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'Books', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'Books')->first();
        $col   = $board->columns()->orderBy('position')->first();

        $hourly = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Dev', 'position' => 1, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'hourly', 'rate_amount_minor' => 10000,
        ]);
        TaskTimeEntry::create([
            'workspace_id' => $ws->id, 'card_id' => $hourly->id, 'user_id' => $u->id,
            'started_at' => now()->subMinutes(120), 'ended_at' => now(),
            'minutes' => 120, 'source' => 'manual',
        ]);
        $flat = TaskCard::create([
            'workspace_id' => $ws->id, 'board_id' => $board->id, 'column_id' => $col->id,
            'title' => 'Brand', 'position' => 2, 'priority' => 'normal',
            'billable' => true, 'rate_type' => 'flat', 'rate_amount_minor' => 30000,
        ]);

        $invoice = app(ClientInvoiceService::class)->draftFromCards([$hourly->id, $flat->id], $ws, $u->id);

        // hourly: 2h x $100 = 20000 ; flat: 30000.
        $this->assertSame(50000, (int) $invoice->subtotal_minor);
        $this->assertSame(50000, (int) $invoice->grand_total_minor);
        $this->assertCount(2, $invoice->line_items);
    }

    // ------------------------------------------------------------------
    // markPaidManual + gap-free receipt numbering
    // ------------------------------------------------------------------

    public function test_mark_paid_manual_issues_gapless_receipts(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        $a = $svc->createStandalone(['line_items' => [['label' => 'A', 'amount_minor' => 10000, 'quantity' => 1]]], $ws, $u->id);
        $b = $svc->createStandalone(['line_items' => [['label' => 'B', 'amount_minor' => 20000, 'quantity' => 1]]], $ws, $u->id);

        $svc->markPaidManual($a, 'bank_transfer', 'TXN-1');
        $svc->markPaidManual($b, 'cash');

        $a->refresh();
        $b->refresh();
        $this->assertSame('paid', $a->status);
        $this->assertNotNull($a->paid_at);
        $this->assertSame(10000, (int) $a->amount_paid_minor);
        $this->assertSame('manual', $a->paid_method);

        $ra = Receipt::where('invoice_id', $a->id)->first();
        $rb = Receipt::where('invoice_id', $b->id)->first();
        $this->assertNotNull($ra);
        $this->assertNotNull($rb);
        $fy = InvoiceService::financialYearFor(now());
        $this->assertStringStartsWith('RCP/' . $fy . '/', $ra->number);
        // Sequential + gap-free.
        $this->assertSame(((int) $ra->seq) + 1, (int) $rb->seq);
        $this->assertSame(10000, (int) $ra->amount_minor);
    }

    public function test_mark_paid_is_idempotent_and_does_not_reissue_receipt(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $svc = app(ClientInvoiceService::class);
        $inv = $svc->createStandalone(['line_items' => [['label' => 'A', 'amount_minor' => 10000, 'quantity' => 1]]], $ws, $u->id);

        $svc->markPaidManual($inv, 'cash');
        $svc->markPaidManual($inv->fresh(), 'cash'); // re-entrant

        $this->assertSame(1, Receipt::where('invoice_id', $inv->id)->count());
    }

    // ------------------------------------------------------------------
    // Refund state machine: paid -> partially_refunded -> refunded
    // ------------------------------------------------------------------

    public function test_partial_then_full_refund_transitions_state(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $svc = app(ClientInvoiceService::class);
        $inv = $svc->createStandalone(['line_items' => [['label' => 'Work', 'amount_minor' => 12000, 'quantity' => 1]]], $ws, $u->id);
        $svc->markPaidManual($inv, 'cash');

        // Partial refund.
        $r1 = $svc->refund($inv->fresh(), 5000, 'partial');
        $inv->refresh();
        $this->assertSame('partially_refunded', $inv->status);
        $this->assertSame(5000, $inv->refundedTotalMinor());
        $this->assertSame(7000, (int) $inv->amount_paid_minor);
        $this->assertSame('succeeded', $r1->status);
        $this->assertSame(1, CreditNote::where('refund_id', $r1->id)->count());

        // Remaining balance refund completes it.
        $r2 = $svc->refund($inv->fresh(), 7000, 'remainder');
        $inv->refresh();
        $this->assertSame('refunded', $inv->status);
        $this->assertSame(12000, $inv->refundedTotalMinor());
        $this->assertSame(0, (int) $inv->amount_paid_minor);
        $this->assertSame(2, Refund::where('invoice_id', $inv->id)->count());
    }

    public function test_refund_cannot_exceed_refundable_balance(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $svc = app(ClientInvoiceService::class);
        $inv = $svc->createStandalone(['line_items' => [['label' => 'Work', 'amount_minor' => 12000, 'quantity' => 1]]], $ws, $u->id);
        $svc->markPaidManual($inv, 'cash');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->refund($inv->fresh(), 99999);
    }

    public function test_unpaid_invoice_cannot_be_refunded(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $svc = app(ClientInvoiceService::class);
        $inv = $svc->createStandalone(['line_items' => [['label' => 'Work', 'amount_minor' => 1000, 'quantity' => 1]]], $ws, $u->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->refund($inv, 500);
    }

    // ------------------------------------------------------------------
    // Recurring generation: no double-billing
    // ------------------------------------------------------------------

    private function recurringTemplate(User $u, Workspace $ws, int $maxOccurrences = 2): RecurringInvoice
    {
        return RecurringInvoice::create([
            'user_id'           => $u->id,
            'workspace_id'      => $ws->id,
            'title'             => 'Retainer',
            'recipient_email'   => 'client@ex.com',
            'currency'          => 'USD',
            'line_items'        => [['label' => 'Monthly retainer', 'amount_minor' => 25000, 'quantity' => 1]],
            'discount_minor'    => 0,
            'interval'          => 'monthly',
            'interval_count'    => 1,
            'start_date'        => now()->toDateString(),
            'next_run_date'     => now()->toDateString(),
            'max_occurrences'   => $maxOccurrences,
            'occurrences_count' => 0,
            'status'            => 'active',
            'auto_send'         => false,
        ]);
    }

    public function test_run_once_creates_invoice_and_advances_schedule(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $tpl = $this->recurringTemplate($u, $ws);

        $invoice = app(RecurringInvoiceService::class)->runOnce($tpl, now());
        $tpl->refresh();

        $this->assertNotNull($invoice);
        $this->assertSame('client', $invoice->kind);
        $this->assertSame(25000, (int) $invoice->grand_total_minor);
        $this->assertSame((int) $tpl->id, (int) $invoice->recurring_invoice_id);
        $this->assertSame(1, (int) $tpl->occurrences_count);
        $this->assertSame('active', $tpl->status);
        // next_run_date advanced one month forward.
        $this->assertTrue(
            Carbon::parse($tpl->next_run_date)->gt(now()->startOfDay()),
            'next_run_date should advance into the future'
        );
    }

    public function test_generate_due_exhausts_template_without_double_billing(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $tpl = $this->recurringTemplate($u, $ws, 2);
        $svc = app(RecurringInvoiceService::class);

        // First run: due today.
        $this->assertSame(1, $svc->generateDue(now())['generated']);

        // Second run: advance to whenever the template now says it's next due
        // (read the real advanced date so this is robust on month-end dates),
        // which exhausts the template (max 2).
        $nextDue = Carbon::parse($tpl->fresh()->next_run_date)->copy()->addDay();
        $this->assertSame(1, $svc->generateDue($nextDue)['generated']);

        $tpl->refresh();
        $this->assertSame(2, (int) $tpl->occurrences_count);
        $this->assertSame('completed', $tpl->status);

        // Third pass must NOT bill again — template is completed.
        $this->assertSame(0, $svc->generateDue($nextDue->copy()->addMonths(3))['generated']);
        $this->assertSame(2, Invoice::where('recurring_invoice_id', $tpl->id)->count());
    }

    // ------------------------------------------------------------------
    // Resilience: a failed auto-send email must not unwind billing
    // ------------------------------------------------------------------

    public function test_run_once_still_bills_and_advances_when_auto_send_throws(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $tpl = $this->recurringTemplate($u, $ws);
        $tpl->forceFill(['auto_send' => true])->save();

        // Simulate a transport failure on the auto-send email. createStandalone
        // and every other ClientInvoiceService method keep their real behaviour
        // (partial mock) — only markSent blows up, mid-transaction.
        $this->partialMock(ClientInvoiceService::class, function ($mock) {
            $mock->shouldReceive('markSent')
                ->once()
                ->andThrow(new \RuntimeException('SMTP transport failed'));
        });

        $invoice = app(RecurringInvoiceService::class)->runOnce($tpl, now());

        // The generated invoice survives the email failure...
        $this->assertNotNull($invoice);
        $this->assertSame('client', $invoice->kind);
        $this->assertNull($invoice->sent_at, 'send never completed, so sent_at stays null');
        $this->assertSame(1, Invoice::where('recurring_invoice_id', $tpl->id)->count());

        // ...and the schedule advanced exactly once (no skipped / double billing).
        $tpl->refresh();
        $this->assertSame(1, (int) $tpl->occurrences_count);
        $this->assertSame('active', $tpl->status);
        $this->assertNotNull($tpl->last_run_at);
        $this->assertTrue(
            Carbon::parse($tpl->next_run_date)->gt(now()->startOfDay()),
            'next_run_date should still advance even though the email failed'
        );
    }

    public function test_generate_due_one_failing_template_does_not_halt_the_batch(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);

        // Three due templates; the middle one is wired to blow up on generation.
        $good1 = $this->recurringTemplate($u, $ws);
        $good1->forceFill(['recipient_email' => 'good1@ex.com'])->save();
        $bad   = $this->recurringTemplate($u, $ws);
        $bad->forceFill(['recipient_email' => 'bad@ex.com'])->save();
        $good2 = $this->recurringTemplate($u, $ws);
        $good2->forceFill(['recipient_email' => 'good2@ex.com'])->save();

        $this->partialMock(ClientInvoiceService::class, function ($mock) {
            $mock->shouldReceive('createStandalone')
                ->withArgs(fn ($data) => ($data['recipient_email'] ?? '') === 'bad@ex.com')
                ->andThrow(new \RuntimeException('billing backend exploded'));
            // Every other template generates for real.
            $mock->shouldReceive('createStandalone')
                ->withArgs(fn ($data) => ($data['recipient_email'] ?? '') !== 'bad@ex.com')
                ->passthru();
        });

        $tally = app(RecurringInvoiceService::class)->generateDue(now());

        // The two healthy templates still billed despite the bad one throwing.
        $this->assertSame(2, $tally['generated']);
        $this->assertSame(1, Invoice::where('recurring_invoice_id', $good1->id)->count());
        $this->assertSame(1, Invoice::where('recurring_invoice_id', $good2->id)->count());

        // The bad template's run rolled back wholesale — no invoice, no advance.
        $this->assertSame(0, Invoice::where('recurring_invoice_id', $bad->id)->count());
        $bad->refresh();
        $this->assertSame(0, (int) $bad->occurrences_count);
        $this->assertNull($bad->last_run_at);

        // The healthy templates advanced their schedules.
        $this->assertSame(1, (int) $good1->fresh()->occurrences_count);
        $this->assertSame(1, (int) $good2->fresh()->occurrences_count);
    }

    // ------------------------------------------------------------------
    // REST API parity (real Bearer token + unified envelope)
    // ------------------------------------------------------------------

    public function test_api_standalone_create_returns_costed_envelope(): void
    {
        $u = $this->user();
        $token = $this->token($u);

        $resp = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/billing/invoices', [
                'recipient_email' => 'client@ex.com',
                'line_items'      => [
                    ['label' => 'Build', 'amount_minor' => 10000, 'quantity' => 1, 'tax_rate_bps' => 2000, 'tax_name' => 'VAT'],
                ],
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.subtotal_minor', 10000)
            ->assertJsonPath('data.invoice.tax_total_minor', 2000)
            ->assertJsonPath('data.invoice.grand_total_minor', 12000)
            ->assertJsonPath('data.invoice.kind', 'client');
    }

    public function test_api_full_lifecycle_create_pay_refund(): void
    {
        $u = $this->user();
        $token = $this->token($u);
        $headers = $this->authHeaders($token);

        // Create.
        $create = $this->withHeaders($headers)->postJson('/api/v1/billing/invoices', [
            'recipient_email' => 'client@ex.com',
            'line_items'      => [['label' => 'Work', 'amount_minor' => 12000, 'quantity' => 1]],
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.invoice.id');

        // Mark paid.
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$id}/mark-paid", [
            'method' => 'bank_transfer', 'reference' => 'TXN-9',
        ])->assertOk()->assertJsonPath('data.invoice.status', 'paid');

        // Receipt is available.
        $this->withHeaders($headers)->getJson("/api/v1/billing/invoices/{$id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt.method', 'manual');

        // Paying again is rejected with the unified error envelope.
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$id}/mark-paid")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Invoice already paid.');

        // Partial refund -> partially_refunded.
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$id}/refund", [
            'amount_minor' => 5000, 'reason' => 'partial',
        ])->assertOk()->assertJsonPath('data.invoice.status', 'partially_refunded');

        // Full remainder refund -> refunded.
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$id}/refund", [
            'amount_minor' => 7000,
        ])->assertOk()->assertJsonPath('data.invoice.status', 'refunded');

        $inv = Invoice::find($id);
        $this->assertSame(12000, $inv->refundedTotalMinor());
        $this->assertSame(2, Refund::where('invoice_id', $id)->count());
    }

    public function test_api_paid_invoice_cannot_be_edited(): void
    {
        $u = $this->user();
        $token = $this->token($u);
        $headers = $this->authHeaders($token);

        $create = $this->withHeaders($headers)->postJson('/api/v1/billing/invoices', [
            'line_items' => [['label' => 'Work', 'amount_minor' => 5000, 'quantity' => 1]],
        ]);
        $id = $create->json('data.invoice.id');
        $this->withHeaders($headers)->postJson("/api/v1/billing/invoices/{$id}/mark-paid")->assertOk();

        $this->withHeaders($headers)->patchJson("/api/v1/billing/invoices/{$id}", [
            'line_items' => [['label' => 'Hacked', 'amount_minor' => 1, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error.message', 'Paid invoices cannot be edited.');
    }

    public function test_api_run_recurring_generates_invoice(): void
    {
        $u  = $this->user();
        $ws = $this->bind($u);
        $tpl = $this->recurringTemplate($u, $ws);
        $token = $this->token($u);

        $resp = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/v1/billing/recurring/{$tpl->id}/run");

        $resp->assertStatus(201);
        $invoiceId = $resp->json('data.invoice_id');
        $this->assertNotNull($invoiceId);
        $this->assertSame(25000, (int) Invoice::find($invoiceId)->grand_total_minor);
        $this->assertSame(1, (int) $tpl->fresh()->occurrences_count);
    }

    // ------------------------------------------------------------------
    // Concurrent reservations: gap-free invoice & receipt numbering
    //
    // Invoice + receipt numbers come from a shared per-(financial_year,
    // prefix) counter in `invoice_counters`, bumped under lockForUpdate
    // inside a transaction (reserveDraftInvoice / generateReceipt). That
    // is what stops two simultaneous payments from minting duplicate or
    // skipped numbers — a legal/accounting hazard for client money.
    //
    // PHPUnit can't truly fork DB workers, so (mirroring the burst pattern
    // in BiolinkBlockLimitsTest) we fire a rapid back-to-back burst of
    // reservations and assert the persisted sequence is unique, strictly
    // sequential, and gap-free per financial year — plus that it resets at
    // the FY rollover boundary.
    // ------------------------------------------------------------------

    /** Assert a list of seq numbers is unique and forms exactly 1..N with no gaps. */
    private function assertGapFreeSequence(array $seqs, int $expectedCount, string $label = 'sequence'): void
    {
        $this->assertCount($expectedCount, $seqs, "Wrong number of {$label} reservations");
        $this->assertSame(
            $expectedCount,
            count(array_unique($seqs)),
            "{$label} numbers must be unique (no duplicates)"
        );
        sort($seqs);
        $this->assertSame(
            range(1, $expectedCount),
            $seqs,
            "{$label} must be strictly 1..N with no gaps"
        );
    }

    public function test_concurrent_invoice_reservations_are_unique_sequential_and_gapfree(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        Carbon::setTestNow(Carbon::create(2025, 6, 15, 12));
        $fy = InvoiceService::financialYearFor(now());

        $count    = 25;
        $invoices = [];
        for ($i = 0; $i < $count; $i++) {
            $invoices[] = $svc->createStandalone([
                'line_items' => [['label' => 'Work ' . $i, 'amount_minor' => 1000, 'quantity' => 1]],
            ], $ws, $u->id);
        }

        $seqs    = array_map(fn ($inv) => (int) $inv->seq, $invoices);
        $numbers = array_map(fn ($inv) => $inv->number, $invoices);

        $this->assertGapFreeSequence($seqs, $count, 'invoice');
        $this->assertSame($count, count(array_unique($numbers)), 'Invoice numbers must all be unique');
        foreach ($invoices as $inv) {
            $this->assertSame($fy, $inv->financial_year);
            $this->assertSame(
                sprintf('INV/%s/%s', $fy, str_pad((string) $inv->seq, 5, '0', STR_PAD_LEFT)),
                $inv->number
            );
        }
        // The counter row landed exactly on N — proof nothing was skipped.
        $this->assertSame($count, (int) DB::table('invoice_counters')
            ->where('financial_year', $fy)->where('prefix', 'INV')->value('last_seq'));

        Carbon::setTestNow();
    }

    public function test_concurrent_receipt_reservations_are_unique_sequential_and_gapfree(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        Carbon::setTestNow(Carbon::create(2025, 6, 15, 12));
        $fy = InvoiceService::financialYearFor(now());

        $count          = 20;
        $receiptSeqs    = [];
        $receiptNumbers = [];
        for ($i = 0; $i < $count; $i++) {
            $inv = $svc->createStandalone([
                'line_items' => [['label' => 'Job ' . $i, 'amount_minor' => 5000, 'quantity' => 1]],
            ], $ws, $u->id);
            $svc->markPaidManual($inv, 'cash');
            $receipt = Receipt::where('invoice_id', $inv->id)->firstOrFail();
            $receiptSeqs[]    = (int) $receipt->seq;
            $receiptNumbers[] = $receipt->number;
        }

        $this->assertGapFreeSequence($receiptSeqs, $count, 'receipt');
        $this->assertSame($count, count(array_unique($receiptNumbers)), 'Receipt numbers must all be unique');
        foreach ($receiptNumbers as $idx => $number) {
            $this->assertSame(
                sprintf('RCP/%s/%s', $fy, str_pad((string) $receiptSeqs[$idx], 5, '0', STR_PAD_LEFT)),
                $number
            );
        }
        $this->assertSame($count, (int) DB::table('invoice_counters')
            ->where('financial_year', $fy)->where('prefix', 'RCP')->value('last_seq'));

        Carbon::setTestNow();
    }

    public function test_interleaved_invoice_and_receipt_counters_stay_independent_and_gapfree(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9));

        $count       = 15;
        $invoiceSeqs = [];
        $receiptSeqs = [];
        $pending     = [];

        // Mixed traffic: every loop reserves a new invoice number AND, once
        // there's a backlog, pays an earlier invoice (reserving a receipt
        // number). The two prefixes share the counters table but must keep
        // strictly independent, gap-free runs.
        for ($i = 0; $i < $count; $i++) {
            $inv = $svc->createStandalone([
                'line_items' => [['label' => 'Mix ' . $i, 'amount_minor' => 2500, 'quantity' => 1]],
            ], $ws, $u->id);
            $invoiceSeqs[] = (int) $inv->seq;
            $pending[]     = $inv;

            if ($i % 2 === 1) {
                $toPay = array_shift($pending);
                $svc->markPaidManual($toPay, 'cash');
                $receiptSeqs[] = (int) Receipt::where('invoice_id', $toPay->id)->value('seq');
            }
        }
        // Drain the remaining unpaid invoices into receipts.
        foreach ($pending as $toPay) {
            $svc->markPaidManual($toPay, 'cash');
            $receiptSeqs[] = (int) Receipt::where('invoice_id', $toPay->id)->value('seq');
        }

        $this->assertGapFreeSequence($invoiceSeqs, $count, 'invoice');
        $this->assertGapFreeSequence($receiptSeqs, $count, 'receipt');

        Carbon::setTestNow();
    }

    public function test_numbering_resets_and_stays_gapfree_across_financial_year_rollover(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        // Last moments of FY 2025-26 (FY starts in April): 31 Mar 2026.
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 23, 30));
        $fyOld   = InvoiceService::financialYearFor(now()); // 2025-26
        $oldSeqs = [];
        for ($i = 0; $i < 4; $i++) {
            $inv = $svc->createStandalone([
                'line_items' => [['label' => 'Old ' . $i, 'amount_minor' => 1000, 'quantity' => 1]],
            ], $ws, $u->id);
            $this->assertSame($fyOld, $inv->financial_year);
            $oldSeqs[] = (int) $inv->seq;
        }

        // Cross the boundary into FY 2026-27: 1 Apr 2026.
        Carbon::setTestNow(Carbon::create(2026, 4, 1, 0, 5));
        $fyNew = InvoiceService::financialYearFor(now()); // 2026-27
        $this->assertNotSame($fyOld, $fyNew, 'Crossing 31 Mar -> 1 Apr must roll the financial year');

        $newSeqs     = [];
        $newInvoices = [];
        for ($i = 0; $i < 3; $i++) {
            $inv = $svc->createStandalone([
                'line_items' => [['label' => 'New ' . $i, 'amount_minor' => 1000, 'quantity' => 1]],
            ], $ws, $u->id);
            $this->assertSame($fyNew, $inv->financial_year);
            $newSeqs[]     = (int) $inv->seq;
            $newInvoices[] = $inv;
        }

        // Each FY is independently gap-free starting at 1; the new FY does
        // NOT continue the old counter.
        $this->assertGapFreeSequence($oldSeqs, 4, 'old-FY invoice');
        $this->assertGapFreeSequence($newSeqs, 3, 'new-FY invoice');
        $this->assertSame(1, min($newSeqs), 'New FY numbering must reset to 1');

        // Numbers carry their own FY label, so they can never collide across
        // years even though the seqs repeat (1..n within each FY).
        $this->assertStringStartsWith('INV/' . $fyNew . '/', $newInvoices[0]->number);
        $this->assertStringStartsWith('INV/' . $fyOld . '/00001', sprintf('INV/%s/%s', $fyOld, str_pad((string) min($oldSeqs), 5, '0', STR_PAD_LEFT)));

        // Two distinct counter rows exist — one per FY — each at its own max.
        $this->assertSame(4, (int) DB::table('invoice_counters')
            ->where('financial_year', $fyOld)->where('prefix', 'INV')->value('last_seq'));
        $this->assertSame(3, (int) DB::table('invoice_counters')
            ->where('financial_year', $fyNew)->where('prefix', 'INV')->value('last_seq'));

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // Credit-note numbering when refunds pile up
    //
    // The refund path mints a third numbered document — the credit note.
    // Its numbers MUST share the same row-locked, gap-free per-(financial
    // year, prefix="CN") counter in `invoice_counters` as invoices and
    // receipts (the documented invariant: credit_notes.number is UNIQUE and
    // the migration says "Numbering shares invoice_counters with prefix
    // 'CN'"). ClientInvoiceService::refund() used to key the number off the
    // global auto-increment refund id ('CN/<fy>/<refund_id>'), which left
    // gaps, emitted unpadded numbers diverging from invoices/receipts, and
    // could collide against the unique number column. These tests assert
    // every credit note minted through the client-invoice refund path is
    // unique, well-formed (CN/<fy>/<5-digit seq>), and gap-free per FY —
    // including across the financial-year rollover.
    // ------------------------------------------------------------------

    /** Create + pay a standalone client invoice, returning the paid model. */
    private function paidInvoice(Workspace $ws, User $u, int $amountMinor): Invoice
    {
        $svc = app(ClientInvoiceService::class);
        $inv = $svc->createStandalone([
            'line_items' => [['label' => 'Job', 'amount_minor' => $amountMinor, 'quantity' => 1]],
        ], $ws, $u->id);
        $svc->markPaidManual($inv, 'cash');
        return $inv->fresh();
    }

    public function test_piledup_refund_credit_notes_are_unique_wellformed_and_gapfree(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        Carbon::setTestNow(Carbon::create(2025, 6, 15, 12));
        $fy = InvoiceService::financialYearFor(now());

        // 12 paid invoices. Half are refunded in two slices (partial +
        // remainder => 2 credit notes each), half in a single full refund
        // (1 credit note each) — a realistic pile-up of refunds across many
        // invoices, interleaved so refund ids do NOT line up with CN seqs.
        $creditNoteSeqs    = [];
        $creditNoteNumbers = [];
        for ($i = 0; $i < 12; $i++) {
            $inv = $this->paidInvoice($ws, $u, 10000);

            if ($i % 2 === 0) {
                // Partial then remainder: two refunds, two credit notes.
                $r1 = $svc->refund($inv->fresh(), 4000, 'partial');
                $r2 = $svc->refund($inv->fresh(), 6000, 'remainder');
                foreach ([$r1, $r2] as $r) {
                    $cn = CreditNote::where('refund_id', $r->id)->firstOrFail();
                    $creditNoteSeqs[]    = (int) $cn->seq;
                    $creditNoteNumbers[] = $cn->number;
                }
            } else {
                // Single full refund: one credit note.
                $r = $svc->refund($inv->fresh(), 0, 'full'); // 0 => whole remaining balance
                $cn = CreditNote::where('refund_id', $r->id)->firstOrFail();
                $creditNoteSeqs[]    = (int) $cn->seq;
                $creditNoteNumbers[] = $cn->number;
            }
        }

        // 6 invoices * 2 CNs + 6 invoices * 1 CN = 18 credit notes.
        $expected = 18;
        $this->assertCount($expected, $creditNoteNumbers);

        // Every number is globally unique (matches the DB UNIQUE constraint).
        $this->assertSame(
            $expected,
            count(array_unique($creditNoteNumbers)),
            'Credit-note numbers must never collide'
        );
        $this->assertSame(
            $expected,
            CreditNote::query()->distinct()->count('number'),
            'Persisted credit-note numbers must be distinct in the DB'
        );

        // Sequence is strictly 1..N, gap-free, within the FY.
        $this->assertGapFreeSequence($creditNoteSeqs, $expected, 'credit-note');

        // Every number is well-formed: CN/<fy>/<5-digit zero-padded seq>.
        foreach ($creditNoteNumbers as $idx => $number) {
            $this->assertMatchesRegularExpression('#^CN/' . preg_quote($fy, '#') . '/\d{5}$#', $number);
            $this->assertSame(
                sprintf('CN/%s/%s', $fy, str_pad((string) $creditNoteSeqs[$idx], 5, '0', STR_PAD_LEFT)),
                $number
            );
        }

        // The shared counter landed exactly on N — proof nothing was skipped
        // or double-allocated.
        $this->assertSame($expected, (int) DB::table('invoice_counters')
            ->where('financial_year', $fy)->where('prefix', 'CN')->value('last_seq'));

        Carbon::setTestNow();
    }

    public function test_credit_note_numbering_resets_and_stays_gapfree_across_fy_rollover(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        // Last moments of FY 2025-26 (FY starts in April): 31 Mar 2026.
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 23, 30));
        $fyOld   = InvoiceService::financialYearFor(now()); // 2025-26
        $oldSeqs = [];
        $oldNums = [];
        for ($i = 0; $i < 4; $i++) {
            $inv = $this->paidInvoice($ws, $u, 5000);
            $r   = $svc->refund($inv->fresh(), 0, 'old-fy');
            $cn  = CreditNote::where('refund_id', $r->id)->firstOrFail();
            $this->assertSame($fyOld, $cn->financial_year);
            $oldSeqs[] = (int) $cn->seq;
            $oldNums[] = $cn->number;
        }

        // Cross the boundary into FY 2026-27: 1 Apr 2026.
        Carbon::setTestNow(Carbon::create(2026, 4, 1, 0, 5));
        $fyNew = InvoiceService::financialYearFor(now()); // 2026-27
        $this->assertNotSame($fyOld, $fyNew, 'Crossing 31 Mar -> 1 Apr must roll the financial year');

        $newSeqs = [];
        $newNums = [];
        for ($i = 0; $i < 3; $i++) {
            $inv = $this->paidInvoice($ws, $u, 5000);
            $r   = $svc->refund($inv->fresh(), 0, 'new-fy');
            $cn  = CreditNote::where('refund_id', $r->id)->firstOrFail();
            $this->assertSame($fyNew, $cn->financial_year);
            $newSeqs[] = (int) $cn->seq;
            $newNums[] = $cn->number;
        }

        // Each FY is independently gap-free starting at 1; the new FY does
        // NOT continue the old counter.
        $this->assertGapFreeSequence($oldSeqs, 4, 'old-FY credit-note');
        $this->assertGapFreeSequence($newSeqs, 3, 'new-FY credit-note');
        $this->assertSame(1, min($newSeqs), 'New FY credit-note numbering must reset to 1');

        // The FY label embedded in each number is correct on each side of the
        // boundary, so numbers never collide across years even though the
        // seqs repeat (1..n within each FY).
        foreach ($oldNums as $number) {
            $this->assertStringStartsWith('CN/' . $fyOld . '/', $number);
        }
        foreach ($newNums as $number) {
            $this->assertStringStartsWith('CN/' . $fyNew . '/', $number);
        }
        $this->assertSame(7, count(array_unique(array_merge($oldNums, $newNums))));

        // Two distinct counter rows exist — one per FY — each at its own max.
        $this->assertSame(4, (int) DB::table('invoice_counters')
            ->where('financial_year', $fyOld)->where('prefix', 'CN')->value('last_seq'));
        $this->assertSame(3, (int) DB::table('invoice_counters')
            ->where('financial_year', $fyNew)->where('prefix', 'CN')->value('last_seq'));

        Carbon::setTestNow();
    }

    /**
     * The end-to-end refund money-path invariant, in one place:
     *   - a partial refund moves paid -> partially_refunded with the remaining
     *     balance correct;
     *   - the remainder refund moves it -> refunded with the balance zeroed;
     *   - each refund mints exactly one credit note whose number is drawn from
     *     the per-FY `invoice_counters` sequence (NOT the global refund id) and
     *     never collides.
     *
     * The "not id-keyed" proof is the crux: the refund auto-increment id and
     * the per-FY credit-note seq are deliberately forced apart by burning a
     * few refund ids in an EARLIER financial year first, then refunding in a
     * NEW FY where the credit-note counter resets to 1 while the refund ids
     * keep climbing. The old bug keyed the number off the refund id
     * ('CN/<fy>/<refund_id>'), which would embed 4/5 here instead of 1/2 —
     * these assertions fail loudly if that scheme ever returns.
     */
    public function test_refund_credit_notes_are_counter_keyed_not_refund_id_keyed(): void
    {
        $u   = $this->user();
        $ws  = $this->bind($u);
        $svc = app(ClientInvoiceService::class);

        // Burn refund ids 1..3 in an earlier FY so the global refund id and the
        // per-FY credit-note seq diverge from here on.
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 12));
        for ($i = 0; $i < 3; $i++) {
            $warm = $this->paidInvoice($ws, $u, 5000);
            $svc->refund($warm->fresh(), 0, 'warmup'); // refund ids 1, 2, 3
        }

        // New FY: the CN counter resets to 1 even though refund ids continue.
        Carbon::setTestNow(Carbon::create(2026, 4, 1, 0, 5));
        $fy = InvoiceService::financialYearFor(now());

        $inv = $this->paidInvoice($ws, $u, 12000);

        // --- Partial refund -> partially_refunded, remaining balance correct.
        $r1 = $svc->refund($inv->fresh(), 5000, 'partial'); // refund id 4
        $inv->refresh();
        $this->assertSame('partially_refunded', $inv->status);
        $this->assertSame(5000, $inv->refundedTotalMinor());
        $this->assertSame(7000, (int) $inv->amount_paid_minor);
        $this->assertSame(1, CreditNote::where('refund_id', $r1->id)->count());
        $cn1 = CreditNote::where('refund_id', $r1->id)->firstOrFail();

        // --- Full remainder refund -> refunded, balance zeroed.
        $r2 = $svc->refund($inv->fresh(), 7000, 'remainder'); // refund id 5
        $inv->refresh();
        $this->assertSame('refunded', $inv->status);
        $this->assertSame(12000, $inv->refundedTotalMinor());
        $this->assertSame(0, (int) $inv->amount_paid_minor);
        $this->assertSame(1, CreditNote::where('refund_id', $r2->id)->count());
        $cn2 = CreditNote::where('refund_id', $r2->id)->firstOrFail();

        // Refund ids climbed to 4 and 5, but the credit-note seqs reset to 1
        // and 2 in the new FY — the number is keyed off the per-FY counter.
        $this->assertGreaterThanOrEqual(4, (int) $r1->id);
        $this->assertGreaterThan((int) $r1->id, (int) $r2->id);
        $this->assertSame(1, (int) $cn1->seq);
        $this->assertSame(2, (int) $cn2->seq);
        $this->assertSame(sprintf('CN/%s/00001', $fy), $cn1->number);
        $this->assertSame(sprintf('CN/%s/00002', $fy), $cn2->number);

        // Explicitly reject the retired id-keyed scheme 'CN/<fy>/<refund_id>'.
        $this->assertNotSame(sprintf('CN/%s/%d', $fy, $r1->id), $cn1->number);
        $this->assertNotSame(sprintf('CN/%s/%d', $fy, $r2->id), $cn2->number);

        // The two credit notes never collide, and every persisted number is
        // distinct (the DB `credit_notes.number` UNIQUE constraint).
        $this->assertNotSame($cn1->number, $cn2->number);
        $this->assertSame(
            CreditNote::count(),
            CreditNote::query()->distinct()->count('number'),
            'Every persisted credit-note number must be unique'
        );

        // Each credit note snapshots the exact refunded amount (the money that
        // actually moved), so the reversing ledger entry stays correct.
        $this->assertSame(5000, (int) $cn1->amount_minor);
        $this->assertSame(7000, (int) $cn2->amount_minor);

        Carbon::setTestNow();
    }
}
