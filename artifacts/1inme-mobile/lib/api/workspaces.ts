import { apiFetch } from "@/lib/api";

export type Workspace = {
  id: number;
  name: string;
  slug: string | null;
  is_personal: boolean;
  owner_user_id: number;
  is_owner?: boolean;
  color?: string | null;
  icon?: string | null;
  created_at: string | null;
};

/**
 * Maps a workspace appearance icon key (as returned by the API, one of the
 * server's ICON_CHOICES keys) to the closest Feather icon name available in
 * the mobile app. Falls back to a personal/team default.
 */
export function workspaceFeatherIcon(
  ws: Pick<Workspace, "icon" | "is_personal">,
): "user" | "users" | "briefcase" | "home" | "zap" | "star" | "heart" | "globe" | "shopping-bag" | "layers" | "feather" {
  switch (ws.icon) {
    case "user":
      return "user";
    case "users":
      return "users";
    case "briefcase":
      return "briefcase";
    case "building":
      return "home";
    case "rocket":
    case "bolt":
      return "zap";
    case "star":
      return "star";
    case "heart":
      return "heart";
    case "globe":
      return "globe";
    case "store":
      return "shopping-bag";
    case "layer-group":
      return "layers";
    case "palette":
      return "feather";
    default:
      return ws.is_personal ? "user" : "users";
  }
}

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
