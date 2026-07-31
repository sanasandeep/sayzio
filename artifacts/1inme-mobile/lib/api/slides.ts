import { apiFetch } from "@/lib/api";

// Editor API for slide decks (biolink-family links in "slides" mode).
// Mirrors the web slides editor via GET/PUT /links/{id}/slides; new blocks
// are created through the existing POST /links/{id}/blocks endpoint (see
// lib/api/blocks.ts createBlock) which owns the plan gating.

export type SlideDeckBackground = {
  type?: "color" | "gradient" | "image" | "slideshow" | "video" | "template";
  color?: string;
  from_color?: string;
  to_color?: string;
  image_url?: string;
  images?: string[];
  interval_ms?: number;
  video_url?: string;
  video_loop?: boolean;
  video_muted?: boolean;
  video_autoplay?: boolean;
  template_id?: number;
  overlay_color?: string;
  overlay_opacity?: number;
  [key: string]: unknown;
};

export type SlideBlockOverride = {
  enter?: string;
  delay_ms?: number;
  duration_ms?: number;
  align?: string;
  grid_span?: number;
};

export type DeckSlide = {
  id?: number;
  sort_order?: number;
  title: string | null;
  block_ids: number[];
  block_settings: Record<string, SlideBlockOverride>;
  background: SlideDeckBackground;
  animation: { enter?: string; duration_ms?: number };
  transition: string;
  settings: Record<string, unknown>;
};

export type DeckSettings = {
  theme?: Record<string, unknown>;
  transition?: string;
  // Auto-play: milliseconds between slides, 0 = off.
  auto_advance?: number;
  loop?: boolean;
  show_arrows?: boolean;
  [key: string]: unknown;
};

export type SlideDeck = {
  id: number;
  is_published: boolean;
  version: number;
  settings: DeckSettings;
  slides: DeckSlide[];
};

export type DeckBlockOption = {
  id: number;
  type: string;
  label: string | null;
};

export type DeckCreatableType = { type: string; label: string };

export type SlideDeckMeta = {
  link_id: number;
  alias: string;
  public_url: string;
  mode: string;
  blocks: DeckBlockOption[];
  creatable_types: DeckCreatableType[];
};

export type SlideDeckEditor = { deck: SlideDeck; meta: SlideDeckMeta };

export type SlideDeckSavePayload = {
  settings: DeckSettings;
  is_published?: boolean;
  slides: DeckSlide[];
};

export async function getSlideDeck(id: number): Promise<SlideDeckEditor> {
  const res = await apiFetch<{ data: SlideDeckEditor }>(`/links/${id}/slides`);
  return res.data;
}

export async function saveSlideDeck(
  id: number,
  payload: SlideDeckSavePayload,
): Promise<SlideDeckEditor> {
  const res = await apiFetch<{ data: SlideDeckEditor }>(
    `/links/${id}/slides`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
  );
  return res.data;
}
