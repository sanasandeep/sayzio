import { apiFetch } from "@/lib/api";

// ---------------------------------------------------------------------------
// Profile-level verification (account tick) — Task #5439
// ---------------------------------------------------------------------------

export type TickType = {
  id: number;
  slug: string;
  name: string;
  color: string;
  icon: string;
  admin_assigned_only: boolean;
};

export type ProfileVerificationStatus =
  | "unverified"
  | "pending"
  | "verified"
  | "pending_reverification";

export type ProfileVerificationRequest = {
  id: number;
  tick_type_id: number | null;
  tick_type: TickType | null;
  official_name: string;
  purpose: string;
  status: string;
  kind: "new" | "reverification";
  admin_notes: string | null;
  reviewed_at: string | null;
  created_at: string | null;
};

export type ProfileVerificationStatusResponse = {
  status: ProfileVerificationStatus;
  tick_type: TickType | null;
  verified_name: string | null;
  verified_avatar: string | null;
  verified_at: string | null;
  requests: ProfileVerificationRequest[];
  tick_types: TickType[];
};

export async function getProfileVerificationStatus(): Promise<ProfileVerificationStatusResponse> {
  const res = await apiFetch<{ data: ProfileVerificationStatusResponse }>(
    "/profile-verification",
  );
  return res.data;
}

export async function submitProfileVerification(p: {
  tick_type_id: number;
  official_name: string;
  purpose: string;
}): Promise<ProfileVerificationRequest> {
  const res = await apiFetch<{ data: { request: ProfileVerificationRequest } }>(
    "/profile-verification",
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data.request;
}

export async function reVerifyProfile(p: {
  new_name?: string | null;
}): Promise<{ ok: boolean }> {
  const res = await apiFetch<{ data: { ok: boolean } }>(
    "/profile-verification/reverify",
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data;
}

// ---------------------------------------------------------------------------
// Legacy per-link verification (kept for backward compat)
// ---------------------------------------------------------------------------

export type LegacyVerificationRequest = {
  id: number;
  link_id: number | null;
  category: string | null;
  business_name: string | null;
  display_name: string | null;
  status: string;
  reviewed_at: string | null;
  created_at: string | null;
};

export async function listLegacyVerifications(): Promise<LegacyVerificationRequest[]> {
  const res = await apiFetch<{ data: { items: LegacyVerificationRequest[] } }>(
    "/verifications",
  );
  return res.data.items;
}

/** @deprecated use getProfileVerificationStatus() */
export async function listVerifications(): Promise<LegacyVerificationRequest[]> {
  return listLegacyVerifications();
}
