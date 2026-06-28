import type { Feather } from "@expo/vector-icons";

export type LinkKind =
  | "url"
  | "biolink"
  | "file"
  | "vcard"
  | "calendar"
  | "ai_chat"
  | "resume"
  | "paid_page"
  | "brand_kit"
  | "conversational"
  | "slides"
  | "restaurant_menu"
  | "reviews";

type IconName = keyof typeof Feather.glyphMap;

export type LinkKindMeta = {
  kind: LinkKind;
  apiType:
    | "short"
    | "biolink"
    | "file"
    | "vcard"
    | "event"
    | "ai_chat"
    | "resume"
    | "paid_page"
    | "brand_kit"
    | "conversational"
    | "slides"
    | "restaurant_menu"
    | "reviews";
  label: string;
  blurb: string;
  icon: IconName;
};

export const LINK_KINDS: LinkKindMeta[] = [
  {
    kind: "url",
    apiType: "short",
    label: "Short link",
    blurb: "Shorten a long URL with optional alias and analytics.",
    icon: "link-2",
  },
  {
    kind: "biolink",
    apiType: "biolink",
    label: "Biolink page",
    blurb: "A multi-block landing page for your bio.",
    icon: "grid",
  },
  {
    kind: "ai_chat",
    apiType: "ai_chat",
    label: "AI Chat",
    blurb: "A full-page AI assistant that answers visitors for you.",
    icon: "message-circle",
  },
  {
    kind: "file",
    apiType: "file",
    label: "File / PDF",
    blurb: "Share a downloadable file behind a short link.",
    icon: "file",
  },
  {
    kind: "vcard",
    apiType: "vcard",
    label: "vCard",
    blurb: "Tap-to-save digital business card.",
    icon: "user",
  },
  {
    kind: "calendar",
    apiType: "event",
    label: "Event invite",
    blurb: "Calendar invite (.ics) saved with one tap.",
    icon: "calendar",
  },
  {
    kind: "resume",
    apiType: "resume",
    label: "Resume / Portfolio",
    blurb: "A shareable resume page with PDF download.",
    icon: "file-text",
  },
  {
    kind: "paid_page",
    apiType: "paid_page",
    label: "Bizs Profile",
    blurb:
      "A themeable home that automatically shows all your posts, tiers & tips — no linking needed.",
    icon: "award",
  },
  {
    kind: "brand_kit",
    apiType: "brand_kit",
    label: "Brand / Press Kit",
    blurb:
      "A shareable press kit from your saved Brand Kit — logos, colours, fonts & boilerplate.",
    icon: "feather",
  },
  {
    kind: "slides",
    apiType: "slides",
    label: "Slides",
    blurb: "Present a swipeable deck of slides from a single link.",
    icon: "layers",
  },
  {
    kind: "restaurant_menu",
    apiType: "restaurant_menu",
    label: "Restaurant Menu",
    blurb: "A digital menu with sections, items and prices.",
    icon: "coffee",
  },
  {
    kind: "reviews",
    apiType: "reviews",
    label: "Reviews",
    blurb: "Collect and showcase reviews from your audience.",
    icon: "star",
  },
  {
    kind: "conversational",
    apiType: "conversational",
    label: "Conversational",
    blurb: "A guided, chat-style page that responds as visitors tap.",
    icon: "message-square",
  },
];

// Grouped presentation for the "Create a new link" picker. Mirrors the
// four labelled categories + wording used by the web Create Link page
// (artifacts/1inme/resources/views/user/links/create.blade.php) so the
// experience is consistent across surfaces. Only kinds that are actually
// creatable on mobile (i.e. present in LINK_KINDS) are listed — the set of
// available types is unchanged, just regrouped.
export type LinkKindCategory = {
  label: string;
  desc: string;
  kinds: LinkKind[];
};

export const LINK_KIND_CATEGORIES: LinkKindCategory[] = [
  {
    label: "Everyday links",
    desc: "Quick, single-purpose links you can share anywhere in seconds.",
    kinds: ["url", "file", "calendar", "vcard"],
  },
  {
    label: "Pages & mini-sites",
    desc: "Full, customizable pages that live at a single link — no website needed.",
    kinds: ["biolink", "slides", "restaurant_menu", "resume"],
  },
  {
    label: "Business & monetization",
    desc: "Grow your reputation and earn from your audience.",
    kinds: ["paid_page", "brand_kit", "reviews"],
  },
  {
    label: "AI-powered",
    desc: "Let AI answer and guide your visitors for you.",
    kinds: ["ai_chat", "conversational"],
  },
];

export const KINDS_BY_API: Record<string, LinkKindMeta> = LINK_KINDS.reduce(
  (acc, m) => {
    acc[m.apiType] = m;
    return acc;
  },
  {} as Record<string, LinkKindMeta>,
);

export function metaForApiType(t: string | null | undefined): LinkKindMeta {
  if (t && KINDS_BY_API[t]) return KINDS_BY_API[t];
  return LINK_KINDS[0];
}

export function metaForKind(k: LinkKind): LinkKindMeta {
  return LINK_KINDS.find((m) => m.kind === k) ?? LINK_KINDS[0];
}
