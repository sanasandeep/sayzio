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
