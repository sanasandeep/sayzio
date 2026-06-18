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
import { canonicalBlockType } from "./blockTypeRegistry";

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
    serif?: boolean;
  };
  // Profile-card identity designs carry a structural `_profile_layout`
  // token (mirrors the web `profile_identity` bundle). When present, the
  // editor stamps it into `_style._profile_layout` on apply so the public
  // renderer can dispatch on the chosen layout. Other bundles leave this
  // undefined.
  profileLayout?: string;
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

  // ─── Task #1041: expanded link-in-bio shape library ────────────────
  // Mirror of the PHP `link_shapes`, `heading_styles`, `gallery_layouts`,
  // `social_sets`, and `cover_profile` bundles. Mobile only needs the
  // preview hint per variant — the web catalog still owns the full
  // style payload. Variant keys MUST match `BlockVariantCatalog.php`.
  link_shapes: [
    { key: "pill_gradient",        name: "Gradient Pill",       tags: ["bold", "playful"],            preview: { bg: "#ec4899", text: "#fff", radius: 999 } },
    { key: "pill_dotted",          name: "Dotted Pill",         tags: ["playful", "handwritten"],     preview: { bg: "transparent", text: "#fff", radius: 999, border: "#ffffffaa" } },
    { key: "square_double",        name: "Double Border",       tags: ["editorial", "pro"],           preview: { bg: "#ffffff", text: "#111827", radius: 4, border: "#1f2937" } },
    { key: "tab_underline",        name: "Tab Underline",       tags: ["minimal", "editorial"],       preview: { bg: "transparent", text: "#fff", radius: 0, border: "#a78bfa" } },
    { key: "card_lifted",          name: "Lifted Card",         tags: ["three_d", "pro"],             preview: { bg: "#ffffff", text: "#111827", radius: 14 } },
    { key: "card_arch",            name: "Arch Card",           tags: ["playful", "editorial"],       preview: { bg: "#fafaf9", text: "#1c1917", radius: 32, border: "#e7e5e4" } },
    { key: "square_neumorphic",    name: "Soft Neumorphic",     tags: ["minimal", "three_d"],         preview: { bg: "#1a1a2e", text: "#cbd5e1", radius: 20 } },
    { key: "pill_glass_dark",      name: "Dark Glass Pill",     tags: ["glass", "dark", "pro"],       preview: { bg: "rgba(0,0,0,0.4)", text: "#fff", radius: 999, border: "#ffffff22" } },
    { key: "image_cover_dark",     name: "Cover · Dark Overlay",tags: ["dark", "editorial", "maximalist"], preview: { bg: "#0a0612", text: "#fff", radius: 20 } },
    { key: "image_cover_polaroid", name: "Cover · Polaroid",    tags: ["retro", "playful", "editorial"],   preview: { bg: "#ffffff", text: "#1f2937", radius: 6, border: "#ffffff" } },
    { key: "image_cover_neon",     name: "Cover · Neon Frame",  tags: ["neon", "bold", "maximalist"], preview: { bg: "#0b0420", text: "#a5f3fc", radius: 14, border: "#22d3ee" } },
    { key: "image_cover_arch",     name: "Cover · Arch",        tags: ["editorial", "pro", "minimal"],preview: { bg: "#1a1a2e", text: "#fff", radius: 40 } },
  ],
  // Mirror of the PHP `link_buttons` bundle (icon & image placement). The
  // mobile renderer doesn't honor the `link_layout` placement token yet,
  // so applying these on mobile degrades to the variant's colours; the
  // keys are mirrored here so the gallery selected-state stays in sync.
  link_buttons: [
    { key: "icon_left_solid",    name: "Icon Left",            tags: ["minimal", "bold"],     preview: { bg: "#7c3aed", text: "#fff", radius: 14 } },
    { key: "icon_right_solid",   name: "Icon Right",           tags: ["minimal", "bold"],     preview: { bg: "#2563eb", text: "#fff", radius: 14 } },
    { key: "icon_both_solid",    name: "Icon Both Sides",      tags: ["bold", "pro"],         preview: { bg: "#0f172a", text: "#fff", radius: 14 } },
    { key: "icon_only_dark",     name: "Icon Only",            tags: ["minimal", "bold"],     preview: { bg: "#111827", text: "#fff", radius: 12 } },
    { key: "icon_circle_left",   name: "Icon Circle Left",     tags: ["minimal", "pro"],      preview: { bg: "#ffffff", text: "#111827", radius: 14, border: "#2563eb" } },
    { key: "icon_circle_right",  name: "Icon Circle Right",    tags: ["minimal", "pro"],      preview: { bg: "#ffffff", text: "#111827", radius: 14, border: "#16a34a" } },
    { key: "icon_box_purple",    name: "Icon in Box",          tags: ["pro", "corporate"],    preview: { bg: "#ffffff", text: "#111827", radius: 14, border: "#6d28d9" } },
    { key: "icon_box_solid",     name: "Solid Icon Box",       tags: ["bold", "playful"],     preview: { bg: "#fff7ed", text: "#7c2d12", radius: 14, border: "#f97316" } },
    { key: "gradient_icon_left", name: "Gradient · Icon Left", tags: ["bold", "playful"],     preview: { bg: "#ec4899", text: "#fff", radius: 999 } },
    { key: "gradient_icon_right",name: "Gradient · Icon Right",tags: ["bold", "playful"],     preview: { bg: "#22d3ee", text: "#fff", radius: 999 } },
    { key: "outline_icon_left",  name: "Outline · Icon Left",  tags: ["minimal", "pro"],      preview: { bg: "transparent", text: "#7c3aed", radius: 12, border: "#7c3aed" } },
    { key: "outline_icon_right", name: "Outline · Icon Right", tags: ["minimal", "pro"],      preview: { bg: "transparent", text: "#2563eb", radius: 12, border: "#2563eb" } },
    { key: "transparent_icon",   name: "Transparent",          tags: ["minimal"],             preview: { bg: "transparent", text: "#fff", radius: 14, border: "#ffffff55" } },
    { key: "dotted_icon",        name: "Dotted Border",        tags: ["playful", "editorial"],preview: { bg: "transparent", text: "#7c3aed", radius: 14, border: "#7c3aed", dashed: true } },
    { key: "image_left",         name: "Image Left",           tags: ["pro", "editorial"],    preview: { bg: "#ffffff", text: "#111827", radius: 14 } },
    { key: "image_right",        name: "Image Right",          tags: ["pro", "editorial"],    preview: { bg: "#ffffff", text: "#111827", radius: 14 } },
    { key: "image_top",          name: "Image Top",            tags: ["maximalist", "editorial"], preview: { bg: "#1a1a2e", text: "#fff", radius: 16 } },
    { key: "image_icon_rounded", name: "Rounded Image Icon",   tags: ["minimal", "pro"],      preview: { bg: "#ffffff", text: "#111827", radius: 14 } },
    { key: "image_icon_square",  name: "Square Image Icon",    tags: ["minimal", "corporate"],preview: { bg: "#ffffff", text: "#111827", radius: 8 } },
    { key: "image_icon_circle",  name: "Circular Image Icon",  tags: ["minimal", "playful"],  preview: { bg: "#ffffff", text: "#111827", radius: 999 } },
  ],
  heading_styles: [
    { key: "oversize_serif", name: "Oversize Serif", tags: ["editorial", "pro"],     preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "gradient_swipe", name: "Gradient Swipe", tags: ["bold", "playful"],      preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "neon_glitch",    name: "Neon Glitch",    tags: ["neon", "y2k", "bold"],  preview: { bg: "transparent", text: "#5eead4", radius: 0 } },
    { key: "typewriter",     name: "Typewriter",     tags: ["minimal", "retro"],     preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "wave_letters",   name: "Wave Letters",   tags: ["playful", "maximalist"],preview: { bg: "transparent", text: "#fbbf24", radius: 0 } },
    { key: "extrude_3d",     name: "3D Extrude",     tags: ["three_d", "bold"],      preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "ticker_marquee", name: "Ticker Marquee", tags: ["retro", "bold"],        preview: { bg: "#0f172a", text: "#fbbf24", radius: 4, border: "#1e293b" } },
    { key: "fade_in_up",     name: "Fade In",        tags: ["minimal", "pro"],       preview: { bg: "transparent", text: "#fff", radius: 0 } },
  ],
  gallery_layouts: [
    { key: "grid_two",          name: "Grid · 2 Up",        tags: ["minimal", "editorial"],   preview: { bg: "transparent", text: "#fff", radius: 8 } },
    { key: "grid_three",        name: "Grid · 3 Up",        tags: ["minimal"],                preview: { bg: "transparent", text: "#fff", radius: 6 } },
    { key: "grid_four",         name: "Grid · 4 Up",        tags: ["minimal", "corporate"],   preview: { bg: "transparent", text: "#fff", radius: 4 } },
    { key: "masonry",           name: "Masonry",            tags: ["editorial", "maximalist"],preview: { bg: "transparent", text: "#fff", radius: 8 } },
    { key: "carousel_peek",     name: "Carousel · Peek",    tags: ["playful", "pro"],         preview: { bg: "transparent", text: "#fff", radius: 14 } },
    { key: "stacked_polaroids", name: "Stacked Polaroids",  tags: ["retro", "playful"],       preview: { bg: "#ffffff", text: "#1f2937", radius: 6, border: "#ffffff" } },
    { key: "marquee_strip",     name: "Marquee Strip",      tags: ["bold", "maximalist"],     preview: { bg: "#0a0a14", text: "#fff", radius: 0 } },
    { key: "lightbox_grid",     name: "Lightbox Grid",      tags: ["pro", "minimal"],         preview: { bg: "#0a0a14", text: "#fff", radius: 10, border: "#ffffff15" } },
  ],
  social_sets: [
    { key: "mono_line",       name: "Mono · Line",      tags: ["minimal", "editorial"],     preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "mono_solid",      name: "Mono · Solid",     tags: ["minimal", "corporate"],     preview: { bg: "rgba(255,255,255,0.1)", text: "#fff", radius: 12 } },
    { key: "sketch",          name: "Sketch",           tags: ["handwritten", "playful"],   preview: { bg: "#fffaf0", text: "#1f2937", radius: 14, border: "#1f2937", dashed: true } },
    { key: "brand_color",     name: "Brand Color",      tags: ["playful", "bold"],          preview: { bg: "transparent", text: "#fff", radius: 12 } },
    { key: "tile_brand",      name: "Brand Tiles",      tags: ["bold", "maximalist"],       preview: { bg: "#0a0a14", text: "#fff", radius: 14, border: "#ffffff15" } },
    { key: "wordmark",        name: "Wordmark",         tags: ["editorial", "pro"],         preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "glassy",          name: "Glassy",           tags: ["glass", "pro"],             preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 20, border: "#ffffff40" } },
    { key: "neon_pop",        name: "Neon Pop",         tags: ["neon", "dark", "bold"],     preview: { bg: "#0a0a0a", text: "#a78bfa", radius: 14, border: "#a78bfa" } },
    { key: "animated_pulse",  name: "Animated Pulse",   tags: ["playful", "three_d"],       preview: { bg: "rgba(236,72,153,0.13)", text: "#ec4899", radius: 999 } },
  ],
  cover_profile: [
    { key: "cover_aurora",     name: "Aurora Cover",     tags: ["bold", "playful"],            preview: { bg: "#7c3aed", text: "#fff", radius: 20 } },
    { key: "cover_editorial",  name: "Editorial Cover",  tags: ["editorial", "pro", "minimal"],preview: { bg: "#fafaf9", text: "#1c1917", radius: 12, border: "#e7e5e4" } },
    { key: "cover_dark_neon",  name: "Dark Neon Cover",  tags: ["neon", "dark", "bold"],       preview: { bg: "#05010f", text: "#a78bfa", radius: 20, border: "#a78bfa" } },
    { key: "cover_glass",      name: "Glass Cover",      tags: ["glass", "pro"],               preview: { bg: "rgba(255,255,255,0.08)", text: "#fff", radius: 24, border: "#ffffff40" } },
    { key: "cover_brutalist",  name: "Brutalist Cover",  tags: ["brutalist", "bold"],          preview: { bg: "#ffffff", text: "#000", radius: 0, border: "#000" } },
    { key: "cover_y2k",        name: "Y2K Cover",        tags: ["y2k", "retro", "playful"],    preview: { bg: "#a5f3fc", text: "#1e1b4b", radius: 24, border: "#7c3aed" } },
  ],

  // ─── Task #1741: profile-card identity designs ─────────────────────
  // Mirror of the PHP `profile_identity` bundle. Each variant carries a
  // `profileLayout` token the editor stamps into `_style._profile_layout`
  // on apply; the public renderer dispatches on it to pick one of the ten
  // structural layouts. Variant keys MUST match `BlockVariantCatalog.php`.
  profile_identity: [
    { key: "identity_classic",      name: "Classic Creator",        tags: ["minimal", "pro"],       preview: { bg: "#ffffff", text: "#0f172a", radius: 20, border: "#e5e7eb" },           profileLayout: "classic_creator" },
    { key: "identity_glass",        name: "Modern Glassmorphism",   tags: ["glass", "pro"],         preview: { bg: "rgba(255,255,255,0.10)", text: "#ffffff", radius: 24, border: "#ffffff40" }, profileLayout: "glass" },
    { key: "identity_cover_hero",   name: "Cover Overlay Hero",     tags: ["bold", "editorial"],    preview: { bg: "#0b0b0f", text: "#ffffff", radius: 20 },                              profileLayout: "cover_hero" },
    { key: "identity_split",        name: "Split Card",             tags: ["minimal", "editorial"], preview: { bg: "#f8fafc", text: "#0f172a", radius: 18, border: "#e2e8f0" },           profileLayout: "split" },
    { key: "identity_floating",     name: "Floating Avatar",        tags: ["playful", "pro"],       preview: { bg: "#ffffff", text: "#0f172a", radius: 22, border: "#e5e7eb" },           profileLayout: "floating" },
    { key: "identity_gradient",     name: "Gradient Identity Card", tags: ["bold", "playful"],      preview: { bg: "linear-gradient(150deg,#7c3aed,#d946ef,#fb7185)", text: "#ffffff", radius: 22 }, profileLayout: "gradient" },
    { key: "identity_founder",      name: "Premium Founder Card",   tags: ["pro", "dark"],          preview: { bg: "#0a0a0c", text: "#d4af37", radius: 20, border: "#d4af37" },           profileLayout: "founder" },
    { key: "identity_minimal_dark", name: "Minimal Dark",           tags: ["minimal", "dark"],      preview: { bg: "#0b0b0f", text: "#ffffff", radius: 18, border: "#ffffff20" },         profileLayout: "minimal_dark" },
    { key: "identity_magazine",     name: "Magazine Layout",        tags: ["editorial", "pro"],     preview: { bg: "#ffffff", text: "#1c1917", radius: 14, border: "#e7e5e4", serif: true }, profileLayout: "magazine" },
    { key: "identity_social",       name: "Social Profile Style",   tags: ["minimal", "pro"],       preview: { bg: "#ffffff", text: "#3b82f6", radius: 18, border: "#e5e7eb" },           profileLayout: "social_profile" },
  ],
};

/**
 * Bundle assignments are keyed by the **canonical** block type. The
 * `variantsForType` helper canonicalizes the lookup key first, so legacy
 * aliases (paragraph, markdown, latest_instagram, link_big, ...) inherit
 * their canonical's bundles without duplicate entries here.
 */
const TYPE_BUNDLES: Record<string, string[]> = {
  link: ["link_actions", "link_shapes", "link_buttons"],

  heading: ["headings", "heading_styles"],

  paragraph_rich: ["body_text"],
  list: ["body_text"],

  socials: ["socials", "social_sets"],
  instagram: ["embed", "socials", "social_sets"],
  tiktok_profile: ["embed", "socials", "social_sets"],
  twitter_profile: ["embed", "socials", "social_sets"],
  pinterest_profile: ["embed", "socials", "social_sets"],
  snapchat: ["embed", "socials", "social_sets"],
  twitter_tweet: ["embed"],

  video: ["video"],
  header_video: ["video"],
  youtube: ["video"],
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

  image_grid: ["gallery", "gallery_layouts"],
  image_slider: ["gallery"],

  profile_card: ["profile_identity", "cover_profile"],

  spotify: ["music"],
  apple_music: ["music"],
  soundcloud: ["music"],
  tidal: ["music"],
  mixcloud: ["music"],
  anchor_fm: ["music"],
  audio: ["music"],

  calendly: ["calendar"],

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

  // ── Task #1090: new canonical block types ────────────────────────
  // Mirrors `BlockTypeRegistry::bundlesForCanonical()` on the PHP side.
  // Bundle keys must match entries in BUNDLES above.
  file_list: ["link_actions", "link_shapes"],
  audio_list: ["music"],
  link_tree_group: ["link_actions", "link_shapes"],
  tabs: ["headings"],
  accordion: ["body_text"],
  event_list: ["calendar"],
  menu: ["commerce", "body_text"],
  testimonial_carousel: ["body_text"],
  stats: ["headings", "body_text"],
  affiliate_links: ["link_actions", "link_shapes", "commerce"],
  booking_slots: ["calendar"],
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
    // Task #1041: mask presets — chrome-only variants. Actual mask
    // shape is applied via `_image_style.mask_shape` on the block.
    { key: "mask_circle",      name: "Mask · Circle",    tags: ["minimal", "editorial"],          preview: { bg: "transparent", text: "#fff", radius: 999 } },
    { key: "mask_arch",        name: "Mask · Arch",      tags: ["editorial", "pro"],              preview: { bg: "transparent", text: "#fff", radius: 40 } },
    { key: "mask_blob",        name: "Mask · Blob",      tags: ["playful", "maximalist"],         preview: { bg: "transparent", text: "#fff", radius: 60 } },
    { key: "mask_hexagon",     name: "Mask · Hexagon",   tags: ["three_d", "bold"],               preview: { bg: "transparent", text: "#fff", radius: 12 } },
    { key: "mask_diamond",     name: "Mask · Diamond",   tags: ["editorial", "minimal"],          preview: { bg: "transparent", text: "#fff", radius: 8 } },
    { key: "mask_star",        name: "Mask · Star",      tags: ["playful", "bold"],               preview: { bg: "transparent", text: "#fbbf24", radius: 8 } },
    { key: "mask_heart",       name: "Mask · Heart",     tags: ["playful", "bold"],               preview: { bg: "transparent", text: "#ec4899", radius: 0 } },
    { key: "mask_torn",        name: "Mask · Torn Edge", tags: ["editorial", "maximalist"],       preview: { bg: "transparent", text: "#fff", radius: 0 } },
    { key: "film_strip",       name: "Film Strip",       tags: ["retro", "editorial"],            preview: { bg: "#0a0a0a", text: "#fafaf9", radius: 4, border: "#0a0a0a" } },
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
  const canonical = canonicalBlockType(type);
  const out: MobileVariant[] = [...COMMON];
  for (const bundleId of TYPE_BUNDLES[canonical] ?? []) {
    for (const v of BUNDLES[bundleId] ?? []) out.push(v);
  }
  // One-offs key off the raw stored type so legacy entries (cta_button,
  // faq_v2) still resolve their own special variants.
  for (const v of TYPE_ONE_OFFS[type] ?? TYPE_ONE_OFFS[canonical] ?? []) out.push(v);

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
