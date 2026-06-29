import { apiFetch } from "@/lib/api";

// Mobile parity for the web standalone multi-channel quick-contact widget.
// Posts to the same /assistant/quick-contact contract (validated server-side
// by QuickContactService) so a request lands in the admin Contact Inbox and
// triggers an admin email. The endpoint is not login-gated, but apiFetch
// attaches the bearer token when present so a signed-in caller's name/email
// default in on the server.
//
// Channels:
//   - callback : Indian phone number only (+91 / 10-digit, 6-9 leading)
//   - whatsapp : phone number WITH country code (E.164-ish, +<digits>)
//   - email    : a valid email address
export type QuickContactChannel = "callback" | "whatsapp" | "email";

export type QuickContactInput = {
  channel: QuickContactChannel;
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  message?: string | null;
  // Honeypot decoy. A real user never fills this; the server silently drops
  // any submission whose `website` is non-empty. Sent only when populated so
  // genuine requests post nothing for it.
  website?: string | null;
  // Time-trap: ms elapsed between the form opening and submit (a same-clock
  // delta, immune to clock skew). The server quarantines a submission posted
  // faster than a human plausibly could. Omitted when not measured.
  elapsedMs?: number | null;
};

export type QuickContactResult = {
  ok: boolean;
  message: string;
};

export async function sendQuickContact(
  input: QuickContactInput,
): Promise<QuickContactResult> {
  return apiFetch<QuickContactResult>("/assistant/quick-contact", {
    method: "POST",
    body: JSON.stringify({
      channel: input.channel,
      name: input.name?.trim() || undefined,
      email: input.email?.trim() || undefined,
      phone: input.phone?.trim() || undefined,
      message: input.message?.trim() || undefined,
      website: input.website?.trim() || undefined,
      elapsed_ms:
        typeof input.elapsedMs === "number" && input.elapsedMs >= 0
          ? Math.round(input.elapsedMs)
          : undefined,
    }),
  });
}
