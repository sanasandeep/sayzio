import { apiFetch } from "@/lib/api";

// Mobile parity for the web "AI Brand Kit" feature
// (App\Modules\Api\Controllers\BrandKitController). All responses use the
// unified {data}/{error} envelope.
//
//   GET    /brand-kits                              list + apply targets + gating
//   POST   /brand-kits/estimate                     upfront credit cost
//   POST   /brand-kits/generate                     run generation, save a kit
//   DELETE /brand-kits/{id}                         delete a kit
//   POST   /brand-kits/{id}/apply/biolink/{linkId}  apply to a biolink
//   POST   /brand-kits/{id}/apply/qr/{qrId}         apply to a QR code

export type BrandPalette = {
  primary?: string;
  secondary?: string;
  accent?: string;
  neutrals?: string[];
};

export type BrandFonts = {
  heading?: string;
  body?: string;
};

export type BrandVoice = {
  tone?: string;
  descriptors?: string[];
};

export type BrandKitConfig = {
  palette?: BrandPalette;
  fonts?: BrandFonts;
  voice?: BrandVoice;
  taglines?: string[];
  bio?: string;
  block_theme?: string;
  source?: { type?: string; value?: string };
};

export type BrandKit = {
  id: number;
  name: string;
  slug: string;
  is_default: boolean;
  config: BrandKitConfig;
  created_at: string | null;
};

export type BrandKitTargetLink = {
  id: number;
  title: string | null;
  alias: string;
};

export type BrandKitTargetQr = {
  id: number;
  name: string;
};

export type BrandKitUpgradePlan = {
  slug: string;
  name: string;
};

export type BrandKitsIndex = {
  kits: BrandKit[];
  count: number;
  cap: number;
  can_create: boolean;
  upgrade_plan: BrandKitUpgradePlan | null;
  ai_enabled: boolean;
  balance: number;
  biolinks: BrandKitTargetLink[];
  qr_codes: BrandKitTargetQr[];
  block_themes: string[];
};

export type BrandKitGenerateInput = {
  prompt?: string;
  website_url?: string | null;
  logo_url?: string | null;
};

export type BrandKitEstimate = {
  estimated_credits: number;
  balance: number;
};

export type BrandKitGenerateResult = {
  credits_spent: number;
  balance: number;
  kit: BrandKit;
};

export async function getBrandKits(): Promise<BrandKitsIndex> {
  const res = await apiFetch<{ data: BrandKitsIndex }>("/brand-kits");
  return res.data;
}

// Brand Consistency Score (Task #2664) — audit of the caller's biolinks
// against their default kit. Plan-gated behind `brand_consistency`; each
// finding becomes a one-tap "Apply fix" via applyBrandKitToBiolink(kit_id, link_id).
export type BrandConsistencyMismatch = {
  key: string;
  label: string;
  current: string | null;
  expected: string | null;
};

export type BrandConsistencyFinding = {
  link_id: number;
  title: string;
  alias: string;
  score: number;
  severity: "win" | "tip" | "warning" | "critical";
  headline: string;
  reason: string;
  mismatches: BrandConsistencyMismatch[];
};

export type BrandConsistencyAudit = {
  score: number;
  grade: string;
  label: string;
  kit_id: number;
  kit_name: string;
  links_total: number;
  links_on_brand: number;
  findings: BrandConsistencyFinding[];
};

export type BrandConsistencyResponse = {
  available: boolean;
  has_kit: boolean;
  audit: BrandConsistencyAudit | null;
};

export async function getBrandConsistency(): Promise<BrandConsistencyResponse> {
  const res = await apiFetch<{ data: BrandConsistencyResponse }>(
    "/brand-kits/consistency",
  );
  return res.data;
}

function generatePayload(input: BrandKitGenerateInput): Record<string, unknown> {
  const body: Record<string, unknown> = {};
  if (input.prompt) body.prompt = input.prompt;
  if (input.website_url) body.website_url = input.website_url;
  if (input.logo_url) body.logo_url = input.logo_url;
  return body;
}

export async function estimateBrandKit(
  input: BrandKitGenerateInput,
): Promise<BrandKitEstimate> {
  const res = await apiFetch<{ data: BrandKitEstimate }>("/brand-kits/estimate", {
    method: "POST",
    body: JSON.stringify(generatePayload(input)),
  });
  return res.data;
}

export async function generateBrandKit(
  input: BrandKitGenerateInput,
): Promise<BrandKitGenerateResult> {
  const res = await apiFetch<{ data: BrandKitGenerateResult }>(
    "/brand-kits/generate",
    { method: "POST", body: JSON.stringify(generatePayload(input)) },
  );
  return res.data;
}

export async function deleteBrandKit(id: number): Promise<void> {
  await apiFetch(`/brand-kits/${id}`, { method: "DELETE" });
}

export async function applyBrandKitToBiolink(
  id: number,
  linkId: number,
): Promise<BrandKitTargetLink> {
  const res = await apiFetch<{ data: { link: BrandKitTargetLink } }>(
    `/brand-kits/${id}/apply/biolink/${linkId}`,
    { method: "POST" },
  );
  return res.data.link;
}

// ── AI visual assets (Task #5612) ────────────────────────────────────
//   GET    /brand-kits/{id}/assets                    catalog + gating
//   POST   /brand-kits/{id}/assets/{type}/generate    coin-charged generate
//   POST   /brand-kits/{id}/assets/{type}/apply       one-click apply
//   DELETE /brand-kits/{id}/assets/{type}             delete asset + file

export type BrandAssetMode = "new" | "variation" | "alteration";

export type BrandKitAsset = {
  id: number;
  type: string;
  status: string;
  version: number;
  credits_spent: number;
  prompt: string | null;
  image_url: string | null;
  download_url: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type BrandAssetTypeEntry = {
  type: string;
  label: string;
  hint: string;
  size: string;
  cost: number;
  apply_targets: string[];
  asset: BrandKitAsset | null;
};

export type BrandKitAssetsIndex = {
  enabled: boolean;
  allowed: boolean;
  balance: number;
  types: BrandAssetTypeEntry[];
};

export async function getBrandKitAssets(
  kitId: number,
): Promise<BrandKitAssetsIndex> {
  const res = await apiFetch<{ data: BrandKitAssetsIndex }>(
    `/brand-kits/${kitId}/assets`,
  );
  return res.data;
}

export async function generateBrandKitAsset(
  kitId: number,
  type: string,
  options: { mode?: BrandAssetMode; instructions?: string } = {},
): Promise<{ asset: BrandKitAsset; balance: number }> {
  const body: Record<string, unknown> = {};
  if (options.mode) body.mode = options.mode;
  if (options.instructions) body.instructions = options.instructions;
  const res = await apiFetch<{
    data: { asset: BrandKitAsset; balance: number };
  }>(`/brand-kits/${kitId}/assets/${type}/generate`, {
    method: "POST",
    body: JSON.stringify(body),
  });
  return res.data;
}

export async function applyBrandKitAsset(
  kitId: number,
  type: string,
  target: string,
  extra: { link_id?: number; company_id?: number } = {},
): Promise<void> {
  await apiFetch(`/brand-kits/${kitId}/assets/${type}/apply`, {
    method: "POST",
    body: JSON.stringify({ target, ...extra }),
  });
}

export async function deleteBrandKitAsset(
  kitId: number,
  type: string,
): Promise<void> {
  await apiFetch(`/brand-kits/${kitId}/assets/${type}`, { method: "DELETE" });
}

export async function applyBrandKitToQr(
  id: number,
  qrId: number,
): Promise<BrandKitTargetQr> {
  const res = await apiFetch<{ data: { qr_code: BrandKitTargetQr } }>(
    `/brand-kits/${id}/apply/qr/${qrId}`,
    { method: "POST" },
  );
  return res.data.qr_code;
}
