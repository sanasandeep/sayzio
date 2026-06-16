import { apiFetch } from "@/lib/api";

// Mirrors the public reviews REST surface documented in
// artifacts/1inme/docs/api.md (Reviews section):
//   GET  /reviews/{alias}          → unified feed + summary
//   GET  /reviews/{alias}/summary  → rating summary only
//   POST /reviews/{alias}          → no-login submission
// All responses use the unified {data}/{error} envelope.

export type ReviewMedia = {
  type: "image" | "audio" | "video" | string;
  url: string;
  meta?: Record<string, unknown> | null;
};

export type ReviewAnswer = {
  prompt: string;
  answer: string;
};

export type Review = {
  id: string;
  source: string;
  source_label: string;
  author_name: string;
  author_avatar: string | null;
  rating: number | null;
  body: string | null;
  reply: string | null;
  source_url: string | null;
  pinned: boolean;
  created_at: string | null;
  media: ReviewMedia[];
  answers: ReviewAnswer[];
};

export type ReviewSummary = {
  average: number;
  total: number;
  native: number;
  external: number;
  rated: number;
  breakdown: Record<string, number>;
  percent: Record<string, number>;
};

export type ReviewFeed = {
  reviews: Review[];
  summary: ReviewSummary;
};

export type ReviewSource = "native" | "external" | "both";
export type ReviewSort = "recent" | "rating";

function buildQuery(params: {
  source?: ReviewSource;
  sort?: ReviewSort;
  limit?: number;
}): string {
  const q = new URLSearchParams();
  if (params.source) q.set("source", params.source);
  if (params.sort) q.set("sort", params.sort);
  if (params.limit) q.set("limit", String(params.limit));
  const qs = q.toString();
  return qs ? `?${qs}` : "";
}

export async function getReviews(
  alias: string,
  params: { source?: ReviewSource; sort?: ReviewSort; limit?: number } = {},
): Promise<ReviewFeed> {
  const res = await apiFetch<{ data: ReviewFeed }>(
    `/reviews/${encodeURIComponent(alias)}${buildQuery(params)}`,
  );
  return res.data;
}

export async function getReviewsSummary(
  alias: string,
  params: { source?: ReviewSource } = {},
): Promise<ReviewSummary> {
  const res = await apiFetch<{ data: ReviewSummary }>(
    `/reviews/${encodeURIComponent(alias)}/summary${buildQuery(params)}`,
  );
  return res.data;
}

export type ReviewSubmitResult = {
  status: "approved" | "pending" | "hidden" | string;
  pending: boolean;
  message: string;
};

export async function submitReview(
  alias: string,
  body: {
    author_name?: string;
    author_email?: string;
    rating?: number;
    body?: string;
    answers?: Record<string, string>;
  },
): Promise<ReviewSubmitResult> {
  // `website` is the server-side honeypot — a real client always leaves it
  // empty so legitimate submissions are never flagged as spam.
  const payload: Record<string, unknown> = { website: "" };
  if (body.author_name) payload.author_name = body.author_name;
  if (body.author_email) payload.author_email = body.author_email;
  if (typeof body.rating === "number") payload.rating = body.rating;
  if (body.body) payload.body = body.body;
  if (body.answers && Object.keys(body.answers).length > 0) {
    payload.answers = body.answers;
  }
  const res = await apiFetch<{ data: ReviewSubmitResult }>(
    `/reviews/${encodeURIComponent(alias)}`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

// ── Owner moderation (auth:sanctum) ─────────────────────────────────
// Mirrors the bearer-token surface added to ReviewApiController:
//   GET    /me/reviews                  → own reviews (all statuses)
//   POST   /me/reviews/{id}/approve     → publish
//   POST   /me/reviews/{id}/hide        → hide
//   POST   /me/reviews/{id}/pin         → toggle pinned
//   POST   /me/reviews/{id}/reply       → set / clear owner reply
//   DELETE /me/reviews/{id}             → delete

export type ReviewStatus =
  | "pending"
  | "approved"
  | "hidden"
  | "unverified"
  | string;

export type OwnerReview = {
  id: string;
  status: ReviewStatus;
  is_spam: boolean;
  spam_reason: string | null;
  pinned: boolean;
  author_name: string | null;
  author_email: string | null;
  author_avatar: string | null;
  rating: number | null;
  body: string | null;
  reply: string | null;
  replied_at: string | null;
  verified: boolean;
  created_at: string | null;
  link: { id: string; title: string | null; alias: string } | null;
  media: ReviewMedia[];
  answers: ReviewAnswer[];
};

export type OwnerReviewCounts = {
  pending: number;
  approved: number;
  hidden: number;
  unverified: number;
};

export type OwnerReviewsPage = {
  reviews: OwnerReview[];
  counts: OwnerReviewCounts;
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
};

export async function getMyReviews(
  params: { status?: ReviewStatus; per_page?: number } = {},
): Promise<OwnerReviewsPage> {
  const q = new URLSearchParams();
  if (params.status) q.set("status", params.status);
  if (params.per_page) q.set("per_page", String(params.per_page));
  const qs = q.toString() ? `?${q}` : "";
  const res = await apiFetch<{ data: OwnerReviewsPage }>(`/me/reviews${qs}`);
  return res.data;
}

export async function approveReview(id: string): Promise<OwnerReview> {
  const res = await apiFetch<{ data: OwnerReview }>(
    `/me/reviews/${encodeURIComponent(id)}/approve`,
    { method: "POST" },
  );
  return res.data;
}

export async function hideReview(id: string): Promise<OwnerReview> {
  const res = await apiFetch<{ data: OwnerReview }>(
    `/me/reviews/${encodeURIComponent(id)}/hide`,
    { method: "POST" },
  );
  return res.data;
}

export async function pinReview(id: string): Promise<OwnerReview> {
  const res = await apiFetch<{ data: OwnerReview }>(
    `/me/reviews/${encodeURIComponent(id)}/pin`,
    { method: "POST" },
  );
  return res.data;
}

export async function replyReview(
  id: string,
  reply: string,
): Promise<OwnerReview> {
  const res = await apiFetch<{ data: OwnerReview }>(
    `/me/reviews/${encodeURIComponent(id)}/reply`,
    { method: "POST", body: JSON.stringify({ reply }) },
  );
  return res.data;
}

export async function deleteReview(id: string): Promise<void> {
  await apiFetch(`/me/reviews/${encodeURIComponent(id)}`, { method: "DELETE" });
}
