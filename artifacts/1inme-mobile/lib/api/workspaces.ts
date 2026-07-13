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
 * Icon keys the native editor offers, kept in lockstep with the server's
 * `Workspace::ICON_CHOICES` keys (validated on PATCH /workspaces/{id}). Each
 * one is rendered via {@link workspaceFeatherIcon}, so any key added here must
 * also map to a Feather glyph in that switch.
 */
export const WORKSPACE_ICON_CHOICES = [
  "user",
  "users",
  "briefcase",
  "building",
  "rocket",
  "star",
  "heart",
  "bolt",
  "palette",
  "globe",
  "store",
  "layer-group",
] as const;

/**
 * Colour swatches the native editor offers, kept in lockstep with the server's
 * `Workspace::COLOR_CHOICES` (validated on PATCH /workspaces/{id}).
 */
export const WORKSPACE_COLOR_CHOICES = [
  "#3d6bff",
  "#10b981",
  "#8b5cf6",
  "#ef4444",
  "#f59e0b",
  "#ec4899",
  "#06b6d4",
  "#64748b",
] as const;

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

/**
 * Owner-only rename + restyle. Mirrors the web workspace-settings save; the
 * server re-serializes and returns the updated workspace so callers can refresh
 * the switcher immediately.
 */
export async function updateWorkspace(
  id: number,
  input: { name: string; icon?: string | null; color?: string | null },
): Promise<Workspace> {
  const res = await apiFetch<{ data: { item: Workspace } }>(`/workspaces/${id}`, {
    method: "PATCH",
    body: JSON.stringify({
      name: input.name,
      icon: input.icon ?? null,
      color: input.color ?? null,
    }),
  });
  return res.data.item;
}

export async function listWorkspaceMembers(id: number): Promise<WorkspaceMember[]> {
  const res = await apiFetch<{ data: { items: WorkspaceMember[] } }>(
    `/workspaces/${id}/members`,
  );
  return res.data.items;
}
