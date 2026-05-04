import { apiFetch } from "@/lib/api";

export type ClientPortal = {
  id: number;
  name: string;
  brand_name: string | null;
  brand_color: string | null;
  is_enabled: boolean;
  client_name: string | null;
  shares_count: number;
  links_count: number;
  actions_count: number;
  last_seen_at: string | null;
  created_at: string | null;
};

export type ClientPortalShare = {
  id: number;
  shareable_type: string;
  shareable_id: number | null;
  label: string | null;
  type_label: string;
  position: number;
};

export type ClientPortalLink = {
  id: number;
  email: string;
  status: string;
  expires_at: string | null;
  sent_at: string | null;
  last_used_at: string | null;
};

export type ClientPortalDetail = ClientPortal & {
  welcome_message: string | null;
  brand_logo_url: string | null;
  shares: ClientPortalShare[];
  links: ClientPortalLink[];
};

export async function listClientPortals(): Promise<ClientPortal[]> {
  const res = await apiFetch<{ data: { items: ClientPortal[] } }>(
    "/client-portals",
  );
  return res.data.items;
}

export async function getClientPortal(id: number): Promise<ClientPortalDetail> {
  const res = await apiFetch<{ data: { portal: ClientPortalDetail } }>(
    `/client-portals/${id}`,
  );
  return res.data.portal;
}

export async function createClientPortal(input: {
  name: string;
  brand_name?: string | null;
  brand_color?: string | null;
  welcome_message?: string | null;
}): Promise<ClientPortal> {
  const res = await apiFetch<{ data: { portal: ClientPortal } }>(
    "/client-portals",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.portal;
}

export async function updateClientPortal(
  id: number,
  input: Partial<{
    name: string;
    brand_name: string | null;
    brand_color: string | null;
    welcome_message: string | null;
    is_enabled: boolean;
  }>,
): Promise<ClientPortal> {
  const res = await apiFetch<{ data: { portal: ClientPortal } }>(
    `/client-portals/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.portal;
}

export async function deleteClientPortal(id: number): Promise<void> {
  await apiFetch<unknown>(`/client-portals/${id}`, { method: "DELETE" });
}

export async function sendClientPortalLink(
  id: number,
  input: { email: string; expires_in?: number },
): Promise<{ id: number; email: string; url: string; expires_at: string | null }> {
  const res = await apiFetch<{
    data: {
      link: { id: number; email: string; url: string; expires_at: string | null };
    };
  }>(`/client-portals/${id}/links`, {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data.link;
}
