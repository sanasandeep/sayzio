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
  // Google image search availability (admin-configured keys). When false the
  // client hides the search picker entirely (preview mode).
  image_search_enabled?: boolean;
  brand_kit: { id: number; name: string } | null;
};

export type AiBuilderImageResult = {
  url: string;
  thumbnail: string | null;
  title: string | null;
  source: string | null;
  width: number | null;
  height: number | null;
};

export type AiBuilderPayload = {
  description: string;
  links?: string[];
  images?: string[];
  files?: string[];
  // Default on; sent as an explicit opt-out, mirroring the web intake form's
  // "Use my Brand Kit voice" checkbox.
  use_brand_kit?: boolean;
  // Image preview confirmation (Task #5722): the exact extracted images the
  // creator kept after previewing. Sending the key (even as []) means "I
  // reviewed the candidates — use my list verbatim, don't re-extract".
  kept_images?: string[];
  // Generation fallback slots ('avatar'/'cover') the creator opted out of.
  skip_generated_slots?: string[];
};

// Free preview of the images the builder would auto-source (Task #5722):
// og:image/favicon candidates extracted from the supplied links now (stored
// in the vault), plus what the AI-generation fallback would produce.
export type AiBuilderImagePreview = {
  extracted: string[];
  generation: {
    enabled: boolean;
    cost_per_image: number;
    slots: string[];
  };
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

export async function previewAiBuilderImages(
  linkId: number,
  links: string[],
): Promise<AiBuilderImagePreview> {
  const res = await apiFetch<{ data: AiBuilderImagePreview }>(
    `/links/${linkId}/ai-builder/source-preview`,
    { method: "POST", body: JSON.stringify({ links }) },
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

// Google image search: candidate suggestions the creator explicitly picks
// from (rights disclaimer shown in the UI) — never auto-placed, free of coins.
export async function searchAiBuilderImages(
  linkId: number,
  query: string,
): Promise<{ results: AiBuilderImageResult[]; disclaimer: string }> {
  const res = await apiFetch<{
    data: { results: AiBuilderImageResult[]; disclaimer: string };
  }>(`/links/${linkId}/ai-builder/image-search`, {
    method: "POST",
    body: JSON.stringify({ query }),
  });
  return res.data;
}

// Import chosen candidates into the vault (server-side SSRF-safe download);
// returns relative vault URLs to append to the intake images[] list.
export async function importAiBuilderImages(
  linkId: number,
  urls: string[],
): Promise<{ url: string; source_url: string }[]> {
  const res = await apiFetch<{
    data: { images: { url: string; source_url: string }[] };
  }>(`/links/${linkId}/ai-builder/import-images`, {
    method: "POST",
    body: JSON.stringify({ urls }),
  });
  return res.data.images;
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
