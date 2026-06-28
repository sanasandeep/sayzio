import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web "company SMTP" section and the per-company
// client-facing email template editor. Every endpoint is owner-scoped to the
// authenticated creator's own billing companies on the server (404 otherwise),
// so a creator can configure their own outbound mail and customise the
// invoice/receipt emails their clients receive. The SMTP password is never
// returned to the device — only whether one is set and a masked tail. Previews
// render through the same central Emailer pipeline as real sends.

// ── SMTP transport ─────────────────────────────────────────────────────────

export type CompanySmtpStatus = {
  company_id: number;
  company_name: string;
  smtp_enabled: boolean;
  smtp_host: string | null;
  smtp_port: number | null;
  smtp_encryption: string;
  smtp_username: string | null;
  smtp_from_address: string | null;
  smtp_from_name: string | null;
  has_password: boolean;
  masked_password: string | null;
  is_configured: boolean;
  verified_at: string | null;
  encryption_options: string[];
  // Addresses a test send may target. The server restricts the test send to
  // addresses the creator controls (account email, company contact email,
  // sender address) so it can't be abused as a spam relay to third parties.
  allowed_test_recipients: string[];
};

export type CompanySmtpVerify = { ok: boolean; error: string | null };

export type CompanySmtpSaveResult = CompanySmtpStatus & {
  verify: CompanySmtpVerify | null;
};

export type CompanySmtpUpdate = {
  smtp_enabled: boolean;
  smtp_host?: string | null;
  smtp_port?: number | null;
  smtp_encryption: string;
  smtp_username?: string | null;
  // Blank/omitted leaves the stored password untouched; set clear_password to
  // reset it back to the inherited fallback.
  smtp_password?: string | null;
  smtp_clear_password?: boolean;
  smtp_from_address?: string | null;
  smtp_from_name?: string | null;
};

export type CompanySmtpTestResult = {
  sent: boolean;
  to: string;
  message: string;
};

export async function getCompanySmtp(
  companyId: number,
): Promise<CompanySmtpStatus> {
  const res = await apiFetch<{ data: CompanySmtpStatus }>(
    `/billing/companies/${companyId}/smtp`,
  );
  return res.data;
}

export async function updateCompanySmtp(
  companyId: number,
  payload: CompanySmtpUpdate,
): Promise<CompanySmtpSaveResult> {
  const res = await apiFetch<{ data: CompanySmtpSaveResult }>(
    `/billing/companies/${companyId}/smtp`,
    { method: "PUT", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function verifyCompanySmtp(
  companyId: number,
): Promise<CompanySmtpSaveResult> {
  const res = await apiFetch<{ data: CompanySmtpSaveResult }>(
    `/billing/companies/${companyId}/smtp/verify`,
    { method: "POST" },
  );
  return res.data;
}

export async function testCompanySmtp(
  companyId: number,
  testEmail: string,
): Promise<CompanySmtpTestResult> {
  const res = await apiFetch<{ data: CompanySmtpTestResult }>(
    `/billing/companies/${companyId}/smtp/test`,
    { method: "POST", body: JSON.stringify({ test_email: testEmail }) },
  );
  return res.data;
}

// ── Client-facing email templates ───────────────────────────────────────────

export type CompanyEmailFormat = "html" | "text";

export type CompanyEmailTemplateRow = {
  key: string;
  label: string;
  description: string;
  format: CompanyEmailFormat | string;
  overridden: boolean;
};

export type CompanyEmailPreview = {
  subject: string;
  body: string;
  format: CompanyEmailFormat | string;
};

export type CompanyEmailOverride = {
  subject: string;
  body: string;
  format: CompanyEmailFormat | string;
} | null;

export type CompanyEmailTemplateDetail = {
  key: string;
  label: string;
  description: string;
  format: CompanyEmailFormat | string;
  variables: Record<string, string> | string[];
  default: { subject: string };
  override: CompanyEmailOverride;
  preview: CompanyEmailPreview;
};

export type CompanyEmailSaveResult = {
  override: CompanyEmailOverride;
  preview: CompanyEmailPreview;
};

export async function getCompanyEmailTemplates(
  companyId: number,
): Promise<CompanyEmailTemplateRow[]> {
  const res = await apiFetch<{ data: { templates: CompanyEmailTemplateRow[] } }>(
    `/billing/companies/${companyId}/emails`,
  );
  return res.data.templates;
}

export async function getCompanyEmailTemplate(
  companyId: number,
  key: string,
): Promise<CompanyEmailTemplateDetail> {
  const res = await apiFetch<{ data: CompanyEmailTemplateDetail }>(
    `/billing/companies/${companyId}/emails/${encodeURIComponent(key)}`,
  );
  return res.data;
}

export async function updateCompanyEmailTemplate(
  companyId: number,
  key: string,
  payload: { subject: string; body: string; format: CompanyEmailFormat },
): Promise<CompanyEmailSaveResult> {
  const res = await apiFetch<{ data: CompanyEmailSaveResult }>(
    `/billing/companies/${companyId}/emails/${encodeURIComponent(key)}`,
    { method: "PUT", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function resetCompanyEmailTemplate(
  companyId: number,
  key: string,
): Promise<CompanyEmailSaveResult> {
  const res = await apiFetch<{ data: CompanyEmailSaveResult }>(
    `/billing/companies/${companyId}/emails/${encodeURIComponent(key)}`,
    { method: "DELETE" },
  );
  return res.data;
}

// Live preview of an unsaved draft (subject/body/format) without persisting.
export async function previewCompanyEmailTemplate(
  companyId: number,
  key: string,
  draft: { subject: string; body: string; format: CompanyEmailFormat },
): Promise<CompanyEmailPreview> {
  const res = await apiFetch<{ data: CompanyEmailPreview }>(
    `/billing/companies/${companyId}/emails/${encodeURIComponent(key)}/preview`,
    { method: "POST", body: JSON.stringify(draft) },
  );
  return res.data;
}
