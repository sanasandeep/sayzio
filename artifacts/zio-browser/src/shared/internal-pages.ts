/**
 * Renderer-drawn internal pages (beyond the New Tab page).
 * These URLs never load in a native WebContentsView — the renderer DOM
 * draws them, exactly like about:newtab.
 */
export const INTERNAL_PAGE_URLS = ['about:sayzio', 'about:zio'] as const;

export type InternalPageUrl = (typeof INTERNAL_PAGE_URLS)[number];

export function isInternalPageUrl(url: string | null | undefined): url is InternalPageUrl {
  return !!url && (INTERNAL_PAGE_URLS as readonly string[]).includes(url);
}

/** Tab title shown for each internal page. */
export function internalPageTitle(url: string): string {
  return url === 'about:sayzio' ? 'About Sayzio' : 'About Zio Browser';
}
