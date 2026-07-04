import { apiFetch } from "@/lib/api";

// Mobile parity for the web "AI Staff" (Task #3523) is deliberately narrow:
// list/enable staff + read/apply/dismiss the confirm-before-act suggestions
// they raise. Drafting, chat and chase-generation stay web-only. Consumes
// App\Modules\Api\Controllers\AiStaffController, all under auth:sanctum.
// Responses use the unified {data}/{error} envelope.
//
//   GET   /ai/staff                            list staff
//   PATCH /ai/staff/{id}                       enable/disable
//   GET   /ai/staff/suggestions?staff_id=      list suggestions
//   POST  /ai/staff/suggestions/{id}/apply     apply (needs confirm)
//   POST  /ai/staff/suggestions/{id}/dismiss   dismiss

export type AiStaffDomain = "billing" | "contacts" | "inbox" | "general";

export type AiStaffItem = {
  id: number;
  name: string;
  domain: AiStaffDomain;
  domain_label: string;
  instructions: string | null;
  is_disabled: boolean;
  plan_allowed: boolean;
  last_used_at: string | null;
};

export type AiStaffIndex = {
  staff: AiStaffItem[];
  domains: Record<AiStaffDomain, string>;
};

export type AiStaffSuggestionStatus =
  | "pending"
  | "applied"
  | "dismissed"
  | "error";

export type AiStaffSuggestionItem = {
  id: number;
  ai_staff_id: number;
  staff_name: string | null;
  kind: "draft_invoice" | "chase_invoice";
  status: AiStaffSuggestionStatus;
  title: string;
  message: string | null;
  payload: Record<string, unknown> | null;
  created_at: string | null;
};

export type AiStaffSuggestionApplyResult = {
  suggestion: AiStaffSuggestionItem;
  message: string;
};

export const aiStaff = {
  index: () =>
    apiFetch<{ data: AiStaffIndex }>("/ai/staff").then((r) => r.data),

  setEnabled: (id: number, enabled: boolean) =>
    apiFetch<{ data: AiStaffItem }>(`/ai/staff/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ is_disabled: !enabled }),
    }).then((r) => r.data),

  suggestions: (staffId?: number) =>
    apiFetch<{ data: { suggestions: AiStaffSuggestionItem[] } }>(
      staffId ? `/ai/staff/suggestions?staff_id=${staffId}` : "/ai/staff/suggestions",
    ).then((r) => r.data),

  applySuggestion: (id: number) =>
    apiFetch<{ data: AiStaffSuggestionApplyResult }>(
      `/ai/staff/suggestions/${id}/apply`,
      { method: "POST", body: JSON.stringify({ confirm: true }) },
    ).then((r) => r.data),

  dismissSuggestion: (id: number) =>
    apiFetch<{ data: { suggestion: AiStaffSuggestionItem } }>(
      `/ai/staff/suggestions/${id}/dismiss`,
      { method: "POST" },
    ).then((r) => r.data),
};
