import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Analytics Storage" panel. Read the
// growth of the high-volume analytics tables (link_clicks / page_sessions),
// the retention window the nightly `stats:prune-history` sweep applies, the
// last sweep outcome, and set/clear the hard physical cap + growth-alert
// threshold. Both endpoints are gated server-side behind `settings.manage`,
// returning a 403 otherwise.

// One analytics table's estimated size (planner stats, never count(*)).
// `over_threshold` is true when it has crossed the growth-alert threshold.
export type StatsStorageTable = {
  table: string;
  estimated_rows: number;
  over_threshold: boolean;
};

// Normalized summary of the last recorded prune sweep, or null when none yet.
export type StatsStorageLastRun = {
  ran_at: string | null;
  action: string | null;
  reason: string | null;
  dry_run: boolean;
  effective_days: number | null;
  tables: Record<
    string,
    { estimated_rows?: number; rows_deleted?: number; dropped_partitions?: unknown[] }
  >;
};

export type StatsStorageStatus = {
  available: boolean;
  // Largest stats_retention_days across active plans; -1 = keep forever.
  plan_retention: number;
  // Operator hard physical cap (days), or null when unset.
  hard_max_days: number | null;
  // Effective growth-alert threshold (rows).
  alert_threshold: number;
  // Built-in default threshold (shown as a hint when none is set).
  default_threshold: number;
  // Effective prune window (days), or null when nothing will be pruned.
  effective_days: number | null;
  reason: string;
  // True when a table is over the threshold AND nothing will prune it.
  growth_unbounded: boolean;
  tables: StatsStorageTable[];
  last_run: StatsStorageLastRun | null;
};

// Set/clear payload. A `clear_*` flag wins over a value; omitting both leaves
// the stored setting untouched.
export type StatsStorageUpdate = {
  hard_max_days?: number;
  clear_hard_max_days?: boolean;
  alert_row_threshold?: number;
  clear_alert_row_threshold?: boolean;
};

export async function getStatsStorage(): Promise<StatsStorageStatus> {
  const res = await apiFetch<{ data: StatsStorageStatus }>("/admin/stats-storage");
  return res.data;
}

export async function updateStatsStorage(
  update: StatsStorageUpdate,
): Promise<StatsStorageStatus> {
  const res = await apiFetch<{ data: StatsStorageStatus }>("/admin/stats-storage", {
    method: "PUT",
    body: JSON.stringify(update),
  });
  return res.data;
}
