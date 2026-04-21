import { apiFetch } from "@/lib/api";

export type VaultClient = {
  id: number;
  workspace_id: number;
  name: string;
  company: string | null;
  website: string | null;
  primary_email: string | null;
  primary_phone: string | null;
  visibility: string;
  tags: string[];
};

export type VaultCredential = {
  id: number;
  workspace_id: number;
  label: string;
  url: string | null;
  username: string | null;
  visibility: string;
  tags: string[];
};

export async function listVaultClients(): Promise<VaultClient[]> {
  const res = await apiFetch<{ data: { items: VaultClient[] } }>("/vault/clients");
  return res.data.items;
}

export async function listVaultCredentials(): Promise<VaultCredential[]> {
  const res = await apiFetch<{ data: { items: VaultCredential[] } }>(
    "/vault/credentials",
  );
  return res.data.items;
}
