import { apiFetch } from "@/lib/api";
import type { Link } from "@/lib/api/links";

// Mobile client for the "Build my Link in Bio with AI" flow — parity with the
// web links/{link}/ai-builder surface (App\Modules\Api\Controllers\
// AiBiolinkBuilderController). A free-text prompt plus optional image/link/file
// URLs are sent to the shared AiBiolinkBuilderService, which assembles a full
// page from a curated, plan-allowed block subset and REPLACES the biolink's
// blocks. The AI credit charge (with auto-refund on parse failure) and the
// On-Brand AI `use_brand_kit` opt-in all live server-side.

export type AiBuilderIntake = {
  // Baseline worst-case build cost for the pre-run affordability hint;
  // the /estimate endpoint remains the input-specific quote.
  estimated_cost?: number;
  ai_enabled: boolean;
  balance: number;
  allowed_types: string[];
  max_links: number;
  max_images: number;
  max_files: number;
  // On-Brand AI (Task #2664): whether the caller's plan unlocks injecting
  // their Brand Kit voice, plus a light summary of the default kit (if any).
  on_brand_allowed: boolean;
  brand_kit: { id: number; name: string } | null;
};

export type AiBuilderPayload = {
  description: string;
  links?: string[];
  images?: string[];
  files?: string[];
  // Default on; sent as an explicit opt-out, mirroring the web intake form's
  // "Use my Brand Kit voice" checkbox.
  use_brand_kit?: boolean;
};

export type AiBuilderEstimate = {
  estimated_credits: number;
  balance: number;
};

export type AiBuilderResult = {
  blocks: number;
  credits_spent: number;
  balance: number;
  link: Link;
};

export async function getAiBuilderIntake(
  linkId: number,
): Promise<AiBuilderIntake> {
  const res = await apiFetch<{ data: AiBuilderIntake }>(
    `/links/${linkId}/ai-builder`,
  );
  return res.data;
}

export async function estimateAiBuilder(
  linkId: number,
  payload: AiBuilderPayload,
): Promise<AiBuilderEstimate> {
  const res = await apiFetch<{ data: AiBuilderEstimate }>(
    `/links/${linkId}/ai-builder/estimate`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function generateAiBuilder(
  linkId: number,
  payload: AiBuilderPayload,
): Promise<AiBuilderResult> {
  const res = await apiFetch<{ data: AiBuilderResult }>(
    `/links/${linkId}/ai-builder/generate`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}
