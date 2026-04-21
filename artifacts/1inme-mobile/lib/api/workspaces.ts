import { apiFetch } from "@/lib/api";

export type Workspace = {
  id: number;
  name: string;
  slug: string | null;
  is_personal: boolean;
  owner_user_id: number;
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

export async function listWorkspaceMembers(id: number): Promise<WorkspaceMember[]> {
  const res = await apiFetch<{ data: { items: WorkspaceMember[] } }>(
    `/workspaces/${id}/members`,
  );
  return res.data.items;
}
