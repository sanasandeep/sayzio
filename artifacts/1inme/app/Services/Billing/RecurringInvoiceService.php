<?php

namespace App\Services\Billing;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\RecurringInvoice;
use App\Modules\User\Models\TaxRule;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates concrete client invoices from recurring templates on schedule
 * and advances each template's run state. Used by the
 * `invoices:run-recurring` scheduled command and the manual "run now"
 * action on web/API/mobile.
 */
class RecurringInvoiceService
{
    public function __construct(
        protected ClientInvoiceService $invoices,
        protected NotificationService $notifications,
    ) {}

    /**
     * Generate invoices for every active template whose next_run_date is due.
     * @return int number of invoices generated.
     */
    public function generateDue(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?: now();
        $count = 0;

        RecurringInvoice::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $asOf->toDateString())
            ->orderBy('id')
            ->chunkById(50, function ($templates) use (&$count, $asOf) {
                foreach ($templates as $template) {
                    try {
                        if ($this->runOnce($template, $asOf)) {
                            $count++;
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }

    /** Generate a single invoice from a template and advance its state. */
    public function runOnce(RecurringInvoice $template, ?Carbon $asOf = null): ?Invoice
    {
        $asOf = $asOf ?: now();

        $ws = $template->workspace_id ? Workspace::find($template->workspace_id) : null;
        if (!$ws) {
            // Fall back to the owner's personal workspace if any.
            $ws = Workspace::where('user_id', $template->user_id)->orderBy('id')->first();
        }
        if (!$ws) return null;

        return DB::transaction(function () use ($template, $ws, $asOf) {
            $invoice = $this->invoices->createStandalone([
                'billing_company_id' => $template->billing_company_id,
                'vault_client_id'    => $template->vault_client_id,
                'recipient_email'    => $template->recipient_email,
                'currency'           => $template->currency,
                'line_items'         => is_array($template->line_items) ? $template->line_items : [],
                'discount_minor'     => (int) $template->discount_minor,
                'notes_md'           => $template->notes_md,
            ], $ws, $template->user_id);

            $invoice->forceFill(['recurring_invoice_id' => $template->id])->save();

            // Advance template scheduling state.
            $next = $template->advanceFrom(
                $template->next_run_date ? Carbon::parse($template->next_run_date) : $asOf
            );
            $template->forceFill([
                'occurrences_count' => (int) $template->occurrences_count + 1,
                'last_run_at'       => now(),
                'next_run_date'     => $next->toDateString(),
            ]);
            if ($template->isExhausted()) {
                $template->status = 'completed';
            }
            $template->save();

            // Auto-send if configured (mirrors manual send path).
            if ($template->auto_send && $invoice->recipient_email) {
                try {
                    $this->invoices->markSent($invoice);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $invoice->fresh();
        });
    }

    /** Resolve the default tax rule for a template (used by previews). */
    public function taxRuleFor(RecurringInvoice $template): ?TaxRule
    {
        return $template->tax_rule_id ? TaxRule::find($template->tax_rule_id) : null;
    }
}
