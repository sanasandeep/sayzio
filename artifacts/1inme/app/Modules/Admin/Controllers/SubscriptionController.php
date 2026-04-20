<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use Illuminate\Http\Request;

/**
 * Admin subscription lifecycle timeline. Reads from the existing
 * subscription / invoice / refund / credit-note tables and composes a
 * chronological timeline of events without a separate audit table:
 *
 *   created  → upgraded/renewed (from invoice line_items meta.kind)
 *          → past_due (grace_until set)
 *          → refunded (refund rows, credit note rows)
 *          → cancelled (cancel_at / cancelled status)
 *          → replaced (replaced_by_id set by an upgrade row)
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, Subscription $subscription)
    {
        $subscription->load(['user', 'plan']);

        // Scope strictly to this subscription: direct subscription_id
        // match OR an invoice line item referencing this subscription
        // (plan_renewal / plan_upgrade meta). This keeps events for
        // *other* subscriptions of the same user out of the timeline.
        $allInvoices = Invoice::where('user_id', $subscription->user_id)
            ->orderBy('issued_at')->get();
        $invoices = $allInvoices->filter(function (Invoice $inv) use ($subscription) {
            if ((int) $inv->subscription_id === (int) $subscription->id) return true;
            foreach ((array) $inv->line_items as $li) {
                $kind = $li['meta']['kind'] ?? null;
                if ($kind === 'plan_renewal'
                    && (int) ($li['meta']['renew_subscription_id'] ?? 0) === $subscription->id) return true;
                if ($kind === 'plan_upgrade'
                    && (int) ($li['meta']['upgrade_from_subscription_id'] ?? 0) === $subscription->id) return true;
            }
            return false;
        })->values();
        $invoiceIds = $invoices->pluck('id');
        $refunds = Refund::whereIn('invoice_id', $invoiceIds)
            ->orderBy('created_at')->get();
        $creditNotes = CreditNote::whereIn('invoice_id', $invoiceIds)
            ->orderBy('created_at')->get();

        $events = $this->buildTimeline($subscription, $invoices, $refunds, $creditNotes);

        return view('admin.subscriptions.show', compact(
            'subscription', 'invoices', 'refunds', 'creditNotes', 'events'
        ));
    }

    protected function buildTimeline(
        Subscription $sub,
        $invoices,
        $refunds,
        $creditNotes
    ): array {
        $events = [];

        $events[] = [
            'at'    => $sub->created_at,
            'kind'  => 'created',
            'label' => "Subscription created on plan \"{$sub->plan?->name}\" ({$sub->billing_cycle})",
            'meta'  => ['status' => $sub->status],
        ];

        // Derive renewal / upgrade events from invoice line-item metadata.
        foreach ($invoices as $inv) {
            foreach ((array) $inv->line_items as $li) {
                $kind = $li['meta']['kind'] ?? null;
                if ($kind === 'plan_renewal'
                    && (int) ($li['meta']['renew_subscription_id'] ?? 0) === $sub->id) {
                    $events[] = [
                        'at'    => $inv->paid_at ?? $inv->issued_at,
                        'kind'  => 'renewed',
                        'label' => "Renewed via invoice {$inv->number} ({$inv->status})",
                        'meta'  => ['invoice_id' => $inv->id, 'status' => $inv->status],
                    ];
                }
                if ($kind === 'plan_upgrade'
                    && (int) ($li['meta']['upgrade_from_subscription_id'] ?? 0) === $sub->id) {
                    $events[] = [
                        'at'    => $inv->paid_at ?? $inv->issued_at,
                        'kind'  => 'upgraded',
                        'label' => "Upgrade charged on invoice {$inv->number}",
                        'meta'  => ['invoice_id' => $inv->id],
                    ];
                }
            }
        }

        if ($sub->grace_until && in_array($sub->status, ['past_due', 'grace', 'expired'], true)) {
            $events[] = [
                'at'    => $sub->current_period_end,
                'kind'  => 'past_due',
                'label' => "Renewal failed; grace period until {$sub->grace_until->toDateString()}",
                'meta'  => ['grace_until' => (string) $sub->grace_until],
            ];
        }

        foreach ($refunds as $r) {
            $events[] = [
                'at'    => $r->processed_at ?? $r->created_at,
                'kind'  => 'refunded',
                'label' => "Refund of "
                    . number_format($r->amount_minor / 100, 2)
                    . " {$r->currency} ({$r->status})",
                'meta'  => ['refund_id' => $r->id, 'status' => $r->status],
            ];
        }

        foreach ($creditNotes as $cn) {
            $events[] = [
                'at'    => $cn->created_at,
                'kind'  => 'credit_note',
                'label' => "Credit note {$cn->number} issued",
                'meta'  => ['credit_note_id' => $cn->id, 'number' => $cn->number],
            ];
        }

        if ($sub->cancel_at_period_end) {
            $events[] = [
                'at'    => $sub->cancel_at ?? $sub->current_period_end,
                'kind'  => 'cancel_scheduled',
                'label' => "Cancellation scheduled at period end",
                'meta'  => [],
            ];
        }

        if ($sub->status === 'cancelled') {
            $events[] = [
                'at'    => $sub->cancel_at ?? $sub->updated_at,
                'kind'  => 'cancelled',
                'label' => "Subscription cancelled",
                'meta'  => [],
            ];
        }

        if ($sub->status === 'expired') {
            $events[] = [
                'at'    => $sub->grace_until ?? $sub->updated_at,
                'kind'  => 'expired',
                'label' => "Subscription expired (grace period ended)",
                'meta'  => [],
            ];
        }

        if ($sub->replaced_by_id) {
            $events[] = [
                'at'    => $sub->updated_at,
                'kind'  => 'replaced',
                'label' => "Replaced by subscription #{$sub->replaced_by_id} via upgrade",
                'meta'  => ['replaced_by_id' => $sub->replaced_by_id],
            ];
        }

        usort($events, function ($a, $b) {
            return ($a['at'] <=> $b['at']) ?: 0;
        });
        return $events;
    }
}
