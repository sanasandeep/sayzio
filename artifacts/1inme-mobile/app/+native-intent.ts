// Maps incoming universal/app-link URLs (e.g. https://1inme.com/yourhandle or
// 1inme://yourhandle) to internal expo-router routes. Reserved top-level
// segments fall through to their normal screens; everything else is treated
// as a public biolink handle and routed to app/biolink/[handle].tsx.

const RESERVED = new Set([
  "",
  "onboarding",
  "oauth-callback",
  "info",
  "biolink",
  "auth",
  "tabs",
  "_sitemap",
  "+not-found",
  "api",
]);

function decode(seg: string): string {
  try {
    return decodeURIComponent(seg);
  } catch {
    return seg;
  }
}

export function redirectSystemPath({
  path,
  initial,
}: {
  path: string;
  initial: boolean;
}): string {
  void initial;
  try {
    const url = new URL(path, "https://1inme.com");
    const segments = url.pathname.split("/").filter(Boolean);
    const first = segments[0];
    if (!first) return path;

    const decoded = decode(first);
    if (RESERVED.has(decoded.toLowerCase())) return path;
    if (decoded.startsWith("(")) return path;
    if (decoded.startsWith("+")) return path;

    if (segments.length === 1) {
      return `/biolink/${decoded}${url.search ?? ""}`;
    }
    return path;
  } catch {
    return path;
  }
}
