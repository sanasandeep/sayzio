import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Scheduled Jobs" control panel. Both
// surfaces share the same engine server-side (ScheduledJobRegistry +
// CronJobsInspector), so grouping, pause/resume state, run-now and run history
// never drift between web and mobile. Gated behind the `settings.manage`
// admin permission; a regular token gets a 403.

export type ScheduledJob = {
  key: string;
  group: string;
  protected: boolean;
  paused: boolean;
  is_callback: boolean;
  command: string;
  manual_command: string | null;
  expression: string;
  frequency: string;
  purpose: string;
  next_run: string | null;
  last_run: string | null;
  last_run_ok: boolean | null;
  last_run_error: string | null;
  last_runtime: string | null;
  last_exit_code: number | null;
  last_run_source: string | null;
  overdue: boolean;
  without_overlapping: boolean;
  on_one_server: boolean;
  running_now: boolean;
};

export type ScheduledJobGroup = {
  slug: string;
  label: string;
  jobs: ScheduledJob[];
};

export type ScheduledJobsOverview = {
  master_cron_line: string;
  scheduler: {
    state: "ok" | "stale" | "never" | string;
    last_tick: string | null;
    overdue_count: number;
  };
  groups: ScheduledJobGroup[];
};

export type ScheduledJobRun = {
  id: number;
  job_key: string;
  source: string | null;
  status: string;
  started_at: string | null;
  finished_at: string | null;
  runtime: string | null;
  exit_code: number | null;
  error: string | null;
};

export async function getScheduledJobs(): Promise<ScheduledJobsOverview> {
  const res = await apiFetch<{ data: ScheduledJobsOverview }>(
    "/admin/scheduled-jobs",
  );
  return res.data;
}

export async function pauseScheduledJob(
  key: string,
): Promise<{ job_key: string; paused: boolean }> {
  const res = await apiFetch<{ data: { job_key: string; paused: boolean } }>(
    `/admin/scheduled-jobs/${encodeURIComponent(key)}/pause`,
    { method: "POST" },
  );
  return res.data;
}

export async function resumeScheduledJob(
  key: string,
): Promise<{ job_key: string; paused: boolean }> {
  const res = await apiFetch<{ data: { job_key: string; paused: boolean } }>(
    `/admin/scheduled-jobs/${encodeURIComponent(key)}/resume`,
    { method: "POST" },
  );
  return res.data;
}

export async function runScheduledJobNow(
  key: string,
): Promise<{ job_key: string; started: boolean; message: string }> {
  const res = await apiFetch<{
    data: { job_key: string; started: boolean; message: string };
  }>(`/admin/scheduled-jobs/${encodeURIComponent(key)}/run`, {
    method: "POST",
  });
  return res.data;
}

export async function getScheduledJobRuns(
  key: string,
): Promise<{ job_key: string; runs: ScheduledJobRun[] }> {
  const res = await apiFetch<{
    data: { job_key: string; runs: ScheduledJobRun[] };
  }>(`/admin/scheduled-jobs/${encodeURIComponent(key)}/runs`);
  return res.data;
}
