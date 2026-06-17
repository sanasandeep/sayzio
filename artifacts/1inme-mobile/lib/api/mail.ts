import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Email / SMTP" settings page.
// Read status of the effective outbound-mail transport, fully edit it, and
// fire a live "send test email" action. Every endpoint is super-admin only
// on the server (`settings.manage`), returning a 403 otherwise.

export type MailStatusTone = "green" | "amber" | "slate";

export type MailStatusBadge = {
  key: "configured" | "env" | "log" | string;
  label: string;
  tone: MailStatusTone;
};

export type MailSettingsStatus = {
  status: MailStatusBadge;
  mailer: string;
  host: string | null;
  port: number | null;
  encryption: string;
  username: string | null;
  from_address: string | null;
  from_name: string | null;
  has_password: boolean;
  mailers: string[];
  encryption_options: string[];
};

// update() returns the refreshed status plus an optional SMTP
// connection-check result, so the screen can surface "saved, but the
// connection check failed" exactly like the web page.
export type MailVerifyResult = { ok: boolean; error: string | null };
export type MailSettingsSaveResult = MailSettingsStatus & {
  verify: MailVerifyResult | null;
};

export type MailSettingsUpdate = {
  mailer: string;
  host?: string | null;
  port?: number | null;
  encryption: string;
  username?: string | null;
  // Blank/omitted leaves the stored password untouched; set clear_password to
  // reset it back to the env fallback.
  password?: string | null;
  clear_password?: boolean;
  from_address: string;
  from_name: string;
};

export type MailTestResult = {
  sent: boolean;
  to?: string;
  driver?: string;
  message: string;
};

export async function getMailSettings(): Promise<MailSettingsStatus> {
  const res = await apiFetch<{ data: MailSettingsStatus }>("/admin/mail-settings");
  return res.data;
}

export async function updateMailSettings(
  payload: MailSettingsUpdate,
): Promise<MailSettingsSaveResult> {
  const res = await apiFetch<{ data: MailSettingsSaveResult }>("/admin/mail-settings", {
    method: "PUT",
    body: JSON.stringify(payload),
  });
  return res.data;
}

export async function sendTestEmail(testEmail: string): Promise<MailTestResult> {
  const res = await apiFetch<{ data: MailTestResult }>("/admin/mail-settings/test", {
    method: "POST",
    body: JSON.stringify({ test_email: testEmail }),
  });
  return res.data;
}
