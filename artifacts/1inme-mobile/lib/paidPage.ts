// Mirrors app/Modules/User/Support/PaidPageTemplates.php. Each template's
// design tokens are duplicated here in React-Native-friendly shapes (gradient
// stop arrays + numeric radius) so the mobile editor can render a live preview
// of the actual page styling instead of a single accent swatch. The full theme
// is still applied server-side on the public page; this is a faithful mini-mock.
export type PaidPageHeroStyle =
  | "aurora"
  | "glow"
  | "wave"
  | "grid"
  | "spotlight";

export type PaidPageTemplate = {
  id: string;
  name: string;
  tagline: string;
  /** Single representative accent colour for the compact picker swatch. */
  swatch: string;
  /** Full-page background gradient stops (top -> bottom). */
  pageBg: string[];
  /** Hero banner gradient stops (diagonal). */
  heroBg: string[];
  accent: string;
  accentSoft: string;
  text: string;
  textMuted: string;
  cardBg: string;
  cardText: string;
  /** Corner radius in px (mirrors the rem token from PHP). */
  radius: number;
  heroStyle: PaidPageHeroStyle;
  motion: boolean;
};

export const PAID_PAGE_TEMPLATES: PaidPageTemplate[] = [
  {
    id: "aurora",
    name: "Aurora",
    tagline: "Northern-lights gradients on deep space black.",
    swatch: "#a855f7",
    pageBg: ["#1b1147", "#0a0a18", "#05050d"],
    heroBg: ["#7c3aed", "#db2777", "#06b6d4"],
    accent: "#a855f7",
    accentSoft: "rgba(168,85,247,0.18)",
    text: "#f5f3ff",
    textMuted: "rgba(245,243,255,0.62)",
    cardBg: "rgba(255,255,255,0.96)",
    cardText: "#1e1b4b",
    radius: 24,
    heroStyle: "aurora",
    motion: true,
  },
  {
    id: "sunset",
    name: "Sunset Blvd",
    tagline: "Warm Miami-sunset glow with neon edges.",
    swatch: "#fb7185",
    pageBg: ["#2a0a1f", "#160512", "#0c0309"],
    heroBg: ["#f97316", "#ec4899", "#8b5cf6"],
    accent: "#fb7185",
    accentSoft: "rgba(251,113,133,0.18)",
    text: "#fff1f2",
    textMuted: "rgba(255,241,242,0.6)",
    cardBg: "rgba(255,255,255,0.97)",
    cardText: "#3b0a2a",
    radius: 28,
    heroStyle: "glow",
    motion: true,
  },
  {
    id: "electric",
    name: "Electric",
    tagline: "High-voltage cyan + lime on graphite.",
    swatch: "#22d3ee",
    pageBg: ["#07131a", "#04090d"],
    heroBg: ["#22d3ee", "#2563eb", "#a3e635"],
    accent: "#22d3ee",
    accentSoft: "rgba(34,211,238,0.18)",
    text: "#ecfeff",
    textMuted: "rgba(236,254,255,0.6)",
    cardBg: "rgba(255,255,255,0.97)",
    cardText: "#0c2330",
    radius: 16,
    heroStyle: "grid",
    motion: true,
  },
  {
    id: "mono",
    name: "Mono Bold",
    tagline: "Editorial black & white with a single hot accent.",
    swatch: "#f43f5e",
    pageBg: ["#0b0b0c", "#050505"],
    heroBg: ["#18181b", "#27272a", "#f43f5e"],
    accent: "#f43f5e",
    accentSoft: "rgba(244,63,94,0.18)",
    text: "#fafafa",
    textMuted: "rgba(250,250,250,0.55)",
    cardBg: "rgba(255,255,255,0.98)",
    cardText: "#111113",
    radius: 8,
    heroStyle: "spotlight",
    motion: false,
  },
  {
    id: "candy",
    name: "Candy Pop",
    tagline: "Playful pastel-to-neon bubblegum energy.",
    swatch: "#e879f9",
    pageBg: ["#2d1b45", "#1a1030", "#0d0820"],
    heroBg: ["#f472b6", "#c084fc", "#38bdf8"],
    accent: "#e879f9",
    accentSoft: "rgba(232,121,249,0.2)",
    text: "#fdf4ff",
    textMuted: "rgba(253,244,255,0.62)",
    cardBg: "rgba(255,255,255,0.97)",
    cardText: "#3b0764",
    radius: 32,
    heroStyle: "wave",
    motion: true,
  },
];

export const PAID_PAGE_DEFAULT_TEMPLATE = "aurora";

export function paidPageTemplateId(value: unknown): string {
  const id = typeof value === "string" ? value : "";
  return PAID_PAGE_TEMPLATES.some((t) => t.id === id)
    ? id
    : PAID_PAGE_DEFAULT_TEMPLATE;
}

export function getPaidPageTemplate(value: unknown): PaidPageTemplate {
  const id = paidPageTemplateId(value);
  return (
    PAID_PAGE_TEMPLATES.find((t) => t.id === id) ?? PAID_PAGE_TEMPLATES[0]
  );
}
