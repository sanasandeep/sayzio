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
  recipient_email?: string | null;
  kind?: string | null;
  /** True when the most recent attempt to email this invoice failed (client invoices only). */
  last_send_failed?: boolean;
  /** Signed hosted pay link to share manually (client invoices only). */
  pay_url?: string | null;
};

export type InvoiceDetail = Invoice & {
  pdf_url: string | null;
  receipt_pdf_url: string | null;
  lines: InvoiceLine[];
};

export async function getInvoice(id: number): Promise<InvoiceDetail> {
  const res = await apiFetch<{ data: { invoice: InvoiceDetail } }>(
    `/billing/invoices/${id}`,
  );
  return res.data.invoice;
}

export type InvoiceLineInput = {
  label: string;
  amount_minor: number;
  quantity?: number;
  tax_rate_bps?: number;
  tax_name?: string;
  tax_inclusive?: boolean;
  catalog_item_id?: number;
};

export async function createInvoice(input: {
  currency?: string;
  recipient_email?: string;
  vault_client_id?: number;
  billing_company_id?: number;
  notes_md?: string;
  due_date?: string;
  discount_minor?: number;
  inbox_thread_id?: number;
  line_items?: InvoiceLineInput[];
}): Promise<Invoice> {
  const res = await apiFetch<{ data: { invoice: Invoice } }>(
    "/billing/invoices",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.invoice;
}

export async function markInvoicePaid(
  id: number,
  input: { method?: string; reference?: string; email_receipt?: boolean } = {},
): Promise<Invoice> {
  const res = await apiFetch<{ data: { invoice: Invoice } }>(
    `/billing/invoices/${id}/mark-paid`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.invoice;
}

export async function refundInvoice(
  id: number,
  input: { amount_minor?: number; reason?: string } = {},
): Promise<Invoice> {
  const res = await apiFetch<{ data: { invoice: Invoice } }>(
    `/billing/invoices/${id}/refund`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.invoice;
}

export type InvoiceReceipt = {
  id: number;
  number: string | null;
  method: string | null;
  gateway: string | null;
  gateway_ref: string | null;
  created_at: string | null;
  pdf_url: string | null;
  invoice: Invoice;
};

export async function getInvoiceReceipt(id: number): Promise<InvoiceReceipt> {
  const res = await apiFetch<{ data: { receipt: InvoiceReceipt } }>(
    `/billing/invoices/${id}/receipt`,
  );
  return res.data.receipt;
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
