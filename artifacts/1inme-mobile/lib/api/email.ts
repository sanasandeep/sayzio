import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Email Templates" + "Email Log" pages
// and the user-facing "Email history" screen. Template/log endpoints are
// super-admin only on the server (`settings.manage`, 403 otherwise); the
// self-scoped history endpoints are available to any signed-in user. Previews
// and resends both run through the same central Emailer pipeline as real
// sends, so what you see/resend matches production.

// ── Email templates (admin) ────────────────────────────────────────────────

export type EmailTemplateFormat = "html" | "text";

export type EmailTemplateRow = {
  key: string;
  label: string;
  description: string;
  format: EmailTemplateFormat | string;
  overridden: boolean;
};

export type EmailTemplateCategory = {
  category: string;
  label: string;
  templates: EmailTemplateRow[];
};

export type EmailPreview = {
  subject: string;
  body: string;
  format: EmailTemplateFormat | string;
};

export type EmailTemplateOverride = {
  subject: string;
  body: string;
  format: EmailTemplateFormat | string;
} | null;

export type EmailTemplateDetail = {
  key: string;
  category: string;
  label: string;
  description: string;
  format: EmailTemplateFormat | string;
  variables: Record<string, string> | string[];
  default: { subject: string; view: string | null };
  override: EmailTemplateOverride;
  preview: EmailPreview;
};

export type EmailTemplateSaveResult = {
  override: EmailTemplateOverride;
  preview: EmailPreview;
};

export async function getEmailTemplates(): Promise<EmailTemplateCategory[]> {
  const res = await apiFetch<{ data: { categories: EmailTemplateCategory[] } }>(
    "/admin/email-templates",
  );
  return res.data.categories;
}

export async function getEmailTemplate(
  key: string,
): Promise<EmailTemplateDetail> {
  const res = await apiFetch<{ data: EmailTemplateDetail }>(
    `/admin/email-templates/${encodeURIComponent(key)}`,
  );
  return res.data;
}

export async function updateEmailTemplate(
  key: string,
  payload: { subject: string; body: string; format: EmailTemplateFormat },
): Promise<EmailTemplateSaveResult> {
  const res = await apiFetch<{ data: EmailTemplateSaveResult }>(
    `/admin/email-templates/${encodeURIComponent(key)}`,
    { method: "PUT", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function resetEmailTemplate(
  key: string,
): Promise<EmailTemplateSaveResult> {
  const res = await apiFetch<{ data: EmailTemplateSaveResult }>(
    `/admin/email-templates/${encodeURIComponent(key)}`,
    { method: "DELETE" },
  );
  return res.data;
}

// Live preview of an unsaved draft (subject/body/format) without persisting.
export async function previewEmailTemplate(
  key: string,
  draft: { subject: string; body: string; format: EmailTemplateFormat },
): Promise<EmailPreview> {
  const res = await apiFetch<{ data: EmailPreview }>(
    `/admin/email-templates/${encodeURIComponent(key)}/preview`,
    { method: "POST", body: JSON.stringify(draft) },
  );
  return res.data;
}

// ── Email activity log (admin) ──────────────────────────────────────────────

export type EmailLogStatus = "sent" | "failed" | "pending" | string;

export type EmailLogRow = {
  id: number;
  recipient: string;
  subject: string | null;
  email_key: string | null;
  category: string | null;
  status: EmailLogStatus;
  is_resend: boolean;
  created_at: string | null;
};

export type EmailLogDetail = EmailLogRow & {
  format: string | null;
  body: string | null;
  error: string | null;
  meta: Record<string, unknown> | null;
  user: { id: number; name: string; email: string } | null;
};

export type EmailLogPage = {
  logs: EmailLogRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  categories: Record<string, string>;
};

export type EmailLogFilters = {
  q?: string;
  category?: string;
  status?: string;
  page?: number;
};

export async function getEmailLogs(
  filters: EmailLogFilters = {},
): Promise<EmailLogPage> {
  const params = new URLSearchParams();
  if (filters.q) params.set("q", filters.q);
  if (filters.category) params.set("category", filters.category);
  if (filters.status) params.set("status", filters.status);
  if (filters.page) params.set("page", String(filters.page));
  const qs = params.toString();
  const res = await apiFetch<{ data: EmailLogPage }>(
    `/admin/email-logs${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

export async function getEmailLog(id: number): Promise<EmailLogDetail> {
  const res = await apiFetch<{ data: EmailLogDetail }>(`/admin/email-logs/${id}`);
  return res.data;
}

export async function resendEmailLog(
  id: number,
): Promise<{ resent_to: string; log: EmailLogRow }> {
  const res = await apiFetch<{ data: { resent_to: string; log: EmailLogRow } }>(
    `/admin/email-logs/${id}/resend`,
    { method: "POST" },
  );
  return res.data;
}

// ── Email history (self-scoped, any signed-in user) ─────────────────────────

export type EmailHistoryRow = {
  id: number;
  subject: string | null;
  status: EmailLogStatus;
  created_at: string | null;
  resendable: boolean;
};

export type EmailHistoryPage = {
  emails: EmailHistoryRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function getEmailHistory(
  page = 1,
): Promise<EmailHistoryPage> {
  const res = await apiFetch<{ data: EmailHistoryPage }>(
    `/me/emails${page > 1 ? `?page=${page}` : ""}`,
  );
  return res.data;
}

export async function resendOwnEmail(
  id: number,
): Promise<{ resent_to: string }> {
  const res = await apiFetch<{ data: { resent_to: string } }>(
    `/me/emails/${id}/resend`,
    { method: "POST" },
  );
  return res.data;
}
