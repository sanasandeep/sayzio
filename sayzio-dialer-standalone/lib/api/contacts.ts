import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type ContactEmail = {
  id?: number;
  label: string | null;
  value: string;
  is_primary?: boolean;
};

export type ContactPhone = {
  id?: number;
  label: string | null;
  value: string;
  value_e164?: string | null;
  is_primary?: boolean;
};

export type ManualChannel = { type: string; label: string; value: string };
export type ManualSocial = { platform: string; label: string; url: string };
export type ManualLocation = {
  label: string;
  address: string;
  lat: number | null;
  lng: number | null;
};

export type ManualProfile = {
  channels: ManualChannel[];
  socials: ManualSocial[];
  location: ManualLocation | null;
};

export type Contact = {
  id: number;
  display_name: string | null;
  given_name: string | null;
  family_name: string | null;
  organization: string | null;
  job_title: string | null;
  notes: string | null;
  emails: ContactEmail[];
  phones: ContactPhone[];
  photo_url: string | null;
  /** Owner-set grouping: brand (business) vs personal relationship. */
  contact_type?: "personal" | "brand" | null;
  manual_profile?: ManualProfile;
  follow_up_at: string | null;
  follow_up_note: string | null;
  follow_up_tz: string | null;
  created_at: string | null;
};

export type ContactsUsage = {
  count: number;
  cap: number | null;
  unlimited: boolean;
  percent: number;
  near_cap: boolean;
  at_cap: boolean;
};

export async function listContacts(
  q?: string,
  contactType?: "personal" | "brand",
): Promise<{
  items: Contact[];
  total: number;
  usage: ContactsUsage;
}> {
  const p = new URLSearchParams();
  if (q) p.set("q", q);
  if (contactType) p.set("contact_type", contactType);
  const qs = p.toString() ? `?${p.toString()}` : "";
  const res = await apiFetch<{
    data: { items: Contact[]; meta: { total: number }; usage: ContactsUsage };
  }>(`/contacts${qs}`);
  return { items: res.data.items, total: res.data.meta.total, usage: res.data.usage };
}

/**
 * Consolidated follow-ups list: contacts with a scheduled `follow_up_at`,
 * soonest-first, split into overdue (due already) and upcoming. Lets the
 * user see everything to act on without opening each contact.
 */
export async function listFollowUps(): Promise<{
  overdue: Contact[];
  upcoming: Contact[];
}> {
  const res = await apiFetch<{
    data: { overdue: Contact[]; upcoming: Contact[] };
  }>(`/contacts/follow-ups`);
  return { overdue: res.data.overdue, upcoming: res.data.upcoming };
}

/**
 * Lightweight count of overdue follow-ups (contacts whose `follow_up_at` is
 * due already). Powers the badge on the Contacts tab's bell button so users
 * notice reminders without opening the full list.
 */
export async function getOverdueFollowUpsCount(): Promise<number> {
  const res = await apiFetch<{ data: { overdue: number } }>(
    `/contacts/follow-ups/count`,
  );
  return res.data.overdue;
}

export async function getContact(id: number): Promise<Contact> {
  const res = await apiFetch<{ data: { contact: Contact } }>(`/contacts/${id}`);
  return res.data.contact;
}

export type ContactPayload = {
  display_name?: string | null;
  given_name?: string | null;
  family_name?: string | null;
  organization?: string | null;
  job_title?: string | null;
  notes?: string | null;
  emails?: ContactEmail[];
  phones?: ContactPhone[];
  contact_type?: "personal" | "brand" | null;
};

export async function createContact(p: ContactPayload): Promise<Contact> {
  const res = await apiFetch<{ data: { contact: Contact } }>(`/contacts`, {
    method: "POST",
    body: JSON.stringify(p),
  });
  return res.data.contact;
}

export async function updateContact(
  id: number,
  p: ContactPayload,
): Promise<Contact> {
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/${id}`,
    { method: "PATCH", body: JSON.stringify(p) },
  );
  return res.data.contact;
}

export async function deleteContact(id: number): Promise<void> {
  await apiFetch(`/contacts/${id}`, { method: "DELETE" });
}

/**
 * Set (or reschedule) a follow-up reminder for this contact/lead
 * (Task #3524). `followUpAt` should be an ISO-8601 datetime; the server
 * stores it as UTC and delivers in-app + email + push when it comes due.
 * `timezone` (Task #3526) optionally records which timezone the reminder was
 * picked in so it is displayed in that same zone instead of the account
 * default; it never changes the absolute instant when `followUpAt` already
 * carries an offset (the presets send a UTC `Z` instant).
 */
export async function setContactFollowUp(
  id: number,
  followUpAt: string,
  note?: string | null,
  timezone?: string | null,
  // Set when restoring a follow-up cleared by accident: allows re-setting a
  // moment already in the past (e.g. an overdue reminder), which the server
  // otherwise rejects for fresh sets/snoozes.
  restore?: boolean,
): Promise<Contact> {
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/${id}/follow-up`,
    {
      method: "POST",
      body: JSON.stringify({
        follow_up_at: followUpAt,
        follow_up_note: note ?? null,
        follow_up_tz: timezone ?? null,
        restore: restore ?? false,
      }),
    },
  );
  return res.data.contact;
}

export async function clearContactFollowUp(id: number): Promise<Contact> {
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/${id}/follow-up`,
    { method: "DELETE" },
  );
  return res.data.contact;
}

// ── Structured call history ───────────────────────────────────────
export type ContactCall = {
  id: number;
  number: string;
  direction: "incoming" | "outgoing" | "missed";
  occurred_at: string | null;
};

/** Structured call history for a contact, newest first (max 200). */
export async function listContactCalls(id: number): Promise<ContactCall[]> {
  const res = await apiFetch<{ data: { calls: ContactCall[] } }>(
    `/contacts/${id}/calls`,
  );
  return res.data.calls;
}

/**
 * Batch-log identified incoming calls against a contact. Idempotent
 * server-side (unique on contact+number+occurred_at), so re-posting the
 * same native queue events after a partial drain is safe.
 */
export async function logContactCalls(
  id: number,
  calls: { number: string; occurred_at: string; direction?: string }[],
): Promise<number> {
  const res = await apiFetch<{ data: { logged: number } }>(
    `/contacts/${id}/calls`,
    { method: "POST", body: JSON.stringify({ calls }) },
  );
  return res.data.logged;
}

export type ManualProfilePayload = {
  channels?: ManualChannel[];
  socials?: ManualSocial[];
  location?: ManualLocation | null;
};

/**
 * Persist the owner's manual Dialer additions (channels / socials /
 * location) for a contact, kept distinct from auto-pulled biolink data.
 */
export async function updateContactManualProfile(
  id: number,
  p: ManualProfilePayload,
): Promise<ManualProfile> {
  const res = await apiFetch<{ data: { manual_profile: ManualProfile } }>(
    `/contacts/${id}/manual-profile`,
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data.manual_profile;
}

export async function bulkImportContacts(contacts: ContactPayload[]): Promise<{
  created: number;
  updated: number;
  skipped: number;
  duplicates_found: number;
}> {
  const res = await apiFetch<{
    data: {
      created: number;
      updated: number;
      skipped: number;
      duplicates_found?: number;
    };
  }>(`/contacts/bulk`, {
    method: "POST",
    body: JSON.stringify({ contacts }),
  });
  return { duplicates_found: 0, ...res.data };
}

/** Primary (or first) email value for display. */
export function contactPrimaryEmail(c: Contact): string | null {
  const e = c.emails.find((x) => x.is_primary) ?? c.emails[0];
  return e?.value ?? null;
}

/** Primary (or first) phone value for display. */
export function contactPrimaryPhone(c: Contact): string | null {
  const p = c.phones.find((x) => x.is_primary) ?? c.phones[0];
  return p?.value ?? null;
}

// ── Duplicate detection & review ──────────────────────────────────
export type DuplicateGroup = {
  ids: number[];
  reason: string;
  contacts: Contact[];
};

/**
 * Fetch all duplicate groups for the signed-in user.
 * Returns `{ groups, count }` where groups[].contacts is already transformed.
 */
export async function fetchDuplicates(): Promise<{
  groups: DuplicateGroup[];
  count: number;
}> {
  const res = await apiFetch<{
    data: { groups: DuplicateGroup[]; count: number };
  }>("/contacts/duplicates");
  return res.data;
}

/**
 * Dismiss pairs of contacts so they never appear as duplicates again.
 * `pairs` is an array of "idA:idB" strings (any order; server canonicalises).
 */
export async function dismissDuplicates(
  pairs: string[],
): Promise<{ dismissed: number }> {
  const res = await apiFetch<{ data: { dismissed: number } }>(
    "/contacts/duplicates/dismiss",
    { method: "POST", body: JSON.stringify({ pairs }) },
  );
  return res.data;
}

/**
 * Bulk-merge every duplicate group in one call. The first contact in each
 * group becomes the primary; the rest are merged into it.
 */
export async function mergeAllDuplicates(): Promise<{
  groups_merged: number;
  contacts_removed: number;
  groups_failed: number;
}> {
  const res = await apiFetch<{
    data: {
      groups_merged: number;
      contacts_removed: number;
      groups_failed: number;
    };
  }>("/contacts/duplicates/merge-all", { method: "POST" });
  return res.data;
}

/**
 * Merge `loserIds` contacts into the primary contact `primaryId`.
 * Returns the updated primary contact and the count of merged records.
 */
export async function mergeContacts(
  primaryId: number,
  loserIds: number[],
): Promise<{ contact: Contact; merged: number }> {
  const res = await apiFetch<{ data: { contact: Contact; merged: number } }>(
    `/contacts/${primaryId}/merge-duplicate`,
    { method: "POST", body: JSON.stringify({ loser_ids: loserIds }) },
  );
  return res.data;
}

// ── SMS Link-in-Bio ───────────────────────────────────────────────
/**
 * Text the contact's matched Sayzio biolink to one of their saved phone
 * numbers via a configured SMS gateway (Twilio/Plivo). The destination is
 * locked server-side to the contact's own phones.
 */
export async function smsBiolinkToContact(
  id: number,
  opts: { to?: string; config_id?: number } = {},
): Promise<{ sent: boolean; to: string; provider: string }> {
  const res = await apiFetch<{
    data: { sent: boolean; to: string; provider: string };
  }>(`/contacts/${id}/sms-biolink`, {
    method: "POST",
    body: JSON.stringify(opts),
  });
  return res.data;
}

// ── Google Contacts sync ──────────────────────────────────────────
export type GoogleContactsAccount = {
  id: number;
  account_email: string | null;
  pull_enabled: boolean;
  push_enabled: boolean;
  last_sync_status: string | null;
  last_sync_error: string | null;
  last_synced_at: string | null;
};

export type GoogleSyncStats = {
  created: number;
  updated: number;
  deleted: number;
  pushed: number;
  errors: number;
  skipped_capped: number;
};

export type GoogleSyncStatus = "synced" | "throttled" | "in_progress";

export type GoogleSyncResult = {
  status: GoogleSyncStatus;
  retry_after?: number | null;
  stats: GoogleSyncStats | null;
  account: GoogleContactsAccount;
};

export const googleContacts = {
  status: async (): Promise<GoogleContactsAccount | null> => {
    const res = await apiFetch<{ data: { account: GoogleContactsAccount | null } }>(
      `/contacts/google/status`,
    );
    return res.data.account;
  },
  sync: async (): Promise<GoogleSyncResult> => {
    const res = await apiFetch<{ data: GoogleSyncResult }>(
      `/contacts/google/sync`,
      { method: "POST" },
    );
    return res.data;
  },
  update: async (
    prefs: { pull_enabled?: boolean; push_enabled?: boolean },
  ): Promise<GoogleContactsAccount> => {
    const res = await apiFetch<{ data: { account: GoogleContactsAccount } }>(
      `/contacts/google`,
      { method: "PATCH", body: JSON.stringify(prefs) },
    );
    return res.data.account;
  },
  disconnect: async (): Promise<void> => {
    await apiFetch(`/contacts/google`, { method: "DELETE" });
  },
};

// ── Bulk import preview / commit (area 4) ─────────────────────────
export type ImportRow = {
  display_name?: string | null;
  given_name?: string | null;
  family_name?: string | null;
  organization?: string | null;
  phones?: { label: string | null; value: string }[];
  emails?: { label: string | null; value: string }[];
  source_line?: number;
  warnings?: string[];
};

export type ImportPreview = {
  token: string;
  original_name: string;
  rows: ImportRow[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    offset: number;
  };
  stats: {
    total: number;
    warnings: number;
    remaining: number | null;
    over_cap: number;
  };
};

export type ImportRowPayload = {
  display_name?: string | null;
  given_name?: string | null;
  family_name?: string | null;
  organization?: string | null;
  phones?: { label?: string | null; value: string }[];
  emails?: { label?: string | null; value: string }[];
};

export type ContactImportStatus = {
  id: number;
  status: string;
  total: number;
  processed: number;
  created: number;
  failed: { row: number; name: string; reason: string }[];
  failed_count: number;
  skipped_cap: number;
  percent: number;
  in_progress: boolean;
};

export type ImportConfirmResult = {
  queued: boolean;
  import: ContactImportStatus;
  results?: {
    total: number;
    created: number;
    failed: { row: number; name: string; reason: string }[];
    skippedCap: number;
  };
};

export const contactImport = {
  /**
   * Stage 1: upload a CSV/vCard file (local uri from expo-document-picker),
   * parse it server-side and get back a preview token + first page of rows.
   */
  parse: async (file: {
    uri: string;
    name?: string;
    mimeType?: string;
  }): Promise<ImportPreview> => {
    const url = `${getBaseUrl()}/api/v1/contacts/import/parse`;
    const token = await getToken();
    const fd = new FormData();
    fd.append("file", {
      // eslint-disable-next-line @typescript-eslint/ban-ts-comment
      // @ts-ignore – RN-specific FormData entry.
      uri: file.uri,
      name: file.name || "contacts.csv",
      type: file.mimeType || "text/csv",
    } as any);
    const headers: Record<string, string> = {
      Accept: "application/json",
      "User-Agent": MOBILE_USER_AGENT,
      "X-1INME-Client": MOBILE_USER_AGENT,
    };
    if (token) headers.Authorization = `Bearer ${token}`;
    const res = await fetch(url, { method: "POST", body: fd as any, headers });
    const text = await res.text();
    const body = text ? JSON.parse(text) : null;
    if (!res.ok) {
      const nested = body && typeof body.error === "object" ? body.error : null;
      throw {
        status: res.status,
        message:
          nested?.message ||
          (body && body.message) ||
          `Upload failed (${res.status})`,
        code: nested?.code,
      };
    }
    return (body as { data: ImportPreview }).data;
  },
  preview: async (token: string, page = 1): Promise<ImportPreview> => {
    const res = await apiFetch<{ data: ImportPreview }>(
      `/contacts/import/preview/${token}?page=${page}`,
    );
    return res.data;
  },
  updateRow: async (
    token: string,
    index: number,
    payload: ImportRowPayload,
    page = 1,
  ): Promise<ImportPreview> => {
    const res = await apiFetch<{ data: ImportPreview }>(
      `/contacts/import/preview/${token}/rows/${index}?page=${page}`,
      { method: "PATCH", body: JSON.stringify(payload) },
    );
    return res.data;
  },
  skipRow: async (token: string, index: number, page = 1): Promise<ImportPreview> => {
    const res = await apiFetch<{ data: ImportPreview }>(
      `/contacts/import/preview/${token}/rows/${index}?page=${page}`,
      { method: "DELETE" },
    );
    return res.data;
  },
  cancel: async (token: string): Promise<void> => {
    await apiFetch(`/contacts/import/preview/${token}`, { method: "DELETE" });
  },
  confirm: async (token: string): Promise<ImportConfirmResult> => {
    const res = await apiFetch<{ data: ImportConfirmResult }>(
      `/contacts/import/preview/${token}/confirm`,
      { method: "POST" },
    );
    return res.data;
  },
  status: async (id: number): Promise<ContactImportStatus> => {
    const res = await apiFetch<{ data: { import: ContactImportStatus } }>(
      `/contacts/import/status/${id}`,
    );
    return res.data.import;
  },
};
