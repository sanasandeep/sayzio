// Brand palette mirrored from the 1inme.com web app.
//
// Web uses a violet-500 / violet-600 primary (Tailwind primary scale,
// pinned in resources/views/admin/layouts/app.blade.php), pink-500 /
// pink-400 accent, and a gradient endpoint in orange (#fb923c) used in
// the marketing/auth slider artwork. We surface these as theme tokens so
// the mobile app stays visually consistent with the website in both
// light and dark mode.

const brand = {
  violet600: "#7c3aed",
  violet400: "#a78bfa",
  violet50: "#f5f3ff",
  pink500: "#ec4899",
  pink400: "#f472b6",
  orange400: "#fb923c",
};

const colors = {
  light: {
    text: "#0f172a",
    tint: brand.violet600,

    background: "#ffffff",
    foreground: "#0f172a",

    card: "#f7f7fb",
    cardForeground: "#0f172a",

    primary: brand.violet600,
    primaryForeground: "#ffffff",

    accent: brand.pink500,
    accentForeground: "#ffffff",

    secondary: "#f0eefa",
    secondaryForeground: "#1a1a1a",

    muted: "#f0eefa",
    mutedForeground: "#475569",

    destructive: "#ef4444",
    destructiveForeground: "#ffffff",

    border: "#e7e5ee",
    input: "#e7e5ee",

    // Extra brand stops the wordmark / gradient buttons can use without
    // pulling in a separate theme module.
    brandGradient: [brand.violet600, brand.pink500, brand.orange400] as const,
  },
  dark: {
    text: "#fafafa",
    tint: brand.violet400,

    background: "#0a0a0f",
    foreground: "#fafafa",

    card: "#13131c",
    cardForeground: "#fafafa",

    primary: brand.violet400,
    primaryForeground: "#0a0a0f",

    accent: brand.pink400,
    accentForeground: "#0a0a0f",

    secondary: "#1c1c28",
    secondaryForeground: "#fafafa",

    muted: "#1c1c28",
    mutedForeground: "#9ca3af",

    destructive: "#f87171",
    destructiveForeground: "#0a0a0f",

    border: "#23232f",
    input: "#23232f",

    brandGradient: [brand.violet400, brand.pink400, brand.orange400] as const,
  },
  radius: 14,
};

export default colors;
