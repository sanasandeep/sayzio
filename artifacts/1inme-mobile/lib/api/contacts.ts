import { apiFetch } from "@/lib/api";

export type ContactEmail = {
  id: number;
  label: string | null;
  value: string;
  is_primary: boolean;
};

export type ContactPhone = {
  id: number;
  label: string | null;
  value: string;
  value_e164: string | null;
  is_primary: boolean;
};

export type Contact = {
  id: number;
  display_name: string;
  given_name: string | null;
  family_name: string | null;
  organization: string | null;
  job_title: string | null;
  notes: string | null;
  tags: string[];
  emails: ContactEmail[];
  phones: ContactPhone[];
  photo_url: string | null;
  /** Owner-set grouping: brand (business) vs personal relationship. */
  contact_type?: "personal" | "brand" | null;
  follow_up_at: string | null;
  follow_up_note: string | null;
  follow_up_tz: string | null;
  created_at: string | null;
  /** Linked capture records (orders, forms, RSVPs…) — list endpoint only. */
  activity_count?: number;
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

export type ManualProfilePayload = {
  channels?: ManualChannel[];
  socials?: ManualSocial[];
  location?: ManualLocation | null;
};

export type FollowUpsResponse = {
  overdue: Contact[];
  upcoming: Contact[];
};

export type DuplicateGroup = {
  ids: number[];
  reason: string;
  contacts: Contact[];
};

/**
 * Lists the signed-in user's saved contacts/leads. `q` filters server-side
 * across name, organization, email and phone. `tag` filters by a single tag.
 */
export async function listContacts(opts?: {
  q?: string;
  tag?: string;
  per_page?: number;
  contact_type?: "personal" | "brand";
  /** "activity" surfaces the most-engaged contacts first (linked-activity count). */
  sort?: "name" | "activity";
}): Promise<Contact[]> {
  const params = new URLSearchParams();
  if (opts?.q?.trim()) params.set("q", opts.q.trim());
  if (opts?.tag?.trim()) params.set("tag", opts.tag.trim());
  if (opts?.contact_type) params.set("contact_type", opts.contact_type);
  if (opts?.sort === "activity") params.set("sort", "activity");
  params.set("per_page", String(opts?.per_page ?? 100));
  const res = await apiFetch<{ data: { items: Contact[] } }>(
    `/contacts?${params.toString()}`,
  );
  return res.data.items;
}

/** Fetch a single contact by ID. */
export async function getContact(id: number): Promise<Contact> {
  // The show endpoint wraps its payload as { data: { contact } } (unlike
  // notes/tags PATCHes which return the contact directly under data).
  const res = await apiFetch<{ data: { contact: Contact } }>(`/contacts/${id}`);
  return res.data.contact;
}

export type ContactActivityItem = {
  title: string;
  subtitle: string | null;
  date: string | null;
  url: string | null;
  /** Record identifiers (link_id, alias, form_id, thread_id, invoice_id…) for native deep-links. */
  refs?: {
    link_id?: number;
    alias?: string;
    form_id?: number;
    thread_id?: number;
    invoice_id?: number;
    /** Per-record ids so destination screens can highlight the exact record. */
    order_id?: number;
    booking_id?: number;
    rsvp_id?: number;
    ticket_id?: number;
    user_id?: number;
  };
};

/**
 * In-app destination for one activity item, keyed by its group. Returns null
 * when no native screen exists for the record (item stays static).
 */
export function contactActivityHref(
  groupKey: string,
  item: ContactActivityItem,
): string | null {
  const refs = item.refs ?? {};
  switch (groupKey) {
    case "subscriptions":
      return "/subscribers";
    case "form_submissions":
      return refs.form_id ? `/forms/${refs.form_id}` : null;
    case "restaurant_orders":
      return refs.link_id
        ? `/links/${refs.link_id}/restaurant-orders${refs.order_id ? `?highlight=${refs.order_id}` : ""}`
        : null;
    case "store_orders":
      return refs.link_id
        ? `/links/${refs.link_id}/store-orders${refs.order_id ? `?highlight=${refs.order_id}` : ""}`
        : null;
    case "bookings":
      return refs.link_id
        ? `/links/${refs.link_id}/service-booking-dashboard${refs.booking_id ? `?highlight=${refs.booking_id}` : ""}`
        : null;
    case "rsvps":
    case "event_tickets":
      return refs.alias
        ? `/events/people/${encodeURIComponent(refs.alias)}${refs.user_id ? `?highlight_user=${refs.user_id}` : ""}`
        : null;
    case "product_orders":
      return "/orders";
    case "reviews":
      return "/reviews/manage";
    case "conversations":
      return refs.thread_id ? `/inbox/${refs.thread_id}` : null;
    case "invoices":
      return refs.invoice_id ? `/invoices/${refs.invoice_id}` : null;
    default:
      return null;
  }
}

export type ContactActivityGroup = {
  key: string;
  label: string;
  icon: string;
  count: number;
  items: ContactActivityItem[];
};

export type ContactFollowerBridge = {
  is_follower: boolean;
  followed_at?: string | null;
};

export type ContactActivity = {
  groups: ContactActivityGroup[];
  follower_bridge: ContactFollowerBridge;
  is_auto_captured: boolean;
};

/** Unified cross-feature activity timeline for a contact. */
export async function getContactActivity(
  id: number,
): Promise<ContactActivity> {
  const res = await apiFetch<{ data: ContactActivity }>(
    `/contacts/${id}/activity`,
  );
  return res.data;
}

/** Return all distinct tags used across the authenticated user's contacts. */
export async function listContactTags(): Promise<string[]> {
  const res = await apiFetch<{ data: { tags: string[] } }>(`/contacts/tags`);
  return res.data.tags ?? [];
}

/** Update the notes field for a contact. */
export async function updateContactNotes(
  id: number,
  notes: string | null,
): Promise<Contact> {
  const res = await apiFetch<{ data: Contact }>(`/contacts/${id}/notes`, {
    method: "PATCH",
    body: JSON.stringify({ notes }),
  });
  return res.data;
}

/** Replace the full tags list for a contact. */
export async function updateContactTags(
  id: number,
  tags: string[],
): Promise<Contact> {
  const res = await apiFetch<{ data: Contact }>(`/contacts/${id}/tags`, {
    method: "PATCH",
    body: JSON.stringify({ tags }),
  });
  return res.data;
}

/** Fetch upcoming and overdue follow-ups. */
export async function listFollowUps(): Promise<FollowUpsResponse> {
  const res = await apiFetch<{
    data: FollowUpsResponse;
  }>(`/contacts/follow-ups`);
  return res.data;
}

/** Set (or reschedule) a follow-up reminder. */
export async function setFollowUp(
  id: number,
  follow_up_at: string,
  follow_up_note?: string | null,
  follow_up_tz?: string | null,
): Promise<Contact> {
  // Follow-up endpoints wrap the payload as { data: { contact } }.
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/${id}/follow-up`,
    {
      method: "POST",
      body: JSON.stringify({ follow_up_at, follow_up_note, follow_up_tz }),
    },
  );
  return res.data.contact;
}

/** Clear a scheduled follow-up for a contact. */
export async function clearFollowUp(id: number): Promise<Contact> {
  // Follow-up endpoints wrap the payload as { data: { contact } }.
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/${id}/follow-up`,
    {
      method: "DELETE",
    },
  );
  return res.data.contact;
}

/** Best-effort primary email for a contact (falls back to the first on file). */
export function contactPrimaryEmail(c: Contact): string | null {
  return (c.emails.find((e) => e.is_primary) ?? c.emails[0])?.value ?? null;
}

/** Best-effort primary phone for a contact (falls back to the first on file). */
export function contactPrimaryPhone(c: Contact): string | null {
  return (c.phones.find((p) => p.is_primary) ?? c.phones[0])?.value ?? null;
}

/** Display initials (up to 2 chars) for the avatar fallback. */
export function contactInitials(c: Contact): string {
  const name = c.display_name || `${c.given_name ?? ""} ${c.family_name ?? ""}`.trim();
  const parts = name.split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return (parts[0]?.[0] ?? "?").toUpperCase();
}

/**
 * Fetch all duplicate groups for the signed-in user.
 * Returns `{ groups, count }` where groups[].contacts is already transformed.
 */
export async function fetchDuplicates(): Promise<{ groups: DuplicateGroup[]; count: number }> {
  const res = await apiFetch<{ data: { groups: DuplicateGroup[]; count: number } }>(
    "/contacts/duplicates",
  );
  return res.data;
}

/**
 * Lightweight duplicate-group count for the contacts banner/badge.
 * Cheaper than fetchDuplicates(): the server skips hydrating contact rows.
 */
export async function fetchDuplicateCount(): Promise<number> {
  const res = await apiFetch<{ data: { count: number } }>(
    "/contacts/duplicates/count",
  );
  return res.data.count ?? 0;
}

/**
 * Dismiss pairs of contacts so they never appear as duplicates again.
 * `pairs` is an array of "idA:idB" strings (any order; server canonicalises).
 */
export async function dismissDuplicates(pairs: string[]): Promise<{ dismissed: number }> {
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
    data: { groups_merged: number; contacts_removed: number; groups_failed: number };
  }>("/contacts/duplicates/merge-all", { method: "POST" });
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
  /** True when the stored Google OAuth grant expired/was revoked. */
  needs_reauth: boolean;
  needs_reauth_at: string | null;
  /** Friendly "reconnect on the web" copy from the server when needs_reauth. */
  reconnect_message: string | null;
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

/**
 * Google Contacts two-way sync helpers (ported from the standalone dialer).
 * `status` returns null when no Google account is connected.
 */
export const googleContacts = {
  status: async (): Promise<GoogleContactsAccount | null> => {
    const res = await apiFetch<{ data: { account: GoogleContactsAccount | null } }>(
      `/contacts/google/status`,
    );
    return res.data.account;
  },
  /**
   * Begin the OAuth (re)connect flow. Returns the Google authorize URL to
   * open in an in-app browser; the server bounces back to the
   * `sayzio://google-contacts-oauth` deep link when done.
   */
  connect: async (): Promise<{ authorize_url: string }> => {
    const res = await apiFetch<{ data: { authorize_url: string } }>(
      `/contacts/google/connect`,
      { method: "POST" },
    );
    return res.data;
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

/** Minimal contact shape accepted by the bulk-import endpoint. */
export type ContactImportPayload = {
  display_name?: string | null;
  given_name?: string | null;
  family_name?: string | null;
  organization?: string | null;
  emails?: { value: string; label?: string | null }[];
  phones?: { value: string; label?: string | null }[];
};

/**
 * Push a batch of contacts (e.g. from the device address book) to the API.
 * The server dedupes/updates in place and reports how many freshly created
 * contacts now look like duplicates of existing ones.
 */
export async function bulkImportContacts(contacts: ContactImportPayload[]): Promise<{
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

export type MergeCandidate = {
  id: number;
  display_name: string;
  organization: string | null;
  photo_url: string | null;
  is_auto_captured: boolean;
  email: string | null;
  phone: string | null;
};

/**
 * Search the owner's other contacts as targets for the "Merge into…"
 * picker (same candidate rules as the web contact page: same owner,
 * never the contact itself). Up to 20 results.
 */
export async function listMergeCandidates(
  id: number,
  q?: string,
): Promise<MergeCandidate[]> {
  const params = new URLSearchParams();
  if (q?.trim()) params.set("q", q.trim());
  const qs = params.toString();
  const res = await apiFetch<{ data: { candidates: MergeCandidate[] } }>(
    `/contacts/${id}/merge-candidates${qs ? `?${qs}` : ""}`,
  );
  return res.data.candidates;
}

// ── Merge undo (30-day window, web parity) ────────────────────────
export type UndoableMerge = {
  id: number;
  primary_contact_id: number;
  /** Display name of the merged-away contact (from the audit snapshot). */
  source_name: string;
  merged_at: string | null;
};

/**
 * List recent merges that can still be undone. Pass `contactId` to narrow
 * to merges whose surviving primary is that contact (detail screen).
 */
export async function listUndoableMerges(contactId?: number): Promise<{
  merges: UndoableMerge[];
  undo_window_days: number;
}> {
  const qs = contactId ? `?contact_id=${contactId}` : "";
  const res = await apiFetch<{
    data: { merges: UndoableMerge[]; undo_window_days: number };
  }>(`/contacts/merges/undoable${qs}`);
  return res.data;
}

/**
 * Undo a recorded merge: the merged-away contact is recreated from the
 * audit snapshot with its phones/emails/activity. Returns the restored
 * contact.
 */
export async function undoContactMerge(auditId: number): Promise<{ contact: Contact }> {
  const res = await apiFetch<{ data: { contact: Contact } }>(
    `/contacts/merges/${auditId}/undo`,
    { method: "POST" },
  );
  return res.data;
}

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
