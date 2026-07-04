<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiStaffSuggestion;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Services\Billing\ClientInvoiceService;
use RuntimeException;

/**
 * Task #3523 — applies a single AI Staff suggestion, turning the AI's
 * `payload` into a real owned object or a real outbound email. Modeled
 * directly on MarketingSuggestionApplier's atomic claim/apply pattern so
 * two near-simultaneous confirm taps (double-tap, web+mobile) can't both
 * pass a naive "is pending?" check and both act.
 *
 *   draft_invoice  -> creates a real client Invoice (still unsent; the
 *                     owner still has to Send it from the invoice editor)
 *   chase_invoice  -> sends the invoice for the first time (if it was
 *                     never sent) or emails a payment reminder — this is
 *                     the actual "anything is sent" moment, so it only
 *                     ever happens after this explicit confirm.
 */
class AiStaffSuggestionApplier
{
    public function __construct(protected ClientInvoiceService $invoices) {}

    /**
     * @return array{message:string,url:?string}
     */
    public function claimAndApply(User $user, AiStaffSuggestion $suggestion): array
    {
        $claimed = AiStaffSuggestion::query()
            ->whereKey($suggestion->getKey())
            ->where('status', AiStaffSuggestion::STATUS_PENDING)
            ->update([
                'status'     => AiStaffSuggestion::STATUS_APPLIED,
                'applied_at' => now(),
            ]);

        if ($claimed === 0) {
            $current = AiStaffSuggestion::query()->whereKey($suggestion->getKey())->value('status');
            $suggestion->setAttribute('status', $current ?? $suggestion->status);
            $suggestion->syncOriginalAttribute('status');
            throw new AiStaffSuggestionNotPendingException($current !== null ? (string) $current : null);
        }

        try {
            $result = $this->apply($user, $suggestion);
        } catch (\Throwable $e) {
            $suggestion->forceFill([
                'status'     => AiStaffSuggestion::STATUS_ERROR,
                'message'    => mb_substr($e->getMessage(), 0, 500),
                'applied_at' => null,
            ])->save();
            throw $e;
        }

        $suggestion->forceFill([
            'applied_ref_type' => $result['ref_type'] ?? null,
            'applied_ref_id'   => $result['ref_id'] ?? null,
            'message'          => $result['message'],
        ])->save();

        return ['message' => $result['message'], 'url' => $result['url'] ?? null];
    }

    public function dismiss(AiStaffSuggestion $suggestion): void
    {
        $claimed = AiStaffSuggestion::query()
            ->whereKey($suggestion->getKey())
            ->where('status', AiStaffSuggestion::STATUS_PENDING)
            ->update(['status' => AiStaffSuggestion::STATUS_DISMISSED]);

        if ($claimed === 0) {
            $current = AiStaffSuggestion::query()->whereKey($suggestion->getKey())->value('status');
            throw new AiStaffSuggestionNotPendingException($current !== null ? (string) $current : null);
        }
        $suggestion->setAttribute('status', AiStaffSuggestion::STATUS_DISMISSED);
    }

    /**
     * @return array{ref_type:?string,ref_id:?int,message:string,url:?string}
     */
    protected function apply(User $user, AiStaffSuggestion $suggestion): array
    {
        $payload = (array) $suggestion->payload;

        return match ($suggestion->kind) {
            AiStaffSuggestion::KIND_DRAFT_INVOICE => $this->applyDraftInvoice($user, $payload),
            AiStaffSuggestion::KIND_CHASE_INVOICE => $this->applyChaseInvoice($payload),
            default => throw new RuntimeException('Unknown AI Staff suggestion kind.'),
        };
    }

    protected function applyDraftInvoice(User $user, array $payload): array
    {
        $ws = Workspace::query()->find($payload['workspace_id'] ?? null);
        if (!$ws) {
            throw new RuntimeException('The workspace for this draft no longer exists.');
        }

        $data = [
            'line_items' => $payload['line_items'] ?? [],
            'notes_md'   => $payload['notes_md'] ?? null,
            'due_date'   => $payload['due_date'] ?? null,
        ];

        $invoice = $this->invoices->createStandalone($data, $ws, $user->id);

        return [
            'ref_type' => Invoice::class,
            'ref_id'   => $invoice->id,
            'message'  => "Draft invoice {$invoice->number} created. Add a recipient and send it whenever you're ready.",
            'url'      => route('user.client-invoices.edit', $invoice),
        ];
    }

    protected function applyChaseInvoice(array $payload): array
    {
        $invoice = Invoice::query()->find($payload['invoice_id'] ?? null);
        if (!$invoice) {
            throw new RuntimeException('That invoice no longer exists.');
        }
        if (in_array($invoice->status, ['paid', 'refunded', 'partially_refunded'], true)) {
            throw new RuntimeException('This invoice is already settled — nothing to chase.');
        }

        if (!$invoice->sent_at) {
            $this->invoices->markSent($invoice);
            $message = "Invoice {$invoice->number} sent to the client.";
        } else {
            $this->invoices->sendReminder($invoice);
            $message = "Payment reminder sent for invoice {$invoice->number}.";
        }

        return [
            'ref_type' => Invoice::class,
            'ref_id'   => $invoice->id,
            'message'  => $message,
            'url'      => route('user.client-invoices.edit', $invoice),
        ];
    }
}
