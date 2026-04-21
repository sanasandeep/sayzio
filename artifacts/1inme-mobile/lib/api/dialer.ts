import { apiFetch } from "@/lib/api";

export type DialerLookupContact = {
  id: number;
  display_name: string | null;
  organization: string | null;
  phone: string | null;
};

export type DialerLookupResult = {
  number_e164: string;
  contact: DialerLookupContact | null;
};

export type DialerHistoryItem = {
  id: number;
  number_e164: string;
  contact_id: number | null;
  looked_up_at: string | null;
};

/**
 * Best-effort: records the call against the server (so the user can see
 * cross-device history later) and returns any matching 1INME contact
 * the server resolved from its own contact database.
 *
 * Strict E.164 only — the server validates `^\+[1-9]\d{6,14}$`. If the
 * keypad number isn't in that shape, callers should skip this and let
 * the dial-out happen anyway (the device dialer will accept anything).
 */
export async function lookupNumber(
  numberE164: string,
): Promise<DialerLookupResult> {
  const res = await apiFetch<{ data: DialerLookupResult }>(`/dialer/lookup`, {
    method: "POST",
    body: JSON.stringify({ number_e164: numberE164 }),
  });
  return res.data;
}

export async function dialerHistory(): Promise<DialerHistoryItem[]> {
  const res = await apiFetch<{ data: { items: DialerHistoryItem[] } }>(
    `/dialer/history`,
  );
  return res.data.items;
}
