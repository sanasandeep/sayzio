import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type ResumeSectionType =
  | "experience"
  | "education"
  | "skills"
  | "projects"
  | "certifications"
  | "awards"
  | "languages"
  | "links";

export type ResumeHeader = {
  name: string;
  headline: string;
  location: string;
  email: string;
  phone: string;
  website: string;
  photo_url: string | null;
  photo_user_file_id: number | null;
};

export type ResumeSections = {
  header: ResumeHeader;
  summary: string;
  custom_sections: { key: string; title: string }[];
};

export type ResumeItem = {
  id: number;
  section_type: ResumeSectionType | "custom";
  position: number;
  data: Record<string, unknown>;
};

export type ResumeTemplate = {
  id: string;
  name?: string;
  category?: string;
  premium?: boolean;
  thumbnail?: string | null;
};

export type ResumeColorTheme = {
  id: string;
  name?: string;
  accent?: string;
};

export type ResumeVisibility =
  | "public"
  | "registered"
  | "followers"
  | "subscribers"
  | "password";

export type Resume = {
  id: number;
  template_id: string;
  template: ResumeTemplate;
  color_theme_id: string;
  color_theme: ResumeColorTheme;
  sections: ResumeSections;
  /** Items grouped by section_type. Empty groups may be missing entirely. */
  items: Partial<Record<ResumeSectionType | "custom", ResumeItem[]>>;
  is_public: boolean;
  visibility: ResumeVisibility | string;
  allow_indexing: boolean;
  has_password: boolean;
  meta_description: string | null;
  is_public_pdf: boolean;
  public_pdf_url: string | null;
  handle: string | null;
  updated_at: string | null;
};

export type PublishingPayload = {
  is_public: boolean;
  visibility: ResumeVisibility;
  allow_indexing: boolean;
  /** Only honored when visibility === "password". Empty string clears it. */
  password?: string | null;
  meta_description?: string | null;
};

export type ResumeBundle = {
  resume: Resume;
  registries: {
    templates: ResumeTemplate[];
    color_themes: ResumeColorTheme[];
  };
};

export async function getResume(): Promise<ResumeBundle> {
  const res = await apiFetch<{ data: ResumeBundle }>("/resume");
  return res.data;
}

export async function updateResumeHeader(
  payload: Partial<Pick<ResumeHeader, "name" | "headline" | "location" | "email" | "phone" | "website">>,
): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>("/resume/header", {
    method: "PUT",
    body: JSON.stringify(payload),
  });
  return res.data.resume;
}

/**
 * Upload a header photo from the device. Posted as multipart/form-data
 * to mirror the web flow; the server stores it as a UserFile in the
 * vault and writes its id onto the header section, so the resulting
 * `photo_url` ends up identical to what the web editor sees.
 */
export async function uploadResumeHeaderPhoto(args: {
  uri: string;
  name?: string;
  mime?: string;
}): Promise<Resume> {
  const token = await getToken();
  const fd = new FormData();
  const mime = args.mime || guessImageMime(args.uri) || "image/jpeg";
  const ext = extFromMime(mime);
  fd.append("photo", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry shape.
    uri: args.uri,
    name: args.name || `resume-header.${ext}`,
    type: mime,
  } as unknown as Blob);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
    // NB: do NOT set Content-Type — RN fills the multipart boundary in.
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${getBaseUrl()}/api/v1/resume/header/photo`, {
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
      (nested && typeof nested.message === "string" ? (nested.message as string) : null) ||
      (body && typeof body.message === "string" ? (body.message as string) : null) ||
      `Upload failed (${res.status})`;
    throw {
      status: res.status,
      message,
      errors: (body?.errors as Record<string, string[]> | undefined) ??
        (nested?.details as Record<string, string[]> | undefined),
    };
  }
  return (body as { data: { resume: Resume } }).data.resume;
}

export async function removeResumeHeaderPhoto(): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>(
    "/resume/header/photo",
    { method: "DELETE" },
  );
  return res.data.resume;
}

function guessImageMime(uri: string): string | null {
  const m = uri.toLowerCase().match(/\.(jpe?g|png|webp)(?:\?|$)/);
  if (!m) return null;
  const ext = m[1];
  if (ext === "png") return "image/png";
  if (ext === "webp") return "image/webp";
  return "image/jpeg";
}

function extFromMime(mime: string): string {
  if (mime.includes("png")) return "png";
  if (mime.includes("webp")) return "webp";
  return "jpg";
}

function safeJson(text: string): Record<string, unknown> | null {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

export async function updateResumeSummary(summary: string): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>("/resume/summary", {
    method: "PUT",
    body: JSON.stringify({ summary }),
  });
  return res.data.resume;
}

export async function updateResumeTemplate(template_id: string): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>("/resume/template", {
    method: "PUT",
    body: JSON.stringify({ template_id }),
  });
  return res.data.resume;
}

export async function updateResumeColorTheme(color_theme_id: string): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>("/resume/color-theme", {
    method: "PUT",
    body: JSON.stringify({ color_theme_id }),
  });
  return res.data.resume;
}

export async function createResumeItem(
  section_type: ResumeSectionType,
  data: Record<string, unknown>,
): Promise<{ item: ResumeItem; resume: Resume }> {
  const res = await apiFetch<{ data: { item: ResumeItem; resume: Resume } }>(
    "/resume/items",
    { method: "POST", body: JSON.stringify({ section_type, data }) },
  );
  return res.data;
}

export async function updateResumeItem(
  id: number,
  data: Record<string, unknown>,
): Promise<ResumeItem> {
  const res = await apiFetch<{ data: { item: ResumeItem } }>(
    `/resume/items/${id}`,
    { method: "PUT", body: JSON.stringify({ data }) },
  );
  return res.data.item;
}

export async function deleteResumeItem(id: number): Promise<void> {
  await apiFetch(`/resume/items/${id}`, { method: "DELETE" });
}

export async function reorderResumeItems(
  section_type: ResumeSectionType,
  item_ids: number[],
): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>(
    "/resume/items/reorder",
    {
      method: "POST",
      body: JSON.stringify({ section_type, item_ids }),
    },
  );
  return res.data.resume;
}

export async function updateResumePublishing(
  payload: PublishingPayload,
): Promise<{ resume: Resume; public_url: string }> {
  const res = await apiFetch<{ data: { resume: Resume; public_url: string } }>(
    "/resume/publishing",
    { method: "PUT", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function updateResumePublicPdf(is_public_pdf: boolean): Promise<Resume> {
  const res = await apiFetch<{ data: { resume: Resume } }>(
    "/resume/public-pdf",
    { method: "PUT", body: JSON.stringify({ is_public_pdf }) },
  );
  return res.data.resume;
}
