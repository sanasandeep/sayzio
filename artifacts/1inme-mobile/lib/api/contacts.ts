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
  emails: ContactEmail[];
  phones: ContactPhone[];
  photo_url: string | null;
};

/**
 * Lists the signed-in user's saved contacts/leads. Used by the invoice and
 * receipt creation flow to pick a Contact recipient. `q` filters server-side
 * across name, organization, email and phone.
 */
export async function listContacts(q?: string): Promise<Contact[]> {
  const query = q && q.trim() ? `?q=${encodeURIComponent(q.trim())}&per_page=100` : "?per_page=100";
  const res = await apiFetch<{ data: { items: Contact[] } }>(
    `/contacts${query}`,
  );
  return res.data.items;
}

/** Best-effort primary email for a contact (falls back to the first on file). */
export function contactPrimaryEmail(c: Contact): string | null {
  return (c.emails.find((e) => e.is_primary) ?? c.emails[0])?.value ?? null;
}
