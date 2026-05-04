/**
 * Mobile-side mirror of `app/Modules/User/Support/BlockTypeRegistry.php`
 * (Task #1090). Keep in sync by hand — there's no codegen for this file
 * yet, but the test in the PHP registry's docblock gives the contract:
 *
 *   - ALIASES collapse legacy types onto a canonical type plus optional
 *     mode/size/layout pre-fills. Renderer/storage still use the raw
 *     legacy type; the alias map is what the editor / picker / variant
 *     bundle lookup consult to present a unified UI.
 *   - NEW_TYPES adds the canonical types this task introduces (file_list,
 *     audio_list, ...). Web ships matching renderer partials.
 *   - META exposes the modes/sizes/layouts a canonical type's editor
 *     should expose. The mobile editor doesn't yet build a full
 *     consolidated picker UI — this metadata is a stepping stone.
 *
 * Web3 blocks (`nft_gallery`, `crypto_address`) are intentionally out of
 * scope.
 */

export const BLOCK_REGISTRY_VERSION = 1;

export type AliasDescriptor = {
  canonical: string;
  mode?: string;
  size?: string;
  layout?: string;
};

export const BLOCK_TYPE_ALIASES: Record<string, AliasDescriptor> = {
  link_big:         { canonical: "link",            mode: "featured", size: "lg" },
  cta_button:       { canonical: "link",            mode: "cta",      size: "lg" },
  featured_pin:     { canonical: "link",            mode: "pinned",   size: "lg" },
  external_item:    { canonical: "link",            mode: "external_card" },

  heading_logo:     { canonical: "heading",         mode: "with_logo" },
  verified_heading: { canonical: "heading",         mode: "verified" },

  verified_avatar:  { canonical: "avatar",          mode: "verified" },

  paragraph:        { canonical: "paragraph_rich",  mode: "plain" },
  markdown:         { canonical: "paragraph_rich",  mode: "markdown" },

  list_numbered:    { canonical: "list",            layout: "numbered" },
  list_pricing:     { canonical: "list",            layout: "pricing" },

  faq_v2:           { canonical: "faq",             layout: "accordion" },

  socials_multi:    { canonical: "socials",         mode: "grouped" },
  socials_custom:   { canonical: "socials",         mode: "custom" },

  image_slider_v2:  { canonical: "image_slider",    layout: "carousel" },

  latest_youtube:   { canonical: "youtube",         mode: "latest" },
  youtube_feed:     { canonical: "youtube",         mode: "feed" },
  instagram_media:  { canonical: "instagram",       mode: "post" },
  latest_instagram: { canonical: "instagram",       mode: "latest" },

  calendly_embed:   { canonical: "calendly",        layout: "inline" },

  profile_card_v1:  { canonical: "profile_card",    layout: "classic" },
  profile_card_v2:  { canonical: "profile_card",    layout: "cover" },
  profile_card_v3:  { canonical: "profile_card",    layout: "stats" },
  profile_card_v4:  { canonical: "profile_card",    layout: "badges" },

  timeline_staged:  { canonical: "timeline",        layout: "staged" },
};

export type NewTypeDescriptor = {
  label: string;
  icon: string;
  category: string;
  meta?: { layouts?: string[]; modes?: string[]; sizes?: string[]; default?: Record<string, string> };
};

export const NEW_BLOCK_TYPES: Record<string, NewTypeDescriptor> = {
  file_list:            { label: "File List",            icon: "folder-open",   category: "media",        meta: { layouts: ["compact","cards","grid","pdf_strip"], default: { layout: "compact" } } },
  audio_list:           { label: "Audio Playlist",       icon: "headphones",    category: "music",        meta: { layouts: ["compact","cards","wave"], default: { layout: "compact" } } },
  link_tree_group:      { label: "Link Group",           icon: "list",          category: "basic",        meta: { layouts: ["list","grid"], default: { layout: "list" } } },
  tabs:                 { label: "Tabs",                 icon: "folder",        category: "layout",       meta: { layouts: ["tabs","pills","underline"], default: { layout: "tabs" } } },
  accordion:            { label: "Accordion",            icon: "bars",          category: "interactive",  meta: { layouts: ["plain","cards"], default: { layout: "plain" } } },
  event_list:           { label: "Event List",           icon: "calendar-day",  category: "utility",      meta: { layouts: ["compact","cards","agenda"], default: { layout: "compact" } } },
  menu:                 { label: "Menu",                 icon: "utensils",      category: "business",     meta: { layouts: ["classic","cards","sections"], default: { layout: "classic" } } },
  menu_section:         { label: "Menu Section",         icon: "list",          category: "business",     meta: { layouts: ["plain","card"], default: { layout: "plain" } } },
  testimonial_carousel: { label: "Testimonial Carousel", icon: "comments",      category: "interactive",  meta: { layouts: ["carousel","stack"], default: { layout: "carousel" } } },
  stats:                { label: "Stats",                icon: "chart-bar",     category: "utility",      meta: { layouts: ["row","grid"], default: { layout: "row" } } },
  affiliate_links:      { label: "Affiliate Links",      icon: "tags",          category: "business",     meta: { layouts: ["compact","cards","grid"], default: { layout: "compact" } } },
  booking_slots:        { label: "Booking Slots",        icon: "calendar-check",category: "integrations", meta: { layouts: ["list","grid"], default: { layout: "list" } } },
};

/**
 * Returns the canonical type for a (possibly-legacy) type slug. Returns
 * the input unchanged for canonical/unknown types.
 */
export function canonicalBlockType(type: string): string {
  return BLOCK_TYPE_ALIASES[type]?.canonical ?? type;
}

/**
 * Editor pre-fills (mode/size/layout) for a brand-new block of a legacy
 * alias. Returns `null` for canonical types.
 */
export function aliasDescriptor(type: string): AliasDescriptor | null {
  return BLOCK_TYPE_ALIASES[type] ?? null;
}

/**
 * Design-variant bundle ids that apply to a canonical type. Mirrors
 * `BlockTypeRegistry::bundlesForCanonical()` so the mobile design
 * gallery shows the right thumbnails for new block types.
 */
export function bundlesForCanonicalType(canonical: string): string[] {
  switch (canonical) {
    case "file_list":            return ["link_actions", "link_shapes"];
    case "audio_list":           return ["music"];
    case "link_tree_group":      return ["link_actions", "link_shapes"];
    case "tabs":                 return ["headings"];
    case "accordion":            return ["body_text"];
    case "event_list":           return ["calendar"];
    case "menu":                 return ["commerce", "body_text"];
    case "menu_section":         return ["commerce", "body_text"];
    case "testimonial_carousel": return ["body_text"];
    case "stats":                return ["headings", "body_text"];
    case "affiliate_links":      return ["link_actions", "link_shapes", "commerce"];
    case "booking_slots":        return ["calendar"];
    default:                     return [];
  }
}
