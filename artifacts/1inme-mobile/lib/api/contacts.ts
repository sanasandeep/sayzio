import { apiFetch } from "@/lib/api";

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

export async function listContacts(q?: string): Promise<{
  items: Contact[];
  total: number;
}> {
  const qs = q ? `?q=${encodeURIComponent(q)}` : "";
  const res = await apiFetch<{
    data: { items: Contact[]; meta: { total: number } };
  }>(`/contacts${qs}`);
  return { items: res.data.items, total: res.data.meta.total };
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
