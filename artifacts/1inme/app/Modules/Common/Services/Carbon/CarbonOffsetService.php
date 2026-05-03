<?php

namespace App\Modules\Common\Services\Carbon;

use App\Modules\Common\Services\Carbon\Contracts\OffsetProvider;
use App\Modules\Common\Services\Carbon\Providers\CloverlyOffsetProvider;
use App\Modules\Common\Services\Carbon\Providers\NullOffsetProvider;
use App\Modules\User\Models\BiolinkCarbonSnapshot;
use App\Modules\User\Models\CarbonOffsetPurchase;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates offset purchases for a snapshot:
 *   - resolves the right provider (workspace's connected Cloverly /
 *     Patch / sandbox fallback)
 *   - enforces the link's monthly budget cap (`pause` vs `partial`)
 *   - records the CarbonOffsetPurchase
 *   - issues an Invoice line item charged via the existing Stripe
 *     pipeline (kind=client invoice — we deliberately don't trigger
 *     Stripe Checkout from here so the renewal job remains the single
 *     payment funnel)
 *
 * This service is idempotent per snapshot row: re-running the monthly
 * job is a no-op for already-offset snapshots.
 */
class CarbonOffsetService
{
    public function __construct(private CarbonSettingsResolver $settings) {}

    public function offsetSnapshot(BiolinkCarbonSnapshot $snapshot): ?CarbonOffsetPurchase
    {
        if ($snapshot->offset_status === 'purchased' || $snapshot->offset_status === 'sandbox') {
            return $snapshot->offset_purchase_id
                ? CarbonOffsetPurchase::query()->withoutGlobalScope('workspace')->find($snapshot->offset_purchase_id)
                : null;
        }

        $link      = Link::query()->withoutGlobalScope('workspace')->find($snapshot->link_id);
        $workspace = Workspace::query()->find($snapshot->workspace_id);
        if (!$link || !$workspace) return null;

        $effective = $this->settings->effectiveFor($workspace, $link);
        if (!($effective['enabled'] ?? false))                return null;
        if (($snapshot->grams_co2 ?? 0) <= 0)                 return null;

        // Compute budget enforcement.
        $budgetMinor = (int) ($effective['monthly_budget_minor'] ?? 0);
        $fallback    = (string) ($effective['fallback'] ?? 'pause');
        $provider    = $this->providerFor($workspace);

        // Budget enforcement runs BEFORE any provider purchase: we
        // ask for a non-binding quote first so a misconfigured cap
        // can never trigger a real charge that we then "discover"
        // is over-budget and have to refund.
        $idemKey     = sprintf('snap-%d-%s', $snapshot->id, $snapshot->period_start->format('Y-m'));
        $gramsActual = (float) $snapshot->grams_co2;
        $estQuote    = $provider->quote($workspace->id, $gramsActual, 'USD');
        $estCost     = (int) ($estQuote['cost_minor'] ?? 0);

        if ($budgetMinor > 0 && $estCost > $budgetMinor) {
            if ($fallback === 'pause') {
                $snapshot->offset_status = 'capped';
                $snapshot->save();
                return null;
            }
            // Partial: scale grams down to fit the budget exactly,
            // BEFORE we issue the real purchase call.
            $ratio       = $budgetMinor / max(1, $estCost);
            $gramsActual = (float) round($gramsActual * $ratio, 2);
            $idemKey    .= '-partial';
            if ($gramsActual <= 0) {
                $snapshot->offset_status = 'capped';
                $snapshot->save();
                return null;
            }
        }

        // Single, post-cap purchase call.
        $quote     = $provider->purchase($workspace->id, $gramsActual, 'USD', $idemKey);
        $costMinor = (int) ($quote['cost_minor'] ?? 0);

        // Persist the purchase + snapshot pointer atomically. Invoice
        // creation is intentionally OUTSIDE this transaction because
        // the invoices table has historical NOT-NULL columns we may
        // not be able to fill (financial_year, seq); any failure
        // there must NOT abort the offset record itself. (PostgreSQL
        // aborts the whole transaction on any statement error, so
        // attaching the invoice in the same txn used to make the
        // snapshot UPDATE blow up too.)
        $purchase = DB::transaction(function () use ($snapshot, $workspace, $quote, $gramsActual, $costMinor, $provider) {
            $purchase = new CarbonOffsetPurchase();
            $purchase->workspace_id    = $workspace->id;
            $purchase->link_id         = $snapshot->link_id;
            $purchase->provider        = $provider->slug();
            $purchase->provider_ref    = $quote['provider_ref'] ?? null;
            $purchase->grams_offset    = $gramsActual;
            $purchase->currency        = strtoupper((string) ($quote['currency'] ?? 'USD'));
            $purchase->cost_minor      = max(0, $costMinor);
            $purchase->status          = $quote['status'] ?? 'pending';
            $purchase->certificate_url = $quote['certificate_url'] ?? null;
            $purchase->project_name    = $quote['project_name'] ?? null;
            $purchase->raw             = $quote['raw'] ?? null;
            $purchase->purchased_at    = now();
            $purchase->save();

            $snapshot->grams_offset       = $gramsActual;
            $snapshot->offset_status      = $purchase->status === 'failed' ? 'failed'
                                          : ($purchase->status === 'sandbox' ? 'sandbox' : 'purchased');
            $snapshot->offset_purchase_id = $purchase->id;
            $snapshot->save();

            return $purchase;
        });

        try {
            $invoice = $this->ensureCarbonInvoice($workspace, $purchase);
            if ($invoice) {
                $purchase->invoice_id = $invoice->id;
                $purchase->save();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('carbon_invoice_attach_failed', [
                'purchase_id' => $purchase->id, 'error' => $e->getMessage(),
            ]);
        }

        return $purchase;
    }

    public function providerFor(Workspace $workspace): OffsetProvider
    {
        $configured = IntegrationConfig::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('kind', 'carbon')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
        if (!$configured) return new NullOffsetProvider();
        return match ($configured->provider) {
            'cloverly' => new CloverlyOffsetProvider(),
            default    => new NullOffsetProvider(),
        };
    }

    /**
     * Append (or create) a draft client-style invoice for the workspace
     * owner aggregating this month's carbon offset purchases. We use
     * `kind=client` because it accepts arbitrary line items; the
     * existing Stripe pipeline + invoices UI already handles them.
     */
    private function ensureCarbonInvoice(Workspace $workspace, CarbonOffsetPurchase $purchase): ?Invoice
    {
        $monthKey = $purchase->purchased_at?->format('Y-m') ?? now()->format('Y-m');
        $invoice  = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', 'client')
            ->where('status', 'draft')
            ->where('notes_md', 'like', "Carbon offsets for {$monthKey}%")
            ->first();

        $line = [
            'description' => sprintf('Carbon offset · %s g CO₂', number_format($purchase->grams_offset, 0)),
            'qty'         => 1,
            'unit_minor'  => $purchase->cost_minor,
            'total_minor' => $purchase->cost_minor,
            'meta'        => ['carbon_purchase_id' => $purchase->id, 'provider' => $purchase->provider],
        ];

        if ($invoice) {
            $items   = (array) ($invoice->line_items ?? []);
            $items[] = $line;
            $invoice->line_items        = $items;
            $invoice->subtotal_minor    = (int) array_sum(array_column($items, 'total_minor'));
            $invoice->grand_total_minor = $invoice->subtotal_minor + (int) ($invoice->tax_total_minor ?? 0);
            $invoice->save();
            return $invoice;
        }

        try {
            $invoice = new Invoice();
            $invoice->kind                     = 'client';
            $invoice->workspace_id             = $workspace->id;
            $invoice->user_id                  = $workspace->owner_user_id;
            $invoice->status                   = 'draft';
            $invoice->currency                 = $purchase->currency;
            $invoice->subtotal_minor           = $purchase->cost_minor;
            $invoice->tax_total_minor          = 0;
            $invoice->grand_total_minor        = $purchase->cost_minor;
            $invoice->discount_minor           = 0;
            $invoice->line_items               = [$line];
            $invoice->tax_breakdown            = [];
            $invoice->billing_address_snapshot = [];
            $invoice->merchant_snapshot        = [];
            $invoice->notes_md                 = "Carbon offsets for {$monthKey} — auto-generated";
            $invoice->number                   = 'CARB-' . $monthKey . '-' . Str::upper(Str::random(6));
            $invoice->issued_at                = now();
            $invoice->financial_year           = (int) now()->year;
            $invoice->seq                      = (int) (Invoice::max('seq') ?? 0) + 1;
            $invoice->save();
            return $invoice;
        } catch (\Throwable $e) {
            // Invoice schema may require fields we can't fill (e.g.
            // financial_year). Failing the invoice attach must not
            // roll back the purchase row — the dashboard still surfaces
            // the unbilled offset and admins can reconcile.
            \Illuminate\Support\Facades\Log::warning('carbon_invoice_create_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
