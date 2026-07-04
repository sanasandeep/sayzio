import { apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type InvoiceLine = {
  id: number;
  description: string | null;
  quantity: number;
  unit_minor: number;
  amount_minor: number;
  /** Per-line tax rate in basis points (2000 = 20%). Present so the edit screen can prefill it. */
  tax_rate_bps?: number;
  /** Whether the line price already includes tax. */
  tax_inclusive?: boolean;
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
  recipient_name?: string | null;
  recipient_address?: string | null;
  vault_client_id?: number | null;
  contact_id?: number | null;
  /** Free-form notes (markdown). Present so the edit screen can prefill it. */
  notes_md?: string | null;
  /** Discount in minor units. Present so the edit screen can prefill it. */
  discount_minor?: number;
  kind?: string | null;
  /** Per-invoice letterhead orientation (falls back to the billing company's default). */
  letterhead_orientation?: "portrait" | "landscape" | null;
  /** Public URL of the per-invoice letterhead override, when set. */
  letterhead_url?: string | null;
  /** True when the most recent attempt to email this invoice failed (client invoices only). */
  last_send_failed?: boolean;
  /** Sanitized, human-friendly reason the latest send failed (client invoices only). */
  last_send_reason?: string | null;
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

/** Optional local letterhead image to attach as a per-invoice override. */
export type LetterheadInput = {
  uri: string;
  mimeType?: string;
};

const MOBILE_USER_AGENT = "1inmeMobile";

/**
 * Appends a value to FormData using PHP/Laravel bracket notation for nested
 * arrays/objects (e.g. `line_items[0][label]`), since Laravel's array
 * validation rules (`line_items.*.label`) require indexed fields rather
 * than a single JSON-encoded string.
 */
function appendFormValue(fd: FormData, key: string, value: unknown): void {
  if (value === undefined || value === null) return;
  if (Array.isArray(value)) {
    value.forEach((item, index) => appendFormValue(fd, `${key}[${index}]`, item));
  } else if (typeof value === "object") {
    Object.entries(value as Record<string, unknown>).forEach(([k, v]) =>
      appendFormValue(fd, `${key}[${k}]`, v),
    );
  } else if (typeof value === "boolean") {
    fd.append(key, value ? "1" : "0");
  } else {
    fd.append(key, String(value));
  }
}

/**
 * Shared multipart/JSON submitter for the invoice/receipt create+update
 * endpoints: plain JSON when no letterhead file/removal flag is present
 * (fast path, matches every other billing call), otherwise a FormData
 * request so the image upload rides the same request as the field data.
 */
async function submitInvoicePayload<T>(
  path: string,
  method: "POST" | "PATCH",
  fields: Record<string, unknown>,
  letterhead?: LetterheadInput,
  removeLetterhead?: boolean,
): Promise<T> {
  if (!letterhead && !removeLetterhead) {
    return apiFetch<T>(path, { method, body: JSON.stringify(fields) });
  }

  const url = `${getBaseUrl()}/api/v1${path.startsWith("/") ? path : `/${path}`}`;
  const token = await getToken();
  const fd = new FormData();
  if (method === "PATCH") fd.append("_method", "PATCH");
  Object.entries(fields).forEach(([key, value]) => appendFormValue(fd, key, value));
  if (removeLetterhead) fd.append("remove_letterhead", "1");
  if (letterhead) {
    const mime = letterhead.mimeType || "image/jpeg";
    const ext = mime.includes("png") ? "png" : mime.includes("webp") ? "webp" : "jpg";
    fd.append("letterhead", {
      // eslint-disable-next-line @typescript-eslint/ban-ts-comment
      // @ts-ignore – RN-specific FormData entry.
      uri: letterhead.uri,
      name: `letterhead.${ext}`,
      type: mime,
    } as any);
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(url, { method: "POST", body: fd as any, headers });
  const text = await res.text();
  const body = text ? JSON.parse(text) : null;
  if (!res.ok) {
    const nested = body && typeof body.error === "object" ? body.error : null;
    const message =
      nested?.message ||
      (body && typeof body.message === "string" ? body.message : null) ||
      `Request failed (${res.status})`;
    throw { status: res.status, message, code: nested?.code, errors: body?.errors };
  }
  return body as T;
}

export async function createInvoice(input: {
  currency?: string;
  recipient_email?: string;
  recipient_name?: string;
  recipient_address?: string;
  vault_client_id?: number;
  contact_id?: number;
  billing_company_id?: number;
  notes_md?: string;
  due_date?: string;
  discount_minor?: number;
  inbox_thread_id?: number;
  letterhead_orientation?: "portrait" | "landscape";
  line_items?: InvoiceLineInput[];
  letterhead?: LetterheadInput;
}): Promise<Invoice> {
  const { letterhead, ...fields } = input;
  const res = await submitInvoicePayload<{ data: { invoice: Invoice } }>(
    "/billing/invoices",
    "POST",
    fields,
    letterhead,
  );
  return res.data.invoice;
}

/** Standalone receipt: created and immediately marked paid (mirrors the web flow). */
export async function createReceipt(input: {
  currency?: string;
  recipient_email?: string;
  recipient_name?: string;
  recipient_address?: string;
  vault_client_id?: number;
  contact_id?: number;
  billing_company_id?: number;
  notes_md?: string;
  discount_minor?: number;
  method?: string;
  reference?: string;
  letterhead_orientation?: "portrait" | "landscape";
  line_items: InvoiceLineInput[];
  letterhead?: LetterheadInput;
}): Promise<Invoice> {
  const { letterhead, ...fields } = input;
  const res = await submitInvoicePayload<{ data: { invoice: Invoice } }>(
    "/billing/receipts",
    "POST",
    fields,
    letterhead,
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
    line_items: InvoiceLineInput[];
    discount_minor: number;
    tax_total_minor: number;
    notes_md: string | null;
    due_date: string | null;
    vault_client_id: number | null;
    contact_id: number | null;
    recipient_email: string | null;
    recipient_name: string | null;
    recipient_address: string | null;
    letterhead_orientation: "portrait" | "landscape";
    letterhead: LetterheadInput;
    remove_letterhead: boolean;
  }>,
): Promise<Invoice> {
  const { letterhead, remove_letterhead, ...fields } = input;
  const res = await submitInvoicePayload<{ data: { invoice: Invoice } }>(
    `/billing/invoices/${id}`,
    "PATCH",
    fields,
    letterhead,
    remove_letterhead,
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
