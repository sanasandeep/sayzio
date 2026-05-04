import { apiFetch } from "@/lib/api";

export type InvoiceLine = {
  id: number;
  description: string | null;
  quantity: number;
  unit_minor: number;
  amount_minor: number;
};

export type Invoice = {
  id: number;
  number: string | null;
  status: string | null;
  currency: string | null;
  subtotal_minor: number;
  tax_total_minor: number;
  grand_total_minor: number;
  issued_at: string | null;
  paid_at: string | null;
  due_at: string | null;
};

export type InvoiceDetail = Invoice & {
  pdf_url: string | null;
  lines: InvoiceLine[];
};

export async function getInvoice(id: number): Promise<InvoiceDetail> {
  const res = await apiFetch<{ data: { invoice: InvoiceDetail } }>(
    `/billing/invoices/${id}`,
  );
  return res.data.invoice;
}

export async function createInvoice(input: {
  currency?: string;
  recipient_email?: string;
  vault_client_id?: number;
  notes_md?: string;
  due_date?: string;
}): Promise<Invoice> {
  const res = await apiFetch<{ data: { invoice: Invoice } }>(
    "/billing/invoices",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.invoice;
}

export async function updateInvoice(
  id: number,
  input: Partial<{
    line_items: { label: string; amount_minor: number; quantity?: number }[];
    discount_minor: number;
    tax_total_minor: number;
    notes_md: string | null;
    due_date: string | null;
    vault_client_id: number | null;
    recipient_email: string | null;
  }>,
): Promise<Invoice> {
  const res = await apiFetch<{ data: { invoice: Invoice } }>(
    `/billing/invoices/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.invoice;
}

export async function deleteInvoice(id: number): Promise<void> {
  await apiFetch<unknown>(`/billing/invoices/${id}`, { method: "DELETE" });
}

export async function sendInvoice(
  id: number,
  input: { recipient_email?: string } = {},
): Promise<{ invoice: Invoice; pay_url: string }> {
  const res = await apiFetch<{ data: { invoice: Invoice; pay_url: string } }>(
    `/billing/invoices/${id}/send`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}
