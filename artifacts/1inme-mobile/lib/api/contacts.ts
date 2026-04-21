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
