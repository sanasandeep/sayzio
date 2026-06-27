import { apiFetch } from "@/lib/api";

// Owner-scoped roadmap triage (auth:sanctum). Mirrors the bearer-token
// surface added to App\Modules\Api\Controllers\RoadmapTriageController,
// which is itself parity for the web /user/links/{link}/roadmap dashboard:
//   GET    /links/{link}/roadmap                  → items + counts
//   PATCH  /links/{link}/roadmap/items/{item}     → update status/fields
//   DELETE /links/{link}/roadmap/items/{item}     → delete an idea
//   POST   /links/{link}/roadmap/items/{item}/merge → merge into another
// All responses use the unified {data}/{error} envelope.

export type RoadmapStatus =
  | "pending"
  | "ideas"
  | "planned"
  | "in_progress"
  | "shipped"
  | "rejected"
  | "merged"
  | string;

export type RoadmapItem = {
  id: number;
  status: RoadmapStatus;
  status_label: string;
  title: string;
  description: string | null;
  votes_count: number;
  is_blocked: boolean;
  block_id: number | null;
  submitter_name: string | null;
  submitter_email: string | null;
  task_card_id: number | null;
  merged_into_id: number | null;
  shipped_at: string | null;
  created_at: string | null;
};

export type RoadmapMergeTarget = {
  id: number;
  title: string;
  status: RoadmapStatus;
  votes_count: number;
};

export type RoadmapBlock = {
  id: number;
  title: string;
};

export type RoadmapTriage = {
  status: RoadmapStatus;
  statuses: Record<string, string>;
  public_statuses: string[];
  block_id: number;
  blocks: RoadmapBlock[];
  counts: Record<string, number>;
  merge_targets: RoadmapMergeTarget[];
  items: RoadmapItem[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export async function getRoadmapTriage(
  linkId: number,
  params: { status?: RoadmapStatus; block_id?: number; page?: number } = {},
): Promise<RoadmapTriage> {
  const q = new URLSearchParams();
  if (params.status) q.set("status", params.status);
  if (params.block_id) q.set("block_id", String(params.block_id));
  if (params.page) q.set("page", String(params.page));
  const qs = q.toString() ? `?${q}` : "";
  const res = await apiFetch<{ data: RoadmapTriage }>(
    `/links/${linkId}/roadmap${qs}`,
  );
  return res.data;
}

export async function updateRoadmapItem(
  linkId: number,
  itemId: number,
  patch: {
    status?: RoadmapStatus;
    title?: string;
    description?: string;
    is_blocked?: boolean;
    sync_to_kanban?: boolean;
  },
): Promise<{ item: RoadmapItem; message: string }> {
  const res = await apiFetch<{ data: { item: RoadmapItem; message: string } }>(
    `/links/${linkId}/roadmap/items/${itemId}`,
    { method: "PATCH", body: JSON.stringify(patch) },
  );
  return res.data;
}

export async function deleteRoadmapItem(
  linkId: number,
  itemId: number,
): Promise<void> {
  await apiFetch(`/links/${linkId}/roadmap/items/${itemId}`, {
    method: "DELETE",
  });
}

export async function mergeRoadmapItem(
  linkId: number,
  itemId: number,
  intoId: number,
): Promise<{ item: RoadmapItem; target: RoadmapItem; message: string }> {
  const res = await apiFetch<{
    data: { item: RoadmapItem; target: RoadmapItem; message: string };
  }>(`/links/${linkId}/roadmap/items/${itemId}/merge`, {
    method: "POST",
    body: JSON.stringify({ into_id: intoId }),
  });
  return res.data;
}
