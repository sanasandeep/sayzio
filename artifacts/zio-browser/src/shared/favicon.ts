/**
 * Favicon helpers shared by renderer surfaces that list URLs
 * (new-tab Recent grid, recently-closed menu, command palette).
 *
 * When we have a stored favicon for a page we use it; otherwise we fall
 * back to a public favicon service keyed by the page's domain so recent
 * items always show the site icon instead of a generic placeholder.
 */

/** Returns a favicon URL derived from the page URL's domain, or null for non-http(s) URLs. */
export function faviconForUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  try {
    const u = new URL(url);
    if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
    return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(u.hostname)}&sz=64`;
  } catch {
    return null;
  }
}

/** Prefer the stored favicon; fall back to a domain-derived one. */
export function resolveFavicon(stored: string | null | undefined, url: string | null | undefined): string | null {
  return stored || faviconForUrl(url);
}
