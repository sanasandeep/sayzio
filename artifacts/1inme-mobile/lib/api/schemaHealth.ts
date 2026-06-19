import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin dashboard schema-health banner.
// Read the live drift report (expected tables/columns the DB is missing
// despite their migration being recorded as ran) and run the one-click
// column repair. Both endpoints are super-admin only on the server
// (`settings.manage`), returning a 403 otherwise.

// One drifted table from the report. When `table_missing` is true the whole
// table is absent (only `migrate --force` can recreate it); otherwise
// `columns` lists the specific missing columns the repair can add in place.
export type SchemaMissing = {
  table: string;
  table_missing: boolean;
  columns: string[];
};

export type SchemaHealthStatus = {
  available: boolean;
  scanned: number;
  missing: SchemaMissing[];
  missing_count: number;
  healthy: boolean;
  error?: string;
};

// repair() returns the schema-level outcome: `added` maps each table to the
// columns that were (re)created, while `unrepairable` lists whole-missing
// tables that still need `php artisan migrate --force`. `still_missing` is the
// re-checked live count after the run, so `healthy` reflects reality.
export type SchemaRepairResult = {
  added: Record<string, string[]>;
  unrepairable: string[];
  added_tables_count: number;
  added_columns_count: number;
  unrepairable_count: number;
  still_missing: number;
  healthy: boolean;
};

export async function getSchemaHealth(): Promise<SchemaHealthStatus> {
  const res = await apiFetch<{ data: SchemaHealthStatus }>("/admin/schema-health");
  return res.data;
}

export async function repairSchema(): Promise<SchemaRepairResult> {
  const res = await apiFetch<{ data: SchemaRepairResult }>(
    "/admin/schema-health/repair",
    { method: "POST" },
  );
  return res.data;
}

// Bearer-token parity for the web admin "Schema repair audit log" page.
// Read-only: lists past one-click schema repair runs — who ran each repair,
// when, and which columns/tables it touched. Gated server-side behind the
// same `settings.manage` permission the web page uses, returning a 403
// otherwise. Only schema metadata is returned, never row data.

export type SchemaRepairAudit = {
  id: number;
  actor_label: string;
  actor_email: string | null;
  actor_guard: string | null;
  // Per-table list of columns the repair added/backfilled.
  added: Record<string, string[]>;
  // Whole-missing tables the repair could not recreate in place.
  unrepairable: string[];
  added_columns_count: number;
  added_tables_count: number;
  unrepairable_count: number;
  changed_schema: boolean;
  ip: string | null;
  created_at: string | null;
};

export type SchemaRepairAuditMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type SchemaRepairAuditsPage = {
  audits: SchemaRepairAudit[];
  meta: SchemaRepairAuditMeta;
};

export async function getSchemaRepairAudits(
  page = 1,
  perPage = 30,
): Promise<SchemaRepairAuditsPage> {
  const res = await apiFetch<{ data: SchemaRepairAuditsPage }>(
    `/admin/schema-health/audits?page=${page}&per_page=${perPage}`,
  );
  return res.data;
}
