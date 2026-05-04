import { apiFetch } from "@/lib/api";

export type CardTemplateChildSummary = {
  type: string;
  label: string;
  icon: string;
  preview: string;
};

export type CardTemplate = {
  id: number;
  name: string;
  category: string;
  category_label: string;
  description: string | null;
  thumbnail_url: string | null;
  plan_tier: string | null;
  locked: boolean;
  children_count: number;
  children: CardTemplateChildSummary[];
};

export type CardTemplateGallery = {
  items: CardTemplate[];
  categories: Record<string, string>;
};

export async function listCardTemplates(
  linkId: number,
): Promise<CardTemplateGallery> {
  const res = await apiFetch<{ data: CardTemplateGallery }>(
    `/links/${linkId}/card-templates`,
  );
  return res.data;
}

export async function applyCardTemplate(
  linkId: number,
  payload: {
    template_id: number;
    insert_after?: number | null;
    tab_id?: string | null;
  },
): Promise<{ block_id: number }> {
  const body: Record<string, unknown> = { template_id: payload.template_id };
  if (payload.insert_after != null) body.insert_after = payload.insert_after;
  if (payload.tab_id != null) body.tab_id = payload.tab_id;
  const res = await apiFetch<{ data: { block_id: number } }>(
    `/links/${linkId}/card-templates/apply`,
    {
      method: "POST",
      body: JSON.stringify(body),
    },
  );
  return res.data;
}
