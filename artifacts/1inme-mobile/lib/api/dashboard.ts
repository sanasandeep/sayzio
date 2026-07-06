import { apiFetch } from "@/lib/api";
import type { Link } from "@/lib/api/links";

export type Dashboard = {
  totals: {
    links: number;
    active_links: number;
    total_clicks: number;
    unique_clicks: number;
    nfc_writes: number;
    followers: number;
    unread_notifs: number;
  };
  by_type: { type: string; count: number; clicks: number }[];
  recent_links: Link[];
  top_link: Link | null;
};

export async function getDashboard(): Promise<Dashboard> {
  const res = await apiFetch<{ data: Dashboard }>(`/dashboard`);
  return res.data;
}

// Mobile parity for the web "Customize dashboard" flow (Task #3525) —
// App\Modules\Api\Controllers\DashboardLayoutController. Same widget
// catalog, presets, and AI designer service as web; no drift possible since
// both surfaces delegate to the shared Support/Service classes.

export type DashboardWidget = {
  key: string;
  label: string;
  description: string;
  icon: string;
  tab: string;
};

export type DashboardWidgetGroup = {
  tab: string;
  label: string;
  widgets: DashboardWidget[];
};

export type DashboardPreset = {
  key: string;
  label: string;
  description: string;
  icon?: string;
  widgets: string[];
};

export type DashboardLayoutState = {
  catalog: DashboardWidget[];
  grouped_catalog: DashboardWidgetGroup[];
  presets: DashboardPreset[];
  current: {
    preset: string | null;
    is_custom: boolean;
    widgets: string[];
    source: string;
  };
  ai_designer_allowed: boolean;
  ai_enabled: boolean;
};

export type DashboardAiAnswers = {
  goal: string;
  priorities?: string[];
  density?: "minimal" | "balanced" | "detailed";
  notes?: string;
  selected_widgets?: string[];
};

export type DashboardAiEstimate = {
  estimated_credits: number;
  balance: number;
};

export type DashboardAiResult = {
  widgets: string[];
  credits_spent: number;
  balance: number;
};

export type DashboardPresetResult = {
  preset: string;
  widgets: string[];
};

export async function getDashboardLayout(): Promise<DashboardLayoutState> {
  const res = await apiFetch<{ data: DashboardLayoutState }>(
    `/dashboard/layout`,
  );
  return res.data;
}

export async function applyDashboardPreset(
  preset: string,
): Promise<DashboardPresetResult> {
  const res = await apiFetch<{ data: DashboardPresetResult }>(
    `/dashboard/layout/preset`,
    { method: "POST", body: JSON.stringify({ preset }) },
  );
  return res.data;
}

export async function estimateDashboardAiDesign(
  answers: DashboardAiAnswers,
): Promise<DashboardAiEstimate> {
  const res = await apiFetch<{ data: DashboardAiEstimate }>(
    `/dashboard/ai/estimate`,
    { method: "POST", body: JSON.stringify(answers) },
  );
  return res.data;
}

export async function generateDashboardAiDesign(
  answers: DashboardAiAnswers,
): Promise<DashboardAiResult> {
  const res = await apiFetch<{ data: DashboardAiResult }>(
    `/dashboard/ai/generate`,
    { method: "POST", body: JSON.stringify(answers) },
  );
  return res.data;
}
