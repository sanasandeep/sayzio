<?php

namespace App\Console\Commands;

use App\Services\Billing\RecurringInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generates concrete client invoices from every active recurring template
 * whose next_run_date is due, then advances each template's schedule state.
 * Idempotent per template per due date — advancing next_run_date after each
 * run prevents double-billing. Scheduled daily; also runnable manually.
 */
class RunRecurringInvoices extends Command
{
    protected $signature = 'invoices:run-recurring
        {--as-of= : Override the "now" date (YYYY-MM-DD) used to find due templates}';

    protected $description = 'Generate due client invoices from recurring-invoice templates and advance their schedules.';

    public function handle(RecurringInvoiceService $service): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse((string) $this->option('as-of'))
            : null;

        $count = $service->generateDue($asOf);

        $this->info("Generated {$count} recurring invoice(s).");

        return self::SUCCESS;
    }
}
