import { apiFetch } from "@/lib/api";

// Mobile parity for the web "AI Brand Studio" feature (Task #5551,
// App\Modules\Api\Controllers\BrandStudioController). All responses use the
// unified {data}/{error} envelope.
//
//   GET    /brand-studio                gating + saved brand kits + past runs
//   POST   /brand-studio/estimate       upfront credit cost
//   POST   /brand-studio/plan           run the AI planning step
//   GET    /brand-studio/{id}           proposal / results detail
//   POST   /brand-studio/{id}/confirm   materialize the kept assets
//   DELETE /brand-studio/{id}           delete a kit record

export type BrandStudioAssetKind =
  | "biolink"
  | "short_link"
  | "qr_code"
  | "form"
  | "vcard";

export type BrandStudioCompositionRow = {
  kind: BrandStudioAssetKind;
  count: number;
  purpose: string;
};

export type BrandStudioProposedAsset = {
  kind: BrandStudioAssetKind;
  purpose?: string;
  title?: string;
  name?: string;
  url?: string;
  template?: string;
  description?: string;
  first_name?: string;
  last_name?: string;
  organization?: string;
  theme_color?: string;
  blocks?: { type: string; settings?: Record<string, unknown> }[];
  [key: string]: unknown;
};

export type BrandStudioCreatedAsset = {
  kind: BrandStudioAssetKind;
  id: number;
  purpose?: string;
  title?: string;
  name?: string;
  alias?: string;
  [key: string]: unknown;
};

export type BrandStudioKitSummary = {
  id: number;
  name: string;
  mode: "kit" | "bulk";
  status: "proposed" | "created";
  asset_count: number;
  credits_spent: number;
  created_at: string | null;
};

export type BrandStudioKitDetail = BrandStudioKitSummary & {
  request: string | null;
  proposal: {
    assets: BrandStudioProposedAsset[];
    composition?: BrandStudioCompositionRow[];
  };
  results: { assets: BrandStudioCreatedAsset[]; skipped: string[] };
};

export type BrandStudioSavedPreset = {
  id: number;
  label: string;
  rows: BrandStudioCompositionRow[];
};

export type BrandStudioIndex = {
  available: boolean;
  ai_enabled: boolean;
  balance: number;
  bulk_cap: number;
  asset_kinds: BrandStudioAssetKind[];
  kit_caps: Record<BrandStudioAssetKind, number>;
  brand_kits: { id: number; name: string }[];
  kits: BrandStudioKitSummary[];
  saved_presets: BrandStudioSavedPreset[];
};

export type BrandStudioPlanInput = {
  request: string;
  mode?: "kit" | "bulk";
  bulk_kind?: BrandStudioAssetKind | null;
  bulk_count?: number | null;
  composition?: BrandStudioCompositionRow[] | null;
  brand_kit_id?: number | null;
  brand_name?: string;
  brand_colors?: string;
  brand_voice?: string;
  brand_description?: string;
};

export type BrandStudioEstimate = {
  estimated_credits: number;
  balance: number;
};

export type BrandStudioPlanResult = {
  credits_spent: number;
  balance: number;
  kit: BrandStudioKitDetail;
};

export type BrandStudioConfirmResult = {
  created: number;
  skipped: string[];
  kit: BrandStudioKitDetail;
};

function planPayload(input: BrandStudioPlanInput): Record<string, unknown> {
  const body: Record<string, unknown> = { request: input.request };
  if (input.mode) body.mode = input.mode;
  if (input.mode === "bulk") {
    if (input.bulk_kind) body.bulk_kind = input.bulk_kind;
    if (input.bulk_count) body.bulk_count = input.bulk_count;
  } else if (input.composition && input.composition.length) {
    body.composition = input.composition;
  }
  if (input.brand_kit_id) body.brand_kit_id = input.brand_kit_id;
  if (input.brand_name) body.brand_name = input.brand_name;
  if (input.brand_colors) body.brand_colors = input.brand_colors;
  if (input.brand_voice) body.brand_voice = input.brand_voice;
  if (input.brand_description) body.brand_description = input.brand_description;
  return body;
}

export async function getBrandStudio(): Promise<BrandStudioIndex> {
  const res = await apiFetch<{ data: BrandStudioIndex }>("/brand-studio");
  return res.data;
}

export async function estimateBrandStudio(
  input: BrandStudioPlanInput,
): Promise<BrandStudioEstimate> {
  const res = await apiFetch<{ data: BrandStudioEstimate }>(
    "/brand-studio/estimate",
    { method: "POST", body: JSON.stringify(planPayload(input)) },
  );
  return res.data;
}

export async function planBrandStudio(
  input: BrandStudioPlanInput,
): Promise<BrandStudioPlanResult> {
  const res = await apiFetch<{ data: BrandStudioPlanResult }>(
    "/brand-studio/plan",
    { method: "POST", body: JSON.stringify(planPayload(input)) },
  );
  return res.data;
}

export type BrandStudioKitShow = {
  kit: BrandStudioKitDetail;
  balance: number;
  low_balance_threshold: number;
};

export async function getBrandStudioKit(
  id: number,
): Promise<BrandStudioKitShow> {
  const res = await apiFetch<{ data: BrandStudioKitShow }>(
    `/brand-studio/${id}`,
  );
  return res.data;
}

export async function confirmBrandStudioKit(
  id: number,
  keep?: number[],
): Promise<BrandStudioConfirmResult> {
  const res = await apiFetch<{ data: BrandStudioConfirmResult }>(
    `/brand-studio/${id}/confirm`,
    { method: "POST", body: JSON.stringify(keep ? { keep } : {}) },
  );
  return res.data;
}

export async function deleteBrandStudioKit(id: number): Promise<void> {
  await apiFetch(`/brand-studio/${id}`, { method: "DELETE" });
}

export async function saveBrandStudioPreset(
  name: string,
  composition: BrandStudioCompositionRow[],
): Promise<BrandStudioSavedPreset> {
  const res = await apiFetch<{ data: { preset: BrandStudioSavedPreset } }>(
    "/brand-studio/presets",
    { method: "POST", body: JSON.stringify({ name, composition }) },
  );
  return res.data.preset;
}

export async function renameBrandStudioPreset(
  id: number,
  name: string,
): Promise<BrandStudioSavedPreset> {
  const res = await apiFetch<{ data: { preset: BrandStudioSavedPreset } }>(
    `/brand-studio/presets/${id}`,
    { method: "PATCH", body: JSON.stringify({ name }) },
  );
  return res.data.preset;
}

export async function deleteBrandStudioPreset(id: number): Promise<void> {
  await apiFetch(`/brand-studio/presets/${id}`, { method: "DELETE" });
}
