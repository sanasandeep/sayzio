import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Email / SMTP" settings page
// (Task #1589). Read-only status of the effective outbound-mail transport
// plus a live "send test email" action. Both endpoints are super-admin
// only on the server (`settings.manage`), returning a 403 otherwise.

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
  from_address: string | null;
  from_name: string | null;
  has_password: boolean;
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

export async function sendTestEmail(testEmail: string): Promise<MailTestResult> {
  const res = await apiFetch<{ data: MailTestResult }>("/admin/mail-settings/test", {
    method: "POST",
    body: JSON.stringify({ test_email: testEmail }),
  });
  return res.data;
}
