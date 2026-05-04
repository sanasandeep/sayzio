/**
 * Mobile-side mirror of `app/Modules/User/Support/BlockVariantCatalog.php`.
 *
 * The mobile renderer doesn't yet honor every CSS property the web editor
 * supports, so we keep this catalog deliberately small and focused on the
 * surface props that translate cleanly to React Native: background color,
 * text color, border (color/width/style), corner radius, and a single
 * shadow tier. Variant keys MUST match the web catalog so a creator who
 * picks "Glass Card" in the web editor sees the same key marked as
 * selected on mobile.
 */
export type MobileVariant = {
  key: string;
  name: string;
  tags: string[];
  preview: {
    bg: string;
    text: string;
    radius: number;
    border?: string;
    dashed?: boolean;
  };
};

const COMMON: MobileVariant[] = [
  { key: "classic",          name: "Classic",        tags: ["minimal"],            preview: { bg: "#1a1a2e", text: "#fff", radius: 12, border: "#ffffff20" } },
  { key: "minimal_mono",     name: "Minimal Mono",   tags: ["minimal"],            preview: { bg: "transparent", text: "#fff", radius: 0 } },
  { key: "glass_card",       name: "Glass Card",     tags: ["glass", "pro"],       preview: { bg: "rgba(255,255,255,0.06)", text: "#fff", radius: 20, border: "#ffffff30" } },
  { key: "frosted_pill",     name: "Frosted Pill",   tags: ["glass", "minimal"],   preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 999, border: "#ffffff40" } },
  { key: "neon_outline",     name: "Neon Outline",   tags: ["neon", "bold"],       preview: { bg: "#0a0a0a", text: "#a78bfa", radius: 12, border: "#8b5cf6" } },
  { key: "brutalist",        name: "Brutalist",      tags: ["brutalist", "bold"],  preview: { bg: "#fff", text: "#000", radius: 0, border: "#000" } },
  { key: "soft_pastel",      name: "Soft Pastel",    tags: ["playful"],            preview: { bg: "#fde8ff", text: "#7e22ce", radius: 24 } },
  { key: "editorial_serif",  name: "Editorial",      tags: ["editorial"],          preview: { bg: "transparent", text: "#fff", radius: 0 } },
  { key: "retro_sticker",    name: "Retro Sticker",  tags: ["retro", "playful"],   preview: { bg: "#fef3c7", text: "#78350f", radius: 14, border: "#92400e" } },
  { key: "three_d_layered",  name: "3D Layered",     tags: ["bold"],               preview: { bg: "#7c3aed", text: "#fff", radius: 16 } },
  { key: "handwritten_note", name: "Handwritten",    tags: ["playful"],            preview: { bg: "#fffaf0", text: "#1f2937", radius: 8, border: "#1f2937", dashed: true } },
  { key: "gradient_pop",     name: "Gradient Pop",   tags: ["bold", "playful"],    preview: { bg: "#ec4899", text: "#fff", radius: 16 } },
];

/** Returns variants offered for a given block type. Mobile only ships the
 *  common set today; per-type extras can be added here as the renderer
 *  learns to draw them. */
export function variantsForType(_type: string): MobileVariant[] {
  return COMMON;
}

export function findVariant(type: string, key: string): MobileVariant | undefined {
  return variantsForType(type).find((v) => v.key === key);
}

/**
 * Translates a stored `_variant` key (or raw `_style` overrides) into the
 * subset of React Native style props the renderer actually applies. Returns
 * `null` when there's nothing to overlay so the caller can fall back to the
 * theme defaults without an empty-object branch.
 */
export function variantOverlay(
  type: string,
  settings: Record<string, unknown> | null,
): {
  backgroundColor?: string;
  borderColor?: string;
  borderWidth?: number;
  borderRadius?: number;
  borderStyle?: "solid" | "dashed" | "dotted";
  textColor?: string;
} | null {
  if (!settings) return null;
  const style = (settings._style as Record<string, unknown> | undefined) ?? {};
  const variantKey = typeof style._variant === "string" ? style._variant : "";
  const variant = variantKey ? findVariant(type, variantKey) : undefined;

  // Prefer explicit _style overrides (set by the web editor) over the
  // catalog preview hint — the latter is just a thumbnail approximation.
  const bg = (style.bg_color as string) || variant?.preview.bg;
  const borderColor = (style.border_color as string) || variant?.preview.border;
  const borderStyleRaw = (style.border_style as string) || (variant?.preview.dashed ? "dashed" : undefined);
  const radiusRaw = style.border_radius ?? variant?.preview.radius;
  const textColor = (style.text_color as string) || variant?.preview.text;

  const out: ReturnType<typeof variantOverlay> = {};
  if (bg && bg !== "transparent") out!.backgroundColor = bg;
  if (borderColor) {
    out!.borderColor = borderColor;
    out!.borderWidth = Number(style.border_width ?? 1) || 1;
  }
  if (borderStyleRaw === "dashed" || borderStyleRaw === "dotted" || borderStyleRaw === "solid") {
    out!.borderStyle = borderStyleRaw;
  }
  if (radiusRaw != null && radiusRaw !== "") {
    const n = Number(radiusRaw);
    if (Number.isFinite(n)) out!.borderRadius = Math.min(n, 999);
  }
  if (textColor) out!.textColor = textColor;

  return Object.keys(out!).length === 0 ? null : out;
}
