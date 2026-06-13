import { apiFetch } from "@/lib/api";

// Social proof ("Buzz") campaigns the user owns. Mirrors the web block
// editor's special-panel $userBuzz list — used by the editor's "Buzz"
// picker to drop a `social_proof` block bound to a chosen campaign.
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

// A Buzz campaign type the user can pick when creating one on the spot.
export type ProofType = { type: string; label: string };

export async function listSocialProofs(): Promise<{
  items: SocialProof[];
  types: ProofType[];
}> {
  const res = await apiFetch<{
    data: { items: SocialProof[]; types: ProofType[] };
  }>(`/social/proofs`);
  return { items: res.data.items, types: res.data.types ?? [] };
}

export async function createSocialProof(payload: {
  name: string;
  type: string;
}): Promise<SocialProof> {
  const res = await apiFetch<{ data: { proof: SocialProof } }>(
    `/social/proofs`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.proof;
}
