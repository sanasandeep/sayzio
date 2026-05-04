import { apiFetch } from "@/lib/api";

export type TeamMember = {
  id: number;
  user_id: number;
  role: string;
  name: string | null;
  email: string | null;
  avatar: string | null;
  created_at: string | null;
};

export type TeamInvite = {
  id: number;
  email: string;
  role: string;
  expires_at: string | null;
  created_at: string | null;
};

export type TeamPayload = {
  workspace: { id: number; name: string; is_personal: boolean };
  members: TeamMember[];
  pending_invites: TeamInvite[];
  used_seats: number;
  max_seats: number;
  can_manage: boolean;
};

export type TeamRole = "admin" | "editor" | "replier" | "analyst" | "viewer";

export async function getTeam(): Promise<TeamPayload> {
  const res = await apiFetch<{ data: TeamPayload }>("/team");
  return res.data;
}

export async function inviteTeammate(input: {
  email: string;
  role: TeamRole;
}): Promise<TeamInvite> {
  const res = await apiFetch<{ data: { invite: TeamInvite } }>("/team/invite", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data.invite;
}

export async function revokeTeamInvite(inviteId: number): Promise<void> {
  await apiFetch<unknown>(`/team/invites/${inviteId}`, { method: "DELETE" });
}

export async function removeTeamMember(memberId: number): Promise<void> {
  await apiFetch<unknown>(`/team/members/${memberId}`, { method: "DELETE" });
}
