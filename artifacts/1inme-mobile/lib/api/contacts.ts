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
  manual_profile?: ManualProfile;
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

export async function listContacts(q?: string): Promise<{
  items: Contact[];
  total: number;
  usage: ContactsUsage;
}> {
  const qs = q ? `?q=${encodeURIComponent(q)}` : "";
  const res = await apiFetch<{
    data: { items: Contact[]; meta: { total: number }; usage: ContactsUsage };
  }>(`/contacts${qs}`);
  return { items: res.data.items, total: res.data.meta.total, usage: res.data.usage };
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
}> {
  const res = await apiFetch<{
    data: { created: number; updated: number; skipped: number };
  }>(`/contacts/bulk`, {
    method: "POST",
    body: JSON.stringify({ contacts }),
  });
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

export const googleContacts = {
  status: async (): Promise<GoogleContactsAccount | null> => {
    const res = await apiFetch<{ data: { account: GoogleContactsAccount | null } }>(
      `/contacts/google/status`,
    );
    return res.data.account;
  },
  sync: async (): Promise<{ stats: GoogleSyncStats; account: GoogleContactsAccount }> => {
    const res = await apiFetch<{
      data: { stats: GoogleSyncStats; account: GoogleContactsAccount };
    }>(`/contacts/google/sync`, { method: "POST" });
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
