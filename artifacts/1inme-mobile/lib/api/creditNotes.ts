import { apiFetch } from "@/lib/api";

// Mobile parity for the web billing dashboard's "Credit notes" table.
// Consumes GET /billing/credit-notes (App\Modules\Api\Controllers\
// BillingController::creditNotes). Read-only — credit notes are only ever
// minted server-side by CreditNoteService::issue() on a refund, never
// created directly by a client. `pdf_url` is a short-lived (6h) signed URL
// to the same PDF the web dashboard links to.

export type CreditNote = {
  id: number;
  number: string | null;
  currency: string | null;
  amount_minor: number;
  invoice_id: number | null;
  invoice_number: string | null;
  issued_at: string | null;
  pdf_url: string | null;
};

export async function listCreditNotes(): Promise<CreditNote[]> {
  const res = await apiFetch<{ data?: { items?: CreditNote[] } }>(
    "/billing/credit-notes",
  );
  return res?.data?.items ?? [];
}
