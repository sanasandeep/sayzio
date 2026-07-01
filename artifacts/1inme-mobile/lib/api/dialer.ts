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
 * Near-real-time cross-device sync payload. `cursor` is an opaque lastId-style
 * signature; pass it back as `since` on the next poll. When nothing changed the
 * server omits the lists and returns `changed:false`, so we keep what we have.
 */
export type DialerLiveState = {
  cursor: string;
  changed: boolean;
  favorites?: DialerFavorite[];
  frequent?: DialerFrequent[];
  recents?: DialerRecent[];
};

/**
 * Caller-ID lookup. Records the call against the server (cross-device
 * history) and returns the resolved contact, any Sayzio biolink owner
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

/**
 * Poll the pollable "live" cursor for cross-device sync. Pass the last
 * `cursor` back as `since`; the server only returns the lists when they
 * actually changed, keeping polling cheap.
 */
export async function dialerLive(since?: string): Promise<DialerLiveState> {
  const qs = since ? `?since=${encodeURIComponent(since)}` : "";
  const res = await apiFetch<{ data: DialerLiveState }>(`/dialer/live${qs}`);
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

// ── Universal finder ─────────────────────────────────────────────────
// One grouped search across Contacts, People, My links, Followed and
// Workspaces. Backed by the same server class (DialerSearch) as web + REST
// so the three surfaces can never drift. Fed by BOTH keypad modes (T9 grid
// + alphanumeric keyboard) on the mobile dialer.

export type DialerSearchAction = {
  kind: string;
  url: string | null;
  number?: string | null;
  contact_id?: number | null;
  handle?: string | null;
  user_id?: number | null;
  link_id?: number | null;
  edit_url?: string | null;
  switch_url?: string | null;
  workspace_id?: number | null;
};

export type DialerSearchItem = {
  type: string;
  category: string;
  id: number;
  title: string;
  subtitle: string;
  type_label: string;
  initials: string;
  badge: string | null;
  verified: boolean;
  verified_label: string | null;
  action: DialerSearchAction;
};

export type DialerSearchGroup = {
  key: string;
  label: string;
  items: DialerSearchItem[];
};

export type DialerSearchResult = {
  q: string;
  filter: string | null;
  total: number;
  groups: DialerSearchGroup[];
};

export type DialerSearchFilters = {
  verified?: boolean;
  has_biolink?: boolean;
  tag?: string;
};

/** Universal grouped finder. Same contract as the web + REST dialer search. */
export async function dialerSearch(
  q: string,
  filters: DialerSearchFilters = {},
): Promise<DialerSearchResult> {
  const qs = new URLSearchParams();
  if (q) qs.set("q", q);
  if (filters.verified) qs.set("filter", "verified");
  if (filters.has_biolink) qs.set("has_biolink", "1");
  if (filters.tag) qs.set("tag", filters.tag);
  const res = await apiFetch<{ data: DialerSearchResult }>(
    `/dialer/search?${qs.toString()}`,
  );
  return res.data;
}

// ── Preferred messaging channels ─────────────────────────────────────
// Which of call / SMS / WhatsApp / Telegram / Signal / Viber the one-tap
// channel rows show. Single source of truth shared with the web dialer via
// the server (App\Modules\User\Support\DialerChannels), so surfaces never
// drift. `js` is the deep-link builder mode; `feather` is the icon name.

export type DialerChannelDef = {
  key: string;
  label: string;
  short: string;
  color: string;
  fa: string;
  feather: string;
  js: string;
};

export type DialerChannelPrefs = {
  catalog: DialerChannelDef[];
  enabled: string[];
};

/** The full channel catalog + the user's currently enabled channel keys. */
export async function getDialerChannels(): Promise<DialerChannelPrefs> {
  const res = await apiFetch<{ data: DialerChannelPrefs }>(`/dialer/channels`);
  return res.data;
}

/** Save the user's preferred channels (ordered keys). Returns the resolved set. */
export async function updateDialerChannels(
  channels: string[],
): Promise<DialerChannelPrefs> {
  const res = await apiFetch<{ data: DialerChannelPrefs }>(`/dialer/channels`, {
    method: "PUT",
    body: JSON.stringify({ channels }),
  });
  return res.data;
}
export type DialerChannel = {
  type: string;
  label: string;
  value: string;
  url: string;
  scheme_url?: string;
  source: string;
};

export type DialerSocial = {
  platform: string;
  label: string;
  url: string;
  source?: string;
};

export type DialerLocation = {
  label: string;
  address: string;
  lat: number | null;
  lng: number | null;
  maps_url: string;
  source?: string;
};

export type DialerProfileContact = {
  id: number;
  display_name: string;
  organization: string | null;
  job_title: string | null;
  photo_url: string | null;
  phones: { label: string | null; value: string; value_e164: string | null }[];
  emails: { label: string | null; value: string }[];
};

export type DialerProfileBiolink = {
  user_id: number;
  name: string;
  handle: string | null;
  url: string | null;
  link_id: number | null;
  avatar_url: string | null;
};

export type DialerManualProfile = {
  channels: DialerChannel[];
  socials: DialerSocial[];
  location: DialerLocation | null;
};

export type DialerProfile = {
  number: string;
  contact: DialerProfileContact | null;
  biolink: DialerProfileBiolink | null;
  socials: DialerSocial[];
  locations: DialerLocation[];
  channels: DialerChannel[];
  manual: DialerManualProfile;
  vcard_url: string;
};

/**
 * Rich Identity Profile for a number / contact: matched Sayzio user,
 * auto-pulled socials / locations / reachable channels from their biolink,
 * the owner's manual additions, and a shareable Export-vCard URL.
 */
export async function dialerProfile(params: {
  number?: string;
  contact?: number;
}): Promise<DialerProfile> {
  const qs = new URLSearchParams();
  if (params.number) qs.set("number", params.number);
  if (params.contact != null) qs.set("contact", String(params.contact));
  const res = await apiFetch<{ data: DialerProfile }>(
    `/dialer/profile?${qs.toString()}`,
  );
  return res.data;
}
