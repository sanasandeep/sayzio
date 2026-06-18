import { apiFetch } from "@/lib/api";

export type DialerLookupContact = {
  id: number;
  display_name: string | null;
  organization: string | null;
  phone: string | null;
};

export type DialerBiolink = {
  user_id: number;
  name: string | null;
  handle: string | null;
  url: string | null;
  link_id: number | null;
};

export type DialerActivity = {
  id: number;
  number: string | null;
  number_e164: string | null;
  contact_id: number | null;
  outcome: string | null;
  note: string | null;
  tag: string | null;
  callback_at: string | null;
  at: string | null;
  at_human: string | null;
};

export type DialerLookupResult = {
  number_e164: string;
  is_spam: boolean;
  is_blocked: boolean;
  is_favorite: boolean;
  contact: DialerLookupContact | null;
  biolink: DialerBiolink | null;
  activity: DialerActivity[];
};

export type DialerRecent = {
  number: string | null;
  number_e164: string | null;
  contact_id: number | null;
  name: string | null;
  initials: string;
  biolink: boolean;
  is_spam: boolean;
  is_blocked: boolean;
  calls: number;
  last_at: string | null;
  last_human: string | null;
  outcome: string | null;
  note: string | null;
  tag: string | null;
};

export type DialerFrequent = {
  number: string | null;
  number_e164: string | null;
  contact_id: number | null;
  name: string | null;
  initials: string;
  calls: number;
  biolink: boolean;
  is_spam: boolean;
};

export type DialerFavorite = {
  id: number;
  contact_id: number | null;
  number: string | null;
  number_e164: string | null;
  label: string | null;
  initials: string;
  biolink: boolean;
  sort_order: number;
};

export type DialerHistory = {
  recents: DialerRecent[];
  frequent: DialerFrequent[];
};

/**
 * Caller-ID lookup. Records the call against the server (cross-device
 * history) and returns the resolved contact, any 1INME biolink owner
 * (even for unsaved numbers), per-user spam/block flags, favorite state
 * and recent activity for the number.
 *
 * Strict E.164 only — the server validates `^\+[1-9]\d{6,14}$`.
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

/** Smart grouped recents + frequently-contacted strip. */
export async function dialerHistory(): Promise<DialerHistory> {
  const res = await apiFetch<{ data: DialerHistory }>(`/dialer/history`);
  return res.data;
}

// ── Favorites (speed dial) ───────────────────────────────────────────

export async function listFavorites(): Promise<DialerFavorite[]> {
  const res = await apiFetch<{ data: { items: DialerFavorite[] } }>(
    `/dialer/favorites`,
  );
  return res.data.items;
}

export async function addFavorite(input: {
  contact_id?: number | null;
  number?: string | null;
  label?: string | null;
}): Promise<DialerFavorite> {
  const res = await apiFetch<{
    data: { favorite: DialerFavorite; already?: boolean };
  }>(`/dialer/favorites`, {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data.favorite;
}

export async function removeFavorite(id: number): Promise<void> {
  await apiFetch(`/dialer/favorites/${id}`, { method: "DELETE" });
}

export async function reorderFavorites(
  order: number[],
): Promise<DialerFavorite[]> {
  const res = await apiFetch<{ data: { items: DialerFavorite[] } }>(
    `/dialer/favorites/reorder`,
    { method: "POST", body: JSON.stringify({ order }) },
  );
  return res.data.items;
}

// ── Spam / block flags ───────────────────────────────────────────────

export async function flagNumber(input: {
  number: string;
  is_spam?: boolean;
  is_blocked?: boolean;
}): Promise<{ number_e164: string; is_spam: boolean; is_blocked: boolean }> {
  const res = await apiFetch<{
    data: { number_e164: string; is_spam: boolean; is_blocked: boolean };
  }>(`/dialer/flag`, { method: "POST", body: JSON.stringify(input) });
  return res.data;
}

// ── Call log (mini-CRM) + callback reminders ─────────────────────────

export async function logCall(input: {
  number: string;
  contact_id?: number | null;
  outcome?: string | null;
  note?: string | null;
  tag?: string | null;
}): Promise<DialerActivity> {
  const res = await apiFetch<{ data: { log: DialerActivity } }>(`/dialer/log`, {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data.log;
}

export async function setCallback(input: {
  number: string;
  contact_id?: number | null;
  callback_at: string;
  note?: string | null;
}): Promise<DialerActivity> {
  const res = await apiFetch<{ data: { callback: DialerActivity } }>(
    `/dialer/callback`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.callback;
}

export async function clearCallback(id: number): Promise<void> {
  await apiFetch(`/dialer/callback/${id}`, { method: "DELETE" });
}
