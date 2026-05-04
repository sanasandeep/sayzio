import { apiFetch } from "@/lib/api";

export type SessionInfo = {
  id: string;
  kind: "token" | "web";
  client_kind: string;
  device_label: string;
  platform: string | null;
  user_agent: string | null;
  ip: string | null;
  country: string | null;
  first_seen_at: string | null;
  last_active_at: string | null;
  is_current: boolean;
};

export async function listSessions(): Promise<SessionInfo[]> {
  const r = await apiFetch<{ data: { items: SessionInfo[] } }>(
    "/auth/sessions",
  );
  return r.data.items;
}

export async function revokeSession(id: string): Promise<void> {
  await apiFetch(`/auth/sessions/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

export async function revokeOtherSessions(): Promise<{
  revoked_tokens: number;
  revoked_sessions: number;
}> {
  const r = await apiFetch<{
    data: { revoked_tokens: number; revoked_sessions: number };
  }>("/auth/sessions/others", { method: "DELETE" });
  return r.data;
}
