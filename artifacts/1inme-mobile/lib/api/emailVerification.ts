import { apiFetch } from "@/lib/api";
import type { AuthUser } from "@/contexts/AuthContext";

// Mobile parity for the web post-sign-up "verify your email" reminder.
// Sends a 6-digit code to the signed-in user's email, then confirms it to
// stamp email_verified_at server-side. Backed by /auth/email-verify/* on the
// Laravel API (Sanctum Bearer).

export type SendEmailVerifyCodeResult = {
  sent?: boolean;
  email?: string;
  already_verified?: boolean;
};

export async function sendEmailVerifyCode(): Promise<SendEmailVerifyCodeResult> {
  const res = await apiFetch<{ data: SendEmailVerifyCodeResult }>(
    "/auth/email-verify/send",
    { method: "POST" },
  );
  return res.data;
}

export async function confirmEmailVerifyCode(code: string): Promise<AuthUser> {
  const res = await apiFetch<{ data: { user: AuthUser } }>(
    "/auth/email-verify/confirm",
    { method: "POST", body: JSON.stringify({ code }) },
  );
  return res.data.user;
}
