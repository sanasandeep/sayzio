/**
 * Favicon helpers shared by renderer surfaces that list URLs
 * (new-tab Recent grid, recently-closed menu, command palette).
 *
 * When we have a stored favicon for a page we use it; otherwise we fall
 * back to the site's own /favicon.ico. We deliberately do NOT use any
 * third-party favicon service (e.g. Google s2) — that would leak the
 * user's browsing hostnames to a third party. Requesting /favicon.ico
 * only contacts the site the user already visited.
 */

/** Returns the site's own /favicon.ico URL, or null for non-http(s) URLs. */
export function faviconForUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  try {
    const u = new URL(url);
    if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
    return `${u.origin}/favicon.ico`;
  } catch {
    return null;
  }
}

/** Prefer the stored favicon; fall back to the site's own /favicon.ico. */
export function resolveFavicon(stored: string | null | undefined, url: string | null | undefined): string | null {
  return stored || faviconForUrl(url);
}
