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

export type WhatsappStatus = {
  has_whatsapp_number: boolean;
  // Connected number with all but the last 4 digits masked, or null when none.
  mobile_masked: string | null;
  // Whether the number can be removed; mirrors the web remove flow (a primary
  // number is removable — another verified contact gets auto-promoted; only
  // the last verified email/phone is blocked).
  can_remove: boolean;
  // Human-readable reason the number can't be removed, or null when it can.
  remove_blocked_reason: string | null;
  // Whether the connected number is the account's primary sign-in identifier.
  is_primary: boolean;
  // When removal would auto-promote another verified contact to primary, the
  // masked value of that contact (e.g. "j•••@example.com"); null otherwise.
  promotes_to: string | null;
  // "email" | "phone" for the contact above; null when promotes_to is null.
  promotes_to_kind: "email" | "phone" | null;
};

export type WhatsappDisconnectResult = {
  has_whatsapp_number: boolean;
  mobile_masked: string | null;
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

/** Read the connected WhatsApp number (masked) and whether it can be removed. */
export async function getWhatsappStatus(): Promise<WhatsappStatus> {
  const res = await apiFetch<{ data: WhatsappStatus }>("/me/whatsapp");
  return res.data;
}

/** Disconnect the connected WhatsApp number from this account. */
export async function disconnectWhatsapp(): Promise<WhatsappDisconnectResult> {
  const res = await apiFetch<{ data: WhatsappDisconnectResult }>(
    "/me/whatsapp",
    { method: "DELETE" },
  );
  return res.data;
}
