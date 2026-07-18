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
};

export type BgPresetCatalog = {
  groups: BgPresetGroup[];
  presets: BgPreset[];
};

export async function getBgPresets(): Promise<BgPresetCatalog> {
  const res = await apiFetch<{ data: BgPresetCatalog }>("/bg-presets");
  return res.data;
}
