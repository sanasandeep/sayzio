import { apiFetch } from "@/lib/api";
import type { Block } from "@/lib/api/blocks";
import type {
  CardTemplateChildSummary,
  PreviewLayoutCell,
} from "@/lib/api/cardTemplates";

/**
 * One top-level block in a page template's "what's inside" summary. Mirrors
 * the PHP `TemplateContentSummarizer::summarizePageBlocks` output — the same
 * shape as a card child summary, plus the nested children of a `card`
 * container so the picker can hint at grouped blocks.
 */
export type PageTemplateBlockSummary = CardTemplateChildSummary & {
  children?: CardTemplateChildSummary[];
};

/**
 * A full-page biolink starter/persona design. Applying one REPLACES the
 * link's blocks (unlike a card template, which inserts a single sub-tree),
 * so the apply call carries an overwrite confirmation flag.
 */
export type PageTemplate = {
  id: number;
  name: string;
  category: string;
  category_label: string;
  description: string | null;
  thumbnail_url: string | null;
  plan_tier: string | null;
  locked: boolean;
  /** True when this template is tagged for the viewer's saved persona. */
  recommended: boolean;
  blocks_count: number;
  content: PageTemplateBlockSummary[];
  preview_layout?: PreviewLayoutCell[][];
};

export type PageTemplateGallery = {
  items: PageTemplate[];
  categories: Record<string, string>;
};

export async function listPageTemplates(
  linkId: number,
): Promise<PageTemplateGallery> {
  const res = await apiFetch<{ data: PageTemplateGallery }>(
    `/links/${linkId}/page-templates`,
  );
  return res.data;
}

/**
 * Apply a full-page template. The endpoint replaces the link's blocks and
 * returns the full freshly-created tree (parents first, then children by
 * sort order) so the editor can swap its block list in place.
 *
 * When the link already has blocks and `confirm_overwrite` is not set, the
 * server responds with HTTP 409 + code `confirm_overwrite`; the caller
 * should re-issue with `confirm_overwrite: true` after prompting the user.
 */
export async function applyPageTemplate(
  linkId: number,
  payload: { template_id: number; confirm_overwrite?: boolean },
): Promise<{ blocks: Block[] }> {
  const body: Record<string, unknown> = { template_id: payload.template_id };
  if (payload.confirm_overwrite) body.confirm_overwrite = true;
  const res = await apiFetch<{ data: { blocks: Block[] } }>(
    `/links/${linkId}/page-templates/apply`,
    {
      method: "POST",
      body: JSON.stringify(body),
    },
  );
  return res.data;
}
