// Mobile mirror of the server-side effect-background catalogs
// (TilesBgCatalog / MeshGradientCatalog / PatternCatalog in
// artifacts/1inme/app/Modules/User/Support). Only the *visual data* is
// mirrored here so BiolinkBackgroundPreview can draw a texture close to
// the real web CSS; the server remains the single source of truth for
// validation and public rendering. Unknown keys resolve to null and the
// preview falls back to the server-stamped `bg_effect_colors` gradient,
// so a catalog addition on the web degrades gracefully here.

// ---------------------------------------------------------------- tiles

export type TileGradient = [string, string];

export type TilesSpec = {
  tiles: TileGradient[];
  spans: [number, number][];
};

const TILE_PALETTES: Record<string, TileGradient[]> = {
  tiles_midnight: [
    ["#1e293b", "#0f172a"],
    ["#1d4ed8", "#1e3a8a"],
    ["#334155", "#1e293b"],
    ["#0ea5e9", "#0369a1"],
    ["#312e81", "#1e1b4b"],
    ["#475569", "#1f2937"],
  ],
  tiles_sunset: [
    ["#fb923c", "#ea580c"],
    ["#f43f5e", "#be123c"],
    ["#fbbf24", "#d97706"],
    ["#db2777", "#831843"],
    ["#c2410c", "#7c2d12"],
    ["#fda4af", "#e11d48"],
  ],
  tiles_forest: [
    ["#22c55e", "#15803d"],
    ["#0d9488", "#115e59"],
    ["#84cc16", "#4d7c0f"],
    ["#065f46", "#022c22"],
    ["#4ade80", "#16a34a"],
    ["#365314", "#1a2e05"],
  ],
  tiles_berry: [
    ["#c026d3", "#86198f"],
    ["#8b5cf6", "#6d28d9"],
    ["#ec4899", "#be185d"],
    ["#6b21a8", "#3b0764"],
    ["#d946ef", "#a21caf"],
    ["#4c1d95", "#2e1065"],
  ],
  tiles_ocean: [
    ["#22d3ee", "#0891b2"],
    ["#0ea5e9", "#0369a1"],
    ["#2dd4bf", "#0f766e"],
    ["#155e75", "#164e63"],
    ["#38bdf8", "#0284c7"],
    ["#075985", "#0c4a6e"],
  ],
  tiles_mono: [
    ["#525252", "#262626"],
    ["#737373", "#404040"],
    ["#a3a3a3", "#525252"],
    ["#262626", "#0a0a0a"],
    ["#404040", "#171717"],
    ["#8a8a8a", "#3f3f46"],
  ],
  tiles_pastel: [
    ["#fbcfe8", "#f9a8d4"],
    ["#bfdbfe", "#93c5fd"],
    ["#fde68a", "#fcd34d"],
    ["#bbf7d0", "#86efac"],
    ["#ddd6fe", "#c4b5fd"],
    ["#fed7aa", "#fdba74"],
  ],
};

// Per-layout [colSpan, rowSpan] cycles on a 4-column grid, mirroring
// TilesBgCatalog::LAYOUT_SPANS.
const TILE_LAYOUT_SPANS: Record<string, [number, number][]> = {
  uniform: [[1, 1]],
  metro: [
    [2, 2],
    [1, 1],
    [1, 1],
    [2, 1],
    [1, 2],
    [1, 1],
    [2, 1],
    [1, 1],
  ],
  brick: [
    [2, 1],
    [2, 1],
    [1, 1],
    [2, 1],
    [1, 1],
    [2, 1],
  ],
};

export function resolveTiles(
  palette: string,
  layout: string,
): TilesSpec | null {
  const tiles = TILE_PALETTES[palette];
  if (!tiles) return null;
  const spans = TILE_LAYOUT_SPANS[layout] ?? TILE_LAYOUT_SPANS.uniform;
  return { tiles, spans };
}

// ----------------------------------------------------------------- mesh

export type MeshBlob = {
  color: string;
  x: number; // % of width
  y: number; // % of height
  spread: number; // % radius
};

export type MeshSpec = { base: string; blobs: MeshBlob[] };

const MESH_PRESETS: Record<string, MeshSpec> = {
  mesh_aurora: {
    base: "#0b1026",
    blobs: [
      { color: "#22d3ee", x: 15, y: 20, spread: 55 },
      { color: "#a78bfa", x: 80, y: 15, spread: 50 },
      { color: "#34d399", x: 70, y: 80, spread: 55 },
      { color: "#f472b6", x: 20, y: 85, spread: 45 },
    ],
  },
  mesh_sunrise: {
    base: "#2b1055",
    blobs: [
      { color: "#ff8a5c", x: 20, y: 80, spread: 60 },
      { color: "#ffd166", x: 50, y: 95, spread: 45 },
      { color: "#f25f8e", x: 80, y: 60, spread: 55 },
      { color: "#7357c4", x: 70, y: 10, spread: 55 },
    ],
  },
  mesh_lagoon: {
    base: "#03252e",
    blobs: [
      { color: "#0ea5e9", x: 25, y: 25, spread: 55 },
      { color: "#2dd4bf", x: 75, y: 30, spread: 55 },
      { color: "#a3e635", x: 85, y: 85, spread: 45 },
      { color: "#0369a1", x: 15, y: 80, spread: 55 },
    ],
  },
  mesh_candy: {
    base: "#fdf2f8",
    blobs: [
      { color: "#f9a8d4", x: 20, y: 20, spread: 55 },
      { color: "#c4b5fd", x: 80, y: 25, spread: 55 },
      { color: "#99f6e4", x: 70, y: 85, spread: 50 },
      { color: "#fde68a", x: 15, y: 80, spread: 45 },
    ],
  },
  mesh_ember: {
    base: "#1c0a06",
    blobs: [
      { color: "#f97316", x: 25, y: 75, spread: 55 },
      { color: "#ef4444", x: 75, y: 65, spread: 55 },
      { color: "#facc15", x: 55, y: 95, spread: 40 },
      { color: "#7c2d12", x: 80, y: 15, spread: 55 },
    ],
  },
  mesh_orchid: {
    base: "#170b2b",
    blobs: [
      { color: "#c026d3", x: 25, y: 25, spread: 55 },
      { color: "#8b5cf6", x: 75, y: 20, spread: 55 },
      { color: "#ec4899", x: 80, y: 80, spread: 50 },
      { color: "#312e81", x: 15, y: 80, spread: 55 },
    ],
  },
  mesh_glacier: {
    base: "#eef6fb",
    blobs: [
      { color: "#93c5fd", x: 20, y: 25, spread: 55 },
      { color: "#a5f3fc", x: 75, y: 20, spread: 55 },
      { color: "#c7d2fe", x: 75, y: 85, spread: 50 },
      { color: "#e0f2fe", x: 20, y: 80, spread: 45 },
    ],
  },
  mesh_forest: {
    base: "#0a1f12",
    blobs: [
      { color: "#16a34a", x: 25, y: 30, spread: 55 },
      { color: "#84cc16", x: 75, y: 20, spread: 45 },
      { color: "#0d9488", x: 75, y: 80, spread: 55 },
      { color: "#365314", x: 15, y: 85, spread: 50 },
    ],
  },
  mesh_noir: {
    base: "#0a0a0a",
    blobs: [
      { color: "#404040", x: 25, y: 20, spread: 55 },
      { color: "#525b6b", x: 80, y: 30, spread: 50 },
      { color: "#1f2937", x: 70, y: 85, spread: 55 },
      { color: "#312e3f", x: 15, y: 80, spread: 45 },
    ],
  },
  mesh_peach: {
    base: "#fff7ed",
    blobs: [
      { color: "#fdba74", x: 20, y: 25, spread: 55 },
      { color: "#fda4af", x: 80, y: 20, spread: 50 },
      { color: "#fcd34d", x: 75, y: 85, spread: 45 },
      { color: "#fecaca", x: 20, y: 80, spread: 50 },
    ],
  },
};

export function resolveMesh(key: string): MeshSpec | null {
  return MESH_PRESETS[key] ?? null;
}

// -------------------------------------------------------------- pattern

export type PatternSpec =
  | { kind: "dots"; base: string; accent: string; size: number; radius: number }
  | { kind: "grid"; base: string; accent: string; size: number }
  | {
      kind: "stripes";
      base: string;
      accent: string;
      stripe: number;
      period: number;
    }
  | { kind: "zigzag"; base: string; accent: string; size: number }
  | { kind: "waves"; base: string; accent: string; period: number }
  | { kind: "checker"; base: string; accent: string; size: number }
  | { kind: "crosshatch"; base: string; accent: string; gap: number }
  | { kind: "honeycomb"; base: string; accent: string; w: number; h: number }
  | { kind: "diamonds"; base: string; accent: string; size: number };

const PATTERN_PRESETS: Record<string, PatternSpec> = {
  pattern_dots_dark: {
    kind: "dots",
    base: "#111827",
    accent: "rgba(148,163,184,0.35)",
    size: 22,
    radius: 1.5,
  },
  pattern_dots_light: {
    kind: "dots",
    base: "#f8fafc",
    accent: "rgba(71,85,105,0.35)",
    size: 22,
    radius: 1.5,
  },
  pattern_grid_dark: {
    kind: "grid",
    base: "#0f172a",
    accent: "rgba(148,163,184,0.16)",
    size: 32,
  },
  pattern_grid_light: {
    kind: "grid",
    base: "#ffffff",
    accent: "rgba(59,130,246,0.14)",
    size: 32,
  },
  pattern_stripes_indigo: {
    kind: "stripes",
    base: "#1e1b4b",
    accent: "rgba(129,140,248,0.16)",
    stripe: 12,
    period: 28,
  },
  pattern_stripes_sand: {
    kind: "stripes",
    base: "#fef3c7",
    accent: "rgba(217,119,6,0.14)",
    stripe: 12,
    period: 28,
  },
  pattern_zigzag_teal: {
    kind: "zigzag",
    base: "#042f2e",
    accent: "rgba(45,212,191,0.22)",
    size: 28,
  },
  pattern_waves_blue: {
    kind: "waves",
    base: "#0c4a6e",
    accent: "rgba(125,211,252,0.16)",
    period: 24,
  },
  pattern_checker_mono: {
    kind: "checker",
    base: "#18181b",
    accent: "rgba(161,161,170,0.14)",
    size: 36,
  },
  pattern_crosshatch_rose: {
    kind: "crosshatch",
    base: "#4c0519",
    accent: "rgba(251,113,133,0.14)",
    gap: 12,
  },
  pattern_honeycomb_amber: {
    kind: "honeycomb",
    base: "#451a03",
    accent: "rgba(245,158,11,0.18)",
    w: 48,
    h: 28,
  },
  pattern_diamonds_violet: {
    kind: "diamonds",
    base: "#2e1065",
    accent: "rgba(167,139,250,0.16)",
    size: 30,
  },
};

export function resolvePattern(key: string): PatternSpec | null {
  return PATTERN_PRESETS[key] ?? null;
}
