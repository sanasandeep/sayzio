import { apiFetch } from "@/lib/api";

export type Domain = {
  id: number;
  domain: string;
  type: string;
  is_verified: boolean;
  is_active: boolean;
  verification_token: string | null;
  cname_target: string | null;
  verified_at: string | null;
};

export async function listDomains(): Promise<Domain[]> {
  const res = await apiFetch<{ data: { items: Domain[] } }>("/domains");
  return res.data.items;
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
