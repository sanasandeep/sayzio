import { getBaseUrl } from "@/lib/api";

/**
 * Admin-configured brand logos, read at runtime from the product app's
 * public, CORS-open `/branding.json` feed. This keeps the mobile app's logo a
 * single admin-controlled source of truth (no rebuild when the admin swaps
 * it). The result is cached in-memory for the session and the caller falls
 * back to the bundled wordmark PNGs when the fetch fails or returns nothing.
 */
export interface BrandLogos {
  logoLight: string | null;
  logoDark: string | null;
  icon: string | null;
}

interface BrandingResponse {
  data?: BrandLogos;
}

let cache: BrandLogos | null = null;
let inflight: Promise<BrandLogos | null> | null = null;

export async function fetchBrandLogos(): Promise<BrandLogos | null> {
  if (cache) return cache;
  if (inflight) return inflight;

  inflight = (async () => {
    try {
      const res = await fetch(`${getBaseUrl()}/branding.json`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return null;
      const json = (await res.json()) as BrandingResponse;
      const data = json?.data ?? null;
      if (data) cache = data;
      return data;
    } catch {
      return null;
    } finally {
      inflight = null;
    }
  })();

  return inflight;
}
