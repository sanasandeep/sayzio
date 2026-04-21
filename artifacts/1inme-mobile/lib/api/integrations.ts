import { apiFetch } from "@/lib/api";

export type Integration = {
  id: number;
  kind: string;
  provider: string | null;
  name: string | null;
  is_active: boolean;
  is_default: boolean;
  meta: Record<string, unknown> | null;
  created_at: string | null;
};

export async function listIntegrations(): Promise<Integration[]> {
  const res = await apiFetch<{ data: { items: Integration[] } }>("/integrations");
  return res.data.items;
}

export async function deleteIntegration(id: number): Promise<void> {
  await apiFetch(`/integrations/${id}`, { method: "DELETE" });
}
