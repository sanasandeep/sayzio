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
  { key: "classic",          name: "Classic",        tags: ["minimal"],                  preview: { bg: "#1a1a2e", text: "#fff", radius: 12, border: "#ffffff20" } },
  { key: "minimal_mono",     name: "Minimal Mono",   tags: ["minimal", "editorial"],     preview: { bg: "transparent", text: "#fff", radius: 0 } },
  { key: "glass_card",       name: "Glass Card",     tags: ["glass", "pro"],             preview: { bg: "rgba(255,255,255,0.06)", text: "#fff", radius: 20, border: "#ffffff30" } },
  { key: "frosted_pill",     name: "Frosted Pill",   tags: ["glass", "minimal"],         preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 999, border: "#ffffff40" } },
  { key: "neon_outline",     name: "Neon Outline",   tags: ["neon", "dark", "bold"],     preview: { bg: "#0a0a0a", text: "#a78bfa", radius: 12, border: "#8b5cf6" } },
  { key: "brutalist",        name: "Brutalist",      tags: ["brutalist", "bold"],        preview: { bg: "#fff", text: "#000", radius: 0, border: "#000" } },
  { key: "soft_pastel",      name: "Soft Pastel",    tags: ["playful", "minimal"],       preview: { bg: "#fde8ff", text: "#7e22ce", radius: 24 } },
  { key: "editorial_serif",  name: "Editorial",      tags: ["editorial", "pro"],         preview: { bg: "transparent", text: "#fff", radius: 0 } },
  { key: "retro_sticker",    name: "Retro Sticker",  tags: ["retro", "playful"],         preview: { bg: "#fef3c7", text: "#78350f", radius: 14, border: "#92400e" } },
  { key: "three_d_layered",  name: "3D Layered",     tags: ["three_d", "bold"],          preview: { bg: "#7c3aed", text: "#fff", radius: 16 } },
  { key: "handwritten_note", name: "Handwritten",    tags: ["handwritten", "playful"],   preview: { bg: "#fffaf0", text: "#1f2937", radius: 8, border: "#1f2937", dashed: true } },
  { key: "gradient_pop",     name: "Gradient Pop",   tags: ["bold", "playful"],          preview: { bg: "#ec4899", text: "#fff", radius: 16 } },
];

/**
 * Reusable variant bundles, mirroring the PHP `bundles()` map. Mobile
 * only needs the preview hints the gallery thumbnails consume — the
 * full style payloads live on the web side and are pulled by the
 * renderer through `_style` overrides set when the variant is applied.
 */
const BUNDLES: Record<string, MobileVariant[]> = {
  link_actions: [
    { key: "corporate_row", name: "Corporate Row", tags: ["corporate", "minimal", "pro"], preview: { bg: "#ffffff", text: "#111827", radius: 6, border: "#e5e7eb" } },
    { key: "cta_glow",      name: "CTA Glow",      tags: ["neon", "bold"],                preview: { bg: "#0f172a", text: "#67e8f9", radius: 14, border: "#22d3ee" } },
    { key: "y2k_chrome",    name: "Y2K Chrome",    tags: ["y2k", "retro", "three_d"],     preview: { bg: "#c0c0d8", text: "#1e1b4b", radius: 999, border: "#7280a8" } },
  ],
  headings: [
    { key: "magazine_title", name: "Magazine Title", tags: ["editorial", "pro"],   preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "underline_band", name: "Underline Band", tags: ["minimal", "editorial"], preview: { bg: "transparent", text: "#fff", radius: 0, border: "#a78bfa" } },
    { key: "spotlight_band", name: "Spotlight Band", tags: ["bold", "three_d"],   preview: { bg: "#1e1b4b", text: "#fff", radius: 4 } },
  ],
  body_text: [
    { key: "manuscript",  name: "Manuscript",  tags: ["editorial", "minimal"],   preview: { bg: "transparent", text: "#e5e7eb", radius: 0 } },
    { key: "sticky_note", name: "Sticky Note", tags: ["playful", "handwritten"], preview: { bg: "#fef08a", text: "#422006", radius: 4 } },
  ],
  socials: [
    { key: "icon_pills",   name: "Icon Pills",   tags: ["playful", "glass"],          preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 999, border: "#ffffff30" } },
    { key: "mono_chrome",  name: "Mono Chrome",  tags: ["minimal", "corporate"],      preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "rainbow_row",  name: "Rainbow Row",  tags: ["maximalist", "playful", "bold"], preview: { bg: "#ec4899", text: "#fff", radius: 20 } },
  ],
  video: [
    { key: "cinema_strip",    name: "Cinema Strip",    tags: ["dark", "editorial", "pro"], preview: { bg: "#000", text: "#fafaf9", radius: 4, border: "#000" } },
    { key: "crt_screen",      name: "CRT Screen",      tags: ["retro", "y2k", "dark"],     preview: { bg: "#0a0a14", text: "#86efac", radius: 24, border: "#1f2937" } },
    { key: "broadcast_card",  name: "Broadcast Card",  tags: ["corporate", "pro", "minimal"], preview: { bg: "#0f172a", text: "#f1f5f9", radius: 10, border: "#1e293b" } },
  ],
  embed: [
    { key: "window_chrome", name: "Window Chrome", tags: ["corporate", "minimal"], preview: { bg: "#ffffff", text: "#111827", radius: 10, border: "#d1d5db" } },
    { key: "terminal",      name: "Terminal",      tags: ["dark", "brutalist"],    preview: { bg: "#020617", text: "#86efac", radius: 6, border: "#22c55e" } },
  ],
  form: [
    { key: "paper_form",  name: "Paper Form",  tags: ["minimal", "corporate", "pro"], preview: { bg: "#ffffff", text: "#111827", radius: 8, border: "#e5e7eb" } },
    { key: "pop_form",    name: "Pop Form",    tags: ["bold", "playful"],             preview: { bg: "#fef3c7", text: "#7c2d12", radius: 18, border: "#7c2d12" } },
    { key: "glass_inbox", name: "Glass Inbox", tags: ["glass", "pro"],                preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 20, border: "#ffffff40" } },
  ],
  gallery: [
    { key: "contact_sheet",       name: "Contact Sheet",       tags: ["editorial", "retro"],            preview: { bg: "#1c1917", text: "#fafaf9", radius: 4, border: "#1c1917" } },
    { key: "maximalist_collage",  name: "Maximalist Collage",  tags: ["maximalist", "bold", "playful"], preview: { bg: "#fb7185", text: "#1e1b4b", radius: 20, border: "#facc15" } },
  ],
  music: [
    { key: "vinyl",       name: "Vinyl",        tags: ["retro", "three_d"],     preview: { bg: "#0a0a0a", text: "#fafaf9", radius: 999, border: "#27272a" } },
    { key: "cassette",    name: "Cassette",     tags: ["y2k", "retro", "playful"], preview: { bg: "#fbbf24", text: "#7c2d12", radius: 6, border: "#7c2d12" } },
    { key: "studio_dark", name: "Studio Dark",  tags: ["dark", "pro", "minimal"], preview: { bg: "#0f172a", text: "#cbd5e1", radius: 14, border: "#1e293b" } },
  ],
  calendar: [
    { key: "agenda_card",     name: "Agenda Card",     tags: ["corporate", "minimal", "pro"], preview: { bg: "#ffffff", text: "#111827", radius: 10, border: "#e5e7eb" } },
    { key: "studio_booking",  name: "Studio Booking",  tags: ["editorial", "pro"],            preview: { bg: "#0c0a09", text: "#fafaf9", radius: 6, border: "#292524" } },
  ],
  tip: [
    { key: "tip_jar", name: "Tip Jar", tags: ["playful", "retro"], preview: { bg: "#fef3c7", text: "#7c2d12", radius: 18, border: "#b45309" } },
  ],
  commerce: [
    { key: "boutique_tag",    name: "Boutique Tag",    tags: ["editorial", "pro", "minimal"], preview: { bg: "#fafaf9", text: "#1c1917", radius: 4, border: "#d6d3d1" } },
    { key: "maximalist_card", name: "Maximalist Card", tags: ["maximalist", "bold"],          preview: { bg: "#7c3aed", text: "#fef3c7", radius: 22, border: "#facc15" } },
  ],
  timeline: [
    { key: "milestone_rail", name: "Milestone Rail", tags: ["corporate", "minimal"], preview: { bg: "rgba(255,255,255,0.04)", text: "#f1f5f9", radius: 10, border: "#ffffff30" } },
  ],
};

const TYPE_BUNDLES: Record<string, string[]> = {
  link: ["link_actions"],
  link_big: ["link_actions", "headings"],
  featured_pin: ["link_actions"],
  cta_button: ["link_actions"],
  external_item: ["link_actions"],

  heading: ["headings"],
  heading_logo: ["headings"],
  verified_heading: ["headings"],

  paragraph: ["body_text"],
  paragraph_rich: ["body_text"],
  markdown: ["body_text"],
  list: ["body_text"],
  list_numbered: ["body_text"],
  list_pricing: ["body_text", "commerce"],

  socials: ["socials"],
  socials_multi: ["socials"],
  socials_custom: ["socials"],
  instagram_media: ["embed", "socials"],
  latest_instagram: ["embed", "socials"],
  tiktok_profile: ["embed", "socials"],
  twitter_profile: ["embed", "socials"],
  pinterest_profile: ["embed", "socials"],
  snapchat: ["embed", "socials"],
  twitter_tweet: ["embed"],

  video: ["video"],
  header_video: ["video"],
  youtube: ["video"],
  youtube_feed: ["video"],
  latest_youtube: ["video"],
  vimeo: ["video"],
  twitch: ["video"],
  kick: ["video"],
  rumble_video: ["video"],
  vk_video: ["video"],
  tiktok_video: ["video"],
  twitter_video: ["video"],

  iframe_embed: ["embed"],
  custom_html: ["embed"],
  facebook_post: ["embed"],
  reddit_post: ["embed"],
  telegram_post: ["embed"],
  discord_server: ["embed"],

  form: ["form"],
  contact_form: ["form"],
  email_collector: ["form"],
  email_subscribe: ["form"],
  phone_collector: ["form"],
  direct_message: ["form"],
  whatsapp_widget: ["form"],
  whatsapp_channel_subscribe: ["form"],
  whatsapp_number_subscribe: ["form"],
  typeform: ["form", "embed"],

  image_grid: ["gallery"],
  image_slider: ["gallery"],
  image_slider_v2: ["gallery"],

  spotify: ["music"],
  apple_music: ["music"],
  soundcloud: ["music"],
  tidal: ["music"],
  mixcloud: ["music"],
  anchor_fm: ["music"],
  audio: ["music"],

  calendly: ["calendar"],
  calendly_embed: ["calendar"],

  donation: ["tip"],
  buy_me_coffee: ["tip"],
  patreon: ["tip"],
  ko_fi: ["tip"],
  paypal: ["tip", "form"],

  product: ["commerce"],
  service: ["commerce"],
  catalog: ["commerce"],
  market: ["commerce"],
  price: ["commerce"],

  timeline: ["timeline"],
  timeline_staged: ["timeline"],
  roadmap: ["timeline"],
};

/**
 * Per-type one-off extras kept for backwards compatibility with blocks
 * that picked these keys before bundles existed. Mirrors the PHP
 * `typeOneOffs()` map so a creator who picked "Polaroid" or "Quote
 * Card" on the web sees the same key marked as selected on mobile.
 */
const TYPE_ONE_OFFS: Record<string, MobileVariant[]> = {
  image: [
    { key: "polaroid",         name: "Polaroid",         tags: ["retro", "playful"],              preview: { bg: "#fff", text: "#000", radius: 6 } },
    { key: "magazine_cutout",  name: "Magazine Cutout",  tags: ["maximalist", "editorial", "playful"], preview: { bg: "#ffffff", text: "#000", radius: 0, border: "#facc15" } },
  ],
  avatar: [
    { key: "ring_glow",  name: "Ring Glow",  tags: ["neon", "bold"],         preview: { bg: "transparent", text: "#a78bfa", radius: 999, border: "#a78bfa" } },
    { key: "mono_frame", name: "Mono Frame", tags: ["corporate", "minimal"], preview: { bg: "transparent", text: "#fff", radius: 999, border: "#e5e7eb" } },
  ],
  product: [
    { key: "price_tag", name: "Price Tag", tags: ["retro", "bold"], preview: { bg: "#fef3c7", text: "#7f1d1d", radius: 4, border: "#dc2626" } },
  ],
  coupon: [
    { key: "neon_ticket",  name: "Neon Ticket",  tags: ["neon", "bold"],            preview: { bg: "#0a0a14", text: "#67e8f9", radius: 12, border: "#22d3ee", dashed: true } },
    { key: "y2k_voucher",  name: "Y2K Voucher",  tags: ["y2k", "retro", "playful"], preview: { bg: "#a5f3fc", text: "#581c87", radius: 14, border: "#7c3aed", dashed: true } },
  ],
  testimonials: [
    { key: "quote_card",  name: "Quote Card",  tags: ["editorial", "pro"],     preview: { bg: "#1e1b4b", text: "#e0e7ff", radius: 20 } },
    { key: "sticky_quote", name: "Sticky Quote", tags: ["handwritten", "playful"], preview: { bg: "#fef08a", text: "#422006", radius: 4 } },
  ],
  faq: [
    { key: "paper_card",   name: "Paper Card",     tags: ["minimal", "pro"],              preview: { bg: "#fafaf9", text: "#1c1917", radius: 12, border: "#e7e5e4" } },
    { key: "corporate_qa", name: "Corporate Q&A",  tags: ["corporate", "minimal", "pro"], preview: { bg: "#ffffff", text: "#111827", radius: 6, border: "#d1d5db" } },
  ],
  faq_v2: [
    { key: "corporate_qa", name: "Corporate Q&A", tags: ["corporate", "minimal", "pro"], preview: { bg: "#ffffff", text: "#111827", radius: 6, border: "#d1d5db" } },
  ],
  countdown: [
    { key: "flip_clock",  name: "Flip Clock",  tags: ["retro", "three_d"],       preview: { bg: "#0a0a0a", text: "#fbbf24", radius: 8, border: "#27272a" } },
    { key: "pixel_clock", name: "Pixel Clock", tags: ["y2k", "retro", "playful"], preview: { bg: "#0f172a", text: "#a5f3fc", radius: 4, border: "#22d3ee" } },
  ],
  cta_button: [
    { key: "big_action", name: "Big Action", tags: ["bold", "three_d"], preview: { bg: "#ef4444", text: "#fff", radius: 14 } },
  ],
};

/** Returns variants offered for a given block type, mirroring the PHP
 *  `BlockVariantCatalog::forType`. Common variants come first, then
 *  bundle entries, then one-offs; later duplicates of the same key are
 *  dropped so a saved variant key is always resolvable. */
export function variantsForType(type: string): MobileVariant[] {
  const out: MobileVariant[] = [...COMMON];
  for (const bundleId of TYPE_BUNDLES[type] ?? []) {
    for (const v of BUNDLES[bundleId] ?? []) out.push(v);
  }
  for (const v of TYPE_ONE_OFFS[type] ?? []) out.push(v);

  const seen = new Set<string>();
  return out.filter((v) => {
    if (seen.has(v.key)) return false;
    seen.add(v.key);
    return true;
  });
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
