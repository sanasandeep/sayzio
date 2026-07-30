import { apiFetch } from "@/lib/api";

export type Block = {
  id: number;
  link_id: number;
  type: string;
  sort_order: number;
  parent_id: number | null;
  is_active: boolean;
  settings: Record<string, unknown> | null;
  created_at: string | null;
  updated_at: string | null;
  // Task #1094 — per-block scarcity. `end_date` reuses the existing
  // schedule expiry datetime, `max_clicks` (null/0 = unlimited) is the
  // overall cap, and `click_count` is the running tally maintained by
  // the tracking service. All three are read-only from the editor's
  // perspective except for `end_date` and `max_clicks`.
  start_date?: string | null;
  end_date?: string | null;
  max_clicks?: number | null;
  click_count?: number;
};

export type BlockKind = {
  type: string;
  label: string;
  blurb: string;
  fields: BlockField[];
};

export type BlockField = {
  key: string;
  label: string;
  kind?: "text" | "url" | "multiline";
  hint?: string;
};

export const BLOCK_KINDS: BlockKind[] = [
  {
    type: "header",
    label: "Header",
    blurb: "A title row, used to group sections.",
    fields: [
      { key: "title", label: "Title" },
      { key: "subtitle", label: "Subtitle" },
    ],
  },
  {
    type: "link",
    label: "Link button",
    blurb: "A tappable button pointing to a URL.",
    fields: [
      { key: "label", label: "Button label" },
      { key: "url", label: "URL", kind: "url" },
      { key: "thumbnail", label: "Thumbnail URL", kind: "url" },
    ],
  },
  // Featured-style link variants. Their canonical renderer is the same
  // link button (see blockTypeRegistry), but the editor surfaces the
  // extra text/description/thumbnail fields the web editor exposes.
  // `url` is declared so the trackable-link + auto-UTM sections render;
  // the generic field renderer hides it (the trackable section owns it).
  {
    type: "link_big",
    label: "Big link",
    blurb: "A large, prominent link card with a description.",
    fields: [
      { key: "text", label: "Link text" },
      { key: "description", label: "Description" },
      { key: "url", label: "URL", kind: "url" },
      { key: "thumbnail", label: "Thumbnail URL", kind: "url" },
    ],
  },
  {
    type: "featured_pin",
    label: "Featured pin",
    blurb: "A pinned, accent-colored featured link.",
    fields: [
      { key: "text", label: "Link text" },
      { key: "description", label: "Description" },
      { key: "url", label: "URL", kind: "url" },
    ],
  },
  {
    type: "text",
    label: "Text",
    blurb: "A paragraph of plain text.",
    fields: [{ key: "body", label: "Text", kind: "multiline" }],
  },
  {
    type: "image",
    label: "Image",
    blurb: "An image embedded inline.",
    fields: [
      { key: "url", label: "Image URL", kind: "url" },
      { key: "alt", label: "Alt text" },
    ],
  },
  {
    type: "video",
    label: "Video",
    blurb: "An embedded YouTube/Vimeo video.",
    fields: [{ key: "url", label: "Video URL", kind: "url" }],
  },
  {
    type: "embed",
    label: "Embed",
    blurb: "An arbitrary iframe embed.",
    fields: [{ key: "url", label: "Embed URL", kind: "url" }],
  },
  {
    type: "divider",
    label: "Divider",
    blurb: "A horizontal line between sections.",
    fields: [],
  },
  // The list/pricing kinds use a bespoke editor UI in the block edit
  // screen (style picker + repeating items), so their `fields` array is
  // intentionally empty — the generic field renderer would only see
  // primitives and skip the items array entirely.
  {
    type: "list",
    label: "Bulleted list",
    blurb: "A list of bulleted items with an icon.",
    fields: [],
  },
  {
    type: "list_numbered",
    label: "Numbered list",
    blurb: "A list of numbered items.",
    fields: [],
  },
  {
    type: "list_pricing",
    label: "Pricing list",
    blurb: "A list of priced items, plans, or menu rows.",
    fields: [],
  },
  // Map location uses a bespoke editor UI (map pin-picker + lat/lng/label/
  // zoom/directions toggle) mirroring the web editor's `mapPinPicker`
  // Alpine component, so its `fields` array is intentionally empty.
  {
    type: "map_location",
    label: "Map location",
    blurb: "An address or pinned coordinate with a map preview.",
    fields: [],
  },
  // Gallery/grid image blocks (Task #6016) — their `images` array is
  // edited via a bespoke repeater UI (with the curated stock gallery
  // picker) in the block edit screen, so `fields` stays empty.
  {
    type: "image_grid",
    label: "Image grid",
    blurb: "A grid of images, laid out in columns.",
    fields: [],
  },
  {
    type: "image_slider",
    label: "Image slider",
    blurb: "A swipeable carousel of images.",
    fields: [],
  },
  {
    type: "image_slider_v2",
    label: "Image slider",
    blurb: "A swipeable carousel of images.",
    fields: [],
  },
  {
    type: "tip_jar",
    label: "Tip Jar",
    blurb: "Let visitors send you a tip with preset amounts or a custom value.",
    fields: [
      { key: "title", label: "Heading" },
      { key: "message", label: "Message" },
      { key: "button_text", label: "Button text" },
    ],
  },
];

export function blockKind(type: string): BlockKind | null {
  return BLOCK_KINDS.find((b) => b.type === type) ?? null;
}

// Block-type palette catalog (mirrors the web biolink-editor palette).
// Served by GET /block-catalog — the same picker-visible block types and
// category labels the web editor uses, with a per-user `locked` flag from
// the plan-gating check. Icons come through as Font Awesome `fa-*` slugs.
export type BlockCatalogCategory = {
  key: string;
  label: string;
};

export type BlockCatalogType = {
  type: string;
  label: string;
  icon: string;
  category: string;
  locked: boolean;
};

// Admin-managed Designs catalog additions (Task #6045). Merged on top of
// the hardcoded built-in mirror in `lib/blockVariants.ts`: `custom`
// entries are appended per matching type, `hidden` keys are filtered out
// of the gallery, and `version` keys client caches so admin edits refresh
// without an app update.
export type DesignCatalogCustomVariant = {
  key: string;
  name: string;
  tags: string[];
  shape: string | null;
  types: string[];
  preview: {
    bg?: string;
    text?: string;
    radius?: number;
    border?: string;
    dashed?: boolean;
    serif?: boolean;
    shadow?: string;
  };
};

export type DesignCatalog = {
  version: number;
  hidden: string[];
  custom: DesignCatalogCustomVariant[];
  hidden_templates: string[];
  custom_templates: {
    key: string;
    label: string;
    icon: string;
    preview_bg: string;
    preview_text: string;
  }[];
};

export type BlockCatalog = {
  categories: BlockCatalogCategory[];
  types: BlockCatalogType[];
  design_catalog?: DesignCatalog;
};

export async function getBlockCatalog(): Promise<BlockCatalog> {
  const res = await apiFetch<{ data: BlockCatalog }>(`/block-catalog`);
  return res.data;
}

export async function listBlocks(linkId: number): Promise<Block[]> {
  const res = await apiFetch<{ data: { items: Block[] } }>(
    `/links/${linkId}/blocks`,
  );
  return res.data.items;
}

export async function createBlock(
  linkId: number,
  payload: { type: string; settings?: Record<string, unknown> },
): Promise<Block> {
  const res = await apiFetch<{ data: { block: Block } }>(
    `/links/${linkId}/blocks`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  return res.data.block;
}

export async function updateBlock(
  linkId: number,
  blockId: number,
  patch: Partial<{
    type: string;
    is_active: boolean;
    settings: Record<string, unknown>;
    start_date: string | null;
    end_date: string | null;
    max_clicks: number | null;
  }>,
): Promise<Block> {
  const res = await apiFetch<{ data: { block: Block } }>(
    `/links/${linkId}/blocks/${blockId}`,
    {
      method: "PATCH",
      body: JSON.stringify(patch),
    },
  );
  return res.data.block;
}

export async function deleteBlock(
  linkId: number,
  blockId: number,
): Promise<void> {
  await apiFetch(`/links/${linkId}/blocks/${blockId}`, { method: "DELETE" });
}

// "Fetch details" OG-metadata extractor (GET /og-meta — mobile parity for
// the web block editor's fetch shortcut). Server-side SSRF-guarded fetch;
// rate limited to 10/min per user (shared budget with the web editor).
export type OgMeta = {
  title: string | null;
  description: string | null;
  image_url: string | null;
  favicon_url: string | null;
};

export async function fetchOgMeta(url: string): Promise<OgMeta> {
  const res = await apiFetch<{ data: { meta: OgMeta } }>(
    `/og-meta?url=${encodeURIComponent(url)}`,
  );
  return res.data.meta;
}

export async function reorderBlocks(
  linkId: number,
  order: number[],
): Promise<void> {
  await apiFetch(`/links/${linkId}/blocks/reorder`, {
    method: "POST",
    body: JSON.stringify({ order }),
  });
}
