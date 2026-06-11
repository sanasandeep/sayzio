import { apiFetch } from "@/lib/api";

export type Domain = {
  id: number;
  domain: string;
  type: string;
  is_verified: boolean;
  is_active: boolean;
  is_primary: boolean;
  is_global: boolean;
  verification_token: string | null;
  cname_target: string | null;
  verified_at: string | null;
};

export type AvailableDomains = {
  items: Domain[];
  primary_domain_id: number | null;
  default_host: string;
  can_manage: boolean;
};

export async function listDomains(): Promise<Domain[]> {
  const res = await apiFetch<{ data: { items: Domain[] } }>("/domains");
  return res.data.items;
}

// Domains the caller can attach a link to (own + admin-global), plus the
// admin-chosen primary global domain and the platform env default host.
// Drives the domain pre-selection in the create/edit link flows.
export async function listAvailableDomains(): Promise<AvailableDomains> {
  const res = await apiFetch<{ data: AvailableDomains }>("/domains/available");
  return res.data;
}

// Admin-only: mark a global domain as the platform-wide primary.
export async function makePrimaryDomain(id: number): Promise<Domain> {
  const res = await apiFetch<{ data: { domain: Domain } }>(
    `/domains/${id}/primary`,
    { method: "POST" },
  );
  return res.data.domain;
}

export async function addDomain(domain: string, type: "custom" | "subdomain" = "custom"): Promise<Domain> {
  const res = await apiFetch<{ data: { domain: Domain } }>("/domains", {
    method: "POST",
    body: JSON.stringify({ domain, type }),
  });
  return res.data.domain;
}

export async function deleteDomain(id: number): Promise<void> {
  await apiFetch(`/domains/${id}`, { method: "DELETE" });
}
