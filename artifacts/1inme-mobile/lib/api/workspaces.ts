import { apiFetch } from "@/lib/api";

export type Workspace = {
  id: number;
  name: string;
  slug: string | null;
  is_personal: boolean;
  owner_user_id: number;
  color?: string | null;
  icon?: string | null;
  created_at: string | null;
};

export type WorkspaceMember = {
  id: number;
  user_id: number;
  role: string;
  name: string | null;
  email: string | null;
  avatar: string | null;
  created_at: string | null;
};

export async function listWorkspaces(): Promise<Workspace[]> {
  const res = await apiFetch<{ data: { items: Workspace[] } }>("/workspaces");
  return res.data.items;
}

export async function switchWorkspace(id: number): Promise<void> {
  await apiFetch<unknown>(`/workspaces/${id}/activate`, { method: "POST" });
}

export async function listWorkspaceMembers(id: number): Promise<WorkspaceMember[]> {
  const res = await apiFetch<{ data: { items: WorkspaceMember[] } }>(
    `/workspaces/${id}/members`,
  );
  return res.data.items;
}
