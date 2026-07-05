import { apiFetch } from "@/lib/api";

// Mobile parity for the web Competitor Biolink Teardown flow
// (App\Modules\Api\Controllers\CompetitorTeardownController). Every write
// lands on the caller's own account — team-scoped teardown handoff on
// mobile is a web-only nuance, same as the Card & Brochure Scanner today.
//
//   GET    /links-teardown              recent teardowns + engine/balance
//   POST   /links-teardown              fetch + AI-score a competitor URL
//   GET    /links-teardown/{id}         scored results
//   POST   /links-teardown/{id}/build   hand off to the AI biolink builder

export type TeardownCta = {
  present: boolean;
  quality_score: number;
  feedback: string;
};

export type TeardownAnalysis = {
  overall_score: number;
  summary: string;
  strengths: string[];
  weaknesses: string[];
  missing_elements: string[];
  cta: TeardownCta;
  recommendations: string[];
};

export type TeardownStatus = "pending" | "completed" | "failed" | string;

export type Teardown = {
  id: number;
  competitor_url: string;
  status: TeardownStatus;
  analysis: TeardownAnalysis | null;
  error: string | null;
  credits_spent: number;
  built_link_id: number | null;
  created_at: string | null;
};

export type TeardownIndex = {
  ai_enabled: boolean;
  allowed: boolean;
  balance: number;
  items: Teardown[];
};

export type TeardownBuildResult = {
  link_id: number;
  alias: string;
};

export const teardown = {
  index: () =>
    apiFetch<{ data: TeardownIndex }>("/links-teardown").then((r) => r.data),

  analyze: (url: string) =>
    apiFetch<{ data: Teardown }>("/links-teardown", {
      method: "POST",
      body: JSON.stringify({ url }),
    }).then((r) => r.data),

  show: (id: number) =>
    apiFetch<{ data: Teardown }>(`/links-teardown/${id}`).then((r) => r.data),

  build: (id: number) =>
    apiFetch<{ data: TeardownBuildResult }>(`/links-teardown/${id}/build`, {
      method: "POST",
    }).then((r) => r.data),
};
