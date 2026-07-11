import { apiFetch } from "@/lib/api";

export type CardScanExtracted = {
  kind: "card" | "brochure" | "other";
  full_name: string | null;
  first_name: string | null;
  last_name: string | null;
  title: string | null;
  company: string | null;
  tagline: string | null;
  description: string | null;
  emails: Array<{ value: string; label: string | null }>;
  phones: Array<{ value: string; label: string | null }>;
  website: string | null;
  address: string | null;
  socials: {
    instagram: string | null;
    tiktok: string | null;
    youtube: string | null;
    twitter: string | null;
    linkedin: string | null;
    facebook: string | null;
  };
  branding: {
    primary_color_hex: string | null;
    secondary_color_hex: string | null;
    has_logo: boolean;
    logo_bbox: { x: number; y: number; w: number; h: number } | null;
  };
  logo_url: string | null;
  products: Array<{
    name: string;
    description: string | null;
    price: string | null;
  }>;
  confidence: {
    overall: number;
    name: number;
    email: number;
    phone: number;
    company: number;
  };
};

export type CardScan = {
  id: number;
  status: "processing" | "completed" | "failed";
  kind: string;
  extracted: CardScanExtracted;
  logo_url: string | null;
  source_images: string[];
  credits_spent: number;
  error: string | null;
};

export type DuplicateHint = {
  type: "email" | "phone";
  value: string;
  contacts: Array<{ id: number; name: string }>;
};

export const MAX_INSTRUCTION_LENGTH = 500;

/**
 * Upload one or more card/brochure images and run AI extraction.
 * Returns the scan result and any duplicate contact hints.
 */
export async function runCardScan(
  files: Array<{ uri: string; name: string; type: string }>,
  instruction?: string,
): Promise<{ scan: CardScan; duplicates: DuplicateHint[] }> {
  const form = new FormData();

  for (const f of files) {
    form.append("files[]", {
      uri: f.uri,
      name: f.name,
      type: f.type,
    } as unknown as Blob);
  }

  if (instruction && instruction.trim()) {
    form.append(
      "instruction",
      instruction.trim().slice(0, MAX_INSTRUCTION_LENGTH),
    );
  }

  const res = await apiFetch<{
    data: { scan: CardScan; duplicates: DuplicateHint[] };
  }>("/card-scans", {
    method: "POST",
    body: form,
  });

  return res.data;
}

/** Fetch a previously-run scan by id. */
export async function getCardScan(
  scanId: number,
): Promise<{ scan: CardScan; duplicates: DuplicateHint[] }> {
  const res = await apiFetch<{
    data: { scan: CardScan; duplicates: DuplicateHint[] };
  }>(`/card-scans/${scanId}`);
  return res.data;
}

export type SaveCardScanPayload = {
  create_contact?: boolean;
  create_biolink?: boolean;
  full_name?: string;
  first_name?: string;
  last_name?: string;
  title?: string;
  company?: string;
  tagline?: string;
  description?: string;
  website?: string;
  address?: string;
  emails?: Array<{ value: string; label?: string }>;
  phones?: Array<{ value: string; label?: string }>;
  socials?: {
    instagram?: string;
    tiktok?: string;
    youtube?: string;
    twitter?: string;
    linkedin?: string;
    facebook?: string;
  };
};

/** Persist the reviewed extraction as a contact and/or biolink draft. */
export async function saveCardScan(
  scanId: number,
  payload: SaveCardScanPayload,
): Promise<{
  contact?: { id: number; display_name: string };
  biolink?: { draft_id: number; category: string; answers: Record<string, unknown> };
}> {
  const res = await apiFetch<{
    data: {
      contact?: { id: number; display_name: string };
      biolink?: { draft_id: number; category: string; answers: Record<string, unknown> };
    };
  }>(`/card-scans/${scanId}/save`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return res.data;
}
