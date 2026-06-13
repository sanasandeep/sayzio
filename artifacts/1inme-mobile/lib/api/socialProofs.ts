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

export async function listSocialProofs(): Promise<{ items: SocialProof[] }> {
  const res = await apiFetch<{ data: { items: SocialProof[] } }>(
    `/social/proofs`,
  );
  return { items: res.data.items };
}
