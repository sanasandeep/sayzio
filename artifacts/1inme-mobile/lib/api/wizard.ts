import { apiFetch } from "@/lib/api";
import type { Link } from "@/lib/api/links";

// Mirrors the Laravel BiolinkWizardQuestions taxonomy. The mobile wizard
// fetches this once and drives the category → page-type → industry steps in
// memory, then POSTs every answer at once to /links/wizard/generate. All of
// the question/recipe logic lives server-side and is shared with the web flow.

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
};

export type WizardIndustry = {
  slug: string;
  label: string;
};

export type WizardTaxonomy = {
  categories: WizardCategory[];
  page_types: Record<string, WizardPageType[]>;
  industries: Record<string, WizardIndustry[]>;
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
  questions: WizardQuestion[];
  has_industry_step: boolean;
};

export async function getWizardTaxonomy(): Promise<WizardTaxonomy> {
  const res = await apiFetch<{ data: WizardTaxonomy }>(
    "/links/wizard/taxonomy",
  );
  return res.data;
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
  category: string;
  page_type: string;
  industry?: string | null;
  answers: Record<string, string>;
}): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(
    "/links/wizard/generate",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.link;
}
