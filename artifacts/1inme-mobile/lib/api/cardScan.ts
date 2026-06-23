import { apiFetch, getBaseUrl, MOBILE_USER_AGENT, type ApiError } from "@/lib/api";
import { getToken } from "@/lib/secure";

// Mobile parity for the web "Scan a card / brochure" feature. The mobile
// client uploads one or more images (and optionally PDFs) as multipart; the
// shared Laravel AI extraction service vaults the files, runs the vision
// model, meters AI credits (with auto-refund on failure) and returns the
// parsed result. The review screen then edits the extraction and POSTs it
// back to /save to create a Contact and/or seed a Biolink wizard draft.

/** Same caps the server enforces — surfaced in the picker UI. */
export const CARD_SCAN_MAX_FILES = 6;
export const CARD_SCAN_MAX_MB = 10;
export const CARD_SCAN_MAX_PDF_PAGES = 4;

export type CardScanContactRow = { value: string; label: string | null };

export type CardScanSocials = {
  instagram: string | null;
  tiktok: string | null;
  youtube: string | null;
  twitter: string | null;
  linkedin: string | null;
  facebook: string | null;
};

export type CardScanBranding = {
  primary_color_hex: string | null;
  secondary_color_hex: string | null;
  has_logo: boolean;
  logo_bbox: unknown;
};

export type CardScanConfidence = {
  overall: number;
  name: number;
  email: number;
  phone: number;
  company: number;
};

/** The flat, edit-ready extraction shape (mirrors the service's normalise()). */
export type CardScanExtracted = {
  kind: "card" | "brochure" | "other";
  full_name: string | null;
  first_name: string | null;
  last_name: string | null;
  title: string | null;
  company: string | null;
  tagline: string | null;
  description: string | null;
  emails: CardScanContactRow[];
  phones: CardScanContactRow[];
  website: string | null;
  address: string | null;
  socials: CardScanSocials;
  branding: CardScanBranding;
  logo_url: string | null;
  products: { name: string; description: string | null; price: string | null }[];
  confidence: CardScanConfidence;
};

export type CardScan = {
  id: number;
  status: "pending" | "processing" | "completed" | "failed";
  kind: string;
  extracted: CardScanExtracted;
  logo_url: string | null;
  source_images: string[];
  credits_spent: number;
  error: string | null;
};

export type DuplicateHit = {
  type: "email" | "phone";
  value: string;
  contacts: { id: number; name: string }[];
};

export type CardScanResult = {
  scan: CardScan;
  duplicates: DuplicateHit[];
};

/** A locally-picked file (image or PDF) ready to upload. */
export type ScanUpload = {
  uri: string;
  name: string;
  mime: string;
};

/**
 * Upload the picked files and run AI extraction. Sent as multipart/form-data
 * with each file under `files[]`. Mirrors the web upload endpoint.
 *
 * Throws an {@link ApiError}; callers route plan/credit gates (HTTP 402 /
 * `insufficient_credits`) through `handlePlanLockedError`.
 */
export async function scanCards(files: ScanUpload[]): Promise<CardScanResult> {
  const url = `${getBaseUrl()}/api/v1/card-scans`;
  const token = await getToken();
  const fd = new FormData();
  for (const f of files) {
    fd.append("files[]", {
      // eslint-disable-next-line @typescript-eslint/ban-ts-comment
      // @ts-ignore – React Native FormData accepts the {uri, name, type} shape.
      uri: f.uri,
      name: f.name,
      type: f.mime,
    } as any);
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  // NB: never set Content-Type — RN fills in the multipart boundary itself.

  const res = await fetch(url, { method: "POST", body: fd as any, headers });
  const text = await res.text();
  const body = text ? safeJson(text) : null;

  if (!res.ok) {
    const nested = body && typeof body.error === "object" ? body.error : null;
    const err: ApiError = {
      status: res.status,
      message:
        nested?.message ||
        (body && typeof body.message === "string" ? body.message : null) ||
        `Scan failed (${res.status})`,
      code:
        (nested && typeof nested.code === "string" ? nested.code : undefined) ||
        undefined,
      details:
        nested?.details &&
        typeof nested.details === "object" &&
        !Array.isArray(nested.details)
          ? (nested.details as Record<string, unknown>)
          : undefined,
    };
    throw err;
  }

  return (body?.data ?? body) as CardScanResult;
}

export async function getCardScan(id: number): Promise<CardScanResult> {
  const res = await apiFetch<{ data: CardScanResult }>(`/card-scans/${id}`);
  return res.data;
}

export type CardScanSavePayload = {
  create_contact: boolean;
  create_biolink: boolean;
  full_name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  title?: string | null;
  company?: string | null;
  tagline?: string | null;
  description?: string | null;
  website?: string | null;
  address?: string | null;
  emails?: CardScanContactRow[];
  phones?: CardScanContactRow[];
  socials?: Partial<CardScanSocials>;
};

export type CardScanSaveResult = {
  contact?: { id: number; display_name: string | null };
  biolink?: {
    draft_id: number;
    category: string;
    answers: Record<string, string>;
  };
};

export async function saveCardScan(
  id: number,
  payload: CardScanSavePayload,
): Promise<CardScanSaveResult> {
  const res = await apiFetch<{ data: CardScanSaveResult }>(
    `/card-scans/${id}/save`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}
