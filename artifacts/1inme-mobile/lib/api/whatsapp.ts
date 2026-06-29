import { apiFetch } from "@/lib/api";

/**
 * Add + verify a WhatsApp number from the app (Task #2770) — Bearer-token
 * client for the Laravel /api/v1/me/whatsapp/* endpoints. Mirrors the web
 * onboarding WhatsApp connect step (send code → verify code → number linked).
 *
 * The flow is stateless: the number is passed again on verify alongside the
 * code, so the client owns the "pending number" between the two calls. Once
 * verified, the account's WhatsApp alert toggles (form submissions + payment
 * events) become available.
 */

export type WhatsappSendResult = {
  sent: boolean;
  mobile: string;
  // Admin "Demo mode" toggle: the live code prefixed for on-screen display, or
  // null when the toggle is off.
  demo_reveal?: string | null;
};

export type WhatsappVerifyResult = {
  has_whatsapp_number: boolean;
  mobile: string;
};

/** Step 1 — send a 6-digit code over WhatsApp to the entered number. */
export async function sendWhatsappCode(
  mobile: string,
): Promise<WhatsappSendResult> {
  const res = await apiFetch<{ data: WhatsappSendResult }>(
    "/me/whatsapp/send",
    { method: "POST", body: JSON.stringify({ mobile }) },
  );
  return res.data;
}

/** Step 2 — verify the code and link the number to this account. */
export async function verifyWhatsappCode(
  mobile: string,
  code: string,
): Promise<WhatsappVerifyResult> {
  const res = await apiFetch<{ data: WhatsappVerifyResult }>(
    "/me/whatsapp/verify",
    { method: "POST", body: JSON.stringify({ mobile, code }) },
  );
  return res.data;
}
