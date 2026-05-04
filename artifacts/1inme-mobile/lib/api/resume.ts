import { apiFetch } from "@/lib/api";

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
  visibility: string;
  is_public_pdf: boolean;
  public_pdf_url: string | null;
  handle: string | null;
  updated_at: string | null;
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
