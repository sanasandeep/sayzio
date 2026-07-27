import { apiFetch } from "@/lib/api";

// Background template catalog for the biolink Appearance "Templates"
// picker. Mirrors the web template gallery (admin-managed `bg_templates`
// rows): animated/gradient/mesh/pattern/svg/neon backgrounds built from
// class-based CSS that React Native can't render. The server parses each
// template's CSS into an ordered `colors` array for a LinearGradient
// approximation, and advertises a pre-rendered PNG `swatch` of the real
// texture when an up-to-date thumbnail exists (md5-gated manifest).

export type BgTemplateCategory = { key: string; label: string };

export type BgTemplate = {
  id: number;
  slug: string;
  name: string;
  category: string;
  colors: string[];
  /**
   * Public path of the pre-rendered PNG thumbnail showing the template's
   * REAL CSS texture, or null when the server has no up-to-date thumbnail
   * — fall back to the `colors` LinearGradient approximation.
   */
  swatch?: string | null;
};

export type BgTemplateCatalog = {
  categories: BgTemplateCategory[];
  templates: BgTemplate[];
};

export async function getBgTemplates(): Promise<BgTemplateCatalog> {
  const res = await apiFetch<{ data: BgTemplateCatalog }>("/bg-templates");
  return res.data;
}
