import { apiFetch } from "@/lib/api";

export type VerificationRequest = {
  id: number;
  link_id: number | null;
  category: string | null;
  business_name: string | null;
  display_name: string | null;
  status: string;
  reviewed_at: string | null;
  created_at: string | null;
};

export async function listVerifications(): Promise<VerificationRequest[]> {
  const res = await apiFetch<{ data: { items: VerificationRequest[] } }>(
    "/verifications",
  );
  return res.data.items;
}

export type VerificationCategory = "individual" | "business" | "org" | "creator";

export async function submitVerification(p: {
  link_id: number;
  category: VerificationCategory;
  business_name?: string | null;
  display_name?: string | null;
  purpose?: string | null;
}): Promise<VerificationRequest> {
  const res = await apiFetch<{ data: { verification_request: VerificationRequest } }>(
    "/verifications",
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data.verification_request;
}
