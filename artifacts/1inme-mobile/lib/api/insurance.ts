import { apiFetch } from "@/lib/api";

export type InsuranceState = "primary" | "failover" | "down";

export type InsuranceDashboardItem = {
  id: number;
  title: string | null;
  alias: string;
  long_url: string | null;
  short_url: string;
  state: InsuranceState;
  active_url: string | null;
  last_checked_at: string | null;
  last_failover_at: string | null;
  uptime_ratio: number | null;
  uptime_samples: number;
};

export type InsuranceDashboardMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type InsuranceDashboard = {
  items: InsuranceDashboardItem[];
  meta: InsuranceDashboardMeta;
};

export type InsuranceBackup = {
  id: number;
  position: number;
  url: string;
  label: string | null;
  last_status: string | null;
  last_http_code: number | null;
  last_checked_at: string | null;
};

export type InsuranceCheck = {
  status: string;
  http_code: number | null;
  latency_ms: number | null;
  target_url: string | null;
  checked_at: string | null;
};

export type InsuranceSettings = {
  link: {
    id: number;
    title: string | null;
    alias: string;
    long_url: string | null;
    short_url: string;
  };
  settings: {
    insurance_enabled: boolean;
    insurance_cadence_minutes: number;
    insurance_failure_threshold: number;
    insurance_recovery_threshold: number;
    insurance_auto_restore: boolean;
    insurance_fallback_message: string | null;
  };
  state: {
    insurance_state: InsuranceState;
    insurance_active_url: string | null;
    last_checked_at: string | null;
    last_failover_at: string | null;
  };
  backups: InsuranceBackup[];
  recent_checks: InsuranceCheck[];
  options: {
    cadences: number[];
    max_backups: number;
  };
};

export type InsuranceUpdateInput = {
  insurance_enabled: boolean;
  insurance_cadence_minutes: number;
  insurance_failure_threshold: number;
  insurance_recovery_threshold: number;
  insurance_auto_restore: boolean;
  insurance_fallback_message?: string | null;
  backups: { url: string; label?: string | null }[];
};

export async function getInsuranceDashboard(
  page = 1,
): Promise<InsuranceDashboard> {
  const res = await apiFetch<{ data: InsuranceDashboard }>(
    `/insurance?page=${page}`,
  );
  return res.data;
}

export async function getLinkInsurance(
  linkId: number,
): Promise<InsuranceSettings> {
  const res = await apiFetch<{ data: InsuranceSettings }>(
    `/links/${linkId}/insurance`,
  );
  return res.data;
}

export async function updateLinkInsurance(
  linkId: number,
  input: InsuranceUpdateInput,
): Promise<InsuranceSettings> {
  const res = await apiFetch<{ data: InsuranceSettings }>(
    `/links/${linkId}/insurance`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data;
}

export async function restoreLinkInsurance(
  linkId: number,
): Promise<{ message: string; link: InsuranceSettings }> {
  const res = await apiFetch<{
    data: { message: string; link: InsuranceSettings };
  }>(`/links/${linkId}/insurance/restore`, { method: "POST" });
  return res.data;
}

export async function probeLinkInsurance(
  linkId: number,
): Promise<{ message: string; check: InsuranceCheck; link: InsuranceSettings }> {
  const res = await apiFetch<{
    data: { message: string; check: InsuranceCheck; link: InsuranceSettings };
  }>(`/links/${linkId}/insurance/probe`, { method: "POST" });
  return res.data;
}
