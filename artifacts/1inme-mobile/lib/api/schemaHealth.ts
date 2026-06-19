import { apiFetch } from "@/lib/api";

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
