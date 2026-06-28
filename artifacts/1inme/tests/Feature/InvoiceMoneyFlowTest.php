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
}
