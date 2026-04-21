import type { Feather } from "@expo/vector-icons";

export type LinkKind = "url" | "biolink" | "file" | "vcard" | "calendar";

type IconName = keyof typeof Feather.glyphMap;

export type LinkKindMeta = {
  kind: LinkKind;
  apiType: "short" | "biolink" | "file" | "vcard" | "event";
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
