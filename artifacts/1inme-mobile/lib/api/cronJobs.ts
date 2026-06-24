import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Cron Jobs" reference page. Read-only:
// returns the single master crontab line an operator must add to the server,
// plus the derived list of every scheduled command (command, plain-English
// frequency, raw cron expression, purpose and next run) — all derived live from
// Laravel's registered schedule. Gated server-side behind the same
// `settings.manage` permission the web page uses, returning a 403 otherwise.

// One scheduled job. `command` is the artisan command + args (or a label for a
// scheduled closure when `is_callback` is true); `manual_command` is the full
// `php artisan ...` line an operator can run by hand (null for callbacks).
export type CronJob = {
  is_callback: boolean;
  command: string;
  manual_command: string | null;
  expression: string;
  frequency: string;
  purpose: string;
  next_run: string | null;
  without_overlapping: boolean;
  on_one_server: boolean;
  running_now: boolean;
};

export type CronJobsReference = {
  master_cron_line: string;
  app_path: string;
  jobs: CronJob[];
};

export async function getCronJobs(): Promise<CronJobsReference> {
  const res = await apiFetch<{ data: CronJobsReference }>("/admin/cron-jobs");
  return res.data;
}
