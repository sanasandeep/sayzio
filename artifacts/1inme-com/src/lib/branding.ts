import { BLOG_API_BASE } from "@/lib/blog-posts";

/**
 * The marketing site renders the SAME admin-configured brand logo as the
 * product app, read at runtime from the product app's public, CORS-open
 * `/branding.json` feed (same origin that serves the blog feed). This keeps
 * the logo a single admin-controlled source of truth — no code edits when the
 * admin swaps it. The fetch is cached for the page lifetime and degrades to
 * the bundled text wordmark when it fails.
 */
export interface BrandLogos {
  logoLight: string | null;
  logoDark: string | null;
  icon: string | null;
}

interface BrandingResponse {
  data?: BrandLogos;
}

let cache: Promise<BrandLogos | null> | null = null;

export function fetchBrandLogos(): Promise<BrandLogos | null> {
  if (cache) return cache;
  cache = (async () => {
    try {
      const res = await fetch(`${BLOG_API_BASE}/branding.json`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return null;
      const json = (await res.json()) as BrandingResponse;
      return json.data ?? null;
    } catch {
      return null;
    }
  })();
  return cache;
}
