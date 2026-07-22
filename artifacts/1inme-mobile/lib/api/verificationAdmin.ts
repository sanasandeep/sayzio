import { apiFetch } from "@/lib/api";

// ---------------------------------------------------------------------------
// Reviewer (moderation) API for profile-level verification requests —
// Task #5600, mobile parity for the web /user/profile-verification-admin
// screens. Every endpoint is gated server-side by the
// `user.verifications.review` web-pool permission; a 403 means the
// signed-in user simply isn't a reviewer (surfaces should fail closed).
// ---------------------------------------------------------------------------

export type ReviewQueue = "new" | "reverification";

export type ReviewTickType = {
  id: number;
  name: string;
  color: string;
  is_active: boolean;
  admin_assigned_only: boolean;
  sort_order: number;
};

export type ReviewRequest = {
  id: number;
  kind: ReviewQueue;
  status: "pending" | "approved" | "rejected" | string;
  official_name: string;
  purpose: string;
  created_at: string | null;
  reviewed_at: string | null;
  admin_notes: string | null;
  tick_type: { id: number; name: string; color: string } | null;
  user: {
    id: number;
    name: string | null;
    email: string | null;
    handle: string | null;
  } | null;
  // Detail-only fields (present when fetched via show()).
  logo_path?: string | null;
  proof_files?: string[];
  new_name?: string | null;
  new_avatar?: string | null;
  updates?: { body?: string; message?: string; created_at?: string | null }[];
  reviewer?: { id: number; name: string | null } | null;
};

export type ReviewListResponse = {
  requests: ReviewRequest[];
  meta: { current_page: number; last_page: number; total: number };
  pending_new_count: number;
  pending_reverification_count: number;
};

export async function listVerificationReviews(params: {
  queue?: ReviewQueue;
  status?: string;
  page?: number;
  per_page?: number;
} = {}): Promise<ReviewListResponse> {
  const qs = new URLSearchParams();
  if (params.queue) qs.set("queue", params.queue);
  if (params.status) qs.set("status", params.status);
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));
  const res = await apiFetch<{ data: ReviewListResponse }>(
    `/admin/profile-verification${qs.toString() ? `?${qs}` : ""}`,
  );
  return res.data;
}

export async function getVerificationReview(
  id: number,
): Promise<ReviewRequest> {
  const res = await apiFetch<{ data: { request: ReviewRequest } }>(
    `/admin/profile-verification/${id}`,
  );
  return res.data.request;
}

export async function approveVerificationReview(
  id: number,
  p: { admin_notes?: string | null; tick_type_id?: number | null } = {},
): Promise<ReviewRequest> {
  const res = await apiFetch<{ data: { request: ReviewRequest } }>(
    `/admin/profile-verification/${id}/approve`,
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data.request;
}

export async function rejectVerificationReview(
  id: number,
  p: { admin_notes: string },
): Promise<ReviewRequest> {
  const res = await apiFetch<{ data: { request: ReviewRequest } }>(
    `/admin/profile-verification/${id}/reject`,
    { method: "POST", body: JSON.stringify(p) },
  );
  return res.data.request;
}
