// Brand palette mirrored from the Sayzio web app.
//
// Web uses a blue-led primary (#3d6bff) with two contrast accents — indigo
// (#6e61ff) and magenta (#d76dff) — applied across light and dark modes. Dark
// mode keeps lighter tints of the same hues for legibility. We surface these
// as theme tokens so the mobile app stays visually consistent with the website
// in both light and dark mode.

const brand = {
  blue600: "#3d6bff",
  blue400: "#7d9bff",
  blue50: "#eef3ff",
  indigo: "#6e61ff",
  indigoLight: "#9c92ff",
  magenta500: "#d76dff",
  magenta400: "#e29bff",
  cyan400: "#22d3ee",
  cyan300: "#67e8f9",
  green500: "#22c55e",
  green400: "#4ade80",
};

const colors = {
  light: {
    text: "#0f172a",
    tint: brand.blue600,

    background: "#ffffff",
    foreground: "#0f172a",

    card: "#f7f8fc",
    cardForeground: "#0f172a",

    primary: brand.blue600,
    primaryForeground: "#ffffff",

    accent: brand.magenta500,
    accentForeground: "#ffffff",

    secondary: "#eef2ff",
    secondaryForeground: "#1a1a1a",

    muted: "#eef2ff",
    mutedForeground: "#475569",

    destructive: "#ef4444",
    destructiveForeground: "#ffffff",

    warning: "#d97706",
    warningForeground: "#ffffff",

    success: brand.green500,
    successForeground: "#ffffff",

    border: "#e6e8f2",
    input: "#e6e8f2",

    // Extra brand stops the wordmark / gradient buttons can use without
    // pulling in a separate theme module.
    brandGradient: [brand.blue600, brand.indigo, brand.magenta500] as const,
    // Highlight CTA gradient (electric blue → cyan) — mirrors the web
    // .btn-cta class. Reserved for important primary actions only.
    ctaGradient: [brand.blue600, brand.cyan400] as const,
  },
  dark: {
    text: "#fafafa",
    tint: brand.blue400,

    background: "#0a0a0f",
    foreground: "#fafafa",

    card: "#13131c",
    cardForeground: "#fafafa",

    primary: brand.blue400,
    primaryForeground: "#0a0a0f",

    accent: brand.magenta400,
    accentForeground: "#0a0a0f",

    secondary: "#1c1c28",
    secondaryForeground: "#fafafa",

    muted: "#1c1c28",
    mutedForeground: "#9ca3af",

    destructive: "#f87171",
    destructiveForeground: "#0a0a0f",

    warning: "#fbbf24",
    warningForeground: "#0a0a0f",

    success: brand.green400,
    successForeground: "#0a0a0f",

    border: "#23232f",
    input: "#23232f",

    brandGradient: [brand.blue400, brand.indigoLight, brand.magenta400] as const,
    // Highlight CTA gradient (electric blue → cyan) — mirrors the web
    // .btn-cta class. Reserved for important primary actions only.
    ctaGradient: [brand.blue400, brand.cyan300] as const,
  },
  radius: 14,
};

export default colors;
