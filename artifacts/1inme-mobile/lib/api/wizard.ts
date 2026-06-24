import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import type { Link } from "@/lib/api/links";
import { getToken } from "@/lib/secure";

// Mirrors the Laravel BiolinkWizardQuestions taxonomy. The mobile wizard
// fetches this once and drives the industry (category) → profile-type
// (+ optional niche) steps in memory, then POSTs every answer at once to
// /links/wizard/generate. All of the question/recipe logic lives server-side
// and is shared with the web flow.

export type WizardCategory = {
  slug: string;
  label: string;
  icon?: string;
  blurb?: string;
};

export type WizardPageType = {
  slug: string;
  label: string;
  blurb?: string;
  // FontAwesome icon name (e.g. "fa-store") resolved to a native glyph via
  // resolveIconName(). Always present from the taxonomy endpoint.
  icon?: string;
};

export type WizardIndustry = {
  slug: string;
  label: string;
  // FontAwesome icon name resolved client-side; always present from taxonomy.
  icon?: string;
};

// Step 1: persona groups (the "category" tiles). Step 2: the personas inside
// the chosen group. PersonaCatalog is the single taxonomy source shared with
// the web wizard; selecting a persona drives the question set + recipe.
export type WizardGroup = {
  key: string;
  label: string;
  icon?: string;
  blurb?: string;
};

export type WizardPersona = {
  slug: string;
  label: string;
  icon?: string;
  image?: string;
  group?: string;
  blurb?: string;
  // The legacy combo this persona resolves to (carried by the taxonomy so the
  // questions endpoint can be driven straight from a persona selection).
  category: string;
  page_type: string;
};

export type WizardTaxonomy = {
  // New persona taxonomy (the canonical Step 1/2 source).
  groups: WizardGroup[];
  // Personas keyed by their group `key`.
  personas: Record<string, WizardPersona[]>;
  // Optional niche refinement keyed by persona slug (specific-only).
  industries_by_persona: Record<string, WizardIndustry[]>;
  // Legacy keys (kept for backward compatibility; unused by the new flow).
  categories: WizardCategory[];
  page_types: Record<string, WizardPageType[]>;
  industries: Record<string, WizardIndustry[]>;
};

// Step 2 (starting design): a persona-tagged page template the wizard can seed
// before layering the user's answers on top. Built by the shared
// WizardStartingDesignService so the set matches the web wizard exactly.
export type WizardStartingDesign = {
  id: number;
  name: string;
  category: string;
  category_label: string;
  description?: string;
  thumbnail_url?: string | null;
  plan_tier: string;
  locked: boolean;
  recommended: boolean;
  blocks_count: number;
  content_summary?: { type: string; label: string; icon?: string; count?: number }[];
  preview_layout?: unknown;
};

export type WizardQuestionType =
  | "text"
  | "textarea"
  | "url"
  | "email"
  | "phone"
  | "color"
  | "select"
  | "image";

export type WizardQuestion = {
  key: string;
  label: string;
  type: WizardQuestionType;
  placeholder?: string;
  help?: string;
  required?: boolean;
  options?: { v: string; l: string }[];
};

export type WizardQuestionSet = {
  // The full flat list (kept for callers that don't need the split).
  questions: WizardQuestion[];
  // Pre-split into the two content steps by the server (single source of
  // truth shared with the web wizard): "Basic profile & branding" and
  // "Additional content".
  basics: WizardQuestion[];
  additional: WizardQuestion[];
};

export async function getWizardTaxonomy(): Promise<WizardTaxonomy> {
  const res = await apiFetch<{ data: WizardTaxonomy }>(
    "/links/wizard/taxonomy",
  );
  return res.data;
}

// Step 2: the persona-tagged starting designs (+ the client renders a "Start
// from scratch" card alongside). `q` optionally filters by name/description.
export async function getWizardStartingDesigns(params: {
  persona: string;
  q?: string | null;
}): Promise<WizardStartingDesign[]> {
  const qs = new URLSearchParams();
  qs.set("persona", params.persona);
  if (params.q) qs.set("q", params.q);
  const res = await apiFetch<{ data: { starting_designs: WizardStartingDesign[] } }>(
    `/links/wizard/starting-designs?${qs}`,
  );
  return res.data.starting_designs;
}

export async function getWizardQuestions(params: {
  category: string;
  page_type: string;
  industry?: string | null;
}): Promise<WizardQuestionSet> {
  const qs = new URLSearchParams();
  qs.set("category", params.category);
  qs.set("page_type", params.page_type);
  if (params.industry) qs.set("industry", params.industry);
  const res = await apiFetch<{ data: WizardQuestionSet }>(
    `/links/wizard/questions?${qs}`,
  );
  return res.data;
}

export async function generateWizardPage(payload: {
  persona?: string | null;
  category?: string | null;
  page_type?: string | null;
  industry?: string | null;
  template_id?: number | null;
  answers: Record<string, string>;
}): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(
    "/links/wizard/generate",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.link;
}

// ── AI auto-draft ─────────────────────────────────────────────────
// The optional, user-triggered AI draft. Reuses the same answers the
// instant generator collects, plus optional grounding inputs: the
// user's AI Brains (Minds) and vault files. Mirrors the web wizard's
// "Auto-draft with AI" button. The server respects the credit charge
// (with auto-refund on failure) and is gated behind the AI engine
// being enabled — so this is only offered when `resources.ai_enabled`.

export type WizardMind = { id: number; name: string };
export type WizardVaultFile = {
  id: number;
  name: string;
  type: string;
  url: string;
};
export type WizardResources = {
  ai_enabled: boolean;
  my_minds: WizardMind[];
  platform_minds: WizardMind[];
  vault_files: WizardVaultFile[];
};

export async function getWizardResources(): Promise<WizardResources> {
  const res = await apiFetch<{ data: WizardResources }>(
    "/links/wizard/resources",
  );
  return res.data;
}

export async function aiGenerateWizardPage(payload: {
  persona?: string | null;
  category?: string | null;
  page_type?: string | null;
  industry?: string | null;
  template_id?: number | null;
  answers: Record<string, string>;
  ai_mind_ids?: number[];
  include_platform_mind?: boolean;
  file_ids?: number[];
}): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(
    "/links/wizard/ai-generate",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.link;
}

/**
 * Upload an image answer (avatar/cover/etc.) picked from the device during the
 * wizard. Posted as multipart/form-data to mirror the web editor's upload flow;
 * the server stores it in the user's vault as a UserFile and returns the public
 * URL, which the caller stamps into the question's answer. Pasting a URL by
 * hand stays a valid fallback — this just removes that friction.
 */
export async function uploadWizardImage(args: {
  uri: string;
  name?: string;
  mime?: string;
}): Promise<string> {
  const token = await getToken();
  const fd = new FormData();
  const mime = args.mime || guessImageMime(args.uri) || "image/jpeg";
  const ext = extFromMime(mime);
  fd.append("photo", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry shape.
    uri: args.uri,
    name: args.name || `wizard-image.${ext}`,
    type: mime,
  } as unknown as Blob);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
    // NB: do NOT set Content-Type — RN fills the multipart boundary in.
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${getBaseUrl()}/api/v1/links/wizard/image`, {
    method: "POST",
    body: fd as unknown as BodyInit,
    headers,
  });
  const text = await res.text();
  const body = text ? safeJson(text) : null;
  if (!res.ok) {
    const nested =
      body && typeof body.error === "object" && body.error !== null
        ? (body.error as Record<string, unknown>)
        : null;
    const message =
      (nested && typeof nested.message === "string"
        ? (nested.message as string)
        : null) ||
      (body && typeof body.message === "string"
        ? (body.message as string)
        : null) ||
      `Upload failed (${res.status})`;
    throw { status: res.status, message };
  }
  return (body as { data: { photo_url: string } }).data.photo_url;
}

function guessImageMime(uri: string): string | null {
  const ext = uri.split("?")[0].split(".").pop()?.toLowerCase();
  switch (ext) {
    case "jpg":
    case "jpeg":
      return "image/jpeg";
    case "png":
      return "image/png";
    case "webp":
      return "image/webp";
    case "gif":
      return "image/gif";
    default:
      return null;
  }
}

function extFromMime(mime: string): string {
  switch (mime) {
    case "image/png":
      return "png";
    case "image/webp":
      return "webp";
    case "image/gif":
      return "gif";
    default:
      return "jpg";
  }
}

function safeJson(text: string): Record<string, unknown> | null {
  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    return null;
  }
}
