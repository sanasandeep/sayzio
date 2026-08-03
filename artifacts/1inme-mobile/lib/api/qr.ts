import { apiFetch } from "@/lib/api";

export type QrCode = {
  id: number;
  name: string;
  type: string;
  link_id: number | null;
  project_id: number | null;
  payload: Record<string, unknown> | null;
  design: Record<string, unknown> | null;
  encoded?: string | null;
  preview_url: string | null;
  created_at: string | null;
};

export type QrPreset = {
  id: string;
  name: string;
  tagline?: string;
  design: Record<string, unknown>;
};

export type QrCatalog = {
  dots: Record<string, string[]>;
  outer_eyes: Record<string, string[]>;
  inner_eyes: Record<string, string[]>;
  frames: Record<string, string[]>;
  fonts: string[];
  types: Record<string, { label: string; icon: string; group: string }>;
  presets: QrPreset[];
  default_design: Record<string, unknown>;
};

export type QrArtPreset = { label: string; prompt: string };

export type QrArtAvailability = {
  enabled: boolean;
  allowed: boolean;
  cost: number;
  balance: number;
  recommended_plan: { slug: string; name: string } | null;
  presets: QrArtPreset[];
  /** Per-plan monthly generation allowance; -1 = unlimited. */
  monthly_allowance: number;
  monthly_used: number;
  /** Generations left this month; -1 = unlimited. */
  monthly_remaining: number;
};

export type QrArtResult = {
  image_url: string | null;
  file_id: number | null;
  cost: number | null;
  balance: number | null;
  style: string | null;
  encoded: string;
};

export async function listQrCodes(): Promise<QrCode[]> {
  const res = await apiFetch<{ data: { items: QrCode[] } }>("/qr-codes");
  return res.data.items;
}

export async function getQrArtAvailability(): Promise<QrArtAvailability> {
  const res = await apiFetch<{ data: QrArtAvailability }>("/qr-codes/art-availability");
  return res.data;
}

export async function generateQrArt(p: {
  prompt: string;
  style?: string | null;
  negative_prompt?: string | null;
  data?: string;
  link_id?: number | null;
  type?: string;
  payload?: Record<string, unknown>;
}): Promise<QrArtResult> {
  const res = await apiFetch<{ data: QrArtResult }>("/qr-codes/generate-art", {
    method: "POST",
    body: JSON.stringify(p),
  });
  return res.data;
}

export async function getQrCatalog(): Promise<QrCatalog> {
  const res = await apiFetch<{ data: QrCatalog }>("/qr-codes/catalog");
  return res.data;
}

export async function createQrCode(p: {
  name: string;
  type: string;
  link_id?: number | null;
  project_id?: number | null;
  payload?: Record<string, unknown>;
  design?: Record<string, unknown>;
}): Promise<QrCode> {
  const res = await apiFetch<{ data: { qr_code: QrCode } }>("/qr-codes", {
    method: "POST",
    body: JSON.stringify(p),
  });
  return res.data.qr_code;
}

export async function updateQrCode(
  id: number,
  p: {
    name?: string;
    type?: string;
    link_id?: number | null;
    project_id?: number | null;
    payload?: Record<string, unknown>;
    design?: Record<string, unknown>;
  },
): Promise<QrCode> {
  const res = await apiFetch<{ data: { qr_code: QrCode } }>(`/qr-codes/${id}`, {
    method: "PUT",
    body: JSON.stringify(p),
  });
  return res.data.qr_code;
}

export async function bulkCreateQrCodes(
  items: Array<{
    name: string;
    type: string;
    link_id?: number | null;
    payload?: Record<string, unknown>;
    design?: Record<string, unknown>;
  }>,
): Promise<{ items: QrCode[]; count: number }> {
  const res = await apiFetch<{ data: { items: QrCode[]; count: number } }>("/qr-codes/bulk", {
    method: "POST",
    body: JSON.stringify({ items }),
  });
  return res.data;
}

export async function deleteQrCode(id: number): Promise<void> {
  await apiFetch(`/qr-codes/${id}`, { method: "DELETE" });
}
