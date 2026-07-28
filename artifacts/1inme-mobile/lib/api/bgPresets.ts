import { apiFetch } from "@/lib/api";

// Background preset catalog for the biolink Appearance "Presets" picker.
// Mirrors the web preset gallery (BgPresetCatalog): a static catalog of
// swatches grouped into Gradients / Abstract / Patterns. The server parses
// each preset's CSS into an ordered `colors` array so React Native can
// approximate the swatch with a LinearGradient (RN can't render raw CSS
// background strings).

export type BgPresetGroup = { key: string; label: string };

export type BgPreset = {
  key: string;
  group: string;
  label: string;
  css: string;
  colors: string[];
  /**
   * Public path of the pre-rendered PNG thumbnail showing the preset's REAL
   * CSS texture (stripes, dots, blend-mode abstracts), or null when the
   * server has no up-to-date thumbnail — fall back to the `colors`
   * LinearGradient approximation.
   */
  swatch?: string | null;
};

export type BgPresetCatalog = {
  groups: BgPresetGroup[];
  presets: BgPreset[];
};

export async function getBgPresets(): Promise<BgPresetCatalog> {
  const res = await apiFetch<{ data: BgPresetCatalog }>("/bg-presets");
  return res.data;
}
