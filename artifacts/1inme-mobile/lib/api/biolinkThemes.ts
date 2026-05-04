import { apiFetch } from "@/lib/api";

export type BiolinkSavedTheme = {
  id: number;
  name: string;
  created_at: string | null;
};

export type BiolinkThemeSchedule = {
  id: number;
  theme_id: number;
  theme_name: string | null;
  starts_at: string | null;
  ends_at: string | null;
  timezone: string | null;
  status: "pending" | "active" | "completed" | "cancelled";
  is_live: boolean;
};

export type BiolinkThemesPayload = {
  themes: BiolinkSavedTheme[];
  schedules: BiolinkThemeSchedule[];
  active_id: number | null;
};

export async function listBiolinkThemes(
  linkId: number,
): Promise<BiolinkThemesPayload> {
  const res = await apiFetch<{ data: BiolinkThemesPayload }>(
    `/links/${linkId}/themes`,
  );
  return res.data;
}

export async function saveBiolinkTheme(
  linkId: number,
  name: string,
): Promise<BiolinkSavedTheme> {
  const res = await apiFetch<{ data: { theme: BiolinkSavedTheme } }>(
    `/links/${linkId}/themes`,
    { method: "POST", body: JSON.stringify({ name }) },
  );
  return res.data.theme;
}

export async function deleteBiolinkTheme(
  linkId: number,
  themeId: number,
): Promise<void> {
  await apiFetch(`/links/${linkId}/themes/${themeId}`, { method: "DELETE" });
}

export async function scheduleBiolinkTheme(
  linkId: number,
  payload: {
    theme_id: number;
    starts_at: string;
    ends_at: string;
    timezone?: string;
  },
): Promise<{ id: number }> {
  const res = await apiFetch<{ data: { schedule: { id: number } } }>(
    `/links/${linkId}/themes/schedules`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.schedule;
}

export async function updateBiolinkThemeSchedule(
  linkId: number,
  scheduleId: number,
  payload: { starts_at: string; ends_at: string; timezone?: string },
): Promise<{ id: number }> {
  const res = await apiFetch<{ data: { schedule: { id: number } } }>(
    `/links/${linkId}/themes/schedules/${scheduleId}`,
    { method: "PATCH", body: JSON.stringify(payload) },
  );
  return res.data.schedule;
}

export async function cancelBiolinkThemeSchedule(
  linkId: number,
  scheduleId: number,
): Promise<void> {
  await apiFetch(
    `/links/${linkId}/themes/schedules/${scheduleId}/cancel`,
    { method: "POST" },
  );
}
