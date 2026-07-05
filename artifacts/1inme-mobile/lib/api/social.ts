import { apiFetch } from "@/lib/api";

export type SocialSyncSummary = {
  biolink_count: number;
  on_caller_id: boolean;
  in_public_search: boolean;
  in_dialer_finder: boolean;
  label: string;
};

export type SocialConnection = {
  id: number;
  platform: string;
  platform_label: string;
  handle: string;
  display_name: string | null;
  profile_url: string | null;
  avatar_url: string | null;
  follower_count: number;
  last_refreshed_at: string | null;
  last_refresh_status: string | null;
  last_refresh_error: string | null;
  is_searchable: boolean;
  sync_summary: SocialSyncSummary;
};

export type PlatformOption = { platform: string; label: string };

export async function listConnections(): Promise<{
  items: SocialConnection[];
  platforms: PlatformOption[];
}> {
  const res = await apiFetch<{
    data: { items: SocialConnection[]; platforms: PlatformOption[] };
  }>(`/social/connections`);
  return res.data;
}

export async function connectAccount(payload: {
  platform: string;
  handle: string;
  access_token?: string;
}): Promise<SocialConnection> {
  const res = await apiFetch<{ data: { connection: SocialConnection } }>(
    `/social/connections`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.connection;
}

export async function refreshConnection(id: number): Promise<SocialConnection> {
  const res = await apiFetch<{ data: { connection: SocialConnection } }>(
    `/social/connections/${id}/refresh`,
    { method: "POST" },
  );
  return res.data.connection;
}

export async function disconnect(id: number): Promise<void> {
  await apiFetch(`/social/connections/${id}`, { method: "DELETE" });
}

export async function updateSearchable(
  id: number,
  isSearchable: boolean,
): Promise<SocialConnection> {
  const res = await apiFetch<{ data: { connection: SocialConnection } }>(
    `/social/connections/${id}/searchable`,
    { method: "PATCH", body: JSON.stringify({ is_searchable: isSearchable }) },
  );
  return res.data.connection;
}

export type SocialProof = {
  id: number;
  uuid: string;
  name: string;
  type: string;
  type_label: string;
  is_active: boolean;
  impressions: number;
  clicks: number;
  conversions: number;
  created_at: string | null;
};

export type ProofType = { type: string; label: string };

export async function listProofs(): Promise<{
  items: SocialProof[];
  types: ProofType[];
}> {
  const res = await apiFetch<{ data: { items: SocialProof[]; types: ProofType[] } }>(
    `/social/proofs`,
  );
  return res.data;
}

export async function createProof(payload: {
  name: string;
  type: string;
}): Promise<SocialProof> {
  const res = await apiFetch<{ data: { proof: SocialProof } }>(
    `/social/proofs`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.proof;
}

export async function updateProof(
  id: number,
  payload: { name?: string; is_active?: boolean },
): Promise<SocialProof> {
  const res = await apiFetch<{ data: { proof: SocialProof } }>(
    `/social/proofs/${id}`,
    { method: "PATCH", body: JSON.stringify(payload) },
  );
  return res.data.proof;
}

export async function deleteProof(id: number): Promise<void> {
  await apiFetch(`/social/proofs/${id}`, { method: "DELETE" });
}
