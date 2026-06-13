import { apiFetch } from "@/lib/api";
import type { Block } from "@/lib/api/blocks";

export type CardTemplateChildSummary = {
  type: string;
  label: string;
  icon: string;
  preview: string;
};

/**
 * One cell in the mini-blueprint thumbnail. Mirrors the PHP
 * `TemplatePreviewLayoutBuilder` output — `shape` drives which kind of
 * mock is drawn (avatar circle, pill button, stacked input lines, etc.)
 * so the tile's thumbnail communicates the card's actual contents at a
 * glance instead of rendering as a flat coloured bar.
 */
export type PreviewLayoutCell = {
  span: number;
  shape:
    | "tile"
    | "heading"
    | "pill"
    | "avatar"
    | "media"
    | "dot_row"
    | "text_lines"
    | "form"
    | "list_rows"
    | "hairline"
    | "spacer"
    | "badge";
  bg: string;
  h: number;
  icon?: string;
  lines?: number;
  dots?: number;
  sub?: boolean;
  btn_bg?: string;
  /** Real placeholder copy for text-like shapes (heading, pill, form button). */
  text?: string;
  /** Secondary line for heading/avatar shapes. */
  sub_text?: string;
  /** Short sample strings for list rows. */
  items?: string[];
  /** Absolute URL of a real placeholder image for media/avatar shapes. */
  img?: string;
  /** Overlay a play glyph on media cells (video/audio). */
  play?: boolean;
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
  preview_layout?: PreviewLayoutCell[][];
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
): Promise<{ block_id: number; blocks: Block[] }> {
  const body: Record<string, unknown> = { template_id: payload.template_id };
  if (payload.insert_after != null) body.insert_after = payload.insert_after;
  if (payload.tab_id != null) body.tab_id = payload.tab_id;
  // The apply endpoint returns the full freshly-created sub-tree (parent
  // card first, then its children) so the editor can patch its block list
  // in place instead of refetching everything.
  const res = await apiFetch<{
    data: { block_id: number; blocks: Block[] };
  }>(`/links/${linkId}/card-templates/apply`, {
    method: "POST",
    body: JSON.stringify(body),
  });
  return res.data;
}
